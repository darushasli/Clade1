<?php
/**
 * Google Sign-In — verifies a Google Identity Services ID token (JWT) locally.
 *
 * Uses the JWKS endpoint (https://www.googleapis.com/oauth2/v3/certs) and
 * caches it in a 6h transient. Verifies signature, audience, issuer, exp.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Google_Auth {

    const JWKS_URL     = 'https://www.googleapis.com/oauth2/v3/certs';
    const CACHE_KEY    = 'ug_google_jwks';
    const CACHE_TTL    = 6 * HOUR_IN_SECONDS;
    const ALLOWED_ISS  = [ 'accounts.google.com', 'https://accounts.google.com' ];

    private UG_Settings $settings;

    public function __construct( UG_Settings $settings ) {
        $this->settings = $settings;
    }

    /**
     * Verify a Google ID token. Returns the payload claims on success, or null.
     */
    public function verify_id_token( string $id_token ): ?array {
        $client_id = $this->settings->get( 'google_client_id' );
        if ( empty( $client_id ) ) {
            UG_Logger::error( 'Google Client ID missing' );
            return null;
        }

        $parts = explode( '.', $id_token );
        if ( count( $parts ) !== 3 ) {
            return null;
        }
        [ $header_b64, $payload_b64, $sig_b64 ] = $parts;

        $header  = json_decode( self::b64url_decode( $header_b64 ), true );
        $payload = json_decode( self::b64url_decode( $payload_b64 ), true );
        $sig     = self::b64url_decode( $sig_b64 );
        if ( ! is_array( $header ) || ! is_array( $payload ) || false === $sig ) {
            return null;
        }
        if ( ( $header['alg'] ?? '' ) !== 'RS256' ) {
            return null;
        }

        $kid = $header['kid'] ?? '';
        $jwk = $this->get_key_by_kid( $kid );
        if ( ! $jwk ) {
            return null;
        }

        $pubkey = self::jwk_to_pem( $jwk );
        if ( ! $pubkey ) {
            return null;
        }

        $data = $header_b64 . '.' . $payload_b64;
        $ok   = openssl_verify( $data, $sig, $pubkey, OPENSSL_ALGO_SHA256 );
        if ( 1 !== $ok ) {
            return null;
        }

        // Claim checks.
        if ( ! in_array( $payload['iss'] ?? '', self::ALLOWED_ISS, true ) ) {
            return null;
        }
        if ( ( $payload['aud'] ?? '' ) !== $client_id ) {
            return null;
        }
        if ( ( $payload['exp'] ?? 0 ) < time() ) {
            return null;
        }
        if ( empty( $payload['sub'] ) || empty( $payload['email'] ) ) {
            return null;
        }
        // We require email_verified=true — otherwise the account might be
        // impersonated (Google normally returns true here).
        if ( isset( $payload['email_verified'] ) && ! $payload['email_verified'] ) {
            return null;
        }

        return $payload;
    }

    /**
     * Fetch Google's JWKS with caching.
     */
    private function get_key_by_kid( string $kid ): ?array {
        $jwks = get_transient( self::CACHE_KEY );
        if ( ! is_array( $jwks ) ) {
            $jwks = $this->fetch_jwks();
            if ( is_array( $jwks ) ) {
                set_transient( self::CACHE_KEY, $jwks, self::CACHE_TTL );
            }
        }
        if ( ! is_array( $jwks ) ) {
            return null;
        }
        foreach ( $jwks as $key ) {
            if ( ( $key['kid'] ?? '' ) === $kid ) {
                return $key;
            }
        }
        return null;
    }

    private function fetch_jwks(): ?array {
        $r = wp_remote_get( self::JWKS_URL, [ 'timeout' => 10 ] );
        if ( is_wp_error( $r ) ) {
            return null;
        }
        $data = json_decode( wp_remote_retrieve_body( $r ), true );
        return $data['keys'] ?? null;
    }

    /**
     * Convert a JWK (n, e) to a PEM-formatted RSA public key.
     */
    private static function jwk_to_pem( array $jwk ): ?string {
        $n = self::b64url_decode( $jwk['n'] ?? '' );
        $e = self::b64url_decode( $jwk['e'] ?? '' );
        if ( false === $n || false === $e ) {
            return null;
        }

        $modulus  = self::asn1_int( $n );
        $exponent = self::asn1_int( $e );
        $rsa      = self::asn1_seq( $modulus . $exponent );

        // AlgorithmIdentifier: rsaEncryption (1.2.840.113549.1.1.1) NULL
        $alg_id  = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $bitstr  = "\x03" . self::asn1_len( strlen( $rsa ) + 1 ) . "\x00" . $rsa;
        $spki    = self::asn1_seq( $alg_id . $bitstr );
        $pem     = "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $spki ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
        return $pem;
    }

    private static function asn1_int( string $bytes ): string {
        if ( ord( $bytes[0] ) & 0x80 ) {
            $bytes = "\x00" . $bytes;
        }
        return "\x02" . self::asn1_len( strlen( $bytes ) ) . $bytes;
    }
    private static function asn1_seq( string $bytes ): string {
        return "\x30" . self::asn1_len( strlen( $bytes ) ) . $bytes;
    }
    private static function asn1_len( int $n ): string {
        if ( $n < 128 ) {
            return chr( $n );
        }
        $bytes = '';
        while ( $n > 0 ) {
            $bytes = chr( $n & 0xff ) . $bytes;
            $n   >>= 8;
        }
        return chr( 0x80 | strlen( $bytes ) ) . $bytes;
    }

    private static function b64url_decode( string $s ) {
        return base64_decode( strtr( $s, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ) );
    }
}
