<?php
/**
 * GET       -> current site branding/content settings.
 * PUT/POST  -> updates one or more settings by key (unknown keys ignored,
 *              omitted keys left untouched).
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin_lib.php';
require_once __DIR__ . '/../site_settings_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

uploadgram_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    uploadgram_admin_send_json(['ok' => true, 'settings' => uploadgram_get_site_settings()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'invalid_body'], 400);
}

$settings = uploadgram_set_site_settings($input);
uploadgram_admin_send_json(['ok' => true, 'settings' => $settings]);
