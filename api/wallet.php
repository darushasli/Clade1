<?php
/**
 * GET  -> current balance + transaction history.
 * POST -> start a wallet top-up: creates a pending wallet_transactions row
 *         and returns a ZarinPal pay_url. The balance is only credited once
 *         zarinpal_callback.php verifies the payment.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/firebase_lib.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/zarinpal_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function uploadgram_send_json(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$claims = uploadgram_require_auth();
$uid = (string) $claims['sub'];
$email = (string) ($claims['email'] ?? '');
$user = uploadgram_get_or_create_user($uid, $email);
$db = uploadgram_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('SELECT id, type, amount, status, note, created_at FROM wallet_transactions WHERE uid = ? ORDER BY id DESC LIMIT 200');
    $stmt->execute([$uid]);
    uploadgram_send_json(['ok' => true, 'balance' => (int) $user['balance'], 'transactions' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    uploadgram_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$amount = is_array($input) ? (int) ($input['amount'] ?? 0) : 0;

if ($amount < 10000) {
    uploadgram_send_json(['ok' => false, 'error' => 'amount_too_low'], 400);
}

$insert = $db->prepare("INSERT INTO wallet_transactions (uid, type, amount, status, note) VALUES (?, 'topup', ?, 'pending', 'شارژ کیف پول')");
$insert->execute([$uid, $amount]);
$txId = (int) $db->lastInsertId();

$callbackUrl = uploadgram_base_url() . '/api/zarinpal_callback.php';
$payment = uploadgram_zarinpal_request($amount, 'شارژ کیف پول #' . $txId, $callbackUrl, ['wallet_tx_id' => $txId]);

if (!$payment) {
    $db->prepare("UPDATE wallet_transactions SET status = 'failed' WHERE id = ?")->execute([$txId]);
    uploadgram_send_json(['ok' => false, 'error' => 'gateway_unavailable'], 502);
}

$db->prepare('UPDATE wallet_transactions SET authority = ? WHERE id = ?')->execute([$payment['authority'], $txId]);

uploadgram_send_json(['ok' => true, 'tx_id' => $txId, 'pay_url' => $payment['pay_url']]);
