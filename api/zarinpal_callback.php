<?php
/**
 * ZarinPal redirects the browser back here after the user pays (or cancels).
 * No auth header is available on this request — the order/top-up row is
 * looked up by its stored payment_authority instead, then verified server
 * to server with verify.json before anything is marked paid.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/services_lib.php';
require_once __DIR__ . '/zarinpal_lib.php';

$authority = trim((string) ($_GET['Authority'] ?? ''));
$status = (string) ($_GET['Status'] ?? '');
$db = uploadgram_db();

function uploadgram_redirect_dashboard(array $params): void {
    header('Location: ' . uploadgram_base_url() . '/dashboard.html?' . http_build_query($params));
    exit;
}

if ($authority === '') {
    uploadgram_redirect_dashboard(['payment' => 'error']);
}

$stmt = $db->prepare("SELECT * FROM orders WHERE payment_authority = ? LIMIT 1");
$stmt->execute([$authority]);
$order = $stmt->fetch();

if ($order) {
    if ($order['status'] !== 'awaiting_payment') {
        uploadgram_redirect_dashboard(['payment' => $order['status'] === 'paid' || $order['status'] === 'processing' ? 'success' : 'failed', 'order' => $order['id']]);
    }
    if ($status !== 'OK') {
        $db->prepare("UPDATE orders SET status = 'failed' WHERE id = ?")->execute([$order['id']]);
        uploadgram_redirect_dashboard(['payment' => 'failed', 'order' => $order['id']]);
    }

    $verified = uploadgram_zarinpal_verify((int) $order['total_price'], $authority);
    if (!$verified) {
        $db->prepare("UPDATE orders SET status = 'failed' WHERE id = ?")->execute([$order['id']]);
        uploadgram_redirect_dashboard(['payment' => 'failed', 'order' => $order['id']]);
    }

    $db->prepare("UPDATE orders SET status = 'paid', payment_ref_id = ? WHERE id = ?")->execute([$verified['ref_id'], $order['id']]);
    $db->prepare("INSERT INTO wallet_transactions (uid, type, amount, authority, ref_id, status, note) VALUES (?, 'order', ?, ?, ?, 'success', ?)")
        ->execute([$order['uid'], -(int) $order['total_price'], $authority, $verified['ref_id'], 'سفارش #' . $order['id'] . ' — ' . $order['service_name']]);

    $service = uploadgram_find_service((string) $order['service_id']) ?? [
        'id' => $order['service_id'],
        'name' => $order['service_name'],
    ];
    uploadgram_place_upstream_order($db, (int) $order['id'], $service, $order['link'], (int) $order['quantity']);

    uploadgram_redirect_dashboard(['payment' => 'success', 'order' => $order['id']]);
}

$stmt = $db->prepare("SELECT * FROM wallet_transactions WHERE authority = ? AND type = 'topup' LIMIT 1");
$stmt->execute([$authority]);
$tx = $stmt->fetch();

if ($tx) {
    if ($tx['status'] !== 'pending') {
        uploadgram_redirect_dashboard(['payment' => $tx['status'] === 'success' ? 'success' : 'failed', 'topup' => $tx['id']]);
    }
    if ($status !== 'OK') {
        $db->prepare("UPDATE wallet_transactions SET status = 'failed' WHERE id = ?")->execute([$tx['id']]);
        uploadgram_redirect_dashboard(['payment' => 'failed', 'topup' => $tx['id']]);
    }

    $verified = uploadgram_zarinpal_verify((int) $tx['amount'], $authority);
    if (!$verified) {
        $db->prepare("UPDATE wallet_transactions SET status = 'failed' WHERE id = ?")->execute([$tx['id']]);
        uploadgram_redirect_dashboard(['payment' => 'failed', 'topup' => $tx['id']]);
    }

    $db->beginTransaction();
    $db->prepare("UPDATE wallet_transactions SET status = 'success', ref_id = ? WHERE id = ?")->execute([$verified['ref_id'], $tx['id']]);
    $db->prepare('UPDATE users SET balance = balance + ? WHERE uid = ?')->execute([(int) $tx['amount'], $tx['uid']]);
    $db->commit();

    uploadgram_redirect_dashboard(['payment' => 'success', 'topup' => $tx['id']]);
}

uploadgram_redirect_dashboard(['payment' => 'unknown']);
