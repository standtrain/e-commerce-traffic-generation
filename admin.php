<?php
ob_start();
if (!file_exists('config.php')) {
    if (file_exists('install.php')) {
        header('Location: install.php');
        exit;
    }
    die('System not installed. Please ensure install.php exists.');
}

require_once 'config.php';
require_once 'lang.php';

// Start session for CAPTCHA and authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 兼容旧版config.php：如果缺少搜索函数则在此定义
if (!function_exists('searchLinks')) {
    function searchLinks($filters = []) {
        $pdo = getDBConnection();
        $where = [];
        $params = [];
        if (!empty($filters['platform'])) {
            $where[] = 'platform = ?';
            $params[] = $filters['platform'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $where[] = '(title LIKE ? OR description LIKE ? OR ai_summary LIKE ? OR ai_tags LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }
        $sql = 'SELECT * FROM links';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
if (!function_exists('getDistinctPlatforms')) {
    function getDistinctPlatforms() {
        $pdo = getDBConnection();
        $stmt = $pdo->query('SELECT DISTINCT platform FROM links ORDER BY platform');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// CSRF 保护函数
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

if (!defined('INSTALLED') || INSTALLED !== true) {
    if (file_exists('install.php')) {
        header('Location: install.php');
        exit;
    }
    die('System not installed. Please ensure install.php exists.');
}

$message = '';
$messageType = '';
$action = $_GET['action'] ?? 'list';

function validateCaptcha($input) {
    if (empty($_SESSION['captcha_code'])) {
        return false;
    }
    $valid = strtoupper(trim($input)) === $_SESSION['captcha_code'];
    unset($_SESSION['captcha_code']);
    return $valid;
}

function fetchHttp($url, $extraHeaders = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1');
    $headers = ['Accept-Language: zh-CN,zh;q=0.9,en;q=0.8', 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'];
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, $extraHeaders));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    return ['body' => $response, 'code' => $httpCode, 'error' => $error];
}

function extractMetaFromHtml($html) {
    $result = ['title' => '', 'description' => '', 'image' => '', 'price' => ''];
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $result['title'] = trim(strip_tags($m[1]));
    }
    foreach (['og:title' => 'title', 'og:description' => 'description', 'og:image' => 'image'] as $ogProp => $field) {
        if (preg_match('/<meta\s+(?:property|name)=["\']' . preg_quote($ogProp, '/') . '["\'][^>]*content=["\']([^"\']*)["\']/is', $html, $m)) {
            $val = trim($m[1]);
            if (!empty($val) && (empty($result[$field]) || $ogProp === 'og:title')) {
                $result[$field] = $val;
            }
        }
    }
    if (empty($result['description'])) {
        if (preg_match('/<meta\s+(?:name|property)=["\']description["\'][^>]*content=["\']([^"\']*)["\']/is', $html, $m)) {
            $result['description'] = trim($m[1]);
        }
    }
    foreach (['/¥\s*([\d,]+\.?\d*)/u', '/￥\s*([\d,]+\.?\d*)/u', '/"price"\s*:\s*"?([\d.]+)"?/i', '/data-price=["\']([\d,]+\.?\d*)/i'] as $p) {
        if (preg_match($p, $html, $m)) { $result['price'] = '¥' . $m[1]; break; }
    }
    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // 一键获取URL信息（跳过CSRF验证，只读操作）
    if ($_POST['action'] === 'fetch_url_info') {
        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => t('msg_login_first')]);
            exit;
        }
        $url = $_POST['url'] ?? '';
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'error' => t('msg_enter_url')]);
            exit;
        }

        $result = ['title' => '', 'description' => '', 'image' => '', 'price' => ''];
        $host = parse_url($url, PHP_URL_HOST);

        // === 闲鱼 / Goofish ===
        if (strpos($host, 'goofish.com') !== false || strpos($host, 'xianyu.com') !== false) {
            $itemId = '';
            if (preg_match('/[?&]id=(\d+)/', $url, $m)) {
                $itemId = $m[1];
            }
            if ($itemId) {
                // 尝试闲鱼 H5 API
                $apiUrl = 'https://h5api.m.goofish.com/h5/mtop.taobao.idle.pc.item.detail/1.0/?data=' . urlencode(json_encode(['itemId' => $itemId]));
                $resp = fetchHttp($apiUrl, ['Referer: https://www.goofish.com/']);
                if (!$resp['error'] && $resp['code'] < 400) {
                    $json = json_decode($resp['body'], true);
                    if (isset($json['data'])) {
                        $data = $json['data'];
                        $result['title'] = $data['title'] ?? $data['itemTitle'] ?? '';
                        $result['price'] = isset($data['price']) ? '¥' . $data['price'] : (isset($data['soldPrice']) ? '¥' . $data['soldPrice'] : '');
                        $result['description'] = $data['desc'] ?? $data['description'] ?? '';
                        $result['image'] = $data['images'][0] ?? $data['picUrl'] ?? $data['mainPic'] ?? '';
                    }
                }
                // API 可能需要签名，回退到页面解析
                if (empty($result['title'])) {
                    $resp = fetchHttp($url);
                    if (!$resp['error'] && $resp['code'] < 400) {
                        $result = extractMetaFromHtml($resp['body']);
                        // 尝试从 script 标签中提取 JSON 数据
                        if (preg_match('/"title"\s*:\s*"([^"]+)"/', $resp['body'], $m) && empty($result['title'])) {
                            $result['title'] = $m[1];
                        }
                        if (preg_match('/"price"\s*:\s*"?([\d.]+)"?/', $resp['body'], $m)) {
                            $result['price'] = '¥' . $m[1];
                        }
                        if (preg_match('/"desc"\s*:\s*"([^"]+)"/', $resp['body'], $m) && empty($result['description'])) {
                            $result['description'] = $m[1];
                        }
                        if (preg_match('/"picUrl"\s*:\s*"([^"]+)"/', $resp['body'], $m) && empty($result['image'])) {
                            $img = $m[1];
                            if (strpos($img, '//') === 0) $img = 'https:' . $img;
                            $result['image'] = $img;
                        }
                    }
                }
            }
        }
        // === 淘宝 / 天猫 ===
        elseif (strpos($host, 'taobao.com') !== false || strpos($host, 'tmall.com') !== false || strpos($host, 'tmall.hk') !== false) {
            $itemId = '';
            if (preg_match('/[?&]id=(\d+)/', $url, $m)) $itemId = $m[1];
            elseif (preg_match('/\/item\/(\d+)/', $url, $m)) $itemId = $m[1];
            if ($itemId) {
                $resp = fetchHttp('https://item.taobao.com/item.htm?id=' . $itemId);
                if (!$resp['error'] && $resp['code'] < 400) {
                    $result = extractMetaFromHtml($resp['body']);
                    if (preg_match('/"title"\s*:\s*"([^"]+)"/', $resp['body'], $m) && empty($result['title'])) {
                        $result['title'] = $m[1];
                    }
                    if (preg_match('/"price"\s*:\s*"?([\d.]+)"?/', $resp['body'], $m)) {
                        $result['price'] = '¥' . $m[1];
                    }
                    if (preg_match('/"picUrl"\s*:\s*"([^"]+)"/', $resp['body'], $m) && empty($result['image'])) {
                        $img = $m[1];
                        if (strpos($img, '//') === 0) $img = 'https:' . $img;
                        $result['image'] = $img;
                    }
                }
            }
            if (empty($result['title'])) {
                $resp = fetchHttp($url);
                if (!$resp['error'] && $resp['code'] < 400) {
                    $result = extractMetaFromHtml($resp['body']);
                }
            }
        }
        // === 京东 ===
        elseif (strpos($host, 'jd.com') !== false || strpos($host, 'jd.hk') !== false) {
            $resp = fetchHttp($url);
            if (!$resp['error'] && $resp['code'] < 400) {
                $result = extractMetaFromHtml($resp['body']);
                if (preg_match('/"skuName"\s*:\s*"([^"]+)"/', $resp['body'], $m) && empty($result['title'])) {
                    $result['title'] = $m[1];
                }
                if (preg_match('/"price"\s*:\s*"?([\d.]+)"?/', $resp['body'], $m)) {
                    $result['price'] = '¥' . $m[1];
                }
                if (preg_match('/"imageList"\s*:\s*\[?"(https?:\/\/[^"]+)"/', $resp['body'], $m) && empty($result['image'])) {
                    $result['image'] = $m[1];
                }
            }
        }
        // === 拼多多 ===
        elseif (strpos($host, 'pinduoduo.com') !== false || strpos($host, 'yangkeduo.com') !== false) {
            $resp = fetchHttp($url);
            if (!$resp['error'] && $resp['code'] < 400) {
                $result = extractMetaFromHtml($resp['body']);
                if (preg_match('/"goodsName"\s*:\s*"([^"]+)"/', $resp['body'], $m) && empty($result['title'])) {
                    $result['title'] = $m[1];
                }
                if (preg_match('/"price"\s*:\s*"?([\d.]+)"?/', $resp['body'], $m)) {
                    $result['price'] = '¥' . ($m[1] / 100);
                }
            }
        }
        // === 通用处理 ===
        else {
            $resp = fetchHttp($url);
            if (!$resp['error'] && $resp['code'] < 400) {
                $result = extractMetaFromHtml($resp['body']);
            } else {
                echo json_encode(['success' => false, 'error' => t('msg_cannot_access') . ($resp['error'] ?: "HTTP " . $resp['code'])]);
                exit;
            }
        }

        // 最终回退：尝试 URL 参数中的标题
        if (empty($result['title'])) {
            $parsed = parse_url($url);
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $qs);
                if (!empty($qs['title'])) $result['title'] = $qs['title'];
                elseif (!empty($qs['name'])) $result['title'] = $qs['name'];
            }
        }

        $result['title'] = mb_substr($result['title'], 0, 100);
        $result['description'] = mb_substr($result['description'], 0, 300);
        if (empty($result['title'])) {
            echo json_encode(['success' => false, 'error' => t('msg_cannot_fetch')]);
            exit;
        }
        echo json_encode(['success' => true, 'data' => $result]);
        exit;
    }
    // CSRF 验证
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = t('msg_csrf_fail');
        $messageType = 'error';
    } elseif ($_POST['action'] === 'login') {
        // Validate CAPTCHA (only when enabled)
        $captchaEnabled = isCaptchaEnabled();
        if ($captchaEnabled && !validateCaptcha($_POST['captcha'] ?? '')) {
            $message = t('msg_captcha_fail');
            $messageType = 'error';
        } else {
            $user = getUserByUsername($_POST['username']);
            if ($user && password_verify($_POST['password'], $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                header('Location: admin.php');
                exit;
            } else {
                $message = t('msg_login_fail');
                $messageType = 'error';
            }
        }
    } elseif ($_POST['action'] === 'logout') {
        session_destroy();
        header('Location: admin.php');
        exit;
    } elseif ($_POST['action'] === 'add_link') {
        requireLogin();
        $platform = sanitize($_POST['platform']);
        if ($platform === '自定义' && !empty($_POST['custom_platform'])) {
            $platform = sanitize($_POST['custom_platform']);
        }
        $imagesJson = uploadImages($_FILES['images'] ?? null);
        $newLinkId = addLink(
            $platform,
            sanitize($_POST['title']),
            sanitize($_POST['url']),
            sanitize($_POST['description'] ?? ''),
            $imagesJson,
            sanitize($_POST['display_text'] ?? ''),
            sanitize($_POST['price'] ?? '')
        );
        if (isAiEnabled()) {
            $aiResult = generateAiSummary(
                sanitize($_POST['title']),
                sanitize($_POST['description'] ?? ''),
                $platform
            );
            if ($aiResult) {
                updateLinkAiContent(
                    $newLinkId,
                    $aiResult['summary'] ?? '',
                    $aiResult['detail'] ?? '',
                    $aiResult['tags'] ?? ''
                );
            }
        }
        $message = t('msg_link_added');
        $messageType = 'success';
    } elseif ($_POST['action'] === 'edit_link') {
        requireLogin();
        $platform = sanitize($_POST['platform']);
        if ($platform === '自定义' && !empty($_POST['custom_platform'])) {
            $platform = sanitize($_POST['custom_platform']);
        }
        $currentLink = getLinkById($_POST['id']);
        if (isset($_POST['delete_images']) && $_POST['delete_images'] === '1') {
            if ($currentLink && $currentLink['images']) {
                $oldImages = json_decode($currentLink['images'], true) ?: [];
                foreach ($oldImages as $oldImg) {
                    deleteImage($oldImg);
                }
            }
            $imagesJson = '';
        } else {
            $imagesJson = uploadImages($_FILES['images'] ?? null);
            if (!$imagesJson && isset($_POST['keep_images']) && $_POST['keep_images'] === '1') {
                $imagesJson = $currentLink['images'] ?? '';
            }
            if ($imagesJson && $currentLink && $currentLink['images'] && $currentLink['images'] !== $imagesJson) {
                $oldImages = json_decode($currentLink['images'], true) ?: [];
                foreach ($oldImages as $oldImg) {
                    deleteImage($oldImg);
                }
            }
        }
        updateLink(
            sanitize($_POST['id']),
            $platform,
            sanitize($_POST['title']),
            sanitize($_POST['url']),
            sanitize($_POST['description'] ?? ''),
            $imagesJson,
            sanitize($_POST['display_text'] ?? ''),
            sanitize($_POST['price'] ?? '')
        );
        if (isset($_POST['regenerate_ai']) && $_POST['regenerate_ai'] === '1' && isAiEnabled()) {
            $aiResult = generateAiSummary(
                sanitize($_POST['title']),
                sanitize($_POST['description'] ?? ''),
                $platform
            );
            if ($aiResult) {
                updateLinkAiContent(
                    sanitize($_POST['id']),
                    $aiResult['summary'] ?? '',
                    $aiResult['detail'] ?? '',
                    $aiResult['tags'] ?? ''
                );
            }
        }
        $message = t('msg_link_updated');
        $messageType = 'success';
    } elseif ($_POST['action'] === 'regenerate_ai') {
        requireLogin();
        if (isAiEnabled() && isset($_POST['id'])) {
            $link = getLinkById($_POST['id']);
            if ($link) {
                $aiResult = generateAiSummary(
                    $link['title'],
                    $link['description'] ?? '',
                    $link['platform']
                );
                if ($aiResult) {
                    updateLinkAiContent(
                        $_POST['id'],
                        $aiResult['summary'] ?? '',
                        $aiResult['detail'] ?? '',
                        $aiResult['tags'] ?? ''
                    );
                    echo json_encode(['success' => true, 'data' => $aiResult]);
                    exit;
                }
            }
        }
        echo json_encode(['success' => false, 'error' => t('msg_ai_disabled')]);
        exit;
    } elseif ($_POST['action'] === 'test_ai') {
        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => t('msg_login_first')]);
            exit;
        }

        $apiUrl = getAiApiUrl();
        $apiKey = getAiApiKey();
        $model = getAiModel();

        if (empty($apiUrl) || empty($apiKey)) {
            echo json_encode(['success' => false, 'error' => t('msg_api_incomplete')]);
            exit;
        }

        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => 'Hello']
            ],
            'temperature' => 0.7,
            'max_tokens' => 50
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            echo json_encode(['success' => false, 'error' => t('msg_request_fail') . $error]);
            exit;
        }

        $result = json_decode($response, true);
        if ($result === null) {
            $responsePreview = strlen($response) > 200 ? substr($response, 0, 200) . '...' : $response;
            $responsePreview = str_replace(["\r", "\n", "\t"], ' ', $responsePreview);
            echo json_encode([
                'success' => false,
                'error' => t('msg_api_not_json'),
                'debug' => [
                    'http_code' => $httpCode,
                    'response_preview' => $responsePreview,
                    'api_url' => $apiUrl,
                    'model' => $model
                ]
            ]);
            exit;
        }

        if (isset($result['choices'][0]['message']['content'])) {
            echo json_encode(['success' => true, 'content' => trim($result['choices'][0]['message']['content'])]);
        } elseif (isset($result['error'])) {
            $errorInfo = is_array($result['error']) ? json_encode($result['error'], JSON_UNESCAPED_UNICODE) : $result['error'];
            echo json_encode(['success' => false, 'error' => t('msg_api_error') . $errorInfo]);
        } else {
            echo json_encode(['success' => false, 'error' => t('msg_response_error'), 'debug' => $result]);
        }
        exit;
    } elseif ($_POST['action'] === 'delete_link') {
        requireLogin();
        $link = getLinkById($_POST['id']);
        if ($link && $link['images']) {
            $images = json_decode($link['images'], true) ?: [];
            foreach ($images as $img) {
                deleteImage($img);
            }
        }
        deleteLink($_POST['id']);
        $message = t('msg_link_deleted');
        $messageType = 'success';
    } elseif ($_POST['action'] === 'toggle_link') {
        requireLogin();
        $link = getLinkById($_POST['id']);
        if ($link) {
            $newStatus = $link['status'] === 'active' ? 'disabled' : 'active';
            updateLinkStatus($_POST['id'], $newStatus);
        }
        $message = t('msg_status_updated');
        $messageType = 'success';
    } elseif ($_POST['action'] === 'add_user') {
        requireAdmin();
        if (empty($_POST['username']) || empty($_POST['password'])) {
            $message = t('msg_user_empty');
            $messageType = 'error';
        } elseif (strlen($_POST['password']) < 6) {
            $message = t('msg_password_short');
            $messageType = 'error';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $_POST['username'])) {
            $message = t('msg_username_invalid');
            $messageType = 'error';
        } else {
            $existingUser = getUserByUsername($_POST['username']);
            if ($existingUser) {
                $message = t('msg_username_exists');
                $messageType = 'error';
            } else {
                $role = isset($_POST['is_admin']) ? 'admin' : 'user';
                addUser($_POST['username'], $_POST['password'], $role);
                $message = t('msg_user_added');
                $messageType = 'success';
            }
        }
    } elseif ($_POST['action'] === 'edit_user') {
        requireAdmin();
        if (empty($_POST['username'])) {
            $message = t('msg_username_required');
            $messageType = 'error';
        } else {
            $userId = intval($_POST['id']);
            $role = isset($_POST['is_admin']) ? 'admin' : 'user';

            if ($userId === $_SESSION['user_id'] && $role !== 'admin') {
                $message = t('msg_cannot_demote');
                $messageType = 'error';
            } else {
                updateUser($userId, $_POST['username'], $role);
                if (!empty($_POST['password'])) {
                    if (strlen($_POST['password']) < 6) {
                        $message = t('msg_password_short');
                        $messageType = 'error';
                    } else {
                        updateUserPassword($userId, $_POST['password']);
                        $message = t('msg_user_updated');
                        $messageType = 'success';
                    }
                } else {
                    $message = t('msg_user_updated');
                    $messageType = 'success';
                }
            }
        }
    } elseif ($_POST['action'] === 'delete_user') {
        requireAdmin();
        $userId = intval($_POST['id']);
        if ($userId === $_SESSION['user_id']) {
            $message = t('msg_cannot_del_self');
            $messageType = 'error';
        } else {
            $user = getUserById($userId);
            if ($user && $user['role'] === 'admin') {
                $admins = getUsers();
                $adminCount = 0;
                foreach ($admins as $u) {
                    if ($u['role'] === 'admin') $adminCount++;
                }
                if ($adminCount <= 1) {
                    $message = t('msg_keep_admin');
                    $messageType = 'error';
                } else {
                    deleteUser($userId);
                    $message = t('msg_user_deleted');
                    $messageType = 'success';
                }
            } else {
                deleteUser($userId);
                $message = t('msg_user_deleted');
                $messageType = 'success';
            }
        }
    } elseif ($_POST['action'] === 'save_settings') {
        requireAdmin();
        setSetting('site_name', sanitize($_POST['site_name']));
        setSetting('site_description', sanitize($_POST['site_description']));
        $langVal = in_array($_POST['default_language'] ?? '', ['zh', 'en', 'ja', 'ko']) ? $_POST['default_language'] : 'zh';
        setSetting('default_language', $langVal);
        setSetting('background_type', sanitize($_POST['background_type']));
        setSetting('background_image', sanitize($_POST['background_image']));
        setSetting('footer_code', $_POST['footer_code']);
        setSetting('custom_css', $_POST['custom_css']);
        setSetting('captcha_enabled', isset($_POST['captcha_enabled']) ? '1' : '0');
        setSetting('ai_enabled', isset($_POST['ai_enabled']) ? '1' : '0');
        setSetting('ai_api_url', sanitize($_POST['ai_api_url']));
        setSetting('ai_api_key', sanitize($_POST['ai_api_key']));
        setSetting('ai_model', sanitize($_POST['ai_model']));
        setSetting('contact_wechat', sanitize($_POST['contact_wechat']));
        setSetting('contact_qq', sanitize($_POST['contact_qq']));
        setSetting('contact_email', sanitize($_POST['contact_email']));
        setSetting('contact_phone', sanitize($_POST['contact_phone']));

        $themeMode = in_array($_POST['theme_mode'] ?? '', ['auto', 'dark', 'light']) ? $_POST['theme_mode'] : 'dark';
        setSetting('theme_mode', $themeMode);
        setSetting('auto_dark_start', intval($_POST['auto_dark_start'] ?? 18));
        setSetting('auto_dark_end', intval($_POST['auto_dark_end'] ?? 6));
        setSetting('font_color_title', sanitize($_POST['font_color_title'] ?? ''));
        setSetting('font_color_body', sanitize($_POST['font_color_body'] ?? ''));
        setSetting('font_color_secondary', sanitize($_POST['font_color_secondary'] ?? ''));

        if (isset($_FILES['site_icon_file']) && $_FILES['site_icon_file']['error'] === UPLOAD_ERR_OK) {
            $iconPath = uploadSingleImage($_FILES['site_icon_file']);
            if ($iconPath) {
                setSetting('site_icon', $iconPath);
            }
        }

        if (isset($_FILES['background_image_file']) && $_FILES['background_image_file']['error'] === UPLOAD_ERR_OK) {
            $bgPath = uploadSingleImage($_FILES['background_image_file']);
            if ($bgPath) {
                setSetting('background_image', $bgPath);
            }
        }

        if (isset($_FILES['contact_image_file']) && $_FILES['contact_image_file']['error'] === UPLOAD_ERR_OK) {
            $contactImgPath = uploadSingleImage($_FILES['contact_image_file']);
            if ($contactImgPath) {
                setSetting('contact_image', $contactImgPath);
            }
        }

        $message = t('msg_settings_saved');
        $messageType = 'success';
    } elseif ($_POST['action'] === 'restore_defaults') {
        requireAdmin();
        $defaults = [
            'site_name' => '',
            'site_description' => '精选优质商品，优惠多多',
            'site_icon' => '',
            'background_type' => 'color',
            'background_image' => '',
            'footer_code' => '',
            'custom_css' => '',
            'ai_enabled' => '0',
            'ai_api_url' => '',
            'ai_api_key' => '',
            'ai_model' => 'gpt-3.5-turbo',
            'default_language' => 'zh',
            'contact_wechat' => '',
            'contact_qq' => '',
            'contact_email' => '',
            'contact_phone' => '',
            'contact_image' => '',
            'theme_mode' => 'dark',
            'auto_dark_start' => '18',
            'auto_dark_end' => '6',
            'font_color_title' => '',
            'font_color_body' => '',
            'font_color_secondary' => '',
            'captcha_enabled' => '1',
        ];
        foreach ($defaults as $key => $value) {
            setSetting($key, $value);
        }
        $message = t('msg_settings_restored');
        $messageType = 'success';
    }
}


if ($action === 'login') {
    if (isLoggedIn()) {
        header('Location: admin.php');
        exit;
    }
} elseif (!isLoggedIn()) {
    header('Location: admin.php?action=login');
    exit;
}

if ($action !== 'login') {
    $searchFilters = [
        'platform' => $_GET['platform'] ?? '',
        'status' => $_GET['status'] ?? '',
        'keyword' => $_GET['keyword'] ?? '',
    ];
    $hasFilters = !empty($searchFilters['platform']) || !empty($searchFilters['status']) || !empty($searchFilters['keyword']);
    $links = $hasFilters ? searchLinks($searchFilters) : getLinks();
    $allPlatforms = getDistinctPlatforms();
    $aiEnabled = isAiEnabled();
    $backgroundType = getBackgroundType();
    $backgroundImage = getBackgroundImage();
    if (isAdmin()) {
        $users = getUsers();
        $settings = getAllSettings();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@600;700;900&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            <?php
            if ($action !== 'login') {
                if ($backgroundType === 'color') {
                    echo 'background: ' . ($backgroundImage ?: '#0a0a0a') . ';';
                } elseif ($backgroundType === 'gradient') {
                    echo 'background: linear-gradient(135deg, ' . ($backgroundImage ?: '#0a0a0a, #111') . ');';
                } elseif ($backgroundType === 'image' && $backgroundImage) {
                    echo 'background: url("' . htmlspecialchars($backgroundImage) . '") no-repeat center center fixed; background-size: cover;';
                } else {
                    echo 'background: #0a0a0a;';
                }
            } else {
                echo 'background: #0a0a0a;';
            }
            ?>
            background-attachment: fixed;
        }
        .admin-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #0a0a0a;
            padding: 18px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: var(--radius-xl);
            margin-bottom: 24px;
            box-shadow: 0 6px 32px rgba(0, 255, 65, 0.3);
            position: relative;
            overflow: hidden;
        }

        .admin-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 260px;
            height: 260px;
            background: radial-gradient(circle, rgba(0, 255, 65, 0.08) 0%, transparent 70%);
            pointer-events: none;
        }

        .admin-header h1 {
            font-size: 1.3rem;
            font-weight: 700;
            position: relative;
        }

        .admin-header .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
        }

        .admin-header a {
            color: #0a0a0a;
            text-decoration: none;
            opacity: 0.85;
            transition: opacity var(--transition-fast);
            font-size: 0.88rem;
            font-weight: 600;
        }

        .admin-header a:hover {
            opacity: 1;
        }

        .admin-header .role-label {
            background: rgba(0, 255, 65, 0.12);
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 0.78rem;
        }

        .admin-header .logout-btn {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(0, 0, 0, 0.15);
            color: #0a0a0a;
            padding: 6px 16px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all var(--transition-fast);
        }

        .admin-header .logout-btn:hover {
            background: rgba(0, 0, 0, 0.35);
        }

        /* ===== Login ===== */
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }

        .login-card {
            background: #111;
            padding: 44px 36px;
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 400px;
            box-shadow: var(--shadow-xl), 0 0 40px rgba(0, 255, 65, 0.06);
            position: relative;
            animation: fadeIn 0.4s ease;
            border: 1px solid rgba(0, 255, 65, 0.15);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card h2 {
            text-align: center;
            margin-bottom: 32px;
            color: var(--gray-800);
            font-size: 1.5rem;
            font-weight: 800;
        }

        .login-back {
            text-align: center;
            margin-top: 20px;
        }

        .login-back a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.88rem;
        }

        .login-back a:hover {
            text-decoration: underline;
        }

        /* ===== Nav Tabs ===== */
        .nav-tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 24px;
            background: #111;
            padding: 6px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid rgba(0, 255, 65, 0.12);
        }

        .nav-tabs a {
            padding: 10px 22px;
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--gray-500);
            font-weight: 600;
            font-size: 0.88rem;
            transition: all var(--transition);
        }

        .nav-tabs a:hover {
            background: var(--gray-50);
            color: var(--gray-700);
        }

        .nav-tabs a.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #0a0a0a;
            box-shadow: 0 2px 8px rgba(0, 255, 65, 0.25);
        }

        /* ===== Form Grid ===== */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        /* ===== Password Toggle ===== */
        .password-toggle {
            position: relative;
        }

        .password-toggle input {
            width: 100%;
            padding-right: 44px;
        }

        .toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.05rem;
            opacity: 0.45;
            transition: opacity var(--transition-fast);
        }

        .toggle-btn:hover {
            opacity: 1;
        }

        /* ===== Button Group ===== */
        .btn-group {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        /* ===== Status & Role Badges ===== */
        .status-badge, .role-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .status-active {
            background: rgba(0, 255, 65, 0.12);
            color: var(--primary);
        }

        .status-disabled {
            background: rgba(248, 113, 113, 0.12);
            color: var(--danger);
        }

        .role-admin {
            background: rgba(251, 191, 36, 0.12);
            color: var(--warning);
        }

        .role-user {
            background: rgba(0, 255, 65, 0.08);
            color: var(--primary);
        }

        /* ===== Modal ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 10, 12, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: #111;
            padding: 32px;
            border-radius: var(--radius-xl);
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl), 0 0 40px rgba(0, 255, 65, 0.06);
            animation: modalIn 0.3s ease;
            border: 1px solid rgba(0, 255, 65, 0.15);
        }

        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.96) translateY(8px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-content h2 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 22px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 22px;
            padding-top: 18px;
            border-top: 1px solid var(--gray-100);
        }

        /* ===== Filter Bar ===== */
        .filter-bar {
            padding: 18px;
            background: #0a0a0a;
            border-radius: var(--radius-lg);
            margin-bottom: 18px;
            border: 1px solid rgba(0, 255, 65, 0.1);
        }

        /* ===== Settings Section ===== */
        .form-divider {
            margin: 28px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gray-100);
        }

        .form-divider h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--gray-800);
        }

        .current-icon img, .current-bg img {
            border-radius: 8px;
            border: 2px solid var(--gray-100);
        }

        /* ===== Table Enhancements ===== */
        .table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .link-title-cell {
            font-weight: 600;
            color: var(--gray-800);
        }

        @media (max-width: 768px) {
            .container {
                padding: 14px;
            }
            .admin-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
                padding: 16px;
                border-radius: var(--radius-lg);
            }
            .admin-header h1 {
                font-size: 1.15rem;
            }
            .admin-header .header-right {
                flex-wrap: wrap;
                justify-content: center;
                gap: 8px;
            }
            .admin-header .role-label {
                padding: 3px 8px;
                font-size: 0.72rem;
            }
            .admin-header .logout-btn {
                padding: 5px 14px;
                font-size: 0.8rem;
            }
            .nav-tabs {
                border-radius: var(--radius);
                padding: 5px;
                gap: 3px;
                margin-bottom: 18px;
            }
            .nav-tabs a {
                padding: 8px 14px;
                font-size: 0.82rem;
            }
            .card {
                padding: 20px 16px;
                border-radius: var(--radius-lg);
            }
            .card h2 {
                font-size: 1.1rem;
                margin-bottom: 18px;
            }
            .form-grid {
                gap: 12px;
            }
            .login-card {
                padding: 28px 20px;
                border-radius: var(--radius-lg);
            }
            .login-card h2 {
                font-size: 1.3rem;
                margin-bottom: 24px;
            }
            .modal-content {
                padding: 20px;
                border-radius: var(--radius-lg);
            }
            .modal-content h2 {
                font-size: 1.1rem;
                margin-bottom: 18px;
            }
            .filter-bar {
                padding: 14px;
            }
            .form-divider {
                margin: 22px 0 16px;
            }
            .form-divider h3 {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 480px) {
            .admin-header {
                padding: 14px;
                border-radius: var(--radius);
            }
            .admin-header h1 {
                font-size: 1.05rem;
            }
            .nav-tabs a {
                padding: 7px 10px;
                font-size: 0.78rem;
            }
            .card {
                padding: 16px 12px;
            }
            .card h2 {
                font-size: 1rem;
                margin-bottom: 14px;
            }
            .btn-group {
                gap: 4px;
            }
            .btn-sm {
                padding: 5px 10px;
                font-size: 0.72rem;
            }
            .login-card {
                padding: 24px 16px;
            }
            .modal-content {
                padding: 16px;
                width: 95%;
            }
            .filter-bar {
                padding: 12px;
            }
        }
        <?php echo getThemeCss(); ?>
    </style>
</head>
<body>
<?php if ($action === 'login'): ?>
    <div class="login-container">
        <div class="login-card">
            <h2><?php echo t('admin_login'); ?></h2>
            <?php if ($message): ?>
                <div class="message message-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <form method="POST" id="loginForm">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <div class="form-group">
                    <label><?php echo t('admin_username'); ?></label>
                    <input type="text" name="username" required placeholder="<?php echo t('admin_username_ph'); ?>">
                </div>
                <div class="form-group">
                    <label><?php echo t('admin_password'); ?></label>
                    <div class="password-toggle">
                        <input type="password" name="password" id="login_password" required placeholder="<?php echo t('admin_password_ph'); ?>">
                        <button type="button" class="toggle-btn" onclick="togglePassword('login_password', this)">👁️</button>
                    </div>
                </div>
                <?php if (isCaptchaEnabled()): ?>
                <div class="form-group">
                    <label>验证码</label>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <img src="captcha.php?t=<?php echo time(); ?>" alt="验证码" id="captchaImg" style="height:44px;border-radius:6px;cursor:pointer;" onclick="this.src='captcha.php?t='+Date.now()" title="点击刷新验证码">
                        <input type="text" name="captcha" required maxlength="4" placeholder="请输入验证码" style="width:140px;font-size:1.1rem;letter-spacing:4px;text-align:center;text-transform:uppercase;" autocomplete="off">
                    </div>
                </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary btn-full" style="margin-top: 8px;"><?php echo t('admin_login_btn'); ?></button>
            </form>
            <div class="login-back">
                <a href="index.php"><?php echo t('admin_back_front'); ?></a>
            </div>
        </div>
    </div>


<?php else: ?>
    <div class="container">
        <div class="admin-header">
            <h1><?php echo htmlspecialchars($_SESSION['username']); ?></h1>
            <div class="header-right">
                <a href="index.php"><?php echo t('admin_view_front'); ?></a>
                <span class="role-label"><?php echo isAdmin() ? t('admin_role_admin') : t('admin_role_user'); ?></span>
                <?php
                $langList = ['zh' => '中文', 'en' => 'EN', 'ja' => '日本語', 'ko' => '한국어'];
                $curLang = getCurrentLang();
                ?>
                <span style="display:inline-flex;gap:2px;background:rgba(0, 255, 65, 0.12);border-radius:50px;padding:3px 6px;">
                    <?php foreach ($langList as $lk => $lv): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['lang' => $lk])); ?>" style="padding:2px 8px;border-radius:50px;font-size:0.75rem;<?php echo $lk === $curLang ? 'background:rgba(255,255,255,0.22);font-weight:700;' : ''; ?>"><?php echo $lv; ?></a>
                    <?php endforeach; ?>
                </span>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="logout">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <button type="submit" class="logout-btn"><?php echo t('admin_logout'); ?></button>
                </form>
            </div>
        </div>

        <div class="nav-tabs">
            <a href="admin.php" class="<?php echo !isset($_GET['tab']) ? 'active' : ''; ?>"><?php echo t('admin_tab_links'); ?></a>
            <?php if (isAdmin()): ?>
            <a href="admin.php?tab=users" class="<?php echo isset($_GET['tab']) && $_GET['tab'] === 'users' ? 'active' : ''; ?>"><?php echo t('admin_tab_users'); ?></a>
            <a href="admin.php?tab=settings" class="<?php echo isset($_GET['tab']) && $_GET['tab'] === 'settings' ? 'active' : ''; ?>"><?php echo t('admin_tab_settings'); ?></a>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
        <div class="message message-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if (!isset($_GET['tab']) || $_GET['tab'] === 'links'): ?>
        <div class="card">
            <h2><?php echo t('admin_add_link'); ?></h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_link">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo t('admin_platform'); ?></label>
                        <select name="platform" id="add_platform" required onchange="toggleCustomPlatform(this, 'add_custom_platform')">
                            <option value="淘宝">淘宝</option>
                            <option value="天猫">天猫</option>
                            <option value="京东">京东</option>
                            <option value="闲鱼">闲鱼</option>
                            <option value="亚马逊">亚马逊</option>
                            <option value="拼多多">拼多多</option>
                            <option value="抖音">抖音</option>
                            <option value="唯品会">唯品会</option>
                            <option value="自定义"><?php echo t('other'); ?></option>
                        </select>
                        <input type="text" name="custom_platform" id="add_custom_platform" placeholder="<?php echo t('admin_custom_platform_ph'); ?>" style="display:none; margin-top:8px;">
                    </div>
                    <div class="form-group">
                        <label><?php echo t('admin_link_title'); ?></label>
                        <input type="text" name="title" required placeholder="e.g. Taobao Flagship Store">
                    </div>
                    <div class="form-group">
                        <label><?php echo t('admin_jump_url'); ?></label>
                        <div style="display: flex; gap: 8px;">
                            <input type="url" name="url" id="add_url" required placeholder="https://..." style="flex: 1;">
                            <button type="button" class="btn btn-info btn-sm" onclick="fetchUrlInfo()" id="fetchBtn" style="white-space: nowrap; flex-shrink: 0;"><?php echo t('admin_fetch_btn'); ?></button>
                        </div>
                        <div class="hint"><?php echo t('admin_fetch_hint'); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo t('admin_price_opt'); ?></label>
                        <input type="text" name="price" placeholder="e.g. ¥99.00">
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo t('admin_images_opt'); ?></label>
                    <input type="file" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple onchange="validateFiles(this)">
                    <div class="hint"><?php echo t('admin_images_hint'); ?></div>
                </div>
                <div class="form-group">
                    <label><?php echo t('admin_desc'); ?></label>
                    <textarea name="description" rows="2" placeholder="..."></textarea>
                </div>
                <div class="form-group">
                    <label><?php echo t('admin_display_text'); ?></label>
                    <input type="text" name="display_text" placeholder="<?php echo t('admin_display_ph'); ?>">
                    <div class="hint"><?php echo t('admin_display_hint'); ?></div>
                </div>
                <button type="submit" class="btn btn-primary"><?php echo t('admin_add_btn'); ?></button>
            </form>
        </div>

        <div class="card">
            <h2><?php echo t('admin_link_list'); ?></h2>
            <form method="GET" class="filter-bar">
                <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 120px;">
                        <label style="font-size: 0.78rem; color: var(--gray-500);"><?php echo t('admin_keyword'); ?></label>
                        <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchFilters['keyword']); ?>" placeholder="Title / Description / Tags">
                    </div>
                    <div class="form-group" style="margin-bottom: 0; min-width: 100px;">
                        <label style="font-size: 0.78rem; color: var(--gray-500);"><?php echo t('admin_col_platform'); ?></label>
                        <select name="platform">
                            <option value=""><?php echo t('admin_all_platform'); ?></option>
                            <?php foreach ($allPlatforms as $p): ?>
                            <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $searchFilters['platform'] === $p ? 'selected' : ''; ?>><?php echo htmlspecialchars($p); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0; min-width: 80px;">
                        <label style="font-size: 0.78rem; color: var(--gray-500);"><?php echo t('admin_col_status'); ?></label>
                        <select name="status">
                            <option value=""><?php echo t('admin_all_status'); ?></option>
                            <option value="active" <?php echo $searchFilters['status'] === 'active' ? 'selected' : ''; ?>><?php echo t('admin_status_active'); ?></option>
                            <option value="disabled" <?php echo $searchFilters['status'] === 'disabled' ? 'selected' : ''; ?>><?php echo t('admin_status_disabled'); ?></option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <button type="submit" class="btn btn-primary btn-sm"><?php echo t('admin_filter'); ?></button>
                        <?php if ($hasFilters): ?>
                        <a href="admin.php" class="btn btn-secondary btn-sm"><?php echo t('admin_clear'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($hasFilters): ?>
                <div style="margin-top: 8px; font-size: 0.82rem; color: var(--gray-500);">
                    <?php echo str_replace('{count}', count($links), t('admin_results')); ?>
                </div>
                <?php endif; ?>
            </form>
            <?php if (empty($links)): ?>
                <p style="color: var(--gray-500); text-align: center; padding: 32px;"><?php echo t('admin_no_links'); ?></p>
            <?php else: ?>
                <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo t('admin_col_platform'); ?></th>
                            <th><?php echo t('admin_col_title'); ?></th>
                            <th><?php echo t('admin_col_price'); ?></th>
                            <th>URL</th>
                            <th><?php echo t('admin_col_status'); ?></th>
                            <th><?php echo t('admin_col_clicks'); ?></th>
                            <th><?php echo t('admin_col_created'); ?></th>
                            <th><?php echo t('admin_col_actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($links as $link): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($link['platform']); ?></td>
                                <td class="link-title-cell"><?php echo htmlspecialchars($link['title']); ?></td>
                                <td style="color: var(--danger); font-weight: 600;"><?php echo !empty($link['price']) ? htmlspecialchars($link['price']) : '-'; ?></td>
                                <td><a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" style="color: var(--primary); text-decoration: none;"><?php echo t('admin_view'); ?></a></td>
                                <td>
                                    <span class="status-badge status-<?php echo $link['status']; ?>">
                                        <?php echo $link['status'] === 'active' ? t('admin_status_active') : t('admin_status_disabled'); ?>
                                    </span>
                                </td>
                                <td><?php echo $link['clicks']; ?></td>
                                <td style="color: var(--gray-500); font-size: 0.85rem;"><?php echo $link['created_at']; ?></td>
                                <td class="btn-group">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_link">
                                        <input type="hidden" name="id" value="<?php echo $link['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm">
                                            <?php echo $link['status'] === 'active' ? t('admin_disable') : t('admin_enable'); ?>
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="editLink('<?php echo $link['id']; ?>')"><?php echo t('admin_edit'); ?></button>
                                    <?php if ($aiEnabled): ?>
                                    <button type="button" class="btn btn-info btn-sm" onclick="regenerateAi('<?php echo $link['id']; ?>')">AI</button>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('<?php echo t('msg_confirm_delete'); ?>');">
                                        <input type="hidden" name="action" value="delete_link">
                                        <input type="hidden" name="id" value="<?php echo $link['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"><?php echo t('admin_delete'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
        <?php elseif (isset($_GET['tab']) && $_GET['tab'] === 'users'): ?>
        <?php if (isAdmin()): ?>
        <div class="card">
            <h2><?php echo t('admin_add_user'); ?></h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo t('admin_username_label'); ?> <span style="color: var(--danger);">*</span></label>
                        <input type="text" name="username" required placeholder="a-z, 0-9, _" pattern="[a-zA-Z0-9_]+" title="<?php echo t('msg_username_invalid'); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo t('admin_password_label'); ?> <span style="color: var(--danger);">*</span></label>
                        <div class="password-toggle">
                            <input type="password" name="password" id="add_password" required placeholder="≥6" minlength="6">
                            <button type="button" class="toggle-btn" onclick="togglePassword('add_password', this)">👁️</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?php echo t('admin_role'); ?></label>
                        <select name="is_admin">
                            <option value=""><?php echo t('admin_role_user'); ?></option>
                            <option value="1"><?php echo t('admin_role_admin'); ?></option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><?php echo t('admin_add_user_btn'); ?></button>
            </form>
        </div>

        <div class="card">
            <h2><?php echo t('admin_user_list'); ?></h2>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?php echo t('admin_username_label'); ?></th>
                        <th><?php echo t('admin_role'); ?></th>
                        <th><?php echo t('admin_col_created'); ?></th>
                        <th><?php echo t('admin_col_actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td style="font-weight: 600;"><?php echo htmlspecialchars($user['username']); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                    <?php echo $user['role'] === 'admin' ? t('admin_role_admin') : t('admin_role_user'); ?>
                                </span>
                                <?php if ($user['id'] === $_SESSION['user_id']): ?>
                                <span style="color: var(--gray-400); font-size: 0.8rem; margin-left: 6px;"><?php echo t('admin_current'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="color: var(--gray-500); font-size: 0.85rem;"><?php echo $user['created_at']; ?></td>
                            <td class="btn-group">
                                <button type="button" class="btn btn-primary btn-sm" onclick="editUser('<?php echo $user['id']; ?>')"><?php echo t('admin_edit'); ?></button>
                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('<?php echo t('msg_confirm_delete'); ?>');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"><?php echo t('admin_delete'); ?></button>
                                </form>
                                <?php else: ?>
                                <span style="color: var(--gray-400); font-size: 0.8rem;"><?php echo t('admin_cannot_del_self'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>
        <?php elseif (isset($_GET['tab']) && $_GET['tab'] === 'settings'): ?>
        <?php if (isAdmin()): ?>
        <div class="card">
            <h2><?php echo t('admin_tab_settings'); ?></h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_settings">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo t('admin_site_name'); ?></label>
                        <input type="text" name="site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo t('admin_site_desc'); ?></label>
                        <input type="text" name="site_description" value="<?php echo htmlspecialchars($settings['site_description'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo t('admin_default_lang'); ?></label>
                        <select name="default_language">
                            <option value="zh" <?php echo ($settings['default_language'] ?? 'zh') === 'zh' ? 'selected' : ''; ?>>中文</option>
                            <option value="en" <?php echo ($settings['default_language'] ?? '') === 'en' ? 'selected' : ''; ?>>English</option>
                            <option value="ja" <?php echo ($settings['default_language'] ?? '') === 'ja' ? 'selected' : ''; ?>>日本語</option>
                            <option value="ko" <?php echo ($settings['default_language'] ?? '') === 'ko' ? 'selected' : ''; ?>>한국어</option>
                        </select>
                        <div class="hint"><?php echo t('admin_default_lang_hint'); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo t('admin_site_icon'); ?></label>
                        <input type="file" name="site_icon_file" accept="image/png,image/jpeg,image/gif,image/x-icon,image/svg+xml">
                        <?php if (!empty($settings['site_icon'])): ?>
                        <div class="current-icon">
                            <span style="font-size: 0.85rem; color: var(--gray-500);"><?php echo t('admin_current_icon'); ?></span>
                            <img src="<?php echo htmlspecialchars($settings['site_icon']); ?>" alt="icon" style="max-width:32px;max-height:32px;vertical-align:middle;">
                        </div>
                        <?php endif; ?>
                        <div class="hint"><?php echo t('admin_icon_hint'); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo t('admin_bg_type'); ?></label>
                        <select name="background_type" id="bg_type" onchange="toggleBgInput()">
                            <option value="color" <?php echo ($settings['background_type'] ?? 'color') === 'color' ? 'selected' : ''; ?>><?php echo t('admin_bg_color'); ?></option>
                            <option value="gradient" <?php echo ($settings['background_type'] ?? '') === 'gradient' ? 'selected' : ''; ?>><?php echo t('admin_bg_gradient'); ?></option>
                            <option value="image" <?php echo ($settings['background_type'] ?? '') === 'image' ? 'selected' : ''; ?>><?php echo t('admin_bg_image'); ?></option>
                        </select>
                    </div>
                </div>

                <div id="bg_color_input" class="form-group">
                    <label><?php echo t('admin_bg_label'); ?></label>
                    <input type="text" name="background_image" id="bg_image" value="<?php echo htmlspecialchars($settings['background_image'] ?? ''); ?>" placeholder="#e0e7ff | #e0e7ff, #dbeafe | https://...">
                    <div class="hint">Color: #e0e7ff | Gradient: #e0e7ff, #dbeafe | Image: https://...</div>
                </div>

                <div id="bg_image_upload" class="form-group" style="display:<?php echo ($settings['background_type'] ?? '') === 'image' ? 'block' : 'none'; ?>;">
                    <label><?php echo t('admin_bg_upload'); ?></label>
                    <input type="file" name="background_image_file" accept="image/*">
                    <?php if (!empty($settings['background_image']) && ($settings['background_type'] ?? '') === 'image' && strpos($settings['background_image'], 'http') !== 0): ?>
                    <div class="current-bg">
                        <span style="font-size: 0.85rem; color: var(--gray-500);"><?php echo t('admin_current_bg'); ?></span>
                        <img src="<?php echo htmlspecialchars($settings['background_image']); ?>" alt="bg" style="max-width:100px;max-height:60px;vertical-align:middle;">
                    </div>
                    <?php endif; ?>
                    <div class="hint"><?php echo t('admin_bg_hint'); ?></div>
                </div>

                <div class="form-divider">
                    <h3><?php echo t('admin_theme_settings'); ?></h3>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo t('admin_theme_mode'); ?></label>
                        <select name="theme_mode" id="theme_mode" onchange="toggleAutoTheme()">
                            <option value="dark" <?php echo ($settings['theme_mode'] ?? 'dark') === 'dark' ? 'selected' : ''; ?>><?php echo t('admin_theme_dark'); ?></option>
                            <option value="light" <?php echo ($settings['theme_mode'] ?? '') === 'light' ? 'selected' : ''; ?>><?php echo t('admin_theme_light'); ?></option>
                            <option value="auto" <?php echo ($settings['theme_mode'] ?? '') === 'auto' ? 'selected' : ''; ?>><?php echo t('admin_theme_auto'); ?></option>
                        </select>
                    </div>
                    <div class="form-group" id="auto_theme_range" style="display:<?php echo ($settings['theme_mode'] ?? 'dark') === 'auto' ? 'block' : 'none'; ?>;">
                        <label><?php echo t('admin_theme_auto_hint'); ?></label>
                        <div style="display:flex;gap:10px;align-items:center;">
                            <div style="flex:1;">
                                <div class="hint"><?php echo t('admin_auto_dark_start'); ?></div>
                                <select name="auto_dark_start">
                                    <?php for ($h = 0; $h < 24; $h++): ?>
                                    <option value="<?php echo $h; ?>" <?php echo intval($settings['auto_dark_start'] ?? 18) === $h ? 'selected' : ''; ?>><?php echo sprintf('%02d:00', $h); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <span style="color:var(--gray-500);margin-top:18px;">→</span>
                            <div style="flex:1;">
                                <div class="hint"><?php echo t('admin_auto_dark_end'); ?></div>
                                <select name="auto_dark_end">
                                    <?php for ($h = 0; $h < 24; $h++): ?>
                                    <option value="<?php echo $h; ?>" <?php echo intval($settings['auto_dark_end'] ?? 6) === $h ? 'selected' : ''; ?>><?php echo sprintf('%02d:00', $h); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_preset_theme'); ?></label>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="applyPreset('dark')"><?php echo t('admin_preset_dark'); ?></button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="applyPreset('light')"><?php echo t('admin_preset_light'); ?></button>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo t('admin_font_title'); ?></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" name="font_color_title" id="font_color_title" value="<?php echo htmlspecialchars($settings['font_color_title'] ?: '#ffffff'); ?>" style="width:44px;height:36px;padding:2px;cursor:pointer;" onchange="document.getElementById('font_color_title_text').value=this.value">
                            <input type="text" name="font_color_title_text" id="font_color_title_text" value="<?php echo htmlspecialchars($settings['font_color_title'] ?? ''); ?>" placeholder="#ffffff" style="flex:1;" oninput="syncColor('font_color_title')">
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?php echo t('admin_font_body'); ?></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" name="font_color_body" id="font_color_body" value="<?php echo htmlspecialchars($settings['font_color_body'] ?: '#eeeeee'); ?>" style="width:44px;height:36px;padding:2px;cursor:pointer;" onchange="document.getElementById('font_color_body_text').value=this.value">
                            <input type="text" name="font_color_body_text" id="font_color_body_text" value="<?php echo htmlspecialchars($settings['font_color_body'] ?? ''); ?>" placeholder="#eeeeee" style="flex:1;" oninput="syncColor('font_color_body')">
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?php echo t('admin_font_secondary'); ?></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" name="font_color_secondary" id="font_color_secondary" value="<?php echo htmlspecialchars($settings['font_color_secondary'] ?: '#888888'); ?>" style="width:44px;height:36px;padding:2px;cursor:pointer;" onchange="document.getElementById('font_color_secondary_text').value=this.value">
                            <input type="text" name="font_color_secondary_text" id="font_color_secondary_text" value="<?php echo htmlspecialchars($settings['font_color_secondary'] ?? ''); ?>" placeholder="#888888" style="flex:1;" oninput="syncColor('font_color_secondary')">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_custom_css'); ?></label>
                    <textarea name="custom_css" rows="4" placeholder="body { ... } .card { ... }"><?php echo htmlspecialchars($settings['custom_css'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_footer_code'); ?></label>
                    <textarea name="footer_code" rows="4"><?php echo htmlspecialchars($settings['footer_code'] ?? ''); ?></textarea>
                </div>

                <div class="form-divider">
                    <h3><?php echo t('admin_contact_settings'); ?></h3>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo t('admin_contact_wechat'); ?></label>
                        <input type="text" name="contact_wechat" value="<?php echo htmlspecialchars($settings['contact_wechat'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo t('admin_contact_qq'); ?></label>
                        <input type="text" name="contact_qq" value="<?php echo htmlspecialchars($settings['contact_qq'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo t('admin_contact_email'); ?></label>
                        <input type="text" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo t('admin_contact_phone'); ?></label>
                        <input type="text" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_contact_image'); ?></label>
                    <input type="file" name="contact_image_file" accept="image/jpeg,image/png,image/gif,image/webp">
                    <?php if (!empty($settings['contact_image'])): ?>
                    <div class="current-icon">
                        <span style="font-size: 0.85rem; color: var(--gray-500);"><?php echo t('admin_current_image'); ?></span>
                        <img src="<?php echo htmlspecialchars($settings['contact_image']); ?>" alt="contact" style="max-width:120px;max-height:120px;vertical-align:middle;margin-top:8px;">
                    </div>
                    <?php endif; ?>
                    <div class="hint"><?php echo t('admin_contact_image_hint'); ?></div>
                </div>

                <div class="form-divider">
                    <h3><?php echo t('admin_security_settings'); ?></h3>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="captcha_enabled" value="1" <?php echo ($settings['captcha_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                        <?php echo t('admin_captcha_enable'); ?>
                    </label>
                    <div class="hint"><?php echo t('admin_captcha_enable_hint'); ?></div>
                </div>

                <div class="form-divider">
                    <h3><?php echo t('admin_ai_settings'); ?></h3>
                </div>

                <div class="form-group" style="background: #0a0a0a; padding: 20px; border-radius: var(--radius-lg); margin-bottom: 20px; border: 1px solid rgba(0, 255, 65, 0.1);">
                    <h4 style="margin: 0 0 14px 0; color: var(--gray-800); font-size: 0.95rem;"><?php echo t('admin_ai_api_desc'); ?></h4>
                    <ul style="margin: 0; padding-left: 20px; color: var(--gray-700); font-size: 0.88rem; line-height: 1.8;">
                        <li><strong>API URL：</strong>OpenAI-compatible API endpoint</li>
                        <li style="margin-top: 8px;">
                            <strong>Examples:</strong><br>
                            • <code style="background: #1e1e1e; padding: 2px 6px; border-radius: 4px; color: var(--primary);">https://api.openai.com/v1/chat/completions</code> (OpenAI)<br>
                            • <code style="background: #1e1e1e; padding: 2px 6px; border-radius: 4px; color: var(--primary);">https://api.deepseek.com/v1/chat/completions</code> (DeepSeek)<br>
                            • <code style="background: #1e1e1e; padding: 2px 6px; border-radius: 4px; color: var(--primary);">https://open.bigmodel.cn/api/paas/v4/chat/completions</code> (Zhipu BigModel)<br>
                        </li>
                        <li style="margin-top: 8px;"><strong>API Key：</strong>Secret key from your AI provider</li>
                        <li style="margin-top: 8px;"><strong>Model：</strong>Model name for your API service</li>
                    </ul>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="ai_enabled" value="1" <?php echo ($settings['ai_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                        <?php echo t('admin_ai_enable'); ?>
                    </label>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_ai_api_url'); ?></label>
                    <input type="url" name="ai_api_url" value="<?php echo htmlspecialchars($settings['ai_api_url'] ?? ''); ?>" placeholder="https://api.openai.com/v1/chat/completions">
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_ai_api_key'); ?></label>
                    <input type="password" name="ai_api_key" value="<?php echo htmlspecialchars($settings['ai_api_key'] ?? ''); ?>" placeholder="sk-...">
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_ai_model'); ?></label>
                    <input type="text" name="ai_model" value="<?php echo htmlspecialchars($settings['ai_model'] ?? 'gpt-3.5-turbo'); ?>" placeholder="gpt-3.5-turbo / gpt-4 / deepseek-chat">
                    <div class="hint"><?php echo t('admin_ai_model_hint'); ?></div>
                </div>

                <div class="form-group">
                    <button type="button" id="testAiBtn" class="btn btn-secondary" onclick="testAi()"><?php echo t('admin_ai_test'); ?></button>
                    <div id="aiTestResult" style="margin-top: 10px;"></div>
                </div>

                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="submit" class="btn btn-primary"><?php echo t('admin_save_settings'); ?></button>
                    <button type="button" class="btn btn-danger" onclick="confirmRestore()"><?php echo t('admin_restore_defaults'); ?></button>
                </div>
            </form>
            <form method="POST" id="restoreForm" style="display: none;">
                <input type="hidden" name="action" value="restore_defaults">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            </form>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <div id="editLinkModal" class="modal-overlay">
        <div class="modal-content">
            <h2><?php echo t('admin_edit_link'); ?></h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_link">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label><?php echo t('admin_platform'); ?></label>
                    <select name="platform" id="edit_platform" required onchange="toggleCustomPlatform(this, 'edit_custom_platform')">
                        <option value="淘宝">淘宝</option>
                        <option value="天猫">天猫</option>
                        <option value="京东">京东</option>
                        <option value="闲鱼">闲鱼</option>
                        <option value="亚马逊">亚马逊</option>
                        <option value="拼多多">拼多多</option>
                        <option value="抖音">抖音</option>
                        <option value="唯品会">唯品会</option>
                        <option value="自定义"><?php echo t('other'); ?></option>
                    </select>
                    <input type="text" name="custom_platform" id="edit_custom_platform" placeholder="<?php echo t('admin_custom_platform_ph'); ?>" style="display:none; margin-top:8px;">
                </div>
                <div class="form-group">
                    <label><?php echo t('admin_link_title'); ?></label>
                    <input type="text" name="title" id="edit_title" required>
                </div>
                <div class="form-group">
                    <label><?php echo t('admin_jump_url'); ?></label>
                    <input type="url" name="url" id="edit_url" required>
                </div>
                <div class="form-group">
                    <label><?php echo t('admin_images_opt'); ?></label>
                    <input type="file" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple onchange="validateFiles(this)">
                    <input type="hidden" name="keep_images" id="keep_images" value="1">
                    <div id="current_images" style="margin-top: 10px;"></div>
                    <div class="hint"><?php echo t('admin_images_hint'); ?></div>
                </div>
                <div class="form-group">
                    <label><?php echo t('admin_desc'); ?></label>
                    <textarea name="description" id="edit_description" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label><?php echo t('admin_price_opt'); ?></label>
                    <input type="text" name="price" id="edit_price" placeholder="e.g. ¥99.00">
                </div>
                <?php if ($aiEnabled): ?>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="regenerate_ai" value="1">
                        <?php echo t('admin_regenerate_ai'); ?>
                    </label>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label><?php echo t('admin_display_text'); ?></label>
                    <input type="text" name="display_text" id="edit_display_text" placeholder="<?php echo t('admin_display_ph'); ?>">
                    <div class="hint"><?php echo t('admin_display_hint'); ?></div>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeEditLinkModal()" class="btn btn-secondary"><?php echo t('admin_cancel'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo t('admin_save'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <div id="editUserModal" class="modal-overlay">
        <div class="modal-content">
            <h2><?php echo t('admin_edit_user'); ?></h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="id" id="edit_user_id">
                <div class="form-group">
                    <label><?php echo t('admin_username_label'); ?> <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="username" id="edit_username" required pattern="[a-zA-Z0-9_]+">
                </div>
                <div class="form-group">
                    <label><?php echo t('admin_new_password'); ?></label>
                    <div class="password-toggle">
                        <input type="password" name="password" id="edit_password" placeholder="≥6" minlength="6">
                        <button type="button" class="toggle-btn" onclick="togglePassword('edit_password', this)">👁️</button>
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo t('admin_role'); ?></label>
                    <select name="is_admin" id="edit_is_admin">
                        <option value=""><?php echo t('admin_role_user'); ?></option>
                        <option value="1"><?php echo t('admin_role_admin'); ?></option>
                    </select>
                    <div class="hint" id="edit_role_hint"></div>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeEditUserModal()" class="btn btn-secondary"><?php echo t('admin_cancel'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo t('admin_save'); ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
const linksData = <?php echo json_encode($links ?? []); ?>;
const usersData = <?php echo json_encode($users ?? []); ?>;

function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🙈';
    } else {
        input.type = 'password';
        btn.textContent = '👁️';
    }
}

function editLink(id) {
    const link = linksData.find(l => l.id === id);
    if (link) {
        document.getElementById('edit_id').value = link.id;
        const predefinedPlatforms = ['淘宝', '天猫', '京东', '闲鱼', '亚马逊', '拼多多', '抖音', '唯品会'];
        const platformSelect = document.getElementById('edit_platform');
        const customPlatformInput = document.getElementById('edit_custom_platform');
        if (predefinedPlatforms.includes(link.platform)) {
            platformSelect.value = link.platform;
            customPlatformInput.style.display = 'none';
            customPlatformInput.required = false;
        } else {
            platformSelect.value = '自定义';
            customPlatformInput.style.display = 'block';
            customPlatformInput.required = true;
            customPlatformInput.value = link.platform;
        }
        document.getElementById('edit_title').value = link.title;
        document.getElementById('edit_url').value = link.url;
        document.getElementById('edit_description').value = link.description || '';
        const currentImagesDiv = document.getElementById('current_images');
        if (link.images) {
            let images = [];
            try {
                images = JSON.parse(link.images);
            } catch (e) {
                images = [link.images];
            }
            let html = '';
            images.forEach(function(img, index) {
                const thumbSrc = (typeof img === 'object' && img !== null) ? (img.thumb || img.full || '') : img;
                const fullSrc = (typeof img === 'object' && img !== null) ? (img.full || img.thumb || '') : img;
                html += '<img src="' + thumbSrc + '" data-full="' + fullSrc + '" style="max-width: 80px; max-height: 80px; margin-right: 6px; margin-bottom: 6px; object-fit: cover; border-radius: 8px; border: 2px solid var(--gray-100); cursor: zoom-in;" onclick="window.open(this.dataset.full, \'_blank\')">';
            });
            if (images.length > 0) {
                html += '<label style="margin-left: 10px; font-size: 0.88rem;"><input type="checkbox" name="delete_images" value="1"> Delete images</label>';
            }
            currentImagesDiv.innerHTML = html;
            document.getElementById('keep_images').value = '1';
        } else {
            currentImagesDiv.innerHTML = '';
            document.getElementById('keep_images').value = '0';
        }
        document.getElementById('edit_display_text').value = link.display_text || '';
        document.getElementById('edit_price').value = link.price || '';
        document.getElementById('editLinkModal').style.display = 'flex';
    }
}

function closeEditLinkModal() {
    document.getElementById('editLinkModal').style.display = 'none';
}

function editUser(id) {
    const user = usersData.find(u => u.id == id);
    if (user) {
        document.getElementById('edit_user_id').value = user.id;
        document.getElementById('edit_username').value = user.username;
        document.getElementById('edit_password').value = '';
        document.getElementById('edit_is_admin').value = user.role === 'admin' ? '1' : '';
        const roleHint = document.getElementById('edit_role_hint');
        const isAdminSelect = document.getElementById('edit_is_admin');
        if (user.id == <?php echo (int)($_SESSION['user_id'] ?? 0); ?>) {
            roleHint.innerHTML = '<span style="color: #f59e0b;"><?php echo t('admin_role_hint'); ?></span>';
            isAdminSelect.disabled = true;
        } else {
            roleHint.innerHTML = '';
            isAdminSelect.disabled = false;
        }
        document.getElementById('editUserModal').style.display = 'flex';
    }
}

function closeEditUserModal() {
    document.getElementById('editUserModal').style.display = 'none';
}

function validateFiles(input) {
    if (input.files.length > 9) {
        alert('<?php echo t('msg_max_images'); ?>');
        input.value = '';
        return;
    }
    for (let i = 0; i < input.files.length; i++) {
        if (input.files[i].size > 10 * 1024 * 1024) {
            alert('<?php echo str_replace('{name}', '" + input.files[i].name + "', t('msg_image_too_large')); ?>');
            input.value = '';
            return;
        }
    }
}

function fetchUrlInfo() {
    const url = document.getElementById('add_url').value.trim();
    if (!url) { alert('<?php echo t('msg_enter_url_first'); ?>'); return; }
    const btn = document.getElementById('fetchBtn');
    btn.disabled = true;
    btn.textContent = '...';
    const formData = new FormData();
    formData.append('action', 'fetch_url_info');
    formData.append('url', url);
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const d = data.data;
            if (d.title) document.querySelector('input[name="title"]').value = d.title;
            if (d.description) document.querySelector('textarea[name="description"]').value = d.description;
            if (d.price) document.querySelector('input[name="price"]').value = d.price;
            const platformMap = {'taobao.com':'淘宝','tmall.com':'天猫','tmall.hk':'天猫','jd.com':'京东','jd.hk':'京东','goofish.com':'闲鱼','xianyu.com':'闲鱼','pinduoduo.com':'拼多多','yangkeduo.com':'拼多多','douyin.com':'抖音','amazon':'亚马逊','vip.com':'唯品会'};
            const host = new URL(url).hostname;
            let detected = '';
            for (const [domain, platform] of Object.entries(platformMap)) {
                if (host.includes(domain)) { detected = platform; break; }
            }
            if (detected) {
                const sel = document.getElementById('add_platform');
                const opts = Array.from(sel.options).map(o => o.value);
                if (opts.includes(detected)) { sel.value = detected; }
                else { sel.value = '自定义'; document.getElementById('add_custom_platform').style.display = 'block'; document.getElementById('add_custom_platform').value = detected; }
            }
            alert('<?php echo t('msg_fetch_success'); ?>');
        } else {
            alert('<?php echo t('msg_fetch_fail'); ?>' + data.error);
        }
    })
    .catch(e => alert('<?php echo t('msg_request_fail'); ?>' + e.message))
    .finally(() => { btn.disabled = false; btn.textContent = '<?php echo t('admin_fetch_btn'); ?>'; });
}

function regenerateAi(linkId) {
    if (!confirm('<?php echo t('msg_confirm_regen'); ?>')) return;
    const formData = new FormData();
    formData.append('action', 'regenerate_ai');
    formData.append('id', linkId);
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('<?php echo t('msg_ai_updated'); ?>');
            location.reload();
        } else {
            alert('<?php echo t('msg_ai_fail'); ?>' + (data.error || ''));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('<?php echo t('msg_request_retry'); ?>');
    });
}

function toggleCustomPlatform(selectEl, inputId) {
    const inputEl = document.getElementById(inputId);
    if (selectEl.value === '自定义') {
        inputEl.style.display = 'block';
        inputEl.required = true;
    } else {
        inputEl.style.display = 'none';
        inputEl.required = false;
        inputEl.value = '';
    }
}

function toggleBgInput() {
    const bgType = document.getElementById('bg_type').value;
    const bgInput = document.getElementById('bg_image');
    const bgUpload = document.getElementById('bg_image_upload');
    const hint = bgInput.parentElement.querySelector('.hint');
    if (bgType === 'color') {
        bgInput.value = '#e0e7ff';
        if (hint) hint.textContent = 'Color: #e0e7ff | Gradient: #e0e7ff, #dbeafe | Image: https://...';
        if (bgUpload) bgUpload.style.display = 'none';
    } else if (bgType === 'gradient') {
        bgInput.value = '#e0e7ff, #dbeafe';
        if (hint) hint.textContent = 'Color: #e0e7ff | Gradient: #e0e7ff, #dbeafe | Image: https://...';
        if (bgUpload) bgUpload.style.display = 'none';
    } else {
        bgInput.value = '';
        if (hint) hint.textContent = 'Color: #e0e7ff | Gradient: #e0e7ff, #dbeafe | Image: https://...';
        if (bgUpload) bgUpload.style.display = 'block';
    }
}

function testAi() {
    const btn = document.getElementById('testAiBtn');
    const resultDiv = document.getElementById('aiTestResult');
    btn.disabled = true;
    btn.textContent = '...';
    resultDiv.innerHTML = '<span style="color: var(--gray-500);">Connecting...</span>';
    const formData = new FormData();
    formData.append('action', 'test_ai');
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.indexOf('application/json') !== -1) {
            return response.json();
        } else {
            return response.text().then(text => {
                throw new Error('Non-JSON response (HTTP ' + response.status + ')');
            });
        }
    })
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<span style="color: var(--primary); background: var(--success-light); padding: 12px 16px; border-radius: 10px; display: block; font-size: 0.9rem;">✅ OK<br><strong>AI:</strong> ' + data.content + '</span>';
        } else {
            resultDiv.innerHTML = '<span style="color: var(--danger); background: var(--danger-light); padding: 12px 16px; border-radius: 10px; display: block; font-size: 0.9rem;">❌ ' + data.error + '</span>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<span style="color: var(--danger); background: var(--danger-light); padding: 12px 16px; border-radius: 10px; display: block; font-size: 0.9rem;">❌ ' + error.message + '</span>';
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = '<?php echo t('admin_ai_test'); ?>';
    });
}

function confirmRestore() {
    if (confirm('<?php echo t('msg_restore_confirm'); ?>')) {
        document.getElementById('restoreForm').submit();
    }
}

function toggleAutoTheme() {
    const mode = document.getElementById('theme_mode').value;
    document.getElementById('auto_theme_range').style.display = mode === 'auto' ? 'block' : 'none';
}

function syncColor(name) {
    const textInput = document.getElementById(name + '_text');
    const colorInput = document.getElementById(name);
    if (textInput.value.match(/^#[0-9a-fA-F]{6}$/)) {
        colorInput.value = textInput.value;
    }
}

const presets = {
    dark: {
        background_type: 'color',
        background_image: '#0a0a0a',
        font_color_title: '#ffffff',
        font_color_body: '#eeeeee',
        font_color_secondary: '#888888'
    },
    light: {
        background_type: 'color',
        background_image: '#f5f5f5',
        font_color_title: '#1a1a1a',
        font_color_body: '#333333',
        font_color_secondary: '#777777'
    }
};

function applyPreset(name) {
    const p = presets[name];
    if (!p) return;
    document.getElementById('bg_type').value = p.background_type;
    document.getElementById('bg_image').value = p.background_image;
    toggleBgInput();
    ['font_color_title', 'font_color_body', 'font_color_secondary'].forEach(function(k) {
        document.getElementById(k).value = p[k];
        document.getElementById(k + '_text').value = p[k];
    });
}

// Close modals on backdrop click
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
});
</script>
<div style="text-align: center; padding: 20px; color: var(--gray-400); font-size: 0.82rem;">
    Powered by <a href="https://blog.sttr.top" target="_blank" style="color: var(--primary); text-decoration: none; font-weight: 500;">standtrain</a> &middot; <a href="https://github.com/standtrain/e-commerce-traffic-generation" target="_blank" title="GitHub" style="color: var(--gray-400); text-decoration: none;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-github" viewBox="0 0 16 16">
            <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.012 8.012 0 0 0 16 8c0-4.42-3.58-8-8-8z"/>
        </svg>
    </a>
</div>
</body>
</html>
