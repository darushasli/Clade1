<?php
/**
 * GET -> public site branding/content settings (logo, hero text, contact
 * links, footer text), so static pages can render admin-edited content
 * instead of hardcoded values. No auth — this is public, read-only data.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/site_settings_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'settings' => uploadgram_get_site_settings()], JSON_UNESCAPED_UNICODE);
