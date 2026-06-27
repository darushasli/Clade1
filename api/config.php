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

/** Reads a key out of config.local.php (gitignored) without re-parsing it twice per request. */
function uploadgram_local_config(): array {
    static $local = null;
    if ($local !== null) {
        return $local;
    }
    $path = __DIR__ . '/config.local.php';
    if (is_file($path)) {
        $value = require $path;
        $local = is_array($value) ? $value : [];
    } else {
        $local = [];
    }
    return $local;
}

/**
 * ZarinPal merchant ID. Falls back to ZarinPal's public sandbox merchant ID
 * (documented in their own API examples — not a secret) so the payment flow
 * is testable end-to-end before a real merchant ID is supplied.
 */
function uploadgram_zarinpal_merchant_id(): string {
    $env = getenv('ZARINPAL_MERCHANT_ID');
    if ($env) {
        return $env;
    }
    $local = uploadgram_local_config();
    if (!empty($local['zarinpal_merchant_id'])) {
        return $local['zarinpal_merchant_id'];
    }
    return '00000000-0000-0000-0000-000000000000'; // ZarinPal sandbox merchant id
}

/** True unless a real merchant ID has been configured (env or config.local.php). */
function uploadgram_zarinpal_is_sandbox(): bool {
    $env = getenv('ZARINPAL_SANDBOX');
    if ($env !== false) {
        return $env === '1' || strtolower($env) === 'true';
    }
    $env = getenv('ZARINPAL_MERCHANT_ID');
    if ($env) {
        return false;
    }
    $local = uploadgram_local_config();
    return empty($local['zarinpal_merchant_id']);
}

/** Absolute site base URL (no trailing slash), used to build ZarinPal callback URLs. */
function uploadgram_base_url(): string {
    $env = getenv('UPLOADGRAM_BASE_URL');
    if ($env) {
        return rtrim($env, '/');
    }
    $local = uploadgram_local_config();
    if (!empty($local['base_url'])) {
        return rtrim($local['base_url'], '/');
    }
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

/**
 * MySQL connection settings — env vars first, then config.local.php, then
 * local-dev defaults. A real deployment should set these via env vars or
 * config.local.php (same gitignored file the upstream API key lives in).
 */
function uploadgram_mysql_config(): array {
    $local = uploadgram_local_config();
    return [
        'host' => getenv('UPLOADGRAM_DB_HOST') ?: ($local['db_host'] ?? '127.0.0.1'),
        'port' => getenv('UPLOADGRAM_DB_PORT') ?: ($local['db_port'] ?? '3306'),
        'name' => getenv('UPLOADGRAM_DB_NAME') ?: ($local['db_name'] ?? 'uploadgram'),
        'user' => getenv('UPLOADGRAM_DB_USER') ?: ($local['db_user'] ?? 'root'),
        'pass' => getenv('UPLOADGRAM_DB_PASS') ?: ($local['db_pass'] ?? ''),
    ];
}
