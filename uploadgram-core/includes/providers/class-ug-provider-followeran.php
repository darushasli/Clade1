<?php
/**
 * Followeran (my.followeran.ir) — standard SMM Panel API v2.
 *
 * Standard SMM API request format (POST form-encoded):
 *   key={api_key}&action=add&service={id}&link={link}&quantity={qty}
 *   → { "order": 12345 }
 *   action=status&order={id} → { "charge","start_count","status","remains" }
 *   action=services          → [ { service, name, rate, min, max, category }, ... ]
 *   action=balance           → { "balance","currency" }
 *
 * NOTE: If Followeran's exact param names differ, adjust in build_request().
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Provider_Followeran implements UG_Provider_Interface {

    private string $endpoint;
    private string $fallback;
    private string $key;
    private string $proxy;

    public function __construct( array $config ) {
        $this->endpoint = trim( (string) ( $config['endpoint'] ?? '' ) ) ?: 'https://my.followeran.ir/api/v2';
        // Followeran also serves the same API here; used if the primary host is
        // unreachable (DNS/connection error).
        $this->fallback = trim( (string) ( $config['fallback'] ?? '' ) ) ?: 'https://panel.smmflw.com/api/iran';
        $this->key      = trim( (string) ( $config['api_key'] ?? '' ) );
        $this->proxy    = trim( (string) ( $config['proxy'] ?? '' ) );
    }

    public function name(): string {
        return 'followeran';
    }

    public function test(): array {
        $res = $this->request( [ 'action' => 'balance' ] );
        if ( ! $res['ok'] ) {
            return [ 'ok' => false, 'error' => $res['error'], 'data' => $res['data'] ];
        }
        return [ 'ok' => true, 'data' => $res['data'], 'error' => '' ];
    }

    /**
     * Fetch the remote service catalogue (for mapping to products).
     */
    public function services(): array {
        $res = $this->request( [ 'action' => 'services' ] );
        return $res['ok'] ? [ 'ok' => true, 'data' => $res['data'], 'error' => '' ]
                          : [ 'ok' => false, 'data' => [], 'error' => $res['error'] ];
    }

    public function create_order( array $args ): array {
        $params = [
            'action'   => 'add',
            'service'  => $args['service_id'] ?? '',
            'link'     => $args['target'] ?? '',
            'quantity' => (int) ( $args['quantity'] ?? 0 ),
        ];

        // Optional SMM fields (comments, runs, interval…) passed via extra.
        foreach ( [ 'comments', 'runs', 'interval', 'username', 'min', 'max' ] as $opt ) {
            if ( ! empty( $args['extra'][ $opt ] ) ) {
                $params[ $opt ] = $args['extra'][ $opt ];
            }
        }

        $res = $this->request( $params );
        if ( ! $res['ok'] ) {
            return [ 'ok' => false, 'provider_order_id' => '', 'status' => 'failed', 'data' => $res['data'], 'error' => $res['error'] ];
        }

        $order_id = $res['data']['order'] ?? '';
        if ( empty( $order_id ) ) {
            $err = $res['data']['error'] ?? 'پاسخ نامعتبر از فالوران';
            return [ 'ok' => false, 'provider_order_id' => '', 'status' => 'failed', 'data' => $res['data'], 'error' => $err ];
        }

        return [
            'ok'                => true,
            'provider_order_id' => (string) $order_id,
            'status'            => 'processing',
            'data'              => $res['data'],
            'error'             => '',
        ];
    }

    public function order_status( string $provider_order_id, array $context = [] ): array {
        $res = $this->request( [ 'action' => 'status', 'order' => $provider_order_id ] );
        if ( ! $res['ok'] ) {
            return [ 'ok' => false, 'status' => '', 'data' => $res['data'], 'error' => $res['error'] ];
        }

        $remote = strtolower( (string) ( $res['data']['status'] ?? '' ) );
        return [
            'ok'     => true,
            'status' => $this->map_status( $remote ),
            'data'   => $res['data'],
            'error'  => '',
        ];
    }

    /**
     * Map SMM statuses to our internal set.
     */
    private function map_status( string $remote ): string {
        $map = [
            'completed'  => 'completed',
            'partial'    => 'partial',
            'in progress'=> 'processing',
            'processing' => 'processing',
            'pending'    => 'processing',
            'canceled'   => 'canceled',
            'cancelled'  => 'canceled',
            'refunded'   => 'refunded',
            'fail'       => 'failed',
        ];
        return $map[ $remote ] ?? 'processing';
    }

    /**
     * Perform the HTTP call. Returns [ ok, data, error ].
     */
    private function request( array $params ): array {
        if ( empty( $this->key ) ) {
            return [ 'ok' => false, 'data' => null, 'error' => 'کلید API فالوران تنظیم نشده است' ];
        }

        if ( '' !== $this->proxy && ! UG_Proxy::is_dialable( $this->proxy ) ) {
            return [ 'ok' => false, 'data' => null, 'error' => UG_Proxy::warning( $this->proxy ) ];
        }

        $params['key'] = $this->key;

        $response = UG_Proxy::with( $this->proxy, function () use ( $params ) {
            $r = wp_remote_post( $this->endpoint, [ 'timeout' => 30, 'body' => $params ] );
            // On a connection-level failure, retry once against the fallback host.
            if ( is_wp_error( $r ) && $this->fallback && $this->fallback !== $this->endpoint ) {
                UG_Logger::error( 'Followeran primary failed, trying fallback', [ 'err' => $r->get_error_message() ] );
                $r = wp_remote_post( $this->fallback, [ 'timeout' => 30, 'body' => $params ] );
            }
            return $r;
        } );

        if ( is_wp_error( $response ) ) {
            UG_Logger::error( 'Followeran request failed', [ 'err' => $response->get_error_message() ] );
            return [ 'ok' => false, 'data' => null, 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( 200 !== (int) $code ) {
            return [ 'ok' => false, 'data' => $data ?? $body, 'error' => 'کد وضعیت HTTP ' . $code ];
        }
        if ( null === $data ) {
            return [ 'ok' => false, 'data' => $body, 'error' => 'پاسخ JSON نامعتبر' ];
        }

        return [ 'ok' => true, 'data' => $data, 'error' => '' ];
    }
}
