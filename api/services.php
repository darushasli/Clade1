<?php
/**
 * Product catalog proxy.
 *
 * Calls the upstream reseller panel's "services" action server-side (so the
 * API key never reaches the browser), normalizes whatever shape it returns
 * into a flat list, strips any upstream brand mentions, and caches the
 * result briefly to avoid hammering the upstream API on every page load.
 *
 * Always responds 200 with { ok: bool, services?: [...], error?: string }.
 * The frontend falls back to static demo content whenever ok is false.
 */

require __DIR__ . '/config.php';
require __DIR__ . '/services_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode(uploadgram_get_services(), JSON_UNESCAPED_UNICODE);
