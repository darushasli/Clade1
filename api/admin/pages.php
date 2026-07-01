<?php
/**
 * GET (list)      -> all pages, newest first.
 * GET ?id=        -> one page's full row (including body), for editing.
 * POST            -> create a page { title, slug?, body?, is_published? }.
 *                    slug is auto-generated from title when omitted.
 * PUT             -> update a page { id, title?, slug?, body?, is_published? }.
 * DELETE          -> remove a page { id }.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

uploadgram_require_admin();
$db = uploadgram_db();

function uploadgram_slugify(string $title): string {
    $slug = trim($title);
    $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug);
    $slug = trim($slug, '-');
    $slug = mb_strtolower($slug);
    return $slug !== '' ? $slug : 'page-' . substr(md5((string) microtime(true)), 0, 6);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $db->prepare('SELECT * FROM pages WHERE id = ?');
        $stmt->execute([$id]);
        $page = $stmt->fetch();
        if (!$page) {
            uploadgram_admin_send_json(['ok' => false, 'error' => 'page_not_found'], 404);
        }
        uploadgram_admin_send_json(['ok' => true, 'page' => $page]);
    }

    $rows = $db->query('SELECT id, slug, title, is_published, created_at, updated_at FROM pages ORDER BY id DESC')->fetchAll();
    uploadgram_admin_send_json(['ok' => true, 'pages' => $rows]);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        uploadgram_admin_send_json(['ok' => false, 'error' => 'missing_title'], 400);
    }
    $slugInput = trim((string) ($input['slug'] ?? ''));
    $slug = $slugInput !== '' ? trim(mb_strtolower(preg_replace('/[^a-z0-9\-\p{L}\p{N}]+/u', '-', $slugInput)), '-') : '';
    if ($slug === '') {
        $slug = uploadgram_slugify($title);
    }
    $body = (string) ($input['body'] ?? '');
    $isPublished = empty($input['is_published']) ? 0 : 1;

    $stmt = $db->prepare('SELECT id FROM pages WHERE slug = ?');
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        uploadgram_admin_send_json(['ok' => false, 'error' => 'slug_taken'], 409);
    }

    $insert = $db->prepare('INSERT INTO pages (slug, title, body, is_published) VALUES (?, ?, ?, ?)');
    $insert->execute([$slug, $title, $body, $isPublished]);
    $id = (int) $db->lastInsertId();

    $stmt = $db->prepare('SELECT * FROM pages WHERE id = ?');
    $stmt->execute([$id]);
    uploadgram_admin_send_json(['ok' => true, 'page' => $stmt->fetch()], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) {
        uploadgram_admin_send_json(['ok' => false, 'error' => 'missing_id'], 400);
    }
    $stmt = $db->prepare('SELECT * FROM pages WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        uploadgram_admin_send_json(['ok' => false, 'error' => 'page_not_found'], 404);
    }

    $title = trim((string) ($input['title'] ?? $existing['title']));
    $slugInput = trim((string) ($input['slug'] ?? ''));
    $slug = $slugInput !== '' ? trim(mb_strtolower(preg_replace('/[^a-z0-9\-\p{L}\p{N}]+/u', '-', $slugInput)), '-') : '';
    if ($slug === '') {
        $slug = $existing['slug'];
    }
    $body = array_key_exists('body', $input) ? (string) $input['body'] : $existing['body'];
    $isPublished = isset($input['is_published']) ? (empty($input['is_published']) ? 0 : 1) : $existing['is_published'];

    if ($slug !== $existing['slug']) {
        $dupe = $db->prepare('SELECT id FROM pages WHERE slug = ? AND id <> ?');
        $dupe->execute([$slug, $id]);
        if ($dupe->fetch()) {
            uploadgram_admin_send_json(['ok' => false, 'error' => 'slug_taken'], 409);
        }
    }

    $db->prepare('UPDATE pages SET slug = ?, title = ?, body = ?, is_published = ? WHERE id = ?')
        ->execute([$slug, $title, $body, $isPublished, $id]);

    $stmt = $db->prepare('SELECT * FROM pages WHERE id = ?');
    $stmt->execute([$id]);
    uploadgram_admin_send_json(['ok' => true, 'page' => $stmt->fetch()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        uploadgram_admin_send_json(['ok' => false, 'error' => 'missing_id'], 400);
    }
    $db->prepare('DELETE FROM pages WHERE id = ?')->execute([$id]);
    uploadgram_admin_send_json(['ok' => true, 'id' => $id]);
}

uploadgram_admin_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
