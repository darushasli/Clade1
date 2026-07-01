<?php
/**
 * Verifies Firebase Auth ID tokens (RS256 JWTs) server-side without the
 * Firebase Admin SDK or Composer — just openssl + Google's public JWKS
 * certs. This is the only thing standing between "anyone can call the API
 * with any uid" and "only a real signed-in Firebase user can act as
 * themselves", so every privileged endpoint must run its Authorization
 * header through uploadgram_verify_firebase_token() before trusting `sub`.
 */

const UPLOADGRAM_FIREBASE_PROJECT_ID = 'signin-3a5db';
const UPLOADGRAM_FIREBASE_CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';
const UPLOADGRAM_FIREBASE_CERTS_TTL = 3600; // seconds; Google rotates these infrequently

function uploadgram_base64url_decode(string $data): string {
    $padded = strtr($data, '-_', '+/');
    $padLen = strlen($padded) % 4;
    if ($padLen) {
        $padded .= str_repeat('=', 4 - $padLen);
    }
    return base64_decode($padded);
}

function uploadgram_fetch_google_certs(): ?array {
    $cacheFile = sys_get_temp_dir() . '/uploadgram_firebase_certs.json';
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < UPLOADGRAM_FIREBASE_CERTS_TTL) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $ch = curl_init(UPLOADGRAM_FIREBASE_CERTS_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        // Serve a stale cache rather than hard-failing if Google is briefly unreachable.
        if (is_file($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }
        return null;
    }

    $certs = json_decode($response, true);
    if (!is_array($certs)) {
        return null;
    }

    file_put_contents($cacheFile, json_encode($certs));
    return $certs;
}

/**
 * Verifies a Firebase Auth ID token's signature, issuer, audience, and
 * expiry. Returns the decoded payload (with at least `sub`, `email`,
 * `name`) on success, or null if the token is missing, malformed,
 * expired, or fails signature verification.
 */
function uploadgram_verify_firebase_token(?string $idToken): ?array {
    if (!$idToken || substr_count($idToken, '.') !== 2) {
        return null;
    }

    [$headerB64, $payloadB64, $sigB64] = explode('.', $idToken);

    $header = json_decode(uploadgram_base64url_decode($headerB64), true);
    $payload = json_decode(uploadgram_base64url_decode($payloadB64), true);
    $signature = uploadgram_base64url_decode($sigB64);

    if (!is_array($header) || !is_array($payload) || $signature === false || $signature === '') {
        return null;
    }
    if (($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
        return null;
    }

    $certs = uploadgram_fetch_google_certs();
    if (!$certs || empty($certs[$header['kid']])) {
        return null;
    }

    $publicKey = openssl_pkey_get_public($certs[$header['kid']]);
    if (!$publicKey) {
        return null;
    }

    $signingInput = $headerB64 . '.' . $payloadB64;
    $valid = openssl_verify($signingInput, $signature, $publicKey, OPENSSL_ALGO_SHA256);
    if ($valid !== 1) {
        return null;
    }

    $now = time();
    $expectedIss = 'https://securetoken.google.com/' . UPLOADGRAM_FIREBASE_PROJECT_ID;

    if (($payload['aud'] ?? null) !== UPLOADGRAM_FIREBASE_PROJECT_ID) {
        return null;
    }
    if (($payload['iss'] ?? null) !== $expectedIss) {
        return null;
    }
    if (empty($payload['sub'])) {
        return null;
    }
    if (!isset($payload['exp']) || $now >= (int) $payload['exp']) {
        return null;
    }
    if (!isset($payload['iat']) || (int) $payload['iat'] > $now + 60) {
        return null;
    }

    return $payload;
}

/**
 * Reads the Bearer token from the Authorization header, verifies it, and
 * returns the decoded payload — or sends a 401 JSON error and exits.
 */
function uploadgram_require_auth(): array {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'missing_token'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $payload = uploadgram_verify_firebase_token($m[1]);
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'invalid_token'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return $payload;
}
