<?php
if (!file_exists('config.php')) {
    if (file_exists('install.php')) {
        header('Location: install.php');
        exit;
    }
    die('系统未安装，请确保install.php文件存在。');
}

require_once 'config.php';
require_once 'lang.php';

if (!defined('INSTALLED') || INSTALLED !== true) {
    if (file_exists('install.php')) {
        header('Location: install.php');
        exit;
    }
    die('系统未安装，请确保install.php文件存在。');
}

$activeLinks = getActiveLinks();
$siteName = getSiteName();
$siteDescription = getSiteDescription();
$siteIcon = getSiteIcon();
$backgroundType = getBackgroundType();
$backgroundImage = getBackgroundImage();
$customCss = getCustomCss();
$footerCode = getFooterCode();
$aiEnabled = isAiEnabled();

$bgStyle = '';
if ($backgroundType === 'color') {
    $bgStyle = 'background: ' . ($backgroundImage ?: '#0a0a0a') . ';';
} elseif ($backgroundType === 'gradient') {
    $bgStyle = 'background: linear-gradient(135deg, ' . ($backgroundImage ?: '#0a0a0a, #1a1a1e') . ');';
} elseif ($backgroundType === 'image' && $backgroundImage) {
    $bgStyle = 'background: url("' . htmlspecialchars($backgroundImage) . '") no-repeat center center fixed; background-size: cover;';
}

$platformColors = [
    '淘宝' => '#FF5000',
    '天猫' => '#FF6A00',
    '京东' => '#E1251B',
    '亚马逊' => '#FF9900',
    '闲鱼' => '#FFB800',
    '拼多多' => '#E60023',
    '抖音' => '#000000',
    '快手' => '#FF0000',
    '唯品会' => '#E00056',
    '小红书' => '#FF2442',
    '苏宁' => '#4CAF50',
    '国美' => '#2196F3',
    '其他' => '#6366f1'
];

$platformIcons = [
    '淘宝' => '🛒',
    '天猫' => '👑',
    '京东' => '📦',
    '亚马逊' => '📚',
    '闲鱼' => '🐟',
    '拼多多' => '🎯',
    '抖音' => '🎬',
    '快手' => '📹',
    '唯品会' => '👗',
    '小红书' => '📕',
    '苏宁' => '🏠',
    '国美' => '🔌',
    '其他' => '🔗'
];

$defaultDescriptions = [
    '淘宝' => '淘宝旗舰店，品质保证，售后无忧',
    '天猫' => '天猫正品好货，限时优惠等你来',
    '京东' => '京东自营，正品保障，当日送达',
    '亚马逊' => '海外好货，进口商品，正品直销',
    '闲鱼' => '二手闲置，环保节约，好物低价',
    '拼多多' => '拼团更优惠，三人成团更低价格',
    '抖音' => '网红爆款，直播带货，限时特价',
    '快手' => '老铁推荐，实惠好物，直播精选',
    '唯品会' => '品牌特卖，限时折扣，错过不再',
    '小红书' => '种草推荐，达人分享，潮流好物',
    '苏宁' => '家电数码，正品低价，服务上门',
    '国美' => '连锁门店，品质家电，放心购买',
    '其他' => '精选好物，优惠多多，点击进入'
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?></title>
    <?php if ($siteIcon): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($siteIcon); ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@600;700;900&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            <?php echo $bgStyle; ?>
            background-attachment: fixed;
        }
        <?php echo $customCss; ?>

        /* ===== Hero Section ===== */
        .hero {
            padding: 80px 24px 48px;
            position: relative;
            max-width: 720px;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -120px;
            left: -60px;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(0, 255, 65, 0.06) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-title {
            font-family: 'Noto Serif SC', 'PingFang SC', serif;
            font-size: 3.2rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
            line-height: 1.15;
            animation: heroIn 0.7s cubic-bezier(0.22, 1, 0.36, 1);
            position: relative;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .hero-title::after {
            content: '';
            display: block;
            width: 48px;
            height: 3px;
            background: var(--primary);
            border-radius: 2px;
            margin-top: 20px;
            animation: lineGrow 0.8s cubic-bezier(0.22, 1, 0.36, 1) 0.3s both;
        }

        @keyframes lineGrow {
            from { width: 0; }
            to { width: 48px; }
        }

        .hero-desc {
            font-size: 1.05rem;
            color: var(--gray-500);
            max-width: 420px;
            margin-top: 20px;
            font-weight: 300;
            line-height: 1.7;
            animation: heroIn 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.15s both;
        }

        @keyframes heroIn {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== Search Bar ===== */
        .search-bar {
            background: rgba(10, 10, 10, 0.85);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(0, 255, 65, 0.12);
            border-radius: var(--radius-xl);
            padding: 20px 24px;
            margin: 0 auto 28px;
            max-width: 640px;
            box-shadow: var(--shadow-lg), 0 0 24px rgba(0, 255, 65, 0.05);
            animation: heroIn 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.25s both;
        }

        .search-input-wrap {
            position: relative;
        }

        .search-input-wrap::before {
            content: '🔍';
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1rem;
            pointer-events: none;
            filter: grayscale(0.5);
        }

        .search-bar input {
            width: 100%;
            padding: 12px 14px 12px 44px;
            border: 1.5px solid rgba(0, 255, 65, 0.15);
            border-radius: var(--radius);
            font-size: 0.95rem;
            transition: all var(--transition);
            outline: none;
            background: rgba(0, 255, 65, 0.06);
            color: var(--gray-800);
        }

        .search-bar input:focus {
            border-color: var(--primary);
            background: rgba(0, 255, 65, 0.1);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .search-bar input::placeholder {
            color: var(--gray-400);
        }

        .platform-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 14px;
            justify-content: center;
        }

        .filter-tag {
            padding: 6px 16px;
            border: 1px solid rgba(0, 255, 65, 0.12);
            border-radius: 50px;
            background: rgba(0, 255, 65, 0.06);
            color: var(--gray-600);
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition);
        }

        .filter-tag:hover {
            background: rgba(0, 255, 65, 0.12);
            color: var(--primary);
            transform: translateY(-1px);
            border-color: rgba(0, 255, 65, 0.25);
        }

        .filter-tag.active {
            background: var(--tag-color, var(--primary));
            color: #0a0a0a;
            box-shadow: 0 3px 12px rgba(0, 255, 65, 0.2);
            transform: translateY(-1px);
            font-weight: 600;
        }

        /* ===== Link Card Enhanced ===== */
        .link-card {
            animation: cardIn 0.45s ease both;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(20px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .link-card-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .link-card-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 16px;
            border: 2px solid rgba(0, 255, 65, 0.15);
            transition: all var(--transition);
            box-shadow: 0 4px 16px rgba(0,0,0,0.4);
        }

        .link-card:hover .link-card-img {
            border-color: var(--primary);
            box-shadow: 0 4px 28px rgba(0, 255, 65, 0.3);
            transform: scale(1.06);
        }

        .link-summary {
            font-size: 0.82rem;
            color: var(--gray-600);
            margin-top: 6px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .link-price {
            font-size: 1.1rem;
            font-weight: 800;
            color: #ff4444;
            margin-top: 8px;
            letter-spacing: -0.01em;
            text-shadow: 0 0 8px rgba(255, 68, 68, 0.3);
        }

        /* ===== No Results ===== */
        .no-results {
            text-align: center;
            padding: 64px 24px;
            color: var(--gray-500);
            animation: heroIn 0.4s ease;
        }

        .no-results-icon {
            font-size: 3.5rem;
            margin-bottom: 16px;
            filter: grayscale(0.3);
            opacity: 0.8;
        }

        .no-results h3 {
            font-size: 1.1rem;
            color: var(--gray-600);
            margin-bottom: 8px;
            font-weight: 500;
        }

        /* ===== Language Switcher ===== */
        .lang-switcher {
            position: fixed;
            top: 14px;
            right: 14px;
            display: inline-flex;
            gap: 2px;
            background: rgba(10, 10, 10, 0.9);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            padding: 4px;
            border-radius: 50px;
            box-shadow: var(--shadow-md);
            z-index: 100;
            border: 1px solid rgba(0, 255, 65, 0.12);
        }

        .lang-switcher a {
            padding: 4px 10px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--gray-600);
            transition: all var(--transition-fast);
            white-space: nowrap;
        }

        .lang-switcher a:hover {
            color: var(--primary);
            background: rgba(0, 255, 65, 0.08);
        }

        .lang-switcher a.active {
            background: var(--primary);
            color: #0a0a0a;
            box-shadow: 0 2px 12px rgba(0, 255, 65, 0.35);
        }

        /* ===== Responsive ===== */
        @media (max-width: 768px) {
            .hero {
                padding: 44px 16px 28px;
            }
            .hero-title {
                font-size: 2rem;
            }
            .hero-desc {
                font-size: 0.95rem;
            }
            .search-bar {
                padding: 18px;
                margin: 0 8px 20px;
                border-radius: var(--radius-lg);
            }
            .platform-filters {
                gap: 5px;
            }
            .filter-tag {
                padding: 5px 12px;
                font-size: 0.78rem;
            }
            .lang-switcher {
                top: 10px;
                right: 10px;
                padding: 3px;
                gap: 1px;
            }
            .lang-switcher a {
                padding: 3px 7px;
                font-size: 0.65rem;
            }
        }

        @media (max-width: 480px) {
            .hero-title {
                font-size: 1.7rem;
            }
            .lang-switcher {
                top: 8px;
                right: 8px;
                padding: 3px;
                gap: 1px;
            }
            .lang-switcher a {
                padding: 3px 5px;
                font-size: 0.6rem;
            }
            .search-bar {
                padding: 14px;
                margin: 0 4px 16px;
            }
            .search-bar input {
                padding: 10px 12px 10px 38px;
                font-size: 0.88rem;
            }
            .search-input-wrap::before {
                font-size: 0.9rem;
                left: 12px;
            }
            .filter-tag {
                padding: 4px 10px;
                font-size: 0.72rem;
            }
            .links-grid {
                gap: 12px;
            }
            .link-card {
                padding: 22px 16px 20px;
            }
            .link-card-img {
                width: 72px;
                height: 72px;
            }
            .link-title {
                font-size: 0.95rem;
            }
            .link-price {
                font-size: 1rem;
            }
            .link-platform {
                padding: 3px 10px;
                font-size: 0.7rem;
            }
        }

        /* ===== Detail Modal (legacy) ===== */
        .detail-modal { display: none; }
        .detail-view { display: none; }
        .list-view.hidden { display: none; }
        <?php echo getThemeCss(); ?>
    </style>
</head>
<body>
    <div class="lang-switcher">
        <a href="?lang=zh" class="<?php echo getCurrentLang() === 'zh' ? 'active' : ''; ?>">中文</a>
        <a href="?lang=en" class="<?php echo getCurrentLang() === 'en' ? 'active' : ''; ?>">EN</a>
        <a href="?lang=ja" class="<?php echo getCurrentLang() === 'ja' ? 'active' : ''; ?>">日本語</a>
        <a href="?lang=ko" class="<?php echo getCurrentLang() === 'ko' ? 'active' : ''; ?>">한국어</a>
    </div>

    <div class="container">
        <div class="hero">
            <h1 class="hero-title"><?php echo htmlspecialchars($siteName); ?></h1>
            <?php if ($siteDescription): ?>
            <p class="hero-desc"><?php echo htmlspecialchars($siteDescription); ?></p>
            <?php endif; ?>
        </div>

        <?php if (empty($activeLinks)): ?>
        <div class="card" style="max-width: 480px; margin: 0 auto;">
            <div class="empty-state">
                <div class="empty-state-icon">📦</div>
                <h3 style="font-size: 1.1rem; margin-bottom: 8px;"><?php echo t('no_links'); ?></h3>
                <p style="font-size: 0.9rem;"><?php echo t('no_links_desc'); ?></p>
            </div>
        </div>
        <?php else: ?>

        <div class="search-bar" id="searchBar">
            <div class="search-input-wrap">
                <input type="text" id="searchInput" placeholder="<?php echo t('search_placeholder'); ?>" oninput="filterLinks()">
            </div>
            <div class="platform-filters" id="platformFilters">
                <button class="filter-tag active" data-platform="" onclick="setPlatformFilter(this, '')"><?php echo t('all'); ?></button>
                <?php
                $mainPlatforms = ['淘宝', '天猫', '京东', '闲鱼', '亚马逊', '拼多多', '抖音', '唯品会'];
                $usedPlatforms = [];
                $hasOther = false;
                foreach ($activeLinks as $link) {
                    $p = $link['platform'];
                    if (in_array($p, $mainPlatforms)) {
                        if (!in_array($p, $usedPlatforms)) {
                            $usedPlatforms[] = $p;
                            $pColor = $platformColors[$p] ?? '#6366f1';
                            echo '<button class="filter-tag" data-platform="' . htmlspecialchars($p) . '" onclick="setPlatformFilter(this, \'' . htmlspecialchars($p) . '\')" style="--tag-color: ' . $pColor . '">' . htmlspecialchars($p) . '</button>';
                        }
                    } else {
                        $hasOther = true;
                    }
                }
                if ($hasOther) {
                    echo '<button class="filter-tag" data-platform="__other__" onclick="setPlatformFilter(this, \'__other__\')" style="--tag-color: #8b5cf6">' . t('other') . '</button>';
                }
                ?>
            </div>
        </div>

        <div class="list-view" id="listView">
            <div class="links-grid" id="linksGrid">
                <?php
                $cardIndex = 0;
                foreach ($activeLinks as $link):
                    $color = $platformColors[$link['platform']] ?? '#6366f1';
                    $icon = $platformIcons[$link['platform']] ?? '🔗';
                    $images = json_decode($link['images'] ?? '', true) ?: [];
                    $firstImg = $images[0] ?? '';
                    $displayImage = is_array($firstImg) ? ($firstImg['thumb'] ?? $firstImg['full'] ?? '') : $firstImg;
                    $delay = min($cardIndex * 0.06, 0.6);
                ?>
                <a href="detail.php?id=<?php echo urlencode($link['id']); ?>" class="link-card" style="--card-color: <?php echo $color; ?>; animation-delay: <?php echo $delay; ?>s;" data-platform="<?php echo htmlspecialchars($link['platform']); ?>" data-keywords="<?php echo htmlspecialchars(strtolower($link['title'] . ' ' . $link['platform'] . ' ' . ($link['description'] ?? '') . ' ' . ($link['ai_summary'] ?? '') . ' ' . ($link['ai_tags'] ?? ''))); ?>">
                    <div class="link-card-content">
                        <?php if ($displayImage): ?>
                            <img src="<?php echo htmlspecialchars($displayImage); ?>" alt="<?php echo htmlspecialchars($link['title']); ?>" loading="lazy" class="link-card-img">
                        <?php else: ?>
                            <span class="link-icon"><?php echo $icon; ?></span>
                        <?php endif; ?>
                        <div class="link-title"><?php echo htmlspecialchars($link['title']); ?></div>
                        <div class="link-summary">
                            <?php
                            $displayText = '';
                            if (!empty($link['display_text'])) {
                                $displayText = $link['display_text'];
                            } elseif ($aiEnabled && !empty($link['ai_summary'])) {
                                $displayText = $link['ai_summary'];
                            } elseif (!empty($link['description'])) {
                                $displayText = mb_substr($link['description'], 0, 50);
                                if (mb_strlen($link['description']) > 50) {
                                    $displayText .= '...';
                                }
                            }
                            echo htmlspecialchars($displayText);
                            ?>
                        </div>
                        <?php if (!empty($link['price'])): ?>
                        <div class="link-price"><?php echo t('price_label') ?>：<?php echo htmlspecialchars($link['price']); ?></div>
                        <?php endif; ?>
                        <span class="link-platform"><?php echo htmlspecialchars($link['platform']); ?></span>
                    </div>
                </a>
                <?php
                    $cardIndex++;
                endforeach;
                ?>
            </div>
        </div>

        <?php endif; ?>

        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?> &middot; <a href="contact.php"><?php echo t('footer_contact'); ?></a> &middot; <?php echo t('footer_powered'); ?> <a href="https://blog.sttr.top" target="_blank">standtrain</a> &middot; <a href="https://github.com/standtrain/e-commerce-traffic-generation" target="_blank" title="GitHub" style="color: var(--gray-400); text-decoration: none;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-github" viewBox="0 0 16 16">
                    <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.012 8.012 0 0 0 16 8c0-4.42-3.58-8-8-8z"/>
                </svg>
            </a></p>
        </div>
    </div>

    <script>
        function recordClick(linkId) {
            fetch('record_click.php?id=' + encodeURIComponent(linkId))
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        console.warn('Click recording failed:', data.error);
                    }
                })
                .catch(error => {
                    console.warn('Click recording error:', error);
                });
        }

        let currentPlatform = '';

        function setPlatformFilter(btn, platform) {
            document.querySelectorAll('.filter-tag').forEach(t => t.classList.remove('active'));
            btn.classList.add('active');
            currentPlatform = platform;
            filterLinks();
        }

        const mainPlatforms = ['淘宝', '天猫', '京东', '闲鱼', '亚马逊', '拼多多', '抖音', '唯品会'];

        function filterLinks() {
            const keyword = document.getElementById('searchInput').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.link-card');
            const grid = document.getElementById('linksGrid');
            let visibleCount = 0;
            let visibleIndex = 0;

            cards.forEach(card => {
                let matchPlatform = true;
                if (currentPlatform === '__other__') {
                    matchPlatform = !mainPlatforms.includes(card.dataset.platform);
                } else if (currentPlatform) {
                    matchPlatform = card.dataset.platform === currentPlatform;
                }
                const matchKeyword = !keyword || card.dataset.keywords.includes(keyword);
                if (matchPlatform && matchKeyword) {
                    card.style.display = '';
                    card.style.animationDelay = (visibleIndex * 0.05) + 's';
                    card.style.animation = 'none';
                    card.offsetHeight;
                    card.style.animation = '';
                    visibleCount++;
                    visibleIndex++;
                } else {
                    card.style.display = 'none';
                }
            });

            let noResults = document.getElementById('noResults');
            if (visibleCount === 0) {
                if (!noResults) {
                    noResults = document.createElement('div');
                    noResults.id = 'noResults';
                    noResults.className = 'no-results';
                    noResults.innerHTML = '<div class="no-results-icon">🔍</div><h3><?php echo t('no_results'); ?></h3><p><?php echo t('no_results_desc'); ?></p>';
                    grid.parentNode.appendChild(noResults);
                }
            } else if (noResults) {
                noResults.remove();
            }
        }
    </script>

    <?php echo $footerCode; ?>
</body>
</html>
