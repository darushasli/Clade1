<?php
/**
 * Called once right after Firebase registration finishes. Persists the
 * username/phone collected on the registration form — Firebase Auth itself
 * has no place to store them, so this is the only chance to capture them.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/firebase_lib.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$claims = uploadgram_require_auth();
$uid = (string) $claims['sub'];
$email = (string) ($claims['email'] ?? '');

$input = json_decode(file_get_contents('php://input'), true);
$username = is_array($input) ? trim((string) ($input['username'] ?? '')) : '';
$phone = is_array($input) ? trim((string) ($input['phone'] ?? '')) : '';
$displayName = is_array($input) ? trim((string) ($input['displayName'] ?? '')) : '';

uploadgram_get_or_create_user($uid, $email, $displayName);

$db = uploadgram_db();
$fields = [];
$params = [];
if ($username !== '') {
    $fields[] = 'username = ?';
    $params[] = $username;
}
if ($phone !== '') {
    $fields[] = 'phone = ?';
    $params[] = $phone;
}
if ($displayName !== '') {
    $fields[] = 'display_name = ?';
    $params[] = $displayName;
}
if ($email !== '') {
    $fields[] = 'email = ?';
    $params[] = $email;
}

if ($fields) {
    $params[] = $uid;
    $stmt = $db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE uid = ?');
    $stmt->execute($params);
}

$stmt = $db->prepare('SELECT uid, email, display_name, username, phone, balance, role, is_suspended FROM users WHERE uid = ?');
$stmt->execute([$uid]);
echo json_encode(['ok' => true, 'user' => $stmt->fetch()], JSON_UNESCAPED_UNICODE);
