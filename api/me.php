<?php
/** Dashboard bootstrap: current profile + balance + headline stats for the top bar/stat cards. */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/firebase_lib.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$claims = uploadgram_require_auth();
$uid = (string) $claims['sub'];
$email = (string) ($claims['email'] ?? '');

$user = uploadgram_get_or_create_user($uid, $email);
$db = uploadgram_db();

$stmt = $db->prepare('SELECT COUNT(*) FROM orders WHERE uid = ?');
$stmt->execute([$uid]);
$ordersCount = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE uid = ? AND status NOT IN ('completed','cancelled','refunded')");
$stmt->execute([$uid]);
$activeOrders = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE uid = ? AND status = 'open'");
$stmt->execute([$uid]);
$openTickets = (int) $stmt->fetchColumn();

echo json_encode([
    'ok' => true,
    'user' => [
        'uid' => $user['uid'],
        'email' => $user['email'],
        'display_name' => $user['display_name'],
        'username' => $user['username'],
        'phone' => $user['phone'],
        'balance' => (int) $user['balance'],
        'role' => $user['role'],
        'is_suspended' => (bool) $user['is_suspended'],
    ],
    'stats' => [
        'orders_count' => $ordersCount,
        'active_orders' => $activeOrders,
        'open_tickets' => $openTickets,
    ],
], JSON_UNESCAPED_UNICODE);
