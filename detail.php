<?php
require_once 'config.php';
require_once 'lang.php';

if (!defined('INSTALLED') || INSTALLED !== true) {
    if (file_exists('install.php')) {
        header('Location: install.php');
        exit;
    }
    die('系统未安装');
}

$linkId = $_GET['id'] ?? '';

if (empty($linkId)) {
    header('Location: index.php');
    exit;
}

$link = getLinkById($linkId);

if (!$link || $link['status'] !== 'active') {
    header('Location: index.php');
    exit;
}

recordClick($linkId);

$siteName = getSiteName();
$siteIcon = getSiteIcon();
$bgType = getBackgroundType();
$bgImage = getBackgroundImage();
$customCss = getCustomCss();
$footerCode = getFooterCode();
$aiEnabled = isAiEnabled();

$platformColors = [
    '淘宝' => '#ff5000',
    '天猫' => '#ff0033',
    '京东' => '#e2231a',
    '闲鱼' => '#ffbf00',
    '亚马逊' => '#ff9900',
    '拼多多' => '#e2231a',
    '抖音' => '#fe2c55',
    '唯品会' => '#ee2465',
    '其他' => '#6366f1'
];

$platformIcons = [
    '淘宝' => '🛒',
    '天猫' => '👑',
    '京东' => '📦',
    '闲鱼' => '🐟',
    '亚马逊' => '🌍',
    '拼多多' => '🔴',
    '抖音' => '📱',
    '唯品会' => '💎',
    '其他' => '🔗'
];

$cardColor = $platformColors[$link['platform']] ?? '#6366f1';
$cardIcon = $platformIcons[$link['platform']] ?? '🔗';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($link['title']); ?> - <?php echo htmlspecialchars($siteName); ?></title>
    <?php if ($siteIcon): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($siteIcon); ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@600;700;900&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            min-height: 100vh;
            <?php
            if ($bgType === 'color') {
                echo 'background: ' . ($bgImage ?: '#0a0a0a') . ';';
            } elseif ($bgType === 'gradient') {
                echo 'background: linear-gradient(135deg, ' . ($bgImage ?: '#0a0a0a, #111') . ');';
            } elseif ($bgType === 'image' && $bgImage) {
                echo 'background: url("' . htmlspecialchars($bgImage) . '") no-repeat center center fixed; background-size: cover;';
            } else {
                echo 'background: #0a0a0a;';
            }
            ?>
            background-attachment: fixed;
        }
        <?php echo $customCss; ?>

        .detail-page {
            max-width: 820px;
            margin: 0 auto;
            padding: 20px 18px 110px;
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .detail-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 20px;
            background: rgba(10, 10, 10, 0.85);
            backdrop-filter: blur(12px);
            border-radius: 50px;
            text-decoration: none;
            color: var(--gray-600);
            font-weight: 600;
            font-size: 0.88rem;
            box-shadow: var(--shadow);
            transition: all var(--transition);
            border: 1px solid rgba(0, 255, 65, 0.08);
        }

        .back-btn:hover {
            transform: translateX(-3px);
            box-shadow: var(--shadow-md), 0 0 16px rgba(0, 255, 65, 0.1);
            color: var(--primary);
            border-color: rgba(0, 255, 65, 0.15);
        }

        .platform-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 16px;
            background: linear-gradient(135deg, <?php echo $cardColor; ?>, <?php echo $cardColor; ?>cc);
            color: white;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 3px 12px rgba(0,0,0,0.3);
        }

        .detail-card {
            background: #111;
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(0, 255, 65, 0.12);
        }

        .detail-hero {
            background: linear-gradient(135deg, <?php echo $cardColor; ?>33, <?php echo $cardColor; ?>18);
            padding: 48px 32px;
            text-align: center;
            color: var(--gray-900);
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid rgba(0, 255, 65, 0.12);
        }

        .detail-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, <?php echo $cardColor; ?>25 0%, transparent 70%);
            pointer-events: none;
        }

        .detail-hero h1 {
            font-family: 'Noto Serif SC', 'PingFang SC', serif;
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 6px;
            position: relative;
            letter-spacing: -0.03em;
            line-height: 1.3;
            color: #fff;
        }

        .detail-hero .hero-subtitle {
            opacity: 0.8;
            font-size: 0.92rem;
            font-weight: 400;
            color: var(--gray-600);
        }

        .detail-hero .hero-price {
            margin-top: 16px;
            font-size: 1.8rem;
            font-weight: 800;
            background: rgba(0, 255, 65, 0.08);
            display: inline-block;
            padding: 6px 24px;
            border-radius: 50px;
            backdrop-filter: blur(4px);
            letter-spacing: -0.02em;
            color: var(--primary);
            border: 1px solid rgba(0, 255, 65, 0.15);
        }

        .detail-content {
            padding: 32px;
        }

        /* ===== Image Gallery ===== */
        .detail-images {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 12px;
            margin-bottom: 28px;
        }

        .detail-images img {
            width: 100%;
            height: 190px;
            object-fit: cover;
            border-radius: var(--radius);
            transition: all var(--transition);
            cursor: pointer;
            border: 1px solid rgba(0, 255, 65, 0.1);
        }

        .detail-images img:hover {
            transform: scale(1.02);
            box-shadow: var(--shadow-lg), 0 0 30px rgba(0, 255, 65, 0.15);
            border-color: var(--primary);
        }

        /* ===== Lightbox ===== */
        .lightbox {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.92);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            cursor: zoom-out;
            padding: 16px;
            -webkit-tap-highlight-color: transparent;
        }

        .lightbox.active {
            display: flex;
        }

        .lightbox img {
            max-width: 100%;
            max-height: 100vh;
            object-fit: contain;
            border-radius: 6px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
            opacity: 0;
            transition: opacity 0.25s ease;
            will-change: opacity;
        }

        .lightbox img.visible {
            opacity: 1;
        }

        .lightbox-close {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 36px;
            height: 36px;
            background: rgba(255,255,255,0.15);
            border: none;
            border-radius: 50%;
            color: white;
            font-size: 1.3rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
            z-index: 2;
        }

        .lightbox-close:hover {
            background: rgba(255,255,255,0.3);
        }

        .lightbox-loading {
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
            position: absolute;
        }

        .detail-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 200px;
            background: rgba(0, 255, 65, 0.06);
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            font-size: 4.5rem;
            filter: grayscale(0.2);
            border: 1px solid rgba(0, 255, 65, 0.1);
        }

        /* ===== AI Summary ===== */
        .ai-summary {
            background: rgba(0, 255, 65, 0.1);
            padding: 24px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0, 255, 65, 0.2);
        }

        .ai-summary::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -10%;
            width: 180px;
            height: 180px;
            background: radial-gradient(circle, rgba(0, 255, 65, 0.1) 0%, transparent 70%);
            pointer-events: none;
        }

        .ai-summary h3 {
            font-size: 0.85rem;
            color: var(--primary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .ai-summary p {
            font-size: 1.02rem;
            line-height: 1.8;
            position: relative;
            color: var(--gray-800);
        }

        /* ===== AI Detail ===== */
        .ai-detail {
            color: var(--gray-700);
            line-height: 1.85;
            margin-bottom: 24px;
            padding: 22px;
            background: rgba(0, 255, 65, 0.06);
            border-radius: var(--radius-lg);
            border: 1px solid rgba(0, 255, 65, 0.1);
        }

        .ai-detail h4 {
            color: var(--gray-800);
            margin-bottom: 12px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* ===== Tags ===== */
        .tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 24px;
        }

        .tag {
            padding: 6px 14px;
            background: rgba(0, 255, 65, 0.06);
            color: var(--gray-600);
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 500;
            border: 1px solid rgba(0, 255, 65, 0.12);
            transition: all var(--transition);
        }

        .tag:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(0, 255, 65, 0.12);
            box-shadow: 0 0 12px rgba(0, 255, 65, 0.15);
        }

        /* ===== Description ===== */
        .description-box {
            color: var(--gray-700);
            line-height: 1.8;
            padding: 22px;
            background: rgba(0, 255, 65, 0.06);
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            border: 1px solid rgba(0, 255, 65, 0.1);
        }

        .description-box strong {
            color: var(--primary);
        }

        /* ===== Buy Button ===== */
        .buy-btn {
            position: fixed;
            bottom: 28px;
            left: 50%;
            transform: translateX(-50%);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 48px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #0a0a0a;
            border-radius: 50px;
            text-decoration: none;
            font-size: 1.05rem;
            font-weight: 700;
            box-shadow: 0 8px 32px rgba(0, 255, 65, 0.4);
            transition: all var(--transition);
            z-index: 100;
            letter-spacing: 0.02em;
            animation: btnPulse 3s ease-in-out infinite;
        }

        @keyframes btnPulse {
            0%, 100% { box-shadow: 0 8px 32px rgba(0, 255, 65, 0.4); }
            50% { box-shadow: 0 8px 56px rgba(0, 255, 65, 0.6); }
        }

        .buy-btn:hover {
            transform: translateX(-50%) scale(1.04);
            box-shadow: 0 12px 48px rgba(0, 255, 65, 0.6);
            animation: none;
        }

        /* ===== Safety Modal ===== */
        .safety-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 10, 12, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 20px;
        }

        .safety-modal.active {
            display: flex;
        }

        .safety-content {
            background: #111;
            border-radius: var(--radius-xl);
            padding: 32px;
            max-width: 420px;
            width: 100%;
            text-align: center;
            box-shadow: var(--shadow-xl), 0 0 60px rgba(0, 255, 65, 0.08);
            animation: modalIn 0.3s ease;
            border: 1px solid rgba(0, 255, 65, 0.15);
        }

        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.96) translateY(8px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .safety-icon {
            font-size: 3rem;
            margin-bottom: 12px;
        }

        .safety-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 16px;
        }

        .platform-warning {
            background: rgba(0, 255, 65, 0.1);
            border: 1px solid rgba(0, 255, 65, 0.25);
            border-radius: var(--radius);
            padding: 12px;
            margin-bottom: 14px;
            font-size: 0.92rem;
            color: var(--primary);
            line-height: 1.6;
        }

        .safety-warning {
            background: rgba(251, 191, 36, 0.06);
            border: 1px solid rgba(251, 191, 36, 0.15);
            border-radius: var(--radius);
            padding: 16px;
            margin-bottom: 20px;
            text-align: left;
            font-size: 0.88rem;
            color: var(--warning);
        }

        .safety-warning h4 {
            margin: 0 0 8px 0;
            color: var(--warning);
            font-size: 0.92rem;
        }

        .safety-warning ul {
            margin: 0;
            padding-left: 18px;
        }

        .safety-warning li {
            margin-bottom: 5px;
            line-height: 1.5;
        }

        .countdown {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 16px;
        }

        .jump-btn {
            display: inline-block;
            padding: 12px 32px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #0a0a0a;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all var(--transition);
            box-shadow: 0 4px 18px rgba(0, 255, 65, 0.35);
        }

        .jump-btn.disabled {
            background: var(--gray-200);
            color: var(--gray-400);
            cursor: not-allowed;
            box-shadow: none;
        }

        .jump-btn:not(.disabled):hover {
            transform: scale(1.02);
            box-shadow: 0 6px 24px rgba(0, 255, 65, 0.5);
        }

        /* ===== Footer ===== */
        .footer {
            text-align: center;
            padding: 20px;
            color: var(--gray-400);
            font-size: 0.82rem;
        }

        .footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .footer a:hover {
            color: var(--primary-light);
        }

        @media (max-width: 768px) {
            .detail-page {
                padding: 16px 14px 100px;
            }
            .detail-header {
                gap: 10px;
                margin-bottom: 20px;
            }
            .back-btn {
                padding: 7px 16px;
                font-size: 0.82rem;
            }
            .platform-badge {
                padding: 5px 12px;
                font-size: 0.8rem;
            }
            .detail-hero {
                padding: 28px 18px;
            }
            .detail-hero h1 {
                font-size: 1.3rem;
            }
            .detail-hero .hero-price {
                font-size: 1.4rem;
                padding: 4px 16px;
            }
            .detail-content {
                padding: 20px 16px;
            }
            .lightbox {
                padding: 8px;
            }
            .lightbox-close {
                top: 8px;
                right: 8px;
            }
            .detail-images {
                grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
                gap: 8px;
            }
            .detail-images img {
                height: 140px;
            }
            .buy-btn {
                padding: 11px 32px;
                font-size: 0.92rem;
                bottom: 18px;
            }
            .safety-content {
                padding: 24px 20px;
            }
            .safety-icon {
                font-size: 2.5rem;
            }
            .safety-title {
                font-size: 1.15rem;
            }
            .countdown {
                font-size: 1.8rem;
            }
            .ai-summary {
                padding: 20px;
            }
            .ai-summary p {
                font-size: 0.95rem;
            }
            .ai-detail {
                padding: 18px;
            }
            .tag {
                padding: 5px 11px;
                font-size: 0.78rem;
            }
            .tags {
                gap: 6px;
            }
            .description-box {
                padding: 18px;
            }
        }

        @media (max-width: 480px) {
            .detail-page {
                padding: 12px 10px 90px;
            }
            .detail-header {
                gap: 8px;
                margin-bottom: 16px;
            }
            .back-btn {
                padding: 6px 14px;
                font-size: 0.78rem;
                gap: 4px;
            }
            .platform-badge {
                padding: 4px 10px;
                font-size: 0.75rem;
            }
            .detail-hero {
                padding: 24px 14px;
            }
            .detail-hero h1 {
                font-size: 1.15rem;
            }
            .detail-hero .hero-subtitle {
                font-size: 0.82rem;
            }
            .detail-hero .hero-price {
                font-size: 1.2rem;
                padding: 3px 14px;
                margin-top: 8px;
            }
            .detail-content {
                padding: 16px 12px;
            }
            .detail-images {
                grid-template-columns: repeat(2, 1fr);
                gap: 6px;
            }
            .detail-images img {
                height: 120px;
            }
            .detail-placeholder {
                height: 150px;
                font-size: 3.5rem;
            }
            .buy-btn {
                padding: 10px 28px;
                font-size: 0.88rem;
                bottom: 14px;
                gap: 6px;
            }
            .safety-content {
                padding: 20px 16px;
            }
            .jump-btn {
                padding: 10px 24px;
                font-size: 0.88rem;
            }
            .lightbox-close {
                top: 10px;
                right: 12px;
                width: 32px;
                height: 32px;
                font-size: 1.2rem;
            }
        }
        <?php echo getThemeCss(); ?>
    </style>
</head>
<body>
    <div class="detail-page">
        <div class="detail-header">
            <a href="index.php" class="back-btn"><?php echo t('back_home'); ?></a>
            <span class="platform-badge"><?php echo $cardIcon; ?> <?php echo htmlspecialchars($link['platform']); ?></span>
        </div>

        <div class="detail-card">
            <div class="detail-hero">
                <h1><?php echo htmlspecialchars($link['title']); ?></h1>
                <div class="hero-subtitle"><?php echo $cardIcon; ?> <?php echo htmlspecialchars($link['platform']); ?> <?php echo t('platform_suffix'); ?></div>
                <?php if (!empty($link['price'])): ?>
                <div class="hero-price"><?php echo t('price_label') ?>：<?php echo htmlspecialchars($link['price']); ?> <?php echo t('currency') ?></div>
                <?php endif; ?>
            </div>

            <div class="detail-content">
                <?php
                $detailImages = json_decode($link['images'] ?? '', true) ?: [];
                $hasImages = !empty($detailImages);
                ?>
                <?php if ($hasImages): ?>
                    <div class="detail-images">
                        <?php foreach ($detailImages as $img):
                            $imgFull = is_array($img) ? ($img['full'] ?? $img) : $img;
                            $imgThumb = is_array($img) ? ($img['thumb'] ?? $imgFull) : $img;
                        ?>
                            <img src="<?php echo htmlspecialchars($imgThumb); ?>" data-full="<?php echo htmlspecialchars($imgFull); ?>" alt="<?php echo htmlspecialchars($link['title']); ?>" loading="lazy" onclick="openLightbox(this.dataset.full)" style="cursor: zoom-in;">
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="detail-placeholder"><?php echo $cardIcon; ?></div>
                <?php endif; ?>

                <?php if ($aiEnabled && !empty($link['ai_summary'])): ?>
                    <div class="ai-summary">
                        <h3>🤖 <?php echo t('ai_summary'); ?></h3>
                        <p><?php echo htmlspecialchars($link['ai_summary']); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($link['ai_detail'])): ?>
                    <div class="ai-detail">
                        <h4>📋 <?php echo t('detail_intro'); ?></h4>
                        <p><?php echo htmlspecialchars($link['ai_detail']); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($link['ai_tags'])): ?>
                    <div class="tags">
                        <?php foreach (explode(',', $link['ai_tags']) as $tag): ?>
                            <span class="tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($link['description'])): ?>
                    <div class="description-box">
                        <strong><?php echo t('description') ?>：</strong><?php echo htmlspecialchars($link['description']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <a href="javascript:void(0);" onclick="showSafetyModal()" class="buy-btn">
        🎯 <?php echo t('buy_now'); ?>
    </a>

    <div class="safety-modal" id="safetyModal">
        <div class="safety-content">
            <div class="safety-icon">🛡️</div>
            <div class="safety-title"><?php echo t('safety_title'); ?></div>
            <div class="platform-warning"><?php echo str_replace('{platform}', htmlspecialchars($link['platform']), t('safety_platform')); ?></div>
            <div class="safety-warning">
                <h4>⚠️ <?php echo t('safety_warn_title'); ?></h4>
                <ul>
                    <li><?php echo t('safety_warn_1'); ?></li>
                    <li><?php echo t('safety_warn_2'); ?></li>
                    <li><?php echo t('safety_warn_3'); ?></li>
                    <li><?php echo t('safety_warn_4'); ?></li>
                </ul>
            </div>
            <div class="countdown" id="countdown">5</div>
            <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" id="jumpBtn" class="jump-btn disabled">
                <?php echo str_replace('{seconds}', '5', t('jump_countdown')); ?>
            </a>
        </div>
    </div>

    <script>
        let countdownTimer;
        let remainingSeconds = 5;

        function showSafetyModal() {
            remainingSeconds = 5;
            document.getElementById('countdown').textContent = remainingSeconds;
            document.getElementById('jumpBtn').classList.add('disabled');
            document.getElementById('jumpBtn').textContent = '<?php echo str_replace("{seconds}", "' + remainingSeconds + '", t('jump_countdown')); ?>'.replace('{seconds}', remainingSeconds);
            document.getElementById('safetyModal').classList.add('active');

            countdownTimer = setInterval(function() {
                remainingSeconds--;
                document.getElementById('countdown').textContent = remainingSeconds;
                const jumpBtn = document.getElementById('jumpBtn');
                if (remainingSeconds <= 0) {
                    clearInterval(countdownTimer);
                    jumpBtn.classList.remove('disabled');
                    jumpBtn.textContent = '<?php echo t('jump_now'); ?>';
                } else {
                    jumpBtn.textContent = '<?php echo str_replace("{seconds}", "", t('jump_countdown')); ?>'.replace('{seconds}', remainingSeconds);
                }
            }, 1000);
        }

        function closeSafetyModal() {
            clearInterval(countdownTimer);
            document.getElementById('safetyModal').classList.remove('active');
        }

        document.getElementById('jumpBtn').addEventListener('click', function(e) {
            if (remainingSeconds > 0) {
                e.preventDefault();
            }
        });

        document.getElementById('safetyModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSafetyModal();
            }
        });
    </script>

    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
        <span class="lightbox-loading" id="lightboxLoading">加载中...</span>
        <img id="lightboxImg" src="" alt="">
    </div>

    <script>
    function openLightbox(src) {
        const lb = document.getElementById('lightbox');
        const img = document.getElementById('lightboxImg');
        const loading = document.getElementById('lightboxLoading');
        loading.style.display = 'block';
        img.classList.remove('visible');
        lb.classList.add('active');
        document.body.style.overflow = 'hidden';
        const fullImg = new Image();
        fullImg.onload = function() {
            img.src = src;
            loading.style.display = 'none';
            requestAnimationFrame(function() {
                img.classList.add('visible');
            });
        };
        fullImg.onerror = function() {
            loading.textContent = '加载失败';
        };
        fullImg.src = src;
    }
    function closeLightbox() {
        const lb = document.getElementById('lightbox');
        const img = document.getElementById('lightboxImg');
        img.classList.remove('visible');
        setTimeout(function() {
            lb.classList.remove('active');
            img.src = '';
        }, 200);
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeLightbox();
    });
    </script>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?> &middot; <a href="contact.php"><?php echo t('footer_contact'); ?></a> &middot; <?php echo t('footer_powered'); ?> <a href="https://blog.sttr.top" target="_blank">standtrain</a></p>
    </div>

    <?php if ($footerCode): ?>
    <?php echo $footerCode; ?>
    <?php endif; ?>
</body>
</html>
