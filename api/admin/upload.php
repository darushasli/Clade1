<?php
/**
 * POST -> admin-only image upload (multipart/form-data, field name "file")
 * for branding assets (logo, hero images, etc.) used by the Site Settings
 * panel. Stores the file under assets/uploads/ with a random name (never
 * trusting the client's filename) and returns the relative URL to save into
 * a site_settings value such as logo_url.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

uploadgram_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'upload_failed'], 400);
}

$file = $_FILES['file'];

if ($file['size'] > 4 * 1024 * 1024) {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'file_too_large'], 400);
}

$allowed = [
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
    'image/svg+xml' => 'svg',
    'image/x-icon' => 'ico',
    'image/vnd.microsoft.icon' => 'ico',
];

$mime = mime_content_type($file['tmp_name']);
if (!isset($allowed[$mime])) {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'unsupported_type'], 400);
}

$dir = __DIR__ . '/../../assets/uploads';
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'upload_dir_failed'], 500);
}

$name = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'move_failed'], 500);
}

uploadgram_admin_send_json(['ok' => true, 'url' => 'assets/uploads/' . $name]);
