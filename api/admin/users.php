<?php
/**
 * GET  -> list users (optional ?q= search on email/username/display_name/uid).
 * POST -> one of three admin actions on a single user, by `action`:
 *   - set_role        { uid, role: 'admin'|'user' }
 *   - set_suspended   { uid, is_suspended: bool }
 *   - adjust_balance  { uid, delta: int, note?: string } — positive credits,
 *                       negative debits; recorded in wallet_transactions.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$claims = uploadgram_require_admin();
$adminUid = (string) $claims['sub'];
$db = uploadgram_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q !== '') {
        $stmt = $db->prepare("SELECT uid, email, display_name, username, phone, balance, role, is_suspended, created_at FROM users
            WHERE email LIKE ? OR username LIKE ? OR display_name LIKE ? OR uid LIKE ?
            ORDER BY created_at DESC LIMIT 200");
        $like = '%' . $q . '%';
        $stmt->execute([$like, $like, $like, $like]);
    } else {
        $stmt = $db->query('SELECT uid, email, display_name, username, phone, balance, role, is_suspended, created_at FROM users ORDER BY created_at DESC LIMIT 200');
    }
    uploadgram_admin_send_json(['ok' => true, 'users' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'invalid_body'], 400);
}

$uid = trim((string) ($input['uid'] ?? ''));
$action = (string) ($input['action'] ?? '');
if ($uid === '') {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'missing_uid'], 400);
}

$stmt = $db->prepare('SELECT * FROM users WHERE uid = ?');
$stmt->execute([$uid]);
$user = $stmt->fetch();
if (!$user) {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'user_not_found'], 404);
}

if ($action === 'set_role') {
    $role = ($input['role'] ?? '') === 'admin' ? 'admin' : 'user';
    if ($uid === $adminUid && $role !== 'admin') {
        uploadgram_admin_send_json(['ok' => false, 'error' => 'cannot_demote_self'], 400);
    }
    $db->prepare('UPDATE users SET role = ? WHERE uid = ?')->execute([$role, $uid]);
    uploadgram_admin_send_json(['ok' => true, 'uid' => $uid, 'role' => $role]);
}

if ($action === 'set_suspended') {
    $suspended = !empty($input['is_suspended']);
    if ($uid === $adminUid && $suspended) {
        uploadgram_admin_send_json(['ok' => false, 'error' => 'cannot_suspend_self'], 400);
    }
    $db->prepare('UPDATE users SET is_suspended = ? WHERE uid = ?')->execute([$suspended ? 1 : 0, $uid]);
    uploadgram_admin_send_json(['ok' => true, 'uid' => $uid, 'is_suspended' => $suspended]);
}

if ($action === 'adjust_balance') {
    $delta = (int) ($input['delta'] ?? 0);
    $note = trim((string) ($input['note'] ?? ''));
    if ($delta === 0) {
        uploadgram_admin_send_json(['ok' => false, 'error' => 'invalid_delta'], 400);
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT balance FROM users WHERE uid = ? FOR UPDATE');
        $stmt->execute([$uid]);
        $balance = (int) $stmt->fetchColumn();
        if ($delta < 0 && $balance + $delta < 0) {
            $db->rollBack();
            uploadgram_admin_send_json(['ok' => false, 'error' => 'insufficient_balance', 'balance' => $balance], 400);
        }
        $db->prepare('UPDATE users SET balance = balance + ? WHERE uid = ?')->execute([$delta, $uid]);
        $db->prepare('INSERT INTO wallet_transactions (uid, type, amount, status, note) VALUES (?, ?, ?, ?, ?)')
            ->execute([$uid, 'admin_adjust', $delta, 'success', $note !== '' ? $note : 'تنظیم موجودی توسط مدیر']);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        uploadgram_admin_send_json(['ok' => false, 'error' => 'adjust_failed'], 500);
    }

    $stmt = $db->prepare('SELECT balance FROM users WHERE uid = ?');
    $stmt->execute([$uid]);
    uploadgram_admin_send_json(['ok' => true, 'uid' => $uid, 'balance' => (int) $stmt->fetchColumn()]);
}

uploadgram_admin_send_json(['ok' => false, 'error' => 'unknown_action'], 400);
