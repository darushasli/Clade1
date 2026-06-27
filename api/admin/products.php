<?php
/**
 * Full CRUD over the `products` table — admin-managed catalog items, each
 * with its own optional api_url/api_key/api_action/upstream_service_id so a
 * product can be wired to any external reseller API independently of the
 * single shared upstream key in config.php (see services_lib.php).
 *
 * GET    -> list all products (including inactive ones — unlike the public
 *           catalog in services_lib.php, which only shows is_active=1).
 * POST   -> create a new product.
 * PUT    -> update an existing product ({ id, ...fields }).
 * DELETE -> remove a product ({ id } via query string ?id= or JSON body).
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

uploadgram_require_admin();
$db = uploadgram_db();

const PLATFORMS = ['telegram', 'instagram', 'youtube', 'soundcloud', 'spotify', 'other'];

function uploadgram_products_read_fields(array $input): array {
    $platform = (string) ($input['platform'] ?? 'other');
    if (!in_array($platform, PLATFORMS, true)) {
        $platform = 'other';
    }
    $source = ($input['source'] ?? 'local') === 'upstream' ? 'upstream' : 'local';

    return [
        'name' => trim((string) ($input['name'] ?? '')),
        'category' => trim((string) ($input['category'] ?? '')),
        'platform' => $platform,
        'source' => $source,
        'upstream_service_id' => trim((string) ($input['upstream_service_id'] ?? '')) ?: null,
        'api_url' => trim((string) ($input['api_url'] ?? '')) ?: null,
        'api_key' => trim((string) ($input['api_key'] ?? '')) ?: null,
        'api_action' => trim((string) ($input['api_action'] ?? '')) ?: null,
        'base_rate' => isset($input['base_rate']) && $input['base_rate'] !== '' ? (float) $input['base_rate'] : null,
        'min_qty' => isset($input['min_qty']) && $input['min_qty'] !== '' ? (int) $input['min_qty'] : null,
        'max_qty' => isset($input['max_qty']) && $input['max_qty'] !== '' ? (int) $input['max_qty'] : null,
        'is_active' => empty($input['is_active']) ? 0 : 1,
        'sort_order' => (int) ($input['sort_order'] ?? 0),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $db->query('SELECT * FROM products ORDER BY sort_order ASC, id ASC')->fetchAll();
    uploadgram_admin_send_json(['ok' => true, 'products' => $rows]);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = uploadgram_products_read_fields($input);
    if ($f['name'] === '') {
        uploadgram_admin_send_json(['ok' => false, 'error' => 'missing_name'], 400);
    }

    $stmt = $db->prepare('INSERT INTO products
        (name, category, platform, source, upstream_service_id, api_url, api_key, api_action, base_rate, min_qty, max_qty, is_active, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $f['name'], $f['category'], $f['platform'], $f['source'], $f['upstream_service_id'],
        $f['api_url'], $f['api_key'], $f['api_action'], $f['base_rate'], $f['min_qty'], $f['max_qty'],
        $f['is_active'], $f['sort_order'],
    ]);
    $id = (int) $db->lastInsertId();

    $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    uploadgram_admin_send_json(['ok' => true, 'product' => $stmt->fetch()], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) {
        uploadgram_admin_send_json(['ok' => false, 'error' => 'missing_id'], 400);
    }
    $stmt = $db->prepare('SELECT id FROM products WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        uploadgram_admin_send_json(['ok' => false, 'error' => 'product_not_found'], 404);
    }

    $f = uploadgram_products_read_fields($input);
    if ($f['name'] === '') {
        uploadgram_admin_send_json(['ok' => false, 'error' => 'missing_name'], 400);
    }

    $stmt = $db->prepare('UPDATE products SET
        name = ?, category = ?, platform = ?, source = ?, upstream_service_id = ?,
        api_url = ?, api_key = ?, api_action = ?, base_rate = ?, min_qty = ?, max_qty = ?,
        is_active = ?, sort_order = ?
        WHERE id = ?');
    $stmt->execute([
        $f['name'], $f['category'], $f['platform'], $f['source'], $f['upstream_service_id'],
        $f['api_url'], $f['api_key'], $f['api_action'], $f['base_rate'], $f['min_qty'], $f['max_qty'],
        $f['is_active'], $f['sort_order'], $id,
    ]);

    $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    uploadgram_admin_send_json(['ok' => true, 'product' => $stmt->fetch()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        uploadgram_admin_send_json(['ok' => false, 'error' => 'missing_id'], 400);
    }
    $db->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
    uploadgram_admin_send_json(['ok' => true, 'id' => $id]);
}

uploadgram_admin_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
