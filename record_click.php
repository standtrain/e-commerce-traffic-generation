<?php
if (!file_exists('config.php')) {
    http_response_code(404);
    exit;
}

require_once 'config.php';

if (!defined('INSTALLED') || INSTALLED !== true) {
    http_response_code(404);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = $_GET['id'];
    recordClick($id);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid request']);