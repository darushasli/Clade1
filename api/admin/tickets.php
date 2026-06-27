<?php
/**
 * Ticket endpoints across all users (not uid-scoped, unlike api/tickets.php).
 * GET ?id=N   -> one ticket + its messages + the owning user's email/display_name.
 * GET (no id) -> list of every ticket, optional ?status= filter, newest first,
 *                joined with the owning user's email/display_name.
 * POST { ticket_id, message }          -> admin reply, re-opens a closed ticket.
 * POST { ticket_id, status }           -> change status ('open'|'closed'), no message required.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

uploadgram_require_admin();
$db = uploadgram_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);

    if ($id > 0) {
        $stmt = $db->prepare('SELECT t.*, u.email, u.display_name, u.username FROM tickets t
            LEFT JOIN users u ON u.uid = t.uid WHERE t.id = ?');
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            uploadgram_admin_send_json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $stmt = $db->prepare('SELECT id, sender, message, created_at FROM ticket_messages WHERE ticket_id = ? ORDER BY id ASC');
        $stmt->execute([$id]);
        uploadgram_admin_send_json(['ok' => true, 'ticket' => $ticket, 'messages' => $stmt->fetchAll()]);
    }

    $status = trim((string) ($_GET['status'] ?? ''));
    $sql = 'SELECT t.id, t.uid, t.subject, t.status, t.created_at, t.updated_at, u.email, u.display_name, u.username
        FROM tickets t LEFT JOIN users u ON u.uid = t.uid WHERE 1=1';
    $params = [];
    if ($status !== '') {
        $sql .= ' AND t.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY t.id DESC LIMIT 300';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    uploadgram_admin_send_json(['ok' => true, 'tickets' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'invalid_body'], 400);
}

$ticketId = (int) ($input['ticket_id'] ?? 0);
if ($ticketId <= 0) {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'missing_ticket_id'], 400);
}

$stmt = $db->prepare('SELECT * FROM tickets WHERE id = ?');
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();
if (!$ticket) {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'not_found'], 404);
}

$message = trim((string) ($input['message'] ?? ''));
if ($message !== '') {
    $db->prepare("INSERT INTO ticket_messages (ticket_id, sender, message) VALUES (?, 'admin', ?)")->execute([$ticketId, $message]);
    $db->prepare("UPDATE tickets SET status = 'open' WHERE id = ?")->execute([$ticketId]);
}

if (isset($input['status'])) {
    $status = $input['status'] === 'closed' ? 'closed' : 'open';
    $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$status, $ticketId]);
}

$stmt = $db->prepare('SELECT * FROM tickets WHERE id = ?');
$stmt->execute([$ticketId]);
uploadgram_admin_send_json(['ok' => true, 'ticket' => $stmt->fetch()]);
