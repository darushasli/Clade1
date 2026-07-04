<?php
/**
 * Kavenegar SMS client — uses the "verify/lookup" (OTP template) endpoint.
 *
 * Docs: https://kavenegar.com/rest.html#verify-lookup
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Sms_Kavenegar {

    private UG_Settings $settings;

    public function __construct( UG_Settings $settings ) {
        $this->settings = $settings;
    }

    /**
     * Send a verification code via a pre-approved Kavenegar template.
     *
     * @return array {ok:bool, error?:string, data?:mixed}
     */
    public function send_verify( string $phone, string $code ): array {
        $api_key  = $this->settings->get( 'kavenegar_api_key' );
        $template = $this->settings->get( 'kavenegar_template' );

        if ( empty( $api_key ) || empty( $template ) ) {
            return [ 'ok' => false, 'error' => 'کلید یا الگوی کاوه‌نگار تنظیم نشده.' ];
        }

        $url = sprintf(
            'https://api.kavenegar.com/v1/%s/verify/lookup.json',
            rawurlencode( $api_key )
        );

        $response = wp_remote_post( $url, [
            'timeout' => 20,
            'body'    => [
                'receptor' => $phone,
                'token'    => $code,
                'template' => $template,
                'type'     => 'sms',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'ok' => false, 'error' => $response->get_error_message() ];
        }

        $code_http = (int) wp_remote_retrieve_response_code( $response );
        $body      = wp_remote_retrieve_body( $response );
        $data      = json_decode( $body, true );

        $status = $data['return']['status'] ?? 0;
        if ( 200 !== $code_http || 200 !== (int) $status ) {
            $msg = $data['return']['message'] ?? ( 'HTTP ' . $code_http );
            return [ 'ok' => false, 'error' => $msg, 'data' => $data ];
        }

        return [ 'ok' => true, 'data' => $data['entries'] ?? [] ];
    }

    /**
     * Admin test: send a code to an arbitrary phone. Returns {ok, error?}.
     */
    public function test( string $phone ): array {
        $code = str_pad( (string) random_int( 100000, 999999 ), 6, '0', STR_PAD_LEFT );
        return $this->send_verify( $phone, $code );
    }
}
