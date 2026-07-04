<?php
/**
 * Built-in wallet — no third-party wallet plugin required.
 *
 *  - Balance stored in user meta (_ug_wallet_balance) + full transaction log
 *    in the {prefix}ug_wallet_tx table.
 *  - Top-up flow: a hidden virtual "wallet top-up" WooCommerce product is
 *    purchased through the normal checkout (works with any Iranian payment
 *    gateway plugin). When the order is paid, the wallet is credited.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Wallet {

    const META_BALANCE = '_ug_wallet_balance';
    const OPT_TOPUP    = 'ugc_topup_product_id';
    const MIN_TOPUP    = 10000; // تومان

    public function __construct() {
        // Top-up purchase pipeline.
        add_action( 'init', [ $this, 'ensure_topup_product' ], 20 );
        add_filter( 'woocommerce_is_purchasable', [ $this, 'allow_topup_product' ], 20, 2 );
        add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'allow_topup_in_cart' ], 20, 2 );
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply_topup_price' ], 20 );
        add_action( 'woocommerce_payment_complete', [ $this, 'credit_from_order' ] );
        add_action( 'woocommerce_order_status_completed', [ $this, 'credit_from_order' ] );
        add_action( 'woocommerce_order_status_processing', [ $this, 'credit_from_order' ] );
    }

    /* ══════════════ Balance API ══════════════ */

    public function available(): bool {
        return true; // built-in — always available.
    }

    public function balance( int $user_id = 0 ): float {
        $user_id = $user_id ?: get_current_user_id();
        if ( ! $user_id ) {
            return 0.0;
        }
        return (float) get_user_meta( $user_id, self::META_BALANCE, true );
    }

    public function balance_display( int $user_id = 0 ): string {
        return number_format_i18n( $this->balance( $user_id ) ) . ' ' . __( 'تومان', 'uploadgram-core' );
    }

    /**
     * Debit (کسر). Atomic-ish: re-checks balance right before write.
     */
    public function debit( int $user_id, float $amount, string $description ): bool {
        if ( $amount <= 0 ) {
            return true;
        }
        $balance = $this->balance( $user_id );
        if ( $balance < $amount ) {
            return false;
        }
        $new = round( $balance - $amount, 2 );
        update_user_meta( $user_id, self::META_BALANCE, $new );
        $this->log_tx( $user_id, 'debit', $amount, $new, $description );
        return true;
    }

    /**
     * Credit (شارژ / بازگشت وجه).
     */
    public function credit( int $user_id, float $amount, string $description ): bool {
        if ( $amount <= 0 ) {
            return true;
        }
        $new = round( $this->balance( $user_id ) + $amount, 2 );
        update_user_meta( $user_id, self::META_BALANCE, $new );
        $this->log_tx( $user_id, 'credit', $amount, $new, $description );
        return true;
    }

    /* ══════════════ Transactions ══════════════ */

    public static function tx_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ug_wallet_tx';
    }

    private function log_tx( int $user_id, string $type, float $amount, float $balance, string $description ): void {
        global $wpdb;
        $wpdb->insert( self::tx_table(), [
            'user_id'     => $user_id,
            'type'        => $type,
            'amount'      => $amount,
            'balance'     => $balance,
            'description' => $description,
            'created_at'  => current_time( 'mysql' ),
        ] );
    }

    /**
     * Latest transactions for a user.
     */
    public function transactions( int $user_id, int $limit = 30 ): array {
        global $wpdb;
        $table = self::tx_table();
        $rows  = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d", $user_id, $limit ),
            ARRAY_A
        );
        return $rows ?: [];
    }

    /* ══════════════ Top-up via WooCommerce checkout ══════════════ */

    public function topup_product_id(): int {
        return (int) get_option( self::OPT_TOPUP, 0 );
    }

    /**
     * Create the hidden top-up product once.
     */
    public function ensure_topup_product(): void {
        if ( ! function_exists( 'wc_get_product' ) ) {
            return;
        }
        $id = $this->topup_product_id();
        if ( $id && wc_get_product( $id ) ) {
            return;
        }

        $product = new WC_Product_Simple();
        $product->set_name( 'شارژ کیف پول' );
        $product->set_slug( 'wallet-topup' );
        $product->set_regular_price( '1000' );
        $product->set_virtual( true );
        $product->set_catalog_visibility( 'hidden' );
        $product->set_sold_individually( true );
        $pid = $product->save();

        if ( $pid ) {
            update_option( self::OPT_TOPUP, $pid );
        }
    }

    /**
     * The store disables purchasing globally — re-enable it only for top-up.
     */
    public function allow_topup_product( $purchasable, $product ) {
        if ( $product && (int) $product->get_id() === $this->topup_product_id() ) {
            return true;
        }
        return $purchasable;
    }

    public function allow_topup_in_cart( $passed, $product_id ) {
        if ( (int) $product_id === $this->topup_product_id() ) {
            return true;
        }
        return $passed;
    }

    /**
     * Start a top-up: empties the cart, adds the top-up product with the
     * requested amount, returns the checkout URL.
     */
    public function start_topup( float $amount ): array {
        $pid = $this->topup_product_id();
        if ( ! $pid || ! function_exists( 'WC' ) || null === WC()->cart ) {
            return [ 'ok' => false, 'error' => 'فروشگاه در دسترس نیست.' ];
        }
        if ( $amount < self::MIN_TOPUP ) {
            return [ 'ok' => false, 'error' => 'حداقل مبلغ شارژ ' . number_format_i18n( self::MIN_TOPUP ) . ' تومان است.' ];
        }

        WC()->cart->empty_cart();
        $key = WC()->cart->add_to_cart( $pid, 1, 0, [], [ 'ug_topup_amount' => round( $amount ) ] );

        if ( ! $key ) {
            return [ 'ok' => false, 'error' => 'افزودن شارژ به صورت‌حساب ناموفق بود.' ];
        }
        return [ 'ok' => true, 'redirect' => wc_get_checkout_url() ];
    }

    /**
     * Charge the cart line with the user-chosen amount.
     */
    public function apply_topup_price( $cart ): void {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }
        foreach ( $cart->get_cart() as $item ) {
            if ( ! empty( $item['ug_topup_amount'] ) && isset( $item['data'] ) ) {
                $item['data']->set_price( (float) $item['ug_topup_amount'] );
            }
        }
    }

    /**
     * When a top-up order is paid → credit the wallet (once).
     */
    public function credit_from_order( $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_meta( '_ug_wallet_credited' ) ) {
            return;
        }

        $topup_id = $this->topup_product_id();
        $user_id  = (int) $order->get_user_id();
        if ( ! $topup_id || ! $user_id ) {
            return;
        }

        $amount = 0.0;
        foreach ( $order->get_items() as $item ) {
            if ( (int) $item->get_product_id() === $topup_id ) {
                $amount += (float) $item->get_total();
            }
        }
        if ( $amount <= 0 ) {
            return;
        }

        $this->credit( $user_id, $amount, sprintf( 'شارژ کیف پول — سفارش #%d', $order_id ) );
        $order->update_meta_data( '_ug_wallet_credited', 1 );
        $order->save();
        UG_Logger::info( 'Wallet credited from order', [ 'order' => $order_id, 'user' => $user_id, 'amount' => $amount ] );
    }
}
