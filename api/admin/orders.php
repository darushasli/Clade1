<?php
/**
 * GET  -> list all orders across all users (optional ?status= filter,
 *         optional ?uid= filter), newest first.
 * POST -> one of three admin actions on a single order, by `action`:
 *   - mark_completed { id }                — marks an order as completed.
 *   - retry_upstream  { id }                — re-invokes the per-service
 *                       upstream order placement (for upstream_error/
 *                       manual_required orders), e.g. after fixing a
 *                       product's API config or the shared reseller key.
 *   - cancel_refund   { id, note? }         — cancels the order and credits
 *                       its total_price back to the user's wallet.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin_lib.php';
require_once __DIR__ . '/../services_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

uploadgram_require_admin();
$db = uploadgram_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = trim((string) ($_GET['status'] ?? ''));
    $uid = trim((string) ($_GET['uid'] ?? ''));

    $sql = 'SELECT * FROM orders WHERE 1=1';
    $params = [];
    if ($status !== '') {
        $sql .= ' AND status = ?';
        $params[] = $status;
    }
    if ($uid !== '') {
        $sql .= ' AND uid = ?';
        $params[] = $uid;
    }
    $sql .= ' ORDER BY id DESC LIMIT 300';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    uploadgram_admin_send_json(['ok' => true, 'orders' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'invalid_body'], 400);
}

$orderId = (int) ($input['id'] ?? 0);
$action = (string) ($input['action'] ?? '');
if ($orderId <= 0) {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'missing_id'], 400);
}

$stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order) {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'order_not_found'], 404);
}

if ($action === 'mark_completed') {
    $db->prepare("UPDATE orders SET status = 'completed' WHERE id = ?")->execute([$orderId]);
    uploadgram_admin_send_json(['ok' => true, 'id' => $orderId, 'status' => 'completed']);
}

if ($action === 'retry_upstream') {
    $service = uploadgram_find_service((string) $order['service_id']);
    if (!$service) {
        uploadgram_admin_send_json(['ok' => false, 'error' => 'service_not_found'], 404);
    }
    uploadgram_place_upstream_order($db, $orderId, $service, (string) $order['link'], (int) $order['quantity']);

    $stmt = $db->prepare('SELECT status, upstream_status, upstream_order_id FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $fresh = $stmt->fetch();
    uploadgram_admin_send_json(['ok' => true, 'id' => $orderId] + $fresh);
}

if ($action === 'cancel_refund') {
    $note = trim((string) ($input['note'] ?? ''));
    if (in_array($order['status'], ['completed', 'refunded'], true)) {
        uploadgram_admin_send_json(['ok' => false, 'error' => 'cannot_refund_this_status', 'status' => $order['status']], 400);
    }

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE orders SET status = 'refunded' WHERE id = ?")->execute([$orderId]);
        $db->prepare('UPDATE users SET balance = balance + ? WHERE uid = ?')->execute([$order['total_price'], $order['uid']]);
        $db->prepare('INSERT INTO wallet_transactions (uid, type, amount, status, note) VALUES (?, ?, ?, ?, ?)')
            ->execute([$order['uid'], 'refund', (int) $order['total_price'], 'success', $note !== '' ? $note : 'بازگشت وجه سفارش #' . $orderId]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        uploadgram_admin_send_json(['ok' => false, 'error' => 'refund_failed'], 500);
    }

    uploadgram_admin_send_json(['ok' => true, 'id' => $orderId, 'status' => 'refunded']);
}

uploadgram_admin_send_json(['ok' => false, 'error' => 'unknown_action'], 400);
