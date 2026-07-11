<?php
/**
 * Telegram members — your own uploader bot, via the ug-bridge.php shim.
 *
 * The bot lives on a separate host. `bridge/ug-bridge.php` sits next to the
 * bot, shares its config.php, and inserts orders straight into the bot's
 * `orders` table (status='processing') so processor.php picks them up.
 *
 * Contract (POST JSON, shared `secret`):
 *   quote  { order_type, count }              → { price, assigned_bot, check_bot }
 *   check  { assigned_bot, channel }          → { is_admin }
 *   create { channel, count, order_type,
 *            assigned_bot, web_user_id }       → { order_id, status } | membership_not_verified
 *   status { order_id }                        → { status, result, current_count, target_count }
 *   cancel { order_id }                        → { status }
 *
 * See docs/api-telegram-bot.md + bridge/README.md.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Provider_Telegram implements UG_Provider_Interface {

    private string $endpoint;
    private string $secret;
    private string $proxy;

    public function __construct( array $config ) {
        $this->endpoint = trim( (string) ( $config['endpoint'] ?? '' ) );
        $this->secret   = trim( (string) ( $config['api_key'] ?? '' ) ); // "توکن امنیتی" = bridge secret
        $this->proxy    = trim( (string) ( $config['proxy'] ?? '' ) );
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

    /**
     * Price + bot assignment + which check-bot the customer must make admin.
     */
    public function quote( string $order_type, int $count ): array {
        $res = $this->request( [
            'action'     => 'quote',
            'order_type' => $order_type,
            'count'      => $count,
        ] );
        return $res;
    }

    /**
     * Is the check-bot already an admin of the customer's channel?
     */
    public function check_membership( int $assigned_bot, string $channel ): bool {
        $res = $this->request( [
            'action'       => 'check',
            'assigned_bot' => $assigned_bot,
            'channel'      => $channel,
        ] );
        return ! empty( $res['ok'] ) && ! empty( $res['data']['is_admin'] );
    }

    /**
     * Register a paid order into the bot's processing queue.
     *
     * Expects in $args:
     *   target      → channel link/@username
     *   quantity    → member count
     *   extra.order_type   → ethical|unethical  (default ethical)
     *   extra.assigned_bot → optional preferred bot (else the bridge picks)
     *   extra.web_user_id  → WP user id (for attribution)
     */
    public function create_order( array $args ): array {
        $type = ( ( $args['extra']['order_type'] ?? 'ethical' ) === 'unethical' ) ? 'unethical' : 'ethical';

        $res = $this->request( [
            'action'       => 'create',
            'channel'      => $args['target'] ?? '',
            'count'        => (int) ( $args['quantity'] ?? 0 ),
            'order_type'   => $type,
            'assigned_bot' => (int) ( $args['extra']['assigned_bot'] ?? 0 ),
            'web_user_id'  => (string) ( $args['extra']['web_user_id'] ?? '' ),
        ] );

        if ( ! $res['ok'] ) {
            // Surface the membership gate distinctly so the UI can guide the user.
            $err = $res['error'];
            if ( isset( $res['data']['error'] ) && 'membership_not_verified' === $res['data']['error'] ) {
                $check = $res['data']['check_bot'] ?? '';
                $err   = $check
                    ? sprintf( 'ابتدا ربات @%s را ادمین کانال خود کنید سپس دوباره تلاش کنید.', $check )
                    : 'ابتدا ربات بررسی را ادمین کانال خود کنید.';
            }
            return [ 'ok' => false, 'provider_order_id' => '', 'status' => 'failed', 'data' => $res['data'], 'error' => $err ];
        }

        $data = $res['data'];
        $oid  = $data['order_id'] ?? '';

        return [
            'ok'                => ! empty( $oid ),
            'provider_order_id' => (string) $oid,
            'status'            => $this->map_status( $data['status'] ?? 'processing' ),
            'data'              => (array) $data,
            'error'             => empty( $oid ) ? 'پاسخ نامعتبر از پل ربات' : '',
        ];
    }

    public function order_status( string $provider_order_id, array $context = [] ): array {
        $res = $this->request( [ 'action' => 'status', 'order_id' => $provider_order_id ] );
        if ( ! $res['ok'] ) {
            return [ 'ok' => false, 'status' => '', 'data' => $res['data'], 'error' => $res['error'] ];
        }
        return [
            'ok'     => true,
            'status' => $this->map_status( $res['data']['status'] ?? 'processing' ),
            'data'   => (array) $res['data'],
            'error'  => '',
        ];
    }

    public function cancel( string $provider_order_id ): array {
        return $this->request( [ 'action' => 'cancel', 'order_id' => $provider_order_id ] );
    }

    /**
     * Map the bot's order status to our internal set.
     */
    private function map_status( string $remote ): string {
        $map = [
            'pending'    => 'processing',
            'processing' => 'processing',
            'monitoring' => 'processing', // still working (members being added)
            'completed'  => 'completed',
            'failed'     => 'failed',
        ];
        return $map[ strtolower( $remote ) ] ?? 'processing';
    }

    /**
     * Signed JSON POST to the bridge.
     */
    private function request( array $payload ): array {
        if ( empty( $this->endpoint ) || empty( $this->secret ) ) {
            return [ 'ok' => false, 'data' => null, 'error' => 'آدرس پل یا توکن امنیتی ربات تنظیم نشده است' ];
        }

        if ( '' !== $this->proxy && ! UG_Proxy::is_dialable( $this->proxy ) ) {
            return [ 'ok' => false, 'data' => null, 'error' => UG_Proxy::warning( $this->proxy ) ];
        }

        $payload['secret'] = $this->secret;
        $endpoint          = $this->endpoint;

        $response = UG_Proxy::with( $this->proxy, static function () use ( $endpoint, $payload ) {
            return wp_remote_post( $endpoint, [
                'timeout' => 30,
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( $payload ),
            ] );
        } );

        if ( is_wp_error( $response ) ) {
            UG_Logger::error( 'Telegram bridge request failed', [ 'err' => $response->get_error_message() ] );
            return [ 'ok' => false, 'data' => null, 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( null === $data ) {
            return [ 'ok' => false, 'data' => $body, 'error' => 'پاسخ JSON نامعتبر از پل ربات (HTTP ' . $code . ')' ];
        }
        if ( isset( $data['ok'] ) && false === $data['ok'] ) {
            return [ 'ok' => false, 'data' => $data, 'error' => $data['error'] ?? 'خطای پل ربات' ];
        }
        if ( 200 !== (int) $code ) {
            return [ 'ok' => false, 'data' => $data, 'error' => 'کد وضعیت HTTP ' . $code ];
        }

        return [ 'ok' => true, 'data' => $data, 'error' => '' ];
    }
}
