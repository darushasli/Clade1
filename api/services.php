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

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function uploadgram_sanitize_text($value): string {
    $text = (string) $value;
    $text = preg_replace('/followeran(\.ir)?/i', '', $text);
    $text = preg_replace('/smmflw(\.com)?/i', '', $text);
    $text = preg_replace('/فالوور\s*ایران/u', '', $text);
    return trim(preg_replace('/\s{2,}/', ' ', $text));
}

function uploadgram_first(array $row, array $keys) {
    foreach ($keys as $key) {
        if (isset($row[$key]) && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return null;
}

function uploadgram_normalize_services($decoded): ?array {
    $rows = null;

    if (is_array($decoded)) {
        if (array_is_list($decoded)) {
            $rows = $decoded;
        } else {
            foreach (['data', 'services', 'result', 'list'] as $key) {
                if (isset($decoded[$key]) && is_array($decoded[$key])) {
                    $rows = $decoded[$key];
                    break;
                }
            }
        }
    }

    if (!is_array($rows)) {
        return null;
    }

    $services = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = uploadgram_first($row, ['service', 'id', 'ID']);
        $name = uploadgram_first($row, ['name', 'title']);
        $category = uploadgram_first($row, ['category', 'type', 'group']);
        $rate = uploadgram_first($row, ['rate', 'price', 'cost']);
        $min = uploadgram_first($row, ['min', 'minimum']);
        $max = uploadgram_first($row, ['max', 'maximum']);

        if ($id === null || $name === null) {
            continue;
        }

        $services[] = [
            'id' => (string) $id,
            'name' => uploadgram_sanitize_text($name),
            'category' => uploadgram_sanitize_text($category ?? ''),
            'rate' => is_numeric($rate) ? (float) $rate : null,
            'min' => is_numeric($min) ? (int) $min : null,
            'max' => is_numeric($max) ? (int) $max : null,
        ];
    }

    return $services;
}

function uploadgram_fetch_from_upstream(string $url, string $apiKey): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'key' => $apiKey,
            'action' => 'services',
        ],
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError) {
        return null;
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        return null;
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return uploadgram_normalize_services($decoded);
}

function uploadgram_respond(array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1) Serve from cache when fresh.
if (is_file(UPLOADGRAM_CACHE_FILE) && (time() - filemtime(UPLOADGRAM_CACHE_FILE)) < UPLOADGRAM_CACHE_TTL) {
    $cached = file_get_contents(UPLOADGRAM_CACHE_FILE);
    $cachedDecoded = json_decode($cached, true);
    if (is_array($cachedDecoded)) {
        uploadgram_respond($cachedDecoded);
    }
}

// 2) Resolve API key.
$apiKey = uploadgram_resolve_api_key();
if (!$apiKey) {
    uploadgram_respond(['ok' => false, 'error' => 'service_unavailable']);
}

// 3) Try each upstream endpoint until one returns a usable list.
$services = null;
foreach (uploadgram_upstream_endpoints() as $endpoint) {
    $services = uploadgram_fetch_from_upstream($endpoint, $apiKey);
    if (is_array($services) && count($services) > 0) {
        break;
    }
}

if (!is_array($services) || count($services) === 0) {
    uploadgram_respond(['ok' => false, 'error' => 'catalog_unavailable']);
}

// Cap payload size and apply resale markup to the rate shown to the frontend.
$services = array_slice($services, 0, 200);
foreach ($services as &$service) {
    if ($service['rate'] !== null) {
        $service['rate'] = round($service['rate'] * UPLOADGRAM_MARKUP, 4);
    }
}
unset($service);

$payload = ['ok' => true, 'services' => $services];

file_put_contents(UPLOADGRAM_CACHE_FILE, json_encode($payload, JSON_UNESCAPED_UNICODE));

uploadgram_respond($payload);
