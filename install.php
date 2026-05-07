<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 检查是否已安装，防止重复安装
if (file_exists('config.php')) {
    require_once 'config.php';
    if (defined('INSTALLED') && INSTALLED === true) {
        header('Location: index.php');
        exit;
    }
}

$step = 1;
$error = '';
$success = '';
$dbHost = $dbName = $dbUser = $dbPass = $siteName = $adminPassword = '';
$defaultLang = 'zh';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'check_db') {
        $step = 1;
        $dbHost = trim($_POST['db_host'] ?? '');
        $dbName = trim($_POST['db_name'] ?? '');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = $_POST['db_pass'] ?? '';
        $siteName = trim($_POST['site_name'] ?? '');
        $adminPassword = $_POST['admin_password'] ?? '';
        $defaultLang = in_array($_POST['default_lang'] ?? '', ['zh', 'en', 'ja', 'ko']) ? $_POST['default_lang'] : 'zh';

        if (empty($dbHost) || empty($dbName) || empty($dbUser) || empty($adminPassword)) {
            $error = '请填写所有必填字段！';
        } elseif (strlen($adminPassword) < 6) {
            $error = '管理员密码至少6位！';
        } else {
            $step = 2;
        }
    } elseif ($_POST['action'] === 'install') {
        $step = 2;
        $dbHost = trim($_POST['db_host'] ?? '');
        $dbName = trim($_POST['db_name'] ?? '');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = $_POST['db_pass'] ?? '';
        $siteName = trim($_POST['site_name'] ?? '');
        $adminPassword = $_POST['admin_password'] ?? '';
        $defaultLang = in_array($_POST['default_lang'] ?? '', ['zh', 'en', 'ja', 'ko']) ? $_POST['default_lang'] : 'zh';

        try {
            $pdo = new PDO(
                'mysql:host=' . $dbHost . ';charset=utf8mb4',
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `links` (
    `id` VARCHAR(50) PRIMARY KEY COMMENT '链接ID',
    `platform` VARCHAR(20) NOT NULL COMMENT '平台名称',
    `title` VARCHAR(100) NOT NULL COMMENT '链接标题',
    `url` VARCHAR(500) NOT NULL COMMENT '跳转URL',
    `images` TEXT COMMENT '多张图片路径(JSON数组)',
    `description` TEXT COMMENT '描述说明',
    `display_text` VARCHAR(200) DEFAULT '' COMMENT '手动设置的显示文本',
    `ai_summary` VARCHAR(200) DEFAULT '' COMMENT 'AI总结',
    `ai_detail` TEXT COMMENT 'AI详细介绍',
    `ai_tags` VARCHAR(255) DEFAULT '' COMMENT 'AI标签',
    `status` ENUM('active', 'disabled') DEFAULT 'active' COMMENT '状态',
    `price` VARCHAR(50) DEFAULT '' COMMENT '商品价格',
    `clicks` INT DEFAULT 0 COMMENT '点击次数',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    INDEX `idx_status` (`status`),
    INDEX `idx_platform` (`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='引流链接表'");
            try { $pdo->exec("ALTER TABLE links ADD COLUMN image VARCHAR(500) DEFAULT '' COMMENT '图片路径' AFTER images"); } catch (PDOException $e) { if ($e->getCode() !== '42S21') throw $e; }
            try { $pdo->exec("ALTER TABLE links ADD COLUMN display_text VARCHAR(200) DEFAULT '' COMMENT '手动设置的显示文本' AFTER description"); } catch (PDOException $e) { if ($e->getCode() !== '42S21') throw $e; }
            try { $pdo->exec("ALTER TABLE links ADD COLUMN price VARCHAR(50) DEFAULT '' COMMENT '商品价格' AFTER description"); } catch (PDOException $e) { if ($e->getCode() !== '42S21') throw $e; }
            $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT '用户ID',
    `username` VARCHAR(50) NOT NULL UNIQUE COMMENT '用户名',
    `password` VARCHAR(255) NOT NULL COMMENT '密码',
    `role` ENUM('admin', 'user') DEFAULT 'user' COMMENT '角色：admin-管理员，user-普通用户',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表'");

            $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
            $stmtCheck->execute();
            if ($stmtCheck->fetch()) {
                $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
                $stmtUpdate->execute([$hashedPassword]);
            } else {
                $stmtInsert = $pdo->prepare("INSERT INTO `users` (`username`, `password`, `role`) VALUES ('admin', ?, 'admin')");
                $stmtInsert->execute([$hashedPassword]);
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
    `key` VARCHAR(100) PRIMARY KEY COMMENT '设置键',
    `value` TEXT COMMENT '设置值',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统设置表'");

            $pdo->exec("INSERT INTO `settings` (`key`, `value`) VALUES ('site_name', ''), ('site_description', '精选优质商品，优惠多多'), ('site_icon', ''), ('background_image', ''), ('background_type', 'color'), ('footer_code', ''), ('custom_css', ''), ('ai_enabled', '0'), ('ai_api_url', ''), ('ai_api_key', ''), ('ai_model', 'gpt-3.5-turbo'), ('default_language', 'zh'), ('captcha_enabled', '1')");
            $stmt = $pdo->prepare("UPDATE `settings` SET `value` = ? WHERE `key` = 'site_name'");
            $stmt->execute([$siteName]);
            $stmt = $pdo->prepare("UPDATE `settings` SET `value` = ? WHERE `key` = 'default_language'");
            $stmt->execute([$defaultLang]);

            $pdo->exec("INSERT INTO `links` (`id`, `platform`, `title`, `url`, `description`, `ai_summary`, `ai_detail`, `ai_tags`, `status`, `clicks`, `created_at`) VALUES ('sample_001', '淘宝', '淘宝旗舰店', 'https://www.taobao.com', '淘宝官方旗舰店，全场优惠多多', '', '', '', 'active', 0, NOW()), ('sample_002', '京东', '京东自营店', 'https://www.jd.com', '京东正品保障，当日达服务', '', '', '', 'active', 0, NOW()), ('sample_003', '闲鱼', '闲鱼二手好物', 'https://www.goofish.com', '二手闲置物品，环保又实惠', '', '', '', 'active', 0, NOW()), ('sample_004', '亚马逊', '亚马逊海外购', 'https://www.amazon.cn', '海外正品好货，进口商品优惠', '', '', '', 'active', 0, NOW()), ('sample_005', '拼多多', '拼多多团购', 'https://www.pinduoduo.com', '拼着买更便宜，团购价更低', '', '', '', 'active', 0, NOW()), ('sample_006', '抖音', '抖音小店', 'https://www.douyin.com', '直播带货，网红好物推荐', '', '', '', 'active', 0, NOW())");

            $configContent = <<<'CONFIG'
<?php
session_start();

define('DB_HOST', '__DB_HOST__');
define('DB_NAME', '__DB_NAME__');
define('DB_USER', '__DB_USER__');
define('DB_PASS', '__DB_PASS__');
define('SITE_NAME', '__SITE_NAME__');
define('INSTALLED', true);

$pdo = null;

function getDBConnection() {
    global $pdo;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die('数据库连接失败: ' . $e->getMessage());
        }
    }
    return $pdo;
}

function isAdmin() {
    return isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'admin';
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: admin.php?action=login');
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: admin.php?action=login');
        exit;
    }
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function getLinks() {
    $pdo = getDBConnection();
    $stmt = $pdo->query('SELECT * FROM links ORDER BY created_at DESC');
    return $stmt->fetchAll();
}

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

function getDistinctPlatforms() {
    $pdo = getDBConnection();
    $stmt = $pdo->query('SELECT DISTINCT platform FROM links ORDER BY platform');
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getActiveLinks() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM links WHERE status = 'active' ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

function getLinkById($id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT * FROM links WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function addLink($platform, $title, $url, $description = '', $images = '', $displayText = '', $price = '') {
    $pdo = getDBConnection();
    $id = 'link_' . time() . '_' . mt_rand(1000, 9999);
    $imagesJson = is_array($images) ? json_encode($images) : $images;
    $stmt = $pdo->prepare('INSERT INTO links (id, platform, title, url, images, description, display_text, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$id, $platform, $title, $url, $imagesJson, $description, $displayText, $price]);
    return $id;
}

function updateLinkAiContent($id, $summary, $detail, $tags) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('UPDATE links SET ai_summary = ?, ai_detail = ?, ai_tags = ? WHERE id = ?');
    $stmt->execute([$summary, $detail, $tags, $id]);
}

function updateLink($id, $platform, $title, $url, $description, $images = '', $displayText = '', $price = '') {
    $pdo = getDBConnection();
    $imagesJson = is_array($images) ? json_encode($images) : $images;
    $stmt = $pdo->prepare('UPDATE links SET platform = ?, title = ?, url = ?, images = ?, description = ?, display_text = ?, price = ? WHERE id = ?');
    $stmt->execute([$platform, $title, $url, $imagesJson, $description, $displayText, $price, $id]);
}

function uploadImage($file, $uploadDir = 'uploads/') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return '';
    }
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024;
    if (!in_array($file['type'], $allowedTypes)) {
        return '';
    }
    if ($file['size'] > $maxSize) {
        return '';
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = 'img_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $targetPath = $uploadDir . $newName;
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $targetPath;
    }
    return '';
}

function deleteImage($imagePath) {
    if ($imagePath && file_exists($imagePath)) {
        unlink($imagePath);
    }
}

function compressImage($source, $dest, $maxWidth = 1920, $maxHeight = 1920, $quality = 80) {
    $info = getimagesize($source);
    if (!$info) return false;
    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg': $src = imagecreatefromjpeg($source); break;
        case 'image/png': $src = imagecreatefrompng($source); break;
        case 'image/gif': $src = imagecreatefromgif($source); break;
        case 'image/webp': $src = imagecreatefromwebp($source); break;
        default: return false;
    }
    if (!$src) return false;
    $w = imagesx($src);
    $h = imagesy($src);
    $ratio = min($maxWidth / $w, $maxHeight / $h, 1);
    $newW = (int)($w * $ratio);
    $newH = (int)($h * $ratio);
    $dst = imagecreatetruecolor($newW, $newH);
    if ($mime === 'image/png' || $mime === 'image/gif' || $mime === 'image/webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
    $ext = strtolower(pathinfo($dest, PATHINFO_EXTENSION));
    $saved = false;
    if ($ext === 'webp' && function_exists('imagewebp')) {
        $saved = imagewebp($dst, $dest, $quality);
    } elseif ($ext === 'png') {
        $pngQuality = (int)(($quality / 100) * 9);
        $saved = imagepng($dst, $dest, $pngQuality);
    } elseif ($ext === 'gif') {
        $saved = imagegif($dst, $dest);
    } else {
        $saved = imagejpeg($dst, $dest, $quality);
    }
    imagedestroy($src);
    imagedestroy($dst);
    return $saved;
}

function createThumbnail($source, $dest, $thumbWidth = 400, $thumbHeight = 400, $quality = 70) {
    $info = getimagesize($source);
    if (!$info) return false;
    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg': $src = imagecreatefromjpeg($source); break;
        case 'image/png': $src = imagecreatefrompng($source); break;
        case 'image/gif': $src = imagecreatefromgif($source); break;
        case 'image/webp': $src = imagecreatefromwebp($source); break;
        default: return false;
    }
    if (!$src) return false;
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min($thumbWidth / $w, $thumbHeight / $h);
    $newW = max(1, (int)($w * $scale));
    $newH = max(1, (int)($h * $scale));
    $dst = imagecreatetruecolor($newW, $newH);
    if ($mime === 'image/png' || $mime === 'image/gif' || $mime === 'image/webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
    $saved = imagejpeg($dst, $dest, $quality);
    imagedestroy($src);
    imagedestroy($dst);
    return $saved;
}

function uploadImages($files, $uploadDir = 'uploads/') {
    $uploadedPaths = [];
    if (!isset($files) || !is_array($files)) {
        return '';
    }
    $fileArray = [];
    if (isset($files['name'])) {
        $count = min(count($files['name']), 9); // 最多9张
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $fileArray[] = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
            }
        }
    }
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $maxSize = 10 * 1024 * 1024; // 允许上传最大10MB（压缩后会变小）
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $thumbDir = $uploadDir . 'thumb/';
    if (!is_dir($thumbDir)) {
        mkdir($thumbDir, 0755, true);
    }
    $uploadDir = rtrim($uploadDir, '/') . '/';
    $useGd = function_exists('imagecreatetruecolor');
    foreach ($fileArray as $file) {
        if ($file['size'] > $maxSize) {
            continue;
        }
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $realType = $finfo->file($file['tmp_name']);
        } else {
            $realType = $file['type'];
        }
        if (!in_array($realType, $allowedTypes)) {
            continue;
        }
        $originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($originalExt, $allowedExtensions)) {
            continue;
        }
        $baseName = 'img_' . time() . '_' . mt_rand(1000, 9999);
        $newName = $baseName . '.' . $originalExt;
        $targetPath = $uploadDir . $newName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            continue;
        }
        // 压缩原图（如果GD可用）
        if ($useGd) {
            compressImage($targetPath, $targetPath, 1920, 1920, 80);
            // 生成缩略图
            $thumbPath = $thumbDir . $baseName . '.jpg';
            createThumbnail($targetPath, $thumbPath, 400, 400, 70);
            if (file_exists($thumbPath)) {
                $uploadedPaths[] = ['full' => $targetPath, 'thumb' => $thumbPath];
            } else {
                $uploadedPaths[] = ['full' => $targetPath, 'thumb' => $targetPath];
            }
        } else {
            $uploadedPaths[] = ['full' => $targetPath, 'thumb' => $targetPath];
        }
    }
    return empty($uploadedPaths) ? '' : json_encode($uploadedPaths);
}

function uploadSingleImage($file, $uploadDir = 'uploads/') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return '';
    }
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/x-icon', 'image/svg+xml'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico', 'svg'];
    $maxSize = 2 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return '';
    }
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realType = $finfo->file($file['tmp_name']);
    } else {
        $realType = $file['type'];
    }
    if (!in_array($realType, $allowedTypes)) {
        return '';
    }
    $originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($originalExt, $allowedExtensions)) {
        return '';
    }
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $uploadDir = rtrim($uploadDir, '/') . '/';
    $newName = 'img_' . time() . '_' . mt_rand(1000, 9999) . '.' . $originalExt;
    $targetPath = $uploadDir . $newName;
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $targetPath;
    }
    return '';
}

function updateLinkStatus($id, $status) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('UPDATE links SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
}

function deleteLink($id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('DELETE FROM links WHERE id = ?');
    $stmt->execute([$id]);
}

function recordClick($id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('UPDATE links SET clicks = clicks + 1 WHERE id = ?');
    $stmt->execute([$id]);
}

function getUserByUsername($username) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    return $stmt->fetch();
}

function getUsers() {
    $pdo = getDBConnection();
    $stmt = $pdo->query('SELECT id, username, role, created_at FROM users ORDER BY created_at DESC');
    return $stmt->fetchAll();
}

function getUserById($id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function addUser($username, $password, $role = 'user') {
    $pdo = getDBConnection();
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
    $stmt->execute([$username, $hashedPassword, $role]);
}

function updateUser($id, $username, $role) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('UPDATE users SET username = ?, role = ? WHERE id = ?');
    $stmt->execute([$username, $role, $id]);
}

function updateUserPassword($id, $password) {
    $pdo = getDBConnection();
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->execute([$hashedPassword, $id]);
}

function deleteUser($id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
}

function getSetting($key, $default = '') {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE `key` = ?');
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['value'] : $default;
}

function setSetting($key, $value) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
    $stmt->execute([$key, $value]);
}

function getAllSettings() {
    $pdo = getDBConnection();
    $stmt = $pdo->query('SELECT * FROM settings');
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['key']] = $row['value'];
    }
    return $settings;
}

function getSiteName() {
    return getSetting('site_name', '电商引流平台');
}

function getSiteDescription() {
    return getSetting('site_description', '精选优质商品，优惠多多');
}

function getSiteIcon() {
    return getSetting('site_icon', '');
}

function getBackgroundImage() {
    return getSetting('background_image', '');
}

function getBackgroundType() {
    return getSetting('background_type', 'color');
}

function getFooterCode() {
    return getSetting('footer_code', '');
}

function getCustomCss() {
    return getSetting('custom_css', '');
}

function isAiEnabled() {
    return getSetting('ai_enabled', '0') === '1';
}

function getAiApiUrl() {
    return getSetting('ai_api_url', '');
}

function getAiApiKey() {
    return getSetting('ai_api_key', '');
}

function getAiModel() {
    return getSetting('ai_model', 'gpt-3.5-turbo');
}

function isCaptchaEnabled() {
    return getSetting('captcha_enabled', '1') === '1';
}

function generateAiSummary($title, $description, $platform) {
    if (!isAiEnabled()) {
        return null;
    }
    $apiUrl = getAiApiUrl();
    $apiKey = getAiApiKey();
    $model = getAiModel();
    if (empty($apiUrl) || empty($apiKey)) {
        return null;
    }
    $prompt = "请为以下商品生成简洁的总结和详细介绍：\n\n商品标题：{title}\n商品描述：{description}\n平台：{platform}\n\n请用JSON格式返回，包含：\n- summary：50字以内的简短总结\n- detail：200字以内的详细介绍\n- tags：3-5个相关标签（用逗号分隔）\n\n只返回JSON，不要其他内容";
    $data = [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.7,
        'max_tokens' => 500
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) {
        return null;
    }
    if ($httpCode !== 200 || empty($response)) {
        return null;
    }
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        $content = trim($result['choices'][0]['message']['content']);
        $content = preg_replace('/^```json\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        return json_decode($content, true);
    }
    return null;
}

function testAiConnection() {
    if (!isAiEnabled()) {
        return ['success' => false, 'error' => 'AI功能未启用'];
    }
    $apiUrl = getAiApiUrl();
    $apiKey = getAiApiKey();
    $model = getAiModel();
    if (empty($apiUrl) || empty($apiKey)) {
        return ['success' => false, 'error' => 'API配置不完整'];
    }
    $data = [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => '请用一句话介绍自己']
        ],
        'temperature' => 0.7,
        'max_tokens' => 200
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) {
        return ['success' => false, 'error' => '请求失败：' . $error];
    }
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => 'HTTP错误：' . $httpCode, 'response' => $response];
    }
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        return ['success' => true, 'content' => trim($result['choices'][0]['message']['content'])];
    }
    return ['success' => false, 'error' => '响应格式错误', 'response' => $result];
}
CONFIG;
            $configContent = str_replace(
                ['__DB_HOST__', '__DB_NAME__', '__DB_USER__', '__DB_PASS__', '__SITE_NAME__'],
                [addslashes($dbHost), addslashes($dbName), addslashes($dbUser), addslashes($dbPass), addslashes($siteName)],
                $configContent
            );
            file_put_contents(__DIR__ . '/config.php', $configContent);

            $success = '安装成功！';
        } catch (PDOException $e) {
            $error = '数据库连接失败: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装向导 - 电商引流平台</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: #0a0a0a;
            min-height: 100vh;
            padding: 40px 20px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .install-container {
            background: #111;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl), 0 0 40px rgba(0, 255, 65, 0.06);
            max-width: 540px;
            width: 100%;
            margin: 0 auto;
            animation: fadeIn 0.4s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0, 255, 65, 0.12);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .install-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #0a0a0a;
            padding: 40px 32px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .install-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 260px;
            height: 260px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            pointer-events: none;
        }

        .install-header h1 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 6px;
            position: relative;
        }

        .install-header p {
            opacity: 0.8;
            font-size: 1rem;
            position: relative;
        }

        .install-body {
            padding: 32px;
        }

        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 28px;
        }

        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--gray-400);
            font-size: 0.95rem;
            transition: all var(--transition);
        }

        .step.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #0a0a0a;
            box-shadow: 0 3px 12px rgba(0, 255, 65, 0.3);
        }

        .step-line {
            width: 72px;
            height: 2px;
            background: var(--gray-100);
            margin: 0 14px;
            border-radius: 2px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.85rem;
        }

        .form-group label span {
            color: var(--danger);
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 0.92rem;
            transition: all var(--transition);
            box-sizing: border-box;
            font-family: inherit;
            background: #0a0a0a;
            color: var(--gray-800);
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow), 0 0 16px rgba(0, 255, 65, 0.1);
        }

        .btn {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #0a0a0a;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
        }

        .btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.12), transparent);
            pointer-events: none;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(0, 255, 65, 0.35);
        }

        .alert {
            padding: 12px 16px;
            border-radius: var(--radius);
            margin-bottom: 18px;
            font-size: 0.88rem;
            font-weight: 500;
        }

        .alert.error {
            background: var(--danger-light);
            color: var(--danger);
            border: 1px solid rgba(248, 113, 113, 0.2);
        }

        .alert.success {
            background: var(--success-light);
            color: var(--primary);
            border: 1px solid rgba(0, 255, 65, 0.2);
        }

        .success-icon {
            text-align: center;
            padding: 20px;
        }

        .success-icon .icon {
            font-size: 3.5rem;
            margin-bottom: 16px;
        }

        .info-box {
            background: var(--gray-50);
            padding: 18px 22px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            border: 1px solid var(--gray-100);
        }

        .info-box h3 {
            margin-bottom: 12px;
            font-size: 0.95rem;
            color: var(--gray-800);
        }

        .info-box ul {
            margin: 0;
            padding-left: 18px;
        }

        .info-box li {
            margin-bottom: 6px;
            color: var(--gray-600);
            font-size: 0.88rem;
            line-height: 1.6;
        }

        .hint {
            font-size: 0.78rem;
            color: var(--gray-400);
            margin-top: 5px;
        }

        .field-error {
            color: var(--danger);
            font-size: 0.8rem;
            margin-top: 5px;
            min-height: 18px;
        }

        .form-group.has-error input,
        .form-group.has-error select {
            border-color: var(--danger);
        }

        .info-card {
            background: rgba(251, 191, 36, 0.08);
            border: 1px solid rgba(251, 191, 36, 0.2);
            border-radius: var(--radius);
            padding: 18px 22px;
            text-align: left;
            margin-bottom: 20px;
        }

        .info-card p {
            margin-bottom: 6px;
            font-size: 0.88rem;
            color: var(--warning);
        }

        .info-card p:last-child {
            margin-bottom: 0;
        }

        .info-card code {
            background: rgba(0, 255, 65, 0.08);
            padding: 2px 7px;
            border-radius: 5px;
            font-size: 0.85rem;
            color: var(--primary);
        }

        @media (max-width: 768px) {
            body {
                padding: 16px 12px;
            }
            .install-container {
                border-radius: var(--radius-lg);
            }
            .install-header {
                padding: 24px 18px;
            }
            .install-header h1 {
                font-size: 1.4rem;
            }
            .install-header p {
                font-size: 0.9rem;
            }
            .install-body {
                padding: 20px 16px;
            }
            .step-indicator {
                margin-bottom: 22px;
            }
            .step {
                width: 36px;
                height: 36px;
                font-size: 0.88rem;
            }
            .step-line {
                width: 56px;
            }
            .form-group input, .form-group select {
                padding: 10px 12px;
                font-size: 0.88rem;
            }
            .btn {
                padding: 12px;
                font-size: 0.95rem;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px 8px;
            }
            .install-header {
                padding: 20px 14px;
            }
            .install-header h1 {
                font-size: 1.2rem;
            }
            .install-body {
                padding: 16px 12px;
            }
            .step-indicator {
                margin-bottom: 18px;
            }
            .step {
                width: 32px;
                height: 32px;
                font-size: 0.82rem;
            }
            .step-line {
                width: 40px;
            }
            .info-box {
                padding: 14px 16px;
            }
            .info-box h3 {
                font-size: 0.88rem;
            }
            .info-box li {
                font-size: 0.82rem;
            }
            .info-card {
                padding: 14px 16px;
            }
            .info-card p {
                font-size: 0.82rem;
            }
            .success-icon .icon {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1>电商引流平台</h1>
            <p>简洁高效的商品链接管理工具</p>
        </div>
        <div class="install-body">
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">1</div>
                <div class="step-line"></div>
                <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">2</div>
            </div>
            <?php if ($error): ?>
                <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
                <div class="success-icon">
                    <div class="icon">✅</div>
                    <h2 style="color: var(--primary); margin-bottom: 10px; font-size: 1.4rem;">安装完成！</h2>
                    <p style="color: var(--gray-500); margin-bottom: 18px; font-size: 0.95rem;">系统已配置完成，请牢记以下信息：</p>
                    <div class="info-card">
                        <p><strong>后台管理地址：</strong><code>admin.php</code></p>
                        <p><strong>管理员账号：</strong><code>admin</code></p>
                        <p><strong>管理员密码：</strong><code><?php echo htmlspecialchars($adminPassword); ?></code></p>
                    </div>
                    <a href="index.php" class="btn" style="text-decoration: none; display: inline-block; width: auto; padding: 14px 48px;">前往首页</a>
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="<?php echo $step === 1 ? 'check_db' : 'install'; ?>">
                    <input type="hidden" name="db_host" value="<?php echo htmlspecialchars($dbHost); ?>">
                    <input type="hidden" name="db_name" value="<?php echo htmlspecialchars($dbName); ?>">
                    <input type="hidden" name="db_user" value="<?php echo htmlspecialchars($dbUser); ?>">
                    <input type="hidden" name="db_pass" value="<?php echo htmlspecialchars($dbPass); ?>">
                    <input type="hidden" name="site_name" value="<?php echo htmlspecialchars($siteName); ?>">
                    <input type="hidden" name="admin_password" value="<?php echo htmlspecialchars($adminPassword); ?>">
                    <input type="hidden" name="default_lang" value="<?php echo htmlspecialchars($defaultLang); ?>">
                    <?php if ($step === 1): ?>
                        <div class="info-box">
                            <h3>安装说明</h3>
                            <ul>
                                <li>MySQL数据库信息（需提前创建数据库或使用root账号自动创建）</li>
                                <li>设置网站名称和管理员密码</li>
                                <li>安装完成后会自动跳转到首页</li>
                            </ul>
                        </div>
                        <div class="form-group">
                            <label>数据库主机：<span>*</span></label>
                            <input type="text" name="db_host" id="db_host" value="localhost" required>
                            <div class="field-error" id="err_db_host"></div>
                        </div>
                        <div class="form-group">
                            <label>数据库名称：<span>*</span></label>
                            <input type="text" name="db_name" id="db_name" value="shop_db" required placeholder="请输入数据库名称">
                            <div class="field-error" id="err_db_name"></div>
                        </div>
                        <div class="form-group">
                            <label>数据库用户名 <span>*</span></label>
                            <input type="text" name="db_user" id="db_user" value="root" required placeholder="请输入数据库用户名">
                            <div class="field-error" id="err_db_user"></div>
                        </div>
                        <div class="form-group">
                            <label>数据库密码：</label>
                            <input type="password" name="db_pass" id="db_pass" placeholder="请输入数据库密码">
                            <div class="field-error" id="err_db_pass"></div>
                        </div>
                        <div class="form-group">
                            <label>网站名称</label>
                            <input type="text" name="site_name" id="site_name" value="电商引流平台" placeholder="显示在页面标题">
                            <div class="field-error" id="err_site_name"></div>
                        </div>
                        <div class="form-group">
                            <label>管理员密码：<span>*</span></label>
                            <input type="password" name="admin_password" id="admin_password" required placeholder="至少6位密码" minlength="6">
                            <div class="hint">用于登录后台管理，建议使用强密码</div>
                            <div class="field-error" id="err_admin_password"></div>
                        </div>
                        <div class="form-group">
                            <label>后台语言</label>
                            <select name="default_lang" id="default_lang">
                                <option value="zh" <?php echo $defaultLang === 'zh' ? 'selected' : ''; ?>>中文</option>
                                <option value="en" <?php echo $defaultLang === 'en' ? 'selected' : ''; ?>>English</option>
                                <option value="ja" <?php echo $defaultLang === 'ja' ? 'selected' : ''; ?>>日本語</option>
                                <option value="ko" <?php echo $defaultLang === 'ko' ? 'selected' : ''; ?>>한국어</option>
                            </select>
                            <div class="hint">设置后台管理界面的默认语言</div>
                        </div>
                        <button type="submit" class="btn" onclick="return validateForm()">下一步</button>
                    <?php else: ?>
                        <div class="info-box">
                            <h3>确认安装信息</h3>
                            <ul>
                                <li><strong>数据库：</strong><?php echo htmlspecialchars($dbHost); ?>/<?php echo htmlspecialchars($dbName); ?></li>
                                <li><strong>用户名：</strong><?php echo htmlspecialchars($dbUser); ?></li>
                                <li><strong>网站名称：</strong><?php echo htmlspecialchars($siteName); ?></li>
                                <li><strong>后台语言：</strong><?php echo ['zh' => '中文', 'en' => 'English', 'ja' => '日本語', 'ko' => '한국어'][$defaultLang] ?? '中文'; ?></li>
                            </ul>
                        </div>
                        <button type="submit" class="btn">确认安装</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function clearErrors() {
            document.querySelectorAll('.field-error').forEach(el => el.textContent = '');
            document.querySelectorAll('.form-group').forEach(el => el.classList.remove('has-error'));
        }

        function showError(fieldId, message) {
            const errEl = document.getElementById('err_' + fieldId);
            if (errEl) {
                errEl.textContent = message;
            }
            const formGroup = document.getElementById(fieldId).closest('.form-group');
            if (formGroup) {
                formGroup.classList.add('has-error');
            }
        }

        function validateForm() {
            clearErrors();
            let isValid = true;

            const dbHost = document.getElementById('db_host').value.trim();
            const dbName = document.getElementById('db_name').value.trim();
            const dbUser = document.getElementById('db_user').value.trim();
            const dbPass = document.getElementById('db_pass').value;
            const siteName = document.getElementById('site_name').value.trim();
            const adminPassword = document.getElementById('admin_password').value;

            if (!dbHost) {
                showError('db_host', '数据库主机不能为空');
                isValid = false;
            } else if (/\s/.test(dbHost)) {
                document.getElementById('db_host').value = dbHost.replace(/\s/g, '');
            }

            if (!dbName) {
                showError('db_name', '数据库名称不能为空');
                isValid = false;
            } else if (/\s/.test(dbName)) {
                document.getElementById('db_name').value = dbName.replace(/\s/g, '');
            }

            if (!dbUser) {
                showError('db_user', '数据库用户名不能为空');
                isValid = false;
            } else if (/\s/.test(dbUser)) {
                document.getElementById('db_user').value = dbUser.replace(/\s/g, '');
            }

            if (dbPass && /\s/.test(dbPass)) {
                document.getElementById('db_pass').value = dbPass.replace(/\s/g, '');
            }

            if (!siteName) {
                showError('site_name', '网站名称不能为空');
                isValid = false;
            } else {
                const trimmed = siteName.replace(/^\s+|\s+$/g, '');
                if (siteName !== trimmed) {
                    document.getElementById('site_name').value = trimmed;
                }
            }

            if (!adminPassword) {
                showError('admin_password', '管理员密码不能为空');
                isValid = false;
            } else if (adminPassword.length < 6) {
                showError('admin_password', '管理员密码至少6位');
                isValid = false;
            }

            return isValid;
        }

        const inputs = document.querySelectorAll('input[type="text"], input[type="password"]');
        inputs.forEach(function(input) {
            input.addEventListener('input', function() {
                this.value = this.value.trim();
                const errEl = document.getElementById('err_' + this.id);
                if (errEl) errEl.textContent = '';
                this.closest('.form-group').classList.remove('has-error');
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
