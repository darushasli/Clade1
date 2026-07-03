<?php
/**
 * AJAX endpoints: instant order placement, order polling, admin API test.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Ajax {

    private UG_Dispatcher $dispatcher;
    private UG_Wallet $wallet;
    private UG_Orders $orders;
    private UG_Settings $settings;

    public function __construct( UG_Dispatcher $dispatcher, UG_Wallet $wallet, UG_Orders $orders, UG_Settings $settings ) {
        $this->dispatcher = $dispatcher;
        $this->wallet     = $wallet;
        $this->orders     = $orders;
        $this->settings   = $settings;

        add_action( 'wp_ajax_ug_place_order', [ $this, 'place_order' ] );
        add_action( 'wp_ajax_ug_order_status', [ $this, 'order_status' ] );
        add_action( 'wp_ajax_ug_test_provider', [ $this, 'test_provider' ] );
    }

    /* ── Place an instant order ─────────────── */

    public function place_order(): void {
        check_ajax_referer( 'ug_front', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'برای ثبت سفارش وارد شوید.' ], 401 );
        }

        $user_id    = get_current_user_id();
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $quantity   = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 0;
        $target     = isset( $_POST['target'] ) ? sanitize_text_field( wp_unslash( $_POST['target'] ) ) : '';

        $product = $product_id ? wc_get_product( $product_id ) : null;
        if ( ! $product ) {
            wp_send_json_error( [ 'message' => 'محصول یافت نشد.' ], 404 );
        }

        $m = UG_WC_Integration::meta( $product_id );

        if ( empty( $m['provider'] ) ) {
            wp_send_json_error( [ 'message' => 'این محصول به سرویسی متصل نیست.' ], 400 );
        }

        // Validate quantity for count-based services.
        if ( 'none' !== $m['input_type'] ) {
            if ( $quantity <= 0 ) {
                wp_send_json_error( [ 'message' => 'تعداد نامعتبر است.' ], 400 );
            }
            if ( $m['min'] && $quantity < $m['min'] ) {
                wp_send_json_error( [ 'message' => 'حداقل تعداد: ' . $m['min'] ], 400 );
            }
            if ( $m['max'] && $quantity > $m['max'] ) {
                wp_send_json_error( [ 'message' => 'حداکثر تعداد: ' . $m['max'] ], 400 );
            }
            if ( '' === $target ) {
                wp_send_json_error( [ 'message' => 'لینک/یوزرنیم را وارد کنید.' ], 400 );
            }
        }

        $price = UG_WC_Integration::calc_price( $product_id, $quantity );
        if ( $price <= 0 ) {
            wp_send_json_error( [ 'message' => 'محاسبه قیمت ناموفق بود.' ], 400 );
        }

        // Check & debit wallet BEFORE calling the provider.
        if ( $this->wallet->balance( $user_id ) < $price ) {
            wp_send_json_error( [
                'message'  => 'موجودی کیف پول کافی نیست.',
                'need'     => $price,
                'balance'  => $this->wallet->balance( $user_id ),
                'recharge' => true,
            ], 402 );
        }

        // Create local order (pending) first.
        $order_id = $this->orders->create( [
            'user_id'    => $user_id,
            'product_id' => $product_id,
            'provider'   => $m['provider'],
            'service_id' => $m['service_id'],
            'target'     => $target,
            'quantity'   => $quantity,
            'amount'     => $price,
            'currency'   => $this->settings->default_currency(),
            'status'     => 'pending',
        ] );

        if ( ! $order_id ) {
            wp_send_json_error( [ 'message' => 'ثبت سفارش در پایگاه داده ناموفق بود.' ], 500 );
        }

        // Debit wallet.
        $desc   = sprintf( 'سفارش #%d — %s', $order_id, $product->get_name() );
        $debited = $this->wallet->debit( $user_id, $price, $desc );
        if ( ! $debited ) {
            $this->orders->update( $order_id, [ 'status' => 'failed', 'note' => 'کسر از کیف پول ناموفق' ] );
            wp_send_json_error( [ 'message' => 'کسر از کیف پول ناموفق بود.' ], 402 );
        }

        // Dispatch to provider.
        $result = $this->dispatcher->create_order( $m['provider'], [
            'service_id' => $m['service_id'],
            'target'     => $target,
            'quantity'   => $quantity,
            'extra'      => [ 'local_order' => $order_id ],
        ] );

        if ( empty( $result['ok'] ) ) {
            // Refund wallet, mark failed.
            $this->wallet->credit( $user_id, $price, 'بازگشت وجه سفارش #' . $order_id . ' (ناموفق)' );
            $this->orders->update( $order_id, [
                'status' => 'failed',
                'note'   => $result['error'] ?? 'خطای سرویس',
                'extra'  => [ 'provider_response' => $result['data'] ?? [] ],
            ] );
            wp_send_json_error( [ 'message' => $result['error'] ?: 'ثبت سفارش در سرویس ناموفق بود. وجه بازگردانده شد.' ], 502 );
        }

        // Success — persist provider order id & status.
        $this->orders->update( $order_id, [
            'provider_order_id' => $result['provider_order_id'],
            'status'            => $result['status'] ?: 'processing',
            'extra'             => $result['data'] ?? [],
        ] );

        wp_send_json_success( [
            'message'    => 'سفارش با موفقیت ثبت شد.',
            'order_id'   => $order_id,
            'status'     => $result['status'] ?: 'processing',
            'balance'    => $this->wallet->balance_display( $user_id ),
            'data'       => $this->public_order_data( $result['data'] ?? [] ),
        ] );
    }

    /* ── Poll a single order (for OTP / progress) ── */

    public function order_status(): void {
        check_ajax_referer( 'ug_front', 'nonce' );
        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $order    = $order_id ? $this->orders->get( $order_id ) : null;

        if ( ! $order || (int) $order['user_id'] !== get_current_user_id() ) {
            wp_send_json_error( [ 'message' => 'سفارش یافت نشد.' ], 404 );
        }

        wp_send_json_success( [
            'status'       => $order['status'],
            'status_label' => UG_Orders::status_label( $order['status'] ),
            'data'         => $this->public_order_data( $order['extra'] ),
        ] );
    }

    /* ── Admin: test a provider connection ─── */

    public function test_provider(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی مجاز نیست.' ], 403 );
        }
        check_ajax_referer( 'ug_test', '_wpnonce' );
        $provider = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : '';
        wp_send_json( $this->dispatcher->test( $provider ) );
    }

    /**
     * Whitelist which provider fields are exposed to the browser (e.g. OTP, number).
     */
    private function public_order_data( array $data ): array {
        $allow = [ 'number', 'otp', 'code', 'start_count', 'remains' ];
        return array_intersect_key( $data, array_flip( $allow ) );
    }
}
