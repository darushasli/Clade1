<?php
/**
 * Server-side config for the product-catalog proxy.
 * The upstream API key never reaches the browser — it is only used here, server-side.
 */

function uploadgram_resolve_api_key(): ?string {
    $envKey = getenv('FOLLOWERAN_API_KEY');
    if ($envKey) {
        return $envKey;
    }

    $localConfigPath = __DIR__ . '/config.local.php';
    if (is_file($localConfigPath)) {
        $local = require $localConfigPath;
        if (is_array($local) && !empty($local['api_key'])) {
            return $local['api_key'];
        }
    }

    return null;
}

/**
 * Upstream candidates tried in order; the first one that returns a usable
 * service list wins. Both hosts use the same key/action POST contract.
 */
function uploadgram_upstream_endpoints(): array {
    return [
        'https://my.followeran.ir/api/v2',
        'https://panel.smmflw.com/api/iran',
    ];
}

define('UPLOADGRAM_CACHE_TTL', 300); // seconds
define('UPLOADGRAM_CACHE_FILE', sys_get_temp_dir() . '/uploadgram_services_cache.json');
define('UPLOADGRAM_MARKUP', 1.5); // resale markup multiplier — site prices are 50% above upstream
