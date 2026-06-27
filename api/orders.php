<?php
/**
 * GET  -> list the authenticated user's orders (newest first).
 * POST -> place a new order for a catalog service. pay_method is "wallet"
 *         (instant, deducted from balance) or "zarinpal" (returns a pay_url
 *         to redirect to; the order is finalized in zarinpal_callback.php).
 *
 * rate from services_lib.php follows the standard SMM-panel convention of
 * "price per 1000 units", so total_price = round(rate / 1000 * quantity).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/firebase_lib.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/services_lib.php';
require_once __DIR__ . '/zarinpal_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$claims = uploadgram_require_auth();
$uid = (string) $claims['sub'];
$email = (string) ($claims['email'] ?? '');
uploadgram_get_or_create_user($uid, $email);
$db = uploadgram_db();

function uploadgram_send_json(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('SELECT id, service_id, service_name, platform, link, quantity, unit_rate, total_price, pay_method, status, upstream_status, created_at FROM orders WHERE uid = ? ORDER BY id DESC LIMIT 200');
    $stmt->execute([$uid]);
    uploadgram_send_json(['ok' => true, 'orders' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    uploadgram_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    uploadgram_send_json(['ok' => false, 'error' => 'invalid_body'], 400);
}

$serviceId = trim((string) ($input['service_id'] ?? ''));
$link = trim((string) ($input['link'] ?? ''));
$quantity = (int) ($input['quantity'] ?? 0);
$payMethod = ($input['pay_method'] ?? 'wallet') === 'zarinpal' ? 'zarinpal' : 'wallet';

if ($serviceId === '' || $link === '' || $quantity <= 0) {
    uploadgram_send_json(['ok' => false, 'error' => 'missing_fields'], 400);
}
if (!filter_var($link, FILTER_VALIDATE_URL)) {
    uploadgram_send_json(['ok' => false, 'error' => 'invalid_link'], 400);
}

$service = uploadgram_find_service($serviceId);
if (!$service || $service['rate'] === null) {
    uploadgram_send_json(['ok' => false, 'error' => 'service_not_found'], 404);
}
if ($service['min'] !== null && $quantity < $service['min']) {
    uploadgram_send_json(['ok' => false, 'error' => 'quantity_below_min', 'min' => $service['min']], 400);
}
if ($service['max'] !== null && $quantity > $service['max']) {
    uploadgram_send_json(['ok' => false, 'error' => 'quantity_above_max', 'max' => $service['max']], 400);
}

$totalPrice = (int) round($service['rate'] / 1000 * $quantity);
if ($totalPrice <= 0) {
    uploadgram_send_json(['ok' => false, 'error' => 'invalid_amount'], 400);
}

if ($payMethod === 'wallet') {
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT balance FROM users WHERE uid = ? FOR UPDATE');
        $stmt->execute([$uid]);
        $balance = (int) $stmt->fetchColumn();

        if ($balance < $totalPrice) {
            $db->rollBack();
            uploadgram_send_json(['ok' => false, 'error' => 'insufficient_balance', 'balance' => $balance, 'required' => $totalPrice], 402);
        }

        $db->prepare('UPDATE users SET balance = balance - ? WHERE uid = ?')->execute([$totalPrice, $uid]);

        $insert = $db->prepare('INSERT INTO orders (uid, service_id, service_name, platform, link, quantity, unit_rate, total_price, pay_method, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([$uid, $service['id'], $service['name'], $service['platform'], $link, $quantity, $service['rate'], $totalPrice, 'wallet', 'paid']);
        $orderId = (int) $db->lastInsertId();

        $db->prepare('INSERT INTO wallet_transactions (uid, type, amount, status, note) VALUES (?, ?, ?, ?, ?)')
            ->execute([$uid, 'order', -$totalPrice, 'success', 'سفارش #' . $orderId . ' — ' . $service['name']]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        uploadgram_send_json(['ok' => false, 'error' => 'order_failed'], 500);
    }

    uploadgram_place_upstream_order($db, $orderId, $service, $link, $quantity);
    uploadgram_send_json(['ok' => true, 'order_id' => $orderId, 'status' => 'paid', 'pay_method' => 'wallet']);
}

$insert = $db->prepare('INSERT INTO orders (uid, service_id, service_name, platform, link, quantity, unit_rate, total_price, pay_method, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$insert->execute([$uid, $service['id'], $service['name'], $service['platform'], $link, $quantity, $service['rate'], $totalPrice, 'zarinpal', 'awaiting_payment']);
$orderId = (int) $db->lastInsertId();

$callbackUrl = uploadgram_base_url() . '/api/zarinpal_callback.php';
$payment = uploadgram_zarinpal_request($totalPrice, 'سفارش #' . $orderId . ' — ' . $service['name'], $callbackUrl, ['order_id' => $orderId]);

if (!$payment) {
    $db->prepare("UPDATE orders SET status = 'failed' WHERE id = ?")->execute([$orderId]);
    uploadgram_send_json(['ok' => false, 'error' => 'gateway_unavailable'], 502);
}

$db->prepare('UPDATE orders SET payment_authority = ? WHERE id = ?')->execute([$payment['authority'], $orderId]);

uploadgram_send_json(['ok' => true, 'order_id' => $orderId, 'status' => 'awaiting_payment', 'pay_method' => 'zarinpal', 'pay_url' => $payment['pay_url']]);
