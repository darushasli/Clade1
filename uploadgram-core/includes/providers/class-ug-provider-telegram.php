<?php
/**
 * Telegram members — your own bot.
 *
 * Contract we define (the site POSTs JSON to the bot's endpoint):
 *   POST {endpoint}
 *   Header: Authorization: Bearer {token}
 *   Body:   { "action":"add_members", "order_id":.., "target":"@channel", "quantity":500 }
 *   → { "ok":true, "order_id":"BOT-123", "status":"processing" }
 *
 *   Status: POST { "action":"status", "order_id":"BOT-123" }
 *           → { "ok":true, "status":"completed", "delivered":500 }
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Provider_Telegram implements UG_Provider_Interface {

    private string $endpoint;
    private string $token;

    public function __construct( array $config ) {
        $this->endpoint = $config['endpoint'] ?? '';
        $this->token    = $config['api_key'] ?? '';
    }

    public function name(): string {
        return 'telegram';
    }

    public function test(): array {
        $res = $this->request( [ 'action' => 'ping' ] );
        return $res['ok']
            ? [ 'ok' => true, 'data' => $res['data'], 'error' => '' ]
            : [ 'ok' => false, 'data' => $res['data'], 'error' => $res['error'] ];
    }

    public function create_order( array $args ): array {
        $res = $this->request( [
            'action'   => 'add_members',
            'service'  => $args['service_id'] ?? '',
            'target'   => $args['target'] ?? '',
            'quantity' => (int) ( $args['quantity'] ?? 0 ),
            'meta'     => $args['extra'] ?? [],
        ] );

        if ( ! $res['ok'] ) {
            return [ 'ok' => false, 'provider_order_id' => '', 'status' => 'failed', 'data' => $res['data'], 'error' => $res['error'] ];
        }

        $data = $res['data'];
        $oid  = $data['order_id'] ?? '';

        return [
            'ok'                => ! empty( $oid ),
            'provider_order_id' => (string) $oid,
            'status'            => $data['status'] ?? 'processing',
            'data'              => (array) $data,
            'error'             => empty( $oid ) ? 'پاسخ نامعتبر از ربات' : '',
        ];
    }

    public function order_status( string $provider_order_id, array $context = [] ): array {
        $res = $this->request( [ 'action' => 'status', 'order_id' => $provider_order_id ] );
        if ( ! $res['ok'] ) {
            return [ 'ok' => false, 'status' => '', 'data' => $res['data'], 'error' => $res['error'] ];
        }
        return [
            'ok'     => true,
            'status' => $res['data']['status'] ?? 'processing',
            'data'   => (array) $res['data'],
            'error'  => '',
        ];
    }

    /**
     * Signed JSON POST to the bot endpoint.
     */
    private function request( array $payload ): array {
        if ( empty( $this->endpoint ) || empty( $this->token ) ) {
            return [ 'ok' => false, 'data' => null, 'error' => 'آدرس یا توکن ربات تنظیم نشده است' ];
        }

        $response = wp_remote_post( $this->endpoint, [
            'timeout' => 30,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->token,
            ],
            'body'    => wp_json_encode( $payload ),
        ] );

        if ( is_wp_error( $response ) ) {
            UG_Logger::error( 'Telegram bot request failed', [ 'err' => $response->get_error_message() ] );
            return [ 'ok' => false, 'data' => null, 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( 200 !== (int) $code ) {
            return [ 'ok' => false, 'data' => $data ?? $body, 'error' => 'کد وضعیت HTTP ' . $code ];
        }
        if ( null === $data ) {
            return [ 'ok' => false, 'data' => $body, 'error' => 'پاسخ JSON نامعتبر از ربات' ];
        }
        if ( isset( $data['ok'] ) && false === $data['ok'] ) {
            return [ 'ok' => false, 'data' => $data, 'error' => $data['error'] ?? 'خطای ربات' ];
        }

        return [ 'ok' => true, 'data' => $data, 'error' => '' ];
    }
}
