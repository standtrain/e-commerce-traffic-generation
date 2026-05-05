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

$siteName = getSiteName();
$siteIcon = getSiteIcon();
$bgType = getBackgroundType();
$bgImage = getBackgroundImage();
$customCss = getCustomCss();

$contactWechat = getSetting('contact_wechat');
$contactQq = getSetting('contact_qq');
$contactEmail = getSetting('contact_email');
$contactPhone = getSetting('contact_phone');
$contactImage = getSetting('contact_image');

$hasContact = !empty($contactWechat) || !empty($contactQq) || !empty($contactEmail) || !empty($contactPhone) || !empty($contactImage);

$bgStyle = '';
if ($bgType === 'color') {
    $bgStyle = 'background: ' . ($bgImage ?: '#0a0a0a') . ';';
} elseif ($bgType === 'gradient') {
    $bgStyle = 'background: linear-gradient(135deg, ' . ($bgImage ?: '#0a0a0a, #111') . ');';
} elseif ($bgType === 'image' && $bgImage) {
    $bgStyle = 'background: url("' . htmlspecialchars($bgImage) . '") no-repeat center center fixed; background-size: cover;';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('contact_title'); ?> - <?php echo htmlspecialchars($siteName); ?></title>
    <?php if ($siteIcon): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($siteIcon); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { <?php echo $bgStyle; ?> background-attachment: fixed; }
        <?php echo $customCss; ?>

        .contact-page {
            max-width: 540px;
            margin: 0 auto;
            padding: 40px 18px 72px;
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .contact-header {
            text-align: center;
            margin-bottom: 36px;
        }

        .contact-header h1 {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
            letter-spacing: -0.03em;
        }

        .contact-header p {
            color: var(--gray-500);
            font-size: 1rem;
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
            margin-bottom: 28px;
        }

        .back-btn:hover {
            transform: translateX(-3px);
            box-shadow: var(--shadow-md);
            color: var(--primary);
        }

        .contact-card {
            background: rgba(10, 10, 10, 0.8);
            backdrop-filter: blur(16px);
            border-radius: var(--radius-xl);
            padding: 36px;
            box-shadow: var(--shadow-xl);
            margin-bottom: 24px;
            border: 1px solid rgba(0, 255, 65, 0.08);
        }

        .contact-card h2 {
            color: var(--gray-800);
            font-size: 1.15rem;
            margin-bottom: 24px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--gray-100);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid var(--gray-50);
            transition: all var(--transition);
        }

        .contact-item:last-child {
            border-bottom: none;
        }

        .contact-item:hover {
            padding-left: 6px;
        }

        .contact-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            margin-right: 16px;
            flex-shrink: 0;
            box-shadow: 0 3px 10px rgba(0, 255, 65, 0.2);
        }

        .contact-info {
            flex: 1;
        }

        .contact-label {
            font-size: 0.78rem;
            color: var(--gray-400);
            margin-bottom: 3px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .contact-value {
            font-size: 1.05rem;
            color: var(--gray-800);
            font-weight: 600;
        }

        .contact-value a {
            color: var(--primary);
            text-decoration: none;
            transition: color var(--transition-fast);
        }

        .contact-value a:hover {
            color: var(--primary-dark);
        }

        .contact-image-container {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-100);
        }

        .contact-image-container img {
            max-width: 200px;
            max-height: 200px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            transition: all var(--transition);
        }

        .contact-image-container img:hover {
            transform: scale(1.02);
            box-shadow: var(--shadow-xl);
        }

        .no-contact {
            text-align: center;
            padding: 48px 18px;
            color: var(--gray-400);
        }

        .no-contact .icon {
            font-size: 3.5rem;
            margin-bottom: 12px;
            filter: grayscale(0.3);
        }

        .no-contact p {
            font-size: 0.92rem;
        }

        .footer {
            text-align: center;
            padding: 20px;
            color: var(--gray-400);
            font-size: 0.82rem;
        }

        .footer a {
            color: var(--primary);
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .contact-page {
                padding: 24px 14px 52px;
            }
            .contact-header {
                margin-bottom: 28px;
            }
            .contact-header h1 {
                font-size: 1.7rem;
            }
            .contact-card {
                padding: 22px 18px;
            }
            .back-btn {
                margin-bottom: 22px;
            }
        }

        @media (max-width: 480px) {
            .contact-page {
                padding: 18px 10px 48px;
            }
            .contact-header h1 {
                font-size: 1.5rem;
            }
            .contact-header p {
                font-size: 0.88rem;
            }
            .contact-card {
                padding: 18px 14px;
                border-radius: var(--radius-lg);
            }
            .contact-card h2 {
                font-size: 1rem;
                margin-bottom: 18px;
                padding-bottom: 12px;
            }
            .contact-item {
                padding: 12px 0;
            }
            .contact-icon {
                width: 40px;
                height: 40px;
                font-size: 1.15rem;
                margin-right: 12px;
                border-radius: 12px;
            }
            .contact-label {
                font-size: 0.72rem;
            }
            .contact-value {
                font-size: 0.95rem;
            }
            .contact-image-container img {
                max-width: 160px;
                max-height: 160px;
            }
            .back-btn {
                padding: 7px 16px;
                font-size: 0.82rem;
                margin-bottom: 18px;
            }
        }
        <?php echo getThemeCss(); ?>
    </style>
</head>
<body>
    <div class="container">
        <div class="contact-page">
            <a href="index.php" class="back-btn"><?php echo t('back_home'); ?></a>

            <div class="contact-header">
                <h1><?php echo t('contact_title'); ?></h1>
                <p><?php echo t('contact_desc'); ?></p>
            </div>

            <div class="contact-card">
                <h2>📬 <?php echo t('contact_methods'); ?></h2>

                <?php if ($hasContact): ?>

                    <?php if (!empty($contactWechat)): ?>
                    <div class="contact-item">
                        <div class="contact-icon">💬</div>
                        <div class="contact-info">
                            <div class="contact-label"><?php echo t('wechat'); ?></div>
                            <div class="contact-value"><?php echo htmlspecialchars($contactWechat); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($contactQq)): ?>
                    <div class="contact-item">
                        <div class="contact-icon">🐧</div>
                        <div class="contact-info">
                            <div class="contact-label"><?php echo t('qq'); ?></div>
                            <div class="contact-value">
                                <a href="http://wpa.qq.com/msgrd?v=3&uin=<?php echo htmlspecialchars($contactQq); ?>&site=qq&menu=yes" target="_blank">
                                    <?php echo htmlspecialchars($contactQq); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($contactEmail)): ?>
                    <div class="contact-item">
                        <div class="contact-icon">✉️</div>
                        <div class="contact-info">
                            <div class="contact-label"><?php echo t('email'); ?></div>
                            <div class="contact-value">
                                <a href="mailto:<?php echo htmlspecialchars($contactEmail); ?>"><?php echo htmlspecialchars($contactEmail); ?></a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($contactPhone)): ?>
                    <div class="contact-item">
                        <div class="contact-icon">📞</div>
                        <div class="contact-info">
                            <div class="contact-label"><?php echo t('phone'); ?></div>
                            <div class="contact-value">
                                <a href="tel:<?php echo htmlspecialchars($contactPhone); ?>"><?php echo htmlspecialchars($contactPhone); ?></a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($contactImage)): ?>
                    <div class="contact-image-container">
                        <img src="<?php echo htmlspecialchars($contactImage); ?>" alt="联系方式图片">
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="no-contact">
                        <div class="icon">📭</div>
                        <p><?php echo t('no_contact'); ?></p>
                        <p style="font-size: 0.85rem; margin-top: 8px; color: var(--gray-400);"><?php echo t('no_contact_desc'); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="footer">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?> &middot; <?php echo t('footer_powered'); ?> <a href="https://blog.sttr.top" target="_blank">standtrain</a></p>
            </div>
        </div>
    </div>
</body>
</html>
