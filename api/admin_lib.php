<?php
/**
 * Admin auth gate. Every endpoint under api/admin/*.php must call
 * uploadgram_require_admin() before touching any data — it verifies the
 * Firebase ID token (same as a regular user) and additionally checks
 * users.role === 'admin', exiting with 403 otherwise. There is no HTTP
 * endpoint that grants the admin role to avoid a privilege-escalation
 * hole; it's set via api/admin/bootstrap_admin.php on the server's CLI.
 */

require_once __DIR__ . '/firebase_lib.php';
require_once __DIR__ . '/db.php';

function uploadgram_require_admin(): array {
    $claims = uploadgram_require_auth();
    $uid = (string) $claims['sub'];

    if (!uploadgram_is_admin($uid)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return $claims;
}

function uploadgram_admin_send_json(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
