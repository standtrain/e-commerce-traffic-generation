<?php
if (!file_exists('config.php')) {
    if (file_exists('install.php')) {
        header('Location: install.php');
        exit;
    }
    die('系统未安装，请确保install.php文件存在。');
}

require_once 'config.php';

if (!defined('INSTALLED') || INSTALLED !== true) {
    if (file_exists('install.php')) {
        header('Location: install.php');
        exit;
    }
    die('系统未安装，请确保install.php文件存在。');
}

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$linkId = $_GET['id'];
$link = getLinkById($linkId);

if (!$link) {
    header('Location: index.php');
    exit;
}

if ($link['status'] !== 'active') {
    header('Location: index.php');
    exit;
}

recordClick($linkId);

header('Location: ' . $link['url']);
exit;