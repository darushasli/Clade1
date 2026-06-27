<?php
/**
 * Shared upstream-catalog helpers (fetch / normalize / cache / markup).
 * Used by services.php (public catalog listing) and orders.php (server-side
 * price + min/max validation when a user places an order).
 */

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

/**
 * Classifies a service into one of the platforms UploadGram markets
 * (Instagram / Telegram / YouTube / SoundCloud / Spotify) by keyword
 * matching against its name + category, so the frontend can group the
 * live catalog without needing to trust the upstream's own taxonomy.
 */
function uploadgram_detect_platform(string $name, string $category): string {
    $haystack = mb_strtolower($name . ' ' . $category);
    $map = [
        'instagram'  => ['instagram', 'insta', 'اینستاگرام', 'اینستا'],
        'telegram'   => ['telegram', 'تلگرام'],
        'youtube'    => ['youtube', 'یوتیوب', 'یوتوب'],
        'soundcloud' => ['soundcloud', 'sound cloud', 'ساندکلاد', 'ساند کلاد'],
        'spotify'    => ['spotify', 'اسپاتیفای', 'اسپاتیفاي'],
    ];
    foreach ($map as $platform => $needles) {
        foreach ($needles as $needle) {
            if (mb_strpos($haystack, $needle) !== false) {
                return $platform;
            }
        }
    }
    return 'other';
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

        $cleanName = uploadgram_sanitize_text($name);
        $cleanCategory = uploadgram_sanitize_text($category ?? '');

        $services[] = [
            'id' => (string) $id,
            'name' => $cleanName,
            'category' => $cleanCategory,
            'platform' => uploadgram_detect_platform($cleanName, $cleanCategory),
            'rate' => is_numeric($rate) ? (float) $rate : null,
            'min' => is_numeric($min) ? (int) $min : null,
            'max' => is_numeric($max) ? (int) $max : null,
        ];
    }

    return $services;
}

function uploadgram_fetch_from_upstream(string $url, string $apiKey, array $extraFields = []): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => array_merge([
            'key' => $apiKey,
            'action' => 'services',
        ], $extraFields),
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

    return $decoded;
}

/**
 * Posts an arbitrary action (add / status / balance / ...) to the upstream
 * reseller API and returns the raw decoded JSON, trying each configured
 * endpoint until one responds. Returns null if all endpoints fail.
 */
function uploadgram_upstream_call(string $apiKey, array $fields): ?array {
    foreach (uploadgram_upstream_endpoints() as $endpoint) {
        $decoded = uploadgram_fetch_from_upstream($endpoint, $apiKey, $fields);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return null;
}

/**
 * Returns the full, markup-applied, cached catalog as
 * { ok, services?, error? } — the exact contract services.php responds with.
 */
function uploadgram_get_services(): array {
    if (is_file(UPLOADGRAM_CACHE_FILE) && (time() - filemtime(UPLOADGRAM_CACHE_FILE)) < UPLOADGRAM_CACHE_TTL) {
        $cached = file_get_contents(UPLOADGRAM_CACHE_FILE);
        $cachedDecoded = json_decode($cached, true);
        if (is_array($cachedDecoded)) {
            return $cachedDecoded;
        }
    }

    $apiKey = uploadgram_resolve_api_key();
    if (!$apiKey) {
        return ['ok' => false, 'error' => 'service_unavailable'];
    }

    $services = null;
    foreach (uploadgram_upstream_endpoints() as $endpoint) {
        $decoded = uploadgram_fetch_from_upstream($endpoint, $apiKey);
        $normalized = is_array($decoded) ? uploadgram_normalize_services($decoded) : null;
        if (is_array($normalized) && count($normalized) > 0) {
            $services = $normalized;
            break;
        }
    }

    if (!is_array($services) || count($services) === 0) {
        return ['ok' => false, 'error' => 'catalog_unavailable'];
    }

    $services = array_slice($services, 0, 200);
    foreach ($services as &$service) {
        if ($service['rate'] !== null) {
            $service['rate'] = round($service['rate'] * UPLOADGRAM_MARKUP, 4);
        }
    }
    unset($service);

    $payload = ['ok' => true, 'services' => $services];
    file_put_contents(UPLOADGRAM_CACHE_FILE, json_encode($payload, JSON_UNESCAPED_UNICODE));

    return $payload;
}

/**
 * Places the order with the upstream reseller panel once it's been paid for
 * on our side, and records the upstream's order id/status. Upstream failure
 * here does not undo the payment — the order stays "paid" with
 * upstream_status = 'upstream_error' so an admin can retry it manually.
 */
function uploadgram_place_upstream_order(PDO $db, int $orderId, array $service, string $link, int $quantity): void {
    $apiKey = uploadgram_resolve_api_key();
    $result = $apiKey ? uploadgram_upstream_call($apiKey, [
        'action' => 'add',
        'service' => $service['id'],
        'link' => $link,
        'quantity' => $quantity,
    ]) : null;

    $upstreamOrderId = $result['order'] ?? null;
    if ($upstreamOrderId !== null) {
        $db->prepare("UPDATE orders SET upstream_order_id = ?, upstream_status = 'pending', status = 'processing' WHERE id = ?")
            ->execute([(string) $upstreamOrderId, $orderId]);
    } else {
        $db->prepare("UPDATE orders SET upstream_status = 'upstream_error' WHERE id = ?")
            ->execute([$orderId]);
    }
}

/** Finds one service (with markup already applied) by its upstream id. */
function uploadgram_find_service(string $id): ?array {
    $catalog = uploadgram_get_services();
    if (empty($catalog['ok']) || empty($catalog['services'])) {
        return null;
    }
    foreach ($catalog['services'] as $service) {
        if ($service['id'] === $id) {
            return $service;
        }
    }
    return null;
}
