<?php
/**
 * Copy this file to "config.local.php" (same directory) and fill in your real key.
 * config.local.php is gitignored — it never gets committed.
 *
 * Alternative: set the FOLLOWERAN_API_KEY environment variable on your server
 * instead of using this file (see config.php).
 */
return [
    'api_key' => 'YOUR_API_KEY_HERE',

    // ZarinPal merchant ID. Leave unset to run against ZarinPal's sandbox.
    'zarinpal_merchant_id' => '',

    // Absolute site URL (no trailing slash), used to build ZarinPal callback
    // URLs. Leave unset to auto-detect from the incoming request.
    'base_url' => '',

    // MySQL connection (orders / wallet / tickets / admin panel storage).
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => 'uploadgram',
    'db_user' => 'root',
    'db_pass' => '',
];
