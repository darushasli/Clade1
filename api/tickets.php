<?php
/**
 * Ticket endpoints scoped to the authenticated user.
 * GET  ?id=N       -> one ticket + its messages
 * GET  (no id)     -> list of the user's tickets
 * POST { subject, message }       -> opens a new ticket
 * POST { ticket_id, message }     -> appends a reply to an existing (open) ticket
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/firebase_lib.php';
require_once __DIR__ . '/db.php';

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
uploadgram_get_or_create_user($uid, $email);
$db = uploadgram_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);

    if ($id > 0) {
        $stmt = $db->prepare('SELECT * FROM tickets WHERE id = ? AND uid = ?');
        $stmt->execute([$id, $uid]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            uploadgram_send_json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $stmt = $db->prepare('SELECT id, sender, message, created_at FROM ticket_messages WHERE ticket_id = ? ORDER BY id ASC');
        $stmt->execute([$id]);
        uploadgram_send_json(['ok' => true, 'ticket' => $ticket, 'messages' => $stmt->fetchAll()]);
    }

    $stmt = $db->prepare('SELECT id, subject, department, priority, status, created_at, updated_at FROM tickets WHERE uid = ? ORDER BY id DESC LIMIT 200');
    $stmt->execute([$uid]);
    uploadgram_send_json(['ok' => true, 'tickets' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    uploadgram_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$message = is_array($input) ? trim((string) ($input['message'] ?? '')) : '';
$ticketId = is_array($input) ? (int) ($input['ticket_id'] ?? 0) : 0;

if ($message === '') {
    uploadgram_send_json(['ok' => false, 'error' => 'missing_message'], 400);
}

if ($ticketId > 0) {
    $stmt = $db->prepare("SELECT * FROM tickets WHERE id = ? AND uid = ?");
    $stmt->execute([$ticketId, $uid]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        uploadgram_send_json(['ok' => false, 'error' => 'not_found'], 404);
    }
    if ($ticket['status'] === 'closed') {
        uploadgram_send_json(['ok' => false, 'error' => 'ticket_closed'], 409);
    }

    $db->prepare("INSERT INTO ticket_messages (ticket_id, sender, message) VALUES (?, 'user', ?)")->execute([$ticketId, $message]);
    $db->prepare("UPDATE tickets SET status = 'open' WHERE id = ?")->execute([$ticketId]);

    uploadgram_send_json(['ok' => true, 'ticket_id' => $ticketId]);
}

$subject = trim((string) ($input['subject'] ?? ''));
if ($subject === '') {
    uploadgram_send_json(['ok' => false, 'error' => 'missing_subject'], 400);
}

$department = (string) ($input['department'] ?? '');
if (!in_array($department, uploadgram_ticket_departments(), true)) {
    $department = 'general';
}
$priority = (string) ($input['priority'] ?? '');
if (!in_array($priority, uploadgram_ticket_priorities(), true)) {
    $priority = 'medium';
}

$db->prepare('INSERT INTO tickets (uid, subject, department, priority) VALUES (?, ?, ?, ?)')->execute([$uid, $subject, $department, $priority]);
$ticketId = (int) $db->lastInsertId();
$db->prepare("INSERT INTO ticket_messages (ticket_id, sender, message) VALUES (?, 'user', ?)")->execute([$ticketId, $message]);

uploadgram_send_json(['ok' => true, 'ticket_id' => $ticketId]);
