<?php
/**
 * Numberland (numberland.ir) — virtual number / OTP provider.
 *
 * Typical Numberland API (GET, param-based). Exact method/param names may
 * differ per their docs — they are centralised in request()/create_order()
 * so you can adjust in one place after checking the official docs.
 *
 *   method=getbalance                         → { balance }
 *   method=services                           → [ services ]
 *   method=getnumber&service=..&country=..    → { id, number }
 *   method=getsms&id=..                        → { sms / code }
 *   method=cancel&id=..                        → { status }
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Provider_Numberland implements UG_Provider_Interface {

    private string $endpoint;
    private string $key;

    public function __construct( array $config ) {
        $this->endpoint = $config['endpoint'] ?? 'https://api.numberland.ir/v2.php';
        $this->key      = $config['api_key'] ?? '';
    }

    public function name(): string {
        return 'numberland';
    }

    public function test(): array {
        $res = $this->request( [ 'method' => 'getbalance' ] );
        return $res['ok']
            ? [ 'ok' => true, 'data' => $res['data'], 'error' => '' ]
            : [ 'ok' => false, 'data' => $res['data'], 'error' => $res['error'] ];
    }

    public function services(): array {
        $res = $this->request( [ 'method' => 'services' ] );
        return $res['ok'] ? [ 'ok' => true, 'data' => $res['data'], 'error' => '' ]
                          : [ 'ok' => false, 'data' => [], 'error' => $res['error'] ];
    }

    /**
     * Buy a number. service_id encodes "service:country" or uses extra.
     */
    public function create_order( array $args ): array {
        $service = $args['service_id'] ?? '';
        $country = $args['extra']['country'] ?? '';

        // Allow "service:country" combined id.
        if ( $country === '' && str_contains( $service, ':' ) ) {
            [ $service, $country ] = array_pad( explode( ':', $service, 2 ), 2, '' );
        }

        $res = $this->request( [
            'method'  => 'getnumber',
            'service' => $service,
            'country' => $country,
        ] );

        if ( ! $res['ok'] ) {
            return [ 'ok' => false, 'provider_order_id' => '', 'status' => 'failed', 'data' => $res['data'], 'error' => $res['error'] ];
        }

        $data = $res['data'];
        $id   = $data['id'] ?? ( $data['number_id'] ?? '' );
        $num  = $data['number'] ?? ( $data['phone'] ?? '' );

        if ( empty( $id ) ) {
            return [ 'ok' => false, 'provider_order_id' => '', 'status' => 'failed', 'data' => $data, 'error' => 'شماره‌ای دریافت نشد (احتمالاً موجودی سرویس تمام است)' ];
        }

        return [
            'ok'                => true,
            'provider_order_id' => (string) $id,
            'status'            => 'awaiting_otp',
            'data'              => [ 'number' => $num ] + (array) $data,
            'error'             => '',
        ];
    }

    /**
     * Poll for the SMS/OTP.
     */
    public function order_status( string $provider_order_id, array $context = [] ): array {
        $res = $this->request( [ 'method' => 'getsms', 'id' => $provider_order_id ] );
        if ( ! $res['ok'] ) {
            return [ 'ok' => false, 'status' => '', 'data' => $res['data'], 'error' => $res['error'] ];
        }

        $data = $res['data'];
        $sms  = $data['sms'] ?? ( $data['code'] ?? ( $data['message'] ?? '' ) );

        if ( ! empty( $sms ) ) {
            return [ 'ok' => true, 'status' => 'completed', 'data' => [ 'otp' => $sms ] + (array) $data, 'error' => '' ];
        }

        // Still waiting.
        return [ 'ok' => true, 'status' => 'awaiting_otp', 'data' => (array) $data, 'error' => '' ];
    }

    /**
     * Cancel / release a number.
     */
    public function cancel( string $provider_order_id ): array {
        return $this->request( [ 'method' => 'cancel', 'id' => $provider_order_id ] );
    }

    /**
     * HTTP GET request. Returns [ ok, data, error ].
     */
    private function request( array $params ): array {
        if ( empty( $this->key ) ) {
            return [ 'ok' => false, 'data' => null, 'error' => 'کلید API نامبرلند تنظیم نشده است' ];
        }

        $params['apikey'] = $this->key;
        $url              = add_query_arg( array_map( 'rawurlencode', $params ), $this->endpoint );

        $response = wp_remote_get( $url, [ 'timeout' => 30 ] );

        if ( is_wp_error( $response ) ) {
            UG_Logger::error( 'Numberland request failed', [ 'err' => $response->get_error_message() ] );
            return [ 'ok' => false, 'data' => null, 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( 200 !== (int) $code ) {
            return [ 'ok' => false, 'data' => $data ?? $body, 'error' => 'کد وضعیت HTTP ' . $code ];
        }
        if ( null === $data ) {
            // Some endpoints return plain text; wrap it.
            return [ 'ok' => true, 'data' => [ 'raw' => $body ], 'error' => '' ];
        }

        // Numberland often returns an "amount"/"status" error envelope.
        if ( isset( $data['status'] ) && in_array( strtolower( (string) $data['status'] ), [ 'error', 'fail', '0' ], true ) ) {
            $msg = $data['message'] ?? ( $data['error'] ?? 'خطای نامبرلند' );
            return [ 'ok' => false, 'data' => $data, 'error' => (string) $msg ];
        }

        return [ 'ok' => true, 'data' => $data, 'error' => '' ];
    }
}
