<?php
/**
 * Numberland (numberland.ir) — virtual number / OTP provider.
 *
 * Matches the official numberland.ir/developers v2 API:
 *   Base: https://api.numberland.ir/v2.php/?apikey=[API_CODE]&method=[METHOD]
 *   HTTP: GET · Response: JSON · Fields are UPPERCASE.
 *   Error rule: field RESULT < 0  ⇒ error (see error map below).
 *
 *   method=balance                    → { RESULT: <balance> }
 *   method=getinfo                    → [ { id: SERVICE_COUNTRY_ID, ... }, ... ]
 *   method=getnum&sid=<id>            → { RESULT, ID, NUMBER, AREACODE, AMOUNT }
 *   method=checkstatus&id=<ID>        → { RESULT: 1..6, CODE }
 *   method=cancelnumber&id=<ID>       → { RESULT }
 *   method=bannumber&id=<ID>          → { RESULT }
 *   method=repeat&id=<ID>             → { RESULT }
 *   method=closenumber&id=<ID>        → { RESULT }
 *   method=getcountry / getservice    → [ ... ]
 *
 * See docs/api-numberland.md for the full analysis.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Provider_Numberland implements UG_Provider_Interface {

    private string $endpoint;
    private string $key;

    /** checkstatus RESULT codes → internal status. */
    private const STATUS_MAP = [
        1 => 'awaiting_otp', // waiting for SMS
        2 => 'awaiting_otp', // number ready, waiting for SMS
        3 => 'completed',    // code received (CODE field is filled)
        4 => 'canceled',     // number canceled
        5 => 'failed',       // number banned
        6 => 'failed',       // expired / closed
    ];

    /** Negative RESULT error codes → Persian message. */
    private const ERROR_MAP = [
        -901 => 'کلید API نامعتبر است',
        -902 => 'دسترسی مسدود است (IP مجاز نیست)',
        -990 => 'خطای عمومی سرور نامبرلند',
        -900 => 'پارامتر ارسالی نامعتبر است',
        -202 => 'موجودی حساب نامبرلند کافی نیست',
        -204 => 'این سرویس موجود نیست',
        -205 => 'شماره‌ای برای این سرویس موجود نیست',
        -210 => 'شناسه سفارش نامعتبر است',
        -211 => 'این عملیات روی این سفارش مجاز نیست',
        -212 => 'این شماره قبلاً بسته شده است',
        -304 => 'محدودیت تعداد درخواست (کمی صبر کنید)',
    ];

    public function __construct( array $config ) {
        $this->endpoint = $config['endpoint'] ?? 'https://api.numberland.ir/v2.php';
        $this->key      = $config['api_key'] ?? '';
    }

    public function name(): string {
        return 'numberland';
    }

    public function test(): array {
        $res = $this->request( [ 'method' => 'balance' ] );
        return $res['ok']
            ? [ 'ok' => true, 'data' => $res['data'], 'error' => '' ]
            : [ 'ok' => false, 'data' => $res['data'], 'error' => $res['error'] ];
    }

    /**
     * Remote catalogue of service+country combinations (each has an `id` =
     * SERVICE_COUNTRY_ID which is what you pass to getnum as `sid`).
     */
    public function services(): array {
        $res = $this->request( [ 'method' => 'getinfo' ] );
        return $res['ok'] ? [ 'ok' => true, 'data' => $res['data'], 'error' => '' ]
                          : [ 'ok' => false, 'data' => [], 'error' => $res['error'] ];
    }

    /**
     * Buy / reserve a number.
     * service_id = SERVICE_COUNTRY_ID (the `id` from getinfo).
     */
    public function create_order( array $args ): array {
        $sid = $args['service_id'] ?? '';
        if ( '' === $sid ) {
            return $this->fail_order( 'شناسه سرویس (sid) مشخص نشده است' );
        }

        $res = $this->request( [ 'method' => 'getnum', 'sid' => $sid ] );
        if ( ! $res['ok'] ) {
            return $this->fail_order( $res['error'], $res['data'] );
        }

        $data   = $res['data'];
        $id     = $data['ID']       ?? '';
        $number = $data['NUMBER']   ?? '';
        $area   = $data['AREACODE'] ?? '';

        if ( '' === (string) $id ) {
            return $this->fail_order( 'شماره‌ای دریافت نشد (احتمالاً موجودی سرویس تمام است)', $data );
        }

        return [
            'ok'                => true,
            'provider_order_id' => (string) $id,
            'status'            => 'awaiting_otp',
            'data'              => [
                'number'   => (string) $number,
                'areacode' => (string) $area,
                'amount'   => $data['AMOUNT'] ?? null,
            ] + (array) $data,
            'error'             => '',
        ];
    }

    /**
     * Poll for the SMS/OTP.
     */
    public function order_status( string $provider_order_id, array $context = [] ): array {
        $res = $this->request( [ 'method' => 'checkstatus', 'id' => $provider_order_id ] );
        if ( ! $res['ok'] ) {
            return [ 'ok' => false, 'status' => '', 'data' => $res['data'], 'error' => $res['error'] ];
        }

        $data   = $res['data'];
        $result = isset( $data['RESULT'] ) ? (int) $data['RESULT'] : 0;
        $status = self::STATUS_MAP[ $result ] ?? 'awaiting_otp';
        $code   = $data['CODE'] ?? '';

        return [
            'ok'     => true,
            'status' => $status,
            'data'   => ( '' !== (string) $code ? [ 'otp' => (string) $code ] : [] ) + (array) $data,
            'error'  => '',
        ];
    }

    /**
     * Cancel a number (refund if no code arrived yet).
     */
    public function cancel( string $provider_order_id ): array {
        return $this->action_by_id( 'cancelnumber', $provider_order_id );
    }

    /**
     * Ban a broken number.
     */
    public function ban( string $provider_order_id ): array {
        return $this->action_by_id( 'bannumber', $provider_order_id );
    }

    /**
     * Ask the same number for another SMS.
     */
    public function repeat( string $provider_order_id ): array {
        return $this->action_by_id( 'repeat', $provider_order_id );
    }

    /**
     * Close / release a number.
     */
    public function close( string $provider_order_id ): array {
        return $this->action_by_id( 'closenumber', $provider_order_id );
    }

    public function balance(): array {
        return $this->request( [ 'method' => 'balance' ] );
    }

    public function countries(): array {
        return $this->request( [ 'method' => 'getcountry' ] );
    }

    /* ── helpers ─────────────────────────────── */

    private function action_by_id( string $method, string $id ): array {
        $res = $this->request( [ 'method' => $method, 'id' => $id ] );
        return [
            'ok'    => $res['ok'],
            'data'  => $res['data'],
            'error' => $res['error'],
        ];
    }

    private function fail_order( string $error, $data = null ): array {
        return [ 'ok' => false, 'provider_order_id' => '', 'status' => 'failed', 'data' => $data, 'error' => $error ];
    }

    /**
     * HTTP GET request. Returns [ ok, data, error ].
     * Applies the Numberland error rule (RESULT < 0 ⇒ error).
     */
    private function request( array $params ): array {
        if ( empty( $this->key ) ) {
            return [ 'ok' => false, 'data' => null, 'error' => 'کلید API نامبرلند تنظیم نشده است' ];
        }

        $params = [ 'apikey' => $this->key ] + $params;
        // Docs show base as ".../v2.php/?apikey=..&method=.." — keep that shape.
        $url = rtrim( $this->endpoint, '/' ) . '/?' . http_build_query( $params );

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
            return [ 'ok' => false, 'data' => $body, 'error' => 'پاسخ JSON نامعتبر از نامبرلند' ];
        }

        // Error rule: a negative RESULT means failure.
        if ( isset( $data['RESULT'] ) && is_numeric( $data['RESULT'] ) && (int) $data['RESULT'] < 0 ) {
            $rc  = (int) $data['RESULT'];
            $msg = self::ERROR_MAP[ $rc ] ?? ( 'خطای نامبرلند (کد ' . $rc . ')' );
            return [ 'ok' => false, 'data' => $data, 'error' => $msg ];
        }

        return [ 'ok' => true, 'data' => $data, 'error' => '' ];
    }
}
