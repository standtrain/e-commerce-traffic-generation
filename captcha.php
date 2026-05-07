<?php
/**
 * Ultra-lightweight CAPTCHA — optimised for sub-10ms generation.
 * No external deps, minimal GD calls, uncompressed PNG.
 */

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate code
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$max   = strlen($chars) - 1;
$code  = $chars[rand(0, $max)] . $chars[rand(0, $max)] . $chars[rand(0, $max)] . $chars[rand(0, $max)];

// Store and release lock early — rendering needs no session
$_SESSION['captcha_code'] = $code;
session_write_close();

// Headers
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$w = 130;
$h = 44;

if (!function_exists('imagecreatetruecolor')) {
    http_response_code(500);
    ob_end_clean();
    header('Content-Type: text/plain');
    die('GD library not installed.');
}

$img = imagecreatetruecolor($w, $h);

// Pre-allocate all colours (one pass, ~10 calls instead of ~57)
$bg     = imagecolorallocate($img, 18, 18, 24);
$line1  = imagecolorallocate($img, 55, 55, 70);
$line2  = imagecolorallocate($img, 70, 55, 65);
$dots   = [imagecolorallocate($img, 35, 35, 45), imagecolorallocate($img, 40, 38, 50)];
$chars_ = [
    imagecolorallocate($img, 20, 220, 50),
    imagecolorallocate($img, 10, 240, 80),
    imagecolorallocate($img, 30, 200, 60),
    imagecolorallocate($img, 0, 255, 40),
];

imagefilledrectangle($img, 0, 0, $w, $h, $bg);

// Sparse noise (~15 dots)
for ($i = 0; $i < 15; $i++) {
    imagesetpixel($img, rand(0, $w - 1), rand(0, $h - 1), $dots[$i & 1]);
}

// 1 interference line
imageline($img, rand(0, 40), rand(0, $h), rand(80, $w), rand(0, $h), $line1);
imageline($img, rand(0, 30), rand(0, $h), rand(90, $w), rand(0, $h), $line2);

// Characters — built-in font 5 (8×16 px), positions are constant
$charW = 8;
$charH = 16;
$startX = (int)(($w - $charW * 4) / 2);
$baseY  = (int)(($h - $charH) / 2);

for ($i = 0; $i < 4; $i++) {
    imagestring($img, 5, $startX + $i * $charW + rand(-1, 1), $baseY + rand(-3, 3), $code[$i], $chars_[$i]);
}

ob_end_clean();

// Compression level 0 — no deflate, raw PNG, ~5× faster encode
imagepng($img, null, 0);
imagedestroy($img);
