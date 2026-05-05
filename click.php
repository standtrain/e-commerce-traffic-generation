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

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$linkId = $_GET['id'];
$link = getLinkById($linkId);

if ($link) {
    recordClick($linkId);
    echo json_encode(['success' => true, 'message' => 'Click recorded']);
} else {
    echo json_encode(['success' => false, 'message' => 'Link not found']);
}
exit;