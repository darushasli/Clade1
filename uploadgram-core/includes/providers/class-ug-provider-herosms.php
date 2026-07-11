<?php
/**
 * HeroSMS (hero-sms.com) — virtual number / OTP provider.
 *
 * HeroSMS is 100% SMS-Activate protocol compatible. We use the compatible
 * endpoint (a single handler_api.php) with the API key in the query string:
 *
 *   Base: https://hero-sms.com/stubs/handler_api.php
 *   Auth: ?api_key=<KEY>&action=<ACTION>&...
 *   HTTP: GET · Response: plain string OR JSON depending on the action.
 *
 *   action=getBalance                          → "ACCESS_BALANCE:100.5"
 *   action=getServicesList[&country&lang]      → { status, services:[{code,name}] }
 *   action=getCountries                        → [ { id, rus, eng, chn, visible } ]
 *   action=getPrices[&service&country]         → { "<country>": { "<svc>": {cost,count} } }
 *   action=getNumberV2&service=X&country=Y      → { activationId, phoneNumber, activationCost, ... }
 *                                                 or "NO_NUMBERS"
 *   action=getStatus&id=X                       → "STATUS_WAIT_CODE" | "STATUS_OK:<code>"
 *                                                 | "STATUS_CANCEL" | "STATUS_WAIT_RETRY" | "STATUS_WAIT_RESEND"
 *   action=setStatus&id=X&status=Y              → "ACCESS_*"  (3=resend, 6=finish, 8=cancel)
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Provider_HeroSMS implements UG_Provider_Interface {

    private string $endpoint;
    private string $key;
    private string $proxy;

    /** SMS-Activate string error codes → Persian message. */
    private const ERROR_MAP = [
        'BAD_KEY'            => 'کلید API نامعتبر است',
        'ERROR_SQL'          => 'خطای پایگاه‌دادهٔ سرویس‌دهنده',
        'BAD_ACTION'         => 'عملیات نامعتبر است',
        'BAD_SERVICE'        => 'این سرویس موجود نیست',
        'BAD_STATUS'         => 'وضعیت نامعتبر است',
        'NO_NUMBERS'         => 'در حال حاضر شماره‌ای برای این سرویس/کشور موجود نیست',
        'NO_BALANCE'         => 'موجودی حساب سرویس‌دهنده کافی نیست',
        'NO_ACTIVATION'      => 'شناسهٔ سفارش یافت نشد',
        'WRONG_ACTIVATION_ID'=> 'شناسهٔ سفارش نامعتبر است',
        'BANNED'             => 'حساب مسدود شده است',
        'NO_KEY'             => 'کلید API ارسال نشده است',
        'ERROR_NO_KEY'       => 'کلید API ارسال نشده است',
        'WRONG_SERVICE'      => 'سرویس نامعتبر است',
        'WRONG_COUNTRY'      => 'کشور نامعتبر است',
        'ACCOUNT_INACTIVE'   => 'شماره‌ای در دسترس نیست (حساب غیرفعال)',
        'NO_YULA_MAIL'       => 'این سرویس در حال حاضر پشتیبانی نمی‌شود',
        'NOT_AVAILABLE'      => 'این سرویس در دسترس نیست',
    ];

    public function __construct( array $config ) {
        // Use ?: (not ??) so a saved-but-EMPTY endpoint falls back to the
        // default instead of producing a scheme-less, invalid URL.
        $this->endpoint = trim( (string) ( $config['endpoint'] ?? '' ) ) ?: 'https://hero-sms.com/stubs/handler_api.php';
        $this->key      = trim( (string) ( $config['api_key'] ?? '' ) );
        $this->proxy    = trim( (string) ( $config['proxy'] ?? '' ) );
    }

    public function name(): string {
        return 'herosms';
    }

    /* ══════════════ Interface: test ══════════════ */

    public function test(): array {
        $res = $this->request( [ 'action' => 'getBalance' ] );
        if ( ! $res['ok'] ) {
            return [ 'ok' => false, 'data' => $res['data'], 'error' => $res['error'] ];
        }
        $bal = $this->parse_balance( $res['data'] );
        return [ 'ok' => true, 'data' => [ 'balance' => $bal, 'raw' => $res['data'] ], 'error' => '' ];
    }

    public function balance(): array {
        $res = $this->request( [ 'action' => 'getBalance' ] );
        if ( ! $res['ok'] ) {
            return [ 'ok' => false, 'data' => null, 'error' => $res['error'] ];
        }
        return [ 'ok' => true, 'data' => $this->parse_balance( $res['data'] ), 'error' => '' ];
    }

    /* ══════════════ Catalogue ══════════════ */

    /** Services list: [ { code, name }, ... ]. */
    public function services_list( string $lang = 'en' ): array {
        $res = $this->request( [ 'action' => 'getServicesList', 'lang' => $lang ] );
        if ( ! $res['ok'] ) {
            return [ 'ok' => false, 'data' => [], 'error' => $res['error'] ];
        }
        $data = $res['data'];
        $list = is_array( $data ) ? ( $data['services'] ?? [] ) : [];
        return [ 'ok' => true, 'data' => is_array( $list ) ? $list : [], 'error' => '' ];
    }

    /** Countries list: [ { id, rus, eng, ... }, ... ]. */
    public function countries(): array {
        $res = $this->request( [ 'action' => 'getCountries' ] );
        if ( ! $res['ok'] ) {
            return [ 'ok' => false, 'data' => [], 'error' => $res['error'] ];
        }
        $data = $res['data'];
        // getCountries may return an object keyed by id or a plain array.
        $out = [];
        if ( is_array( $data ) ) {
            foreach ( $data as $k => $c ) {
                if ( ! is_array( $c ) ) { continue; }
                if ( ! isset( $c['id'] ) && ! is_numeric( $k ) ) { continue; }
                $c['id'] = $c['id'] ?? $k;
                $out[]   = $c;
            }
        }
        return [ 'ok' => true, 'data' => $out, 'error' => '' ];
    }

    /**
     * Prices. Optionally filtered by service and/or country.
     * Normalised to a flat list: [ { country, service, cost, count }, ... ].
     */
    public function prices( ?string $service = null, ?string $country = null ): array {
        $params = [ 'action' => 'getPrices' ];
        if ( null !== $service && '' !== $service ) { $params['service'] = $service; }
        if ( null !== $country && '' !== $country ) { $params['country'] = $country; }

        $res = $this->request( $params );
        if ( ! $res['ok'] ) {
            return [ 'ok' => false, 'data' => [], 'error' => $res['error'] ];
        }
        return [ 'ok' => true, 'data' => $this->flatten_prices( $res['data'] ), 'error' => '' ];
    }

    /* ══════════════ Interface: create_order (buy a number) ══════════════ */

    /**
     * Buy / reserve a number.
     * service_id encodes "<service>|<country>" (e.g. "tg|6").
     */
    public function create_order( array $args ): array {
        [ $service, $country ] = $this->split_sid( $args['service_id'] ?? '' );
        if ( '' === $service || '' === $country ) {
            return $this->fail_order( 'سرویس یا کشور مشخص نشده است' );
        }

        $params = [ 'action' => 'getNumberV2', 'service' => $service, 'country' => $country ];
        if ( ! empty( $args['extra']['max_price'] ) ) {
            $params['maxPrice'] = $args['extra']['max_price'];
        }
        $res = $this->request( $params );
        if ( ! $res['ok'] ) {
            return $this->fail_order( $res['error'], $res['data'] );
        }

        $data = $res['data'];
        // Plain string "NO_NUMBERS" is handled as an error in request(); here we
        // expect a JSON object with activationId + phoneNumber.
        if ( ! is_array( $data ) || empty( $data['activationId'] ) ) {
            return $this->fail_order( 'شماره‌ای دریافت نشد (احتمالاً موجودی این سرویس تمام است)', $data );
        }

        $id     = (string) $data['activationId'];
        $number = (string) ( $data['phoneNumber'] ?? '' );

        return [
            'ok'                => true,
            'provider_order_id' => $id,
            'status'            => 'awaiting_otp',
            'data'              => [
                'number'   => $number,
                'cost'     => $data['activationCost'] ?? null,
                'operator' => $data['activationOperator'] ?? '',
            ] + (array) $data,
            'error'             => '',
        ];
    }

    /* ══════════════ Interface: order_status (poll OTP) ══════════════ */

    public function order_status( string $provider_order_id, array $context = [] ): array {
        $res = $this->request( [ 'action' => 'getStatus', 'id' => $provider_order_id ] );
        if ( ! $res['ok'] ) {
            // NO_ACTIVATION means the activation is gone/expired → treat as canceled (refund).
            if ( in_array( $res['error_code'] ?? '', [ 'NO_ACTIVATION', 'WRONG_ACTIVATION_ID' ], true ) ) {
                return [ 'ok' => true, 'status' => 'canceled', 'data' => [], 'error' => '' ];
            }
            return [ 'ok' => false, 'status' => '', 'data' => $res['data'], 'error' => $res['error'] ];
        }

        $raw = is_string( $res['data'] ) ? trim( $res['data'] ) : '';

        // STATUS_OK:<code>  → code received.
        if ( 0 === strpos( $raw, 'STATUS_OK' ) ) {
            $code = trim( substr( $raw, strlen( 'STATUS_OK:' ) ) );
            $code = ltrim( $code, ':' );
            // Finalize the activation on their side so it's properly closed (once).
            $this->set_status( $provider_order_id, 6 );
            return [
                'ok'     => true,
                'status' => 'completed',
                'data'   => [ 'otp' => $code, 'code' => $code ],
                'error'  => '',
            ];
        }

        if ( 0 === strpos( $raw, 'STATUS_CANCEL' ) ) {
            return [ 'ok' => true, 'status' => 'canceled', 'data' => [], 'error' => '' ];
        }

        // STATUS_WAIT_CODE / STATUS_WAIT_RETRY / STATUS_WAIT_RESEND → still waiting.
        return [ 'ok' => true, 'status' => 'awaiting_otp', 'data' => [], 'error' => '' ];
    }

    /* ══════════════ Lifecycle actions ══════════════ */

    /** Cancel & refund (status 8). Only allowed before a code arrives. */
    public function cancel( string $provider_order_id ): array {
        return $this->set_status( $provider_order_id, 8 );
    }

    /** Finish/confirm (status 6). */
    public function finish( string $provider_order_id ): array {
        return $this->set_status( $provider_order_id, 6 );
    }

    /** Request the SMS to be re-sent (status 3). */
    public function resend( string $provider_order_id ): array {
        return $this->set_status( $provider_order_id, 3 );
    }

    private function set_status( string $id, int $status ): array {
        $res = $this->request( [ 'action' => 'setStatus', 'id' => $id, 'status' => $status ] );
        return [ 'ok' => $res['ok'], 'data' => $res['data'], 'error' => $res['error'] ];
    }

    /* ══════════════ Parsing helpers ══════════════ */

    private function split_sid( string $sid ): array {
        $parts   = explode( '|', $sid, 2 );
        $service = trim( $parts[0] ?? '' );
        $country = trim( $parts[1] ?? '' );
        return [ $service, $country ];
    }

    private function parse_balance( $data ): ?float {
        if ( is_string( $data ) && 0 === strpos( $data, 'ACCESS_BALANCE' ) ) {
            $parts = explode( ':', $data, 2 );
            return isset( $parts[1] ) ? (float) $parts[1] : null;
        }
        if ( is_numeric( $data ) ) {
            return (float) $data;
        }
        return null;
    }

    /**
     * getPrices → flat rows [ { country, service, cost, count }, … ].
     *
     * The shape is a nested map that can be keyed by country then service
     * (unfiltered) or partially collapsed when filtered. SMS-Activate country
     * IDs are always NUMERIC and service codes are always ALPHABETIC, so we
     * walk recursively and classify each key by that rule — which correctly
     * preserves both dimensions regardless of filtering or numeric-string keys
     * that would otherwise look like a plain list.
     */
    private function flatten_prices( $data, ?string $country = null, ?string $service = null ): array {
        $out = [];
        if ( ! is_array( $data ) ) {
            return $out;
        }
        // Leaf node.
        if ( isset( $data['cost'] ) || isset( $data['count'] ) ) {
            $out[] = [
                'country' => (string) ( $country ?? '' ),
                'service' => (string) ( $service ?? '' ),
                'cost'    => isset( $data['cost'] ) ? (float) $data['cost'] : 0.0,
                'count'   => isset( $data['count'] ) ? (int) $data['count'] : 0,
            ];
            return $out;
        }
        foreach ( $data as $key => $val ) {
            if ( ! is_array( $val ) ) {
                continue;
            }
            $c = $country;
            $s = $service;
            $ks = (string) $key;
            if ( is_int( $key ) || ctype_digit( $ks ) ) {
                $c = $ks; // numeric key → country id
            } elseif ( '' !== $ks ) {
                $s = $ks; // alphabetic key → service code
            }
            $out = array_merge( $out, $this->flatten_prices( $val, $c, $s ) );
        }
        return $out;
    }

    private function fail_order( string $error, $data = null ): array {
        return [ 'ok' => false, 'provider_order_id' => '', 'status' => 'failed', 'data' => $data, 'error' => $error ];
    }

    /* ══════════════ HTTP ══════════════ */

    /**
     * GET request to the SMS-Activate-compatible handler. Returns
     * [ ok, data, error, error_code ]. `data` is decoded JSON when the body is
     * JSON, otherwise the trimmed string. Known error strings map to Persian.
     */
    private function request( array $params ): array {
        if ( empty( $this->key ) ) {
            return [ 'ok' => false, 'data' => null, 'error' => 'کلید API هیرو‌اس‌ام‌اس تنظیم نشده است', 'error_code' => 'NO_KEY' ];
        }

        // Guard against a malformed/scheme-less endpoint before hitting WP_Http.
        if ( ! preg_match( '#^https?://#i', $this->endpoint ) ) {
            return [ 'ok' => false, 'data' => null, 'error' => 'آدرس API نامعتبر است (باید با https:// شروع شود). در تنظیمات تب هیرو‌اس‌ام‌اس آدرس را درست کنید یا خالی بگذارید تا مقدار پیش‌فرض استفاده شود.', 'error_code' => 'BAD_URL' ];
        }

        // A configured-but-undialable proxy (e.g. VLESS) can't be used directly.
        if ( '' !== $this->proxy && ! UG_Proxy::is_dialable( $this->proxy ) ) {
            return [ 'ok' => false, 'data' => null, 'error' => UG_Proxy::warning( $this->proxy ), 'error_code' => 'BAD_PROXY' ];
        }

        $params = [ 'api_key' => $this->key ] + $params;
        $url    = $this->endpoint . '?' . http_build_query( $params );

        // Optional proxy (useful when hero-sms.com is filtered on an Iran host).
        $response = UG_Proxy::with( $this->proxy, static function () use ( $url ) {
            return wp_remote_get( $url, [
                'timeout'    => 30,
                'sslverify'  => true,
                'headers'    => [ 'Accept' => 'application/json' ],
                'user-agent' => 'UploadGram/' . ( defined( 'UGC_VERSION' ) ? UGC_VERSION : '1' ),
            ] );
        } );

        if ( is_wp_error( $response ) ) {
            UG_Logger::error( 'HeroSMS request failed', [ 'err' => $response->get_error_message() ] );
            $hint = '' === $this->proxy ? ' (اگر سایت هیرو‌اس‌ام‌اس روی هاست شما فیلتر است، در تنظیمات یک «پروکسی» وارد کنید).' : ' (اتصال از طریق پروکسی هم ناموفق بود؛ درستی پروکسی را بررسی کنید).';
            return [ 'ok' => false, 'data' => null, 'error' => 'خطای اتصال: ' . $response->get_error_message() . $hint, 'error_code' => 'NETWORK' ];
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = trim( (string) wp_remote_retrieve_body( $response ) );

        if ( 401 === $code || 403 === $code ) {
            return [ 'ok' => false, 'data' => $body, 'error' => 'دسترسی رد شد — کلید API یا IP سرور مجاز نیست', 'error_code' => 'BAD_KEY' ];
        }

        // Try JSON first.
        $json = json_decode( $body, true );
        $data = ( null !== $json ) ? $json : $body;

        // Plain-string error codes (SMS-Activate style).
        if ( is_string( $data ) ) {
            $token = strtoupper( explode( ':', $data )[0] );
            if ( isset( self::ERROR_MAP[ $token ] ) ) {
                // NO_NUMBERS is a soft "out of stock" — surface as error but tagged.
                return [ 'ok' => false, 'data' => $data, 'error' => self::ERROR_MAP[ $token ], 'error_code' => $token ];
            }
            // Generic unknown *_ERROR / BAD_* / WRONG_* strings.
            if ( preg_match( '/^(BAD_|WRONG_|ERROR_|NO_)[A-Z_]+$/', $data ) ) {
                return [ 'ok' => false, 'data' => $data, 'error' => 'خطای سرویس: ' . $data, 'error_code' => $token ];
            }
        }

        if ( $code >= 400 ) {
            return [ 'ok' => false, 'data' => $data, 'error' => 'کد وضعیت HTTP ' . $code, 'error_code' => 'HTTP_' . $code ];
        }

        return [ 'ok' => true, 'data' => $data, 'error' => '', 'error_code' => '' ];
    }
}
