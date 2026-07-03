<?php
/**
 * User panel — shortcodes for wallet, instant order form, and order history.
 *
 * Shortcodes:
 *   [ug_panel]                     full dashboard (wallet + orders)
 *   [ug_wallet]                    wallet balance + recharge button
 *   [ug_order_form id="123"]       instant order form for a product
 *   [ug_my_orders]                 order history (with live OTP polling)
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Panel {

    private UG_Wallet $wallet;
    private UG_Orders $orders;
    private UG_Settings $settings;

    public function __construct( UG_Wallet $wallet, UG_Orders $orders, UG_Settings $settings ) {
        $this->wallet   = $wallet;
        $this->orders   = $orders;
        $this->settings = $settings;

        add_shortcode( 'ug_panel', [ $this, 'sc_panel' ] );
        add_shortcode( 'ug_wallet', [ $this, 'sc_wallet' ] );
        add_shortcode( 'ug_order_form', [ $this, 'sc_order_form' ] );
        add_shortcode( 'ug_my_orders', [ $this, 'sc_my_orders' ] );

        add_action( 'wp_enqueue_scripts', [ $this, 'assets' ] );
    }

    public function assets(): void {
        wp_register_style( 'ug-panel', UGC_URL . 'assets/panel.css', [], UGC_VERSION );
        wp_register_script( 'ug-panel', UGC_URL . 'assets/panel.js', [ 'jquery' ], UGC_VERSION, true );
        wp_localize_script( 'ug-panel', 'ugPanel', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ug_front' ),
        ] );
        wp_enqueue_style( 'ug-panel' );
        wp_enqueue_script( 'ug-panel' );
    }

    private function login_notice(): string {
        return '<div class="ug-notice">برای مشاهده این بخش ابتدا <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">وارد شوید</a>.</div>';
    }

    /* ── [ug_wallet] ───────────────────────── */

    public function sc_wallet(): string {
        if ( ! is_user_logged_in() ) {
            return $this->login_notice();
        }
        $recharge = $this->recharge_url();
        ob_start();
        ?>
        <div class="ug-wallet-card">
            <div class="ug-wallet-label">موجودی کیف پول</div>
            <div class="ug-wallet-balance" id="ug-wallet-balance"><?php echo esc_html( $this->wallet->balance_display() ); ?></div>
            <?php if ( $recharge ) : ?>
                <a class="ug-btn ug-btn-primary" href="<?php echo esc_url( $recharge ); ?>">شارژ کیف پول</a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Best-effort recharge URL (TeraWallet page if present).
     */
    private function recharge_url(): string {
        $page_id = (int) get_option( 'woo_wallet_settings' )['wallet_endpoint'] ?? 0;
        if ( function_exists( 'woo_wallet' ) && function_exists( 'get_wallet_url' ) ) {
            return get_wallet_url();
        }
        $custom = $this->settings->get( 'recharge_url' );
        return $custom ?: '';
    }

    /* ── [ug_order_form id=".."] ───────────── */

    public function sc_order_form( $atts ): string {
        $atts = shortcode_atts( [ 'id' => 0 ], $atts );
        $pid  = absint( $atts['id'] );
        if ( ! $pid ) {
            global $product;
            if ( $product instanceof WC_Product ) {
                $pid = $product->get_id();
            }
        }
        $product = $pid ? wc_get_product( $pid ) : null;
        if ( ! $product ) {
            return '<div class="ug-notice">محصول یافت نشد.</div>';
        }
        if ( ! is_user_logged_in() ) {
            return $this->login_notice();
        }

        $m       = UG_WC_Integration::meta( $pid );
        $is_count = ( 'none' !== $m['input_type'] && ! $m['fixed'] );

        ob_start();
        ?>
        <form class="ug-order-form" data-product="<?php echo esc_attr( $pid ); ?>"
              data-rate="<?php echo esc_attr( $m['rate'] ); ?>"
              data-fixed="<?php echo $m['fixed'] ? '1' : '0'; ?>"
              data-min="<?php echo esc_attr( $m['min'] ); ?>"
              data-max="<?php echo esc_attr( $m['max'] ); ?>">

            <?php if ( 'none' !== $m['input_type'] ) : ?>
                <label class="ug-field">
                    <span><?php echo 'username' === $m['input_type'] ? 'یوزرنیم' : 'لینک'; ?></span>
                    <input type="text" name="target" placeholder="<?php echo 'username' === $m['input_type'] ? '@username' : 'https://...'; ?>" required>
                </label>
            <?php endif; ?>

            <?php if ( $is_count ) : ?>
                <label class="ug-field">
                    <span>تعداد</span>
                    <input type="number" name="quantity" min="<?php echo esc_attr( $m['min'] ?: 1 ); ?>"
                           max="<?php echo esc_attr( $m['max'] ?: '' ); ?>" value="<?php echo esc_attr( $m['min'] ?: 1000 ); ?>" required>
                </label>
                <?php if ( $m['min'] || $m['max'] ) : ?>
                    <div class="ug-hint">حداقل <?php echo esc_html( number_format_i18n( $m['min'] ) ); ?> — حداکثر <?php echo esc_html( number_format_i18n( $m['max'] ) ); ?></div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="ug-price-row">
                <span>مبلغ قابل پرداخت:</span>
                <strong class="ug-calc-price" data-price="<?php echo esc_attr( $product->get_price() ); ?>">
                    <?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?>
                </strong>
            </div>

            <button type="submit" class="ug-btn ug-btn-primary ug-submit">ثبت سفارش</button>
            <div class="ug-form-msg" style="display:none;"></div>
            <div class="ug-otp-box" style="display:none;"></div>
        </form>
        <?php
        return ob_get_clean();
    }

    /* ── [ug_my_orders] ────────────────────── */

    public function sc_my_orders(): string {
        if ( ! is_user_logged_in() ) {
            return $this->login_notice();
        }
        $orders = $this->orders->for_user( get_current_user_id(), 50 );

        ob_start();
        ?>
        <div class="ug-orders">
            <?php if ( empty( $orders ) ) : ?>
                <div class="ug-notice">هنوز سفارشی ثبت نکرده‌اید.</div>
            <?php else : ?>
                <table class="ug-orders-table">
                    <thead>
                        <tr><th>#</th><th>خدمت</th><th>تعداد</th><th>مبلغ</th><th>وضعیت</th><th>جزئیات</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $orders as $o ) :
                        $product = wc_get_product( $o['product_id'] );
                        $otp     = $o['extra']['otp'] ?? ( $o['extra']['code'] ?? '' );
                        $number  = $o['extra']['number'] ?? '';
                        ?>
                        <tr data-order="<?php echo esc_attr( $o['id'] ); ?>" data-status="<?php echo esc_attr( $o['status'] ); ?>">
                            <td><?php echo (int) $o['id']; ?></td>
                            <td><?php echo esc_html( $product ? $product->get_name() : '—' ); ?></td>
                            <td class="num"><?php echo esc_html( number_format_i18n( $o['quantity'] ) ); ?></td>
                            <td class="num"><?php echo wp_kses_post( wc_price( $o['amount'] ) ); ?></td>
                            <td><span class="ug-status ug-status-<?php echo esc_attr( $o['status'] ); ?>"><?php echo esc_html( UG_Orders::status_label( $o['status'] ) ); ?></span></td>
                            <td class="ug-order-detail">
                                <?php if ( $number ) : ?><div>شماره: <code><?php echo esc_html( $number ); ?></code></div><?php endif; ?>
                                <?php if ( $otp ) : ?><div>کد: <code class="ug-otp"><?php echo esc_html( $otp ); ?></code></div><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ── [ug_panel] ────────────────────────── */

    public function sc_panel(): string {
        if ( ! is_user_logged_in() ) {
            return $this->login_notice();
        }
        return '<div class="ug-panel">'
            . '<div class="ug-panel-head">' . $this->sc_wallet() . '</div>'
            . '<h3 class="ug-panel-title">سفارش‌های من</h3>'
            . $this->sc_my_orders()
            . '</div>';
    }
}
