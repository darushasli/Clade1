<?php
/**
 * POST -> refreshes the live upstream catalog (bypassing the file cache)
 * and imports any service not already represented in the local `products`
 * table as a new row with source='upstream' and upstream_service_id set, so
 * the admin Products panel can show/edit literally everything that the
 * storefront's public catalog can show, not just hand-entered local rows.
 * Already-imported services (matched by upstream_service_id) are skipped.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin_lib.php';
require_once __DIR__ . '/../services_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

uploadgram_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$upstream = uploadgram_get_upstream_services(true);
if (!$upstream['ok']) {
    uploadgram_admin_send_json(['ok' => false, 'error' => $upstream['error'] ?? 'catalog_unavailable'], 502);
}

$db = uploadgram_db();
$existing = uploadgram_imported_upstream_ids();

$insert = $db->prepare('INSERT INTO products
    (name, category, platform, source, upstream_service_id, base_rate, min_qty, max_qty, is_active, sort_order)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 0)');

$added = 0;
$skipped = 0;
foreach ($upstream['services'] as $service) {
    $id = (string) $service['id'];
    if (isset($existing[$id])) {
        $skipped++;
        continue;
    }
    $insert->execute([
        $service['name'],
        $service['category'],
        $service['platform'],
        'upstream',
        $id,
        $service['rate'],
        $service['min'],
        $service['max'],
    ]);
    $added++;
}

uploadgram_admin_send_json([
    'ok' => true,
    'added' => $added,
    'skipped' => $skipped,
    'total_upstream' => count($upstream['services']),
]);
