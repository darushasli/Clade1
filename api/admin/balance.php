<?php
/**
 * GET -> current account balance on the single shared upstream reseller key
 * (api/config.php's UPLOADGRAM_API_KEY / config.local.php), via the
 * upstream's { action: balance } contract. The exact response shape isn't
 * guaranteed across upstream providers, so the raw decoded response is
 * always returned alongside best-effort parsed numeric fields — the admin
 * UI should render whichever is present rather than assume one shape.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin_lib.php';
require_once __DIR__ . '/../services_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

uploadgram_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$result = uploadgram_check_upstream_balance();
if ($result === null) {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'upstream_unavailable'], 502);
}

$balance = null;
foreach (['balance', 'amount', 'credit'] as $key) {
    if (isset($result[$key]) && is_numeric($result[$key])) {
        $balance = (float) $result[$key];
        break;
    }
}

$currency = $result['currency'] ?? null;

uploadgram_admin_send_json([
    'ok' => true,
    'balance' => $balance,
    'currency' => $currency,
    'raw' => $result,
]);
