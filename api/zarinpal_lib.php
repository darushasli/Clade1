<?php
/**
 * ZarinPal payment gateway (REST, v4 JSON API): request.json -> StartPay
 * redirect -> verify.json on callback. Built strictly from ZarinPal's
 * published API contract.
 *
 * This sandbox's network egress policy blocks zarinpal.com / api.zarinpal.com /
 * sandbox.zarinpal.com / payment.zarinpal.com, so none of this has been
 * exercised against a live ZarinPal endpoint — verify against your merchant
 * dashboard's docs before go-live.
 *
 * All money amounts elsewhere in this app (orders.total_price,
 * wallet_transactions.amount, prices shown in the UI) are Iranian Toman,
 * matching the existing site-wide convention (see products.html). ZarinPal's
 * "amount" field is Rial as of their current v4 docs, so every function here
 * takes a Toman amount and converts (x10) right before calling ZarinPal.
 */
const UPLOADGRAM_RIAL_PER_TOMAN = 10;

require_once __DIR__ . '/config.php';

function uploadgram_zarinpal_base_url(): string {
    return uploadgram_zarinpal_is_sandbox()
        ? 'https://sandbox.zarinpal.com'
        : 'https://api.zarinpal.com';
}

function uploadgram_zarinpal_startpay_url(string $authority): string {
    $base = uploadgram_zarinpal_is_sandbox()
        ? 'https://sandbox.zarinpal.com/pg/StartPay/'
        : 'https://payment.zarinpal.com/pg/StartPay/';
    return $base . $authority;
}

function uploadgram_zarinpal_post(string $path, array $body): ?array {
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);

    $ch = curl_init(uploadgram_zarinpal_base_url() . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Content-Length: ' . strlen($payload),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $curlError) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Starts a payment for $amountToman (positive int, Iranian Toman).
 * Returns ['authority' => string, 'pay_url' => string] on success, null on failure.
 */
function uploadgram_zarinpal_request(int $amountToman, string $description, string $callbackUrl, array $metadata = []): ?array {
    $body = [
        'merchant_id' => uploadgram_zarinpal_merchant_id(),
        'amount' => $amountToman * UPLOADGRAM_RIAL_PER_TOMAN,
        'description' => $description,
        'callback_url' => $callbackUrl,
    ];
    if ($metadata) {
        $body['metadata'] = $metadata;
    }

    $resp = uploadgram_zarinpal_post('/pg/v4/payment/request.json', $body);
    $data = $resp['data'] ?? null;
    if (!is_array($data) || empty($data['authority']) || (int) ($data['code'] ?? 0) !== 100) {
        return null;
    }

    return [
        'authority' => (string) $data['authority'],
        'pay_url' => uploadgram_zarinpal_startpay_url($data['authority']),
    ];
}

/**
 * Verifies a completed payment ($amountToman must match the original request).
 * Returns ['ref_id' => string, 'card_pan' => ?string] on success (code 100 =
 * freshly verified, 101 = already verified before), null otherwise.
 */
function uploadgram_zarinpal_verify(int $amountToman, string $authority): ?array {
    $body = [
        'merchant_id' => uploadgram_zarinpal_merchant_id(),
        'amount' => $amountToman * UPLOADGRAM_RIAL_PER_TOMAN,
        'authority' => $authority,
    ];

    $resp = uploadgram_zarinpal_post('/pg/v4/payment/verify.json', $body);
    $data = $resp['data'] ?? null;
    $code = (int) ($data['code'] ?? 0);
    if (!is_array($data) || ($code !== 100 && $code !== 101)) {
        return null;
    }

    return [
        'ref_id' => (string) ($data['ref_id'] ?? ''),
        'card_pan' => $data['card_pan'] ?? null,
    ];
}
