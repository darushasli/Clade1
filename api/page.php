<?php
/**
 * GET ?slug= -> public lookup of one published custom page, for page.html
 * to render. Unpublished pages and unknown slugs both 404 — never leaks
 * draft content.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_slug'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = uploadgram_db();
$stmt = $db->prepare('SELECT id, slug, title, body, updated_at FROM pages WHERE slug = ? AND is_published = 1');
$stmt->execute([$slug]);
$page = $stmt->fetch();

if (!$page) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'page_not_found'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'page' => $page], JSON_UNESCAPED_UNICODE);
