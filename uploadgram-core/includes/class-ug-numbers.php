<?php
/**
 * Virtual numbers (HeroSMS) — dynamic catalogue UI + buy/poll flow.
 *
 *   [ug_numbers_app]                → pick a service → pick a country (flag,
 *                                     live price, stock) → buy a number from
 *                                     the wallet → live OTP polling + cancel.
 *   [ug_numbers_app view="showcase"] → public, prices only, buy button sends
 *                                      guests to /auth/.
 *
 * Nothing is pre-created as a WooCommerce product: HeroSMS offers hundreds of
 * services across hundreds of countries, so numbers are bought on-demand and
 * orders are stored in the UG_Orders table (provider = herosms).
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Numbers {

    private UG_Dispatcher $dispatcher;
    private UG_Wallet $wallet;
    private UG_Orders $orders;
    private UG_Settings $settings;
    private UG_HeroSMS_Catalog $catalog;

    public function __construct( UG_Dispatcher $dispatcher, UG_Wallet $wallet, UG_Orders $orders, UG_Settings $settings, UG_HeroSMS_Catalog $catalog ) {
        $this->dispatcher = $dispatcher;
        $this->wallet     = $wallet;
        $this->orders     = $orders;
        $this->settings   = $settings;
        $this->catalog    = $catalog;

        add_shortcode( 'ug_numbers_app', [ $this, 'render' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'assets' ] );

        add_action( 'wp_ajax_ug_number_countries',        [ $this, 'ajax_countries' ] );
        add_action( 'wp_ajax_nopriv_ug_number_countries', [ $this, 'ajax_countries' ] );
        add_action( 'wp_ajax_ug_buy_number',    [ $this, 'ajax_buy' ] );
        add_action( 'wp_ajax_ug_number_status', [ $this, 'ajax_status' ] );
        add_action( 'wp_ajax_ug_number_cancel', [ $this, 'ajax_cancel' ] );
    }

    public function assets(): void {
        wp_register_style( 'ug-numbers', UGC_URL . 'assets/numbers.css', [], UGC_VERSION );
        wp_register_script( 'ug-numbers', UGC_URL . 'assets/numbers.js', [ 'jquery' ], UGC_VERSION, true );
        wp_localize_script( 'ug-numbers', 'ugNum', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ug_front' ),
            'loggedIn' => is_user_logged_in(),
            'authUrl'  => home_url( '/auth/' ),
        ] );
    }

    private function asset( string $p ): string {
        return function_exists( 'ug_asset' ) ? ug_asset( $p ) : ( get_template_directory_uri() . '/assets/' . ltrim( $p, '/' ) );
    }

    /* ══════════════ Shortcode ══════════════ */

    public function render( $atts = [] ): string {
        wp_enqueue_style( 'ug-numbers' );
        wp_enqueue_script( 'ug-numbers' );

        $a    = shortcode_atts( [ 'view' => 'buy' ], (array) $atts );
        $view = ( 'showcase' === $a['view'] ) ? 'showcase' : 'buy';

        $services = $this->catalog->services();
        if ( empty( $services ) ) {
            return '<div class="ug-app-empty">در حال حاضر سرویس شماره مجازی در دسترس نیست. (کلید API هیرو‌اس‌ام‌اس را در پیشخوان › آپلودگرام › هیرو‌اس‌ام‌اس وارد کنید و «به‌روزرسانی فهرست» را بزنید.)</div>';
        }

        ob_start(); ?>
        <div class="ug-nums" data-view="<?php echo esc_attr( $view ); ?>">
          <div class="ug-nums-toolbar">
            <input type="search" class="ug-nums-search" placeholder="جستجوی سرویس (مثلاً تلگرام، واتساپ، اینستاگرام)…" aria-label="جستجوی سرویس">
          </div>
          <div class="ug-nums-services">
            <?php foreach ( $services as $i => $s ) :
                $label = $s['fa'] ?: $s['name']; ?>
              <button type="button" class="ug-nums-svc<?php echo 0 === $i ? ' active' : ''; ?>"
                      data-code="<?php echo esc_attr( $s['code'] ); ?>"
                      data-name="<?php echo esc_attr( $label ); ?>"
                      data-search="<?php echo esc_attr( mb_strtolower( $label . ' ' . $s['name'] . ' ' . $s['code'] ) ); ?>">
                <img src="<?php echo esc_url( $this->asset( $s['icon'] ) ); ?>" alt="" loading="lazy">
                <span><?php echo esc_html( $label ); ?></span>
              </button>
            <?php endforeach; ?>
          </div>
          <div class="ug-nums-panel">
            <div class="ug-nums-hint">یک سرویس را انتخاب کنید تا کشورها و قیمت‌ها نمایش داده شود.</div>
            <div class="ug-nums-countries"></div>
          </div>
          <div class="ug-nums-active"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ══════════════ AJAX: countries for a service ══════════════ */

    public function ajax_countries(): void {
        check_ajax_referer( 'ug_front', 'nonce' );
        $service = isset( $_POST['service'] ) ? sanitize_text_field( wp_unslash( $_POST['service'] ) ) : '';
        if ( '' === $service ) {
            wp_send_json_error( [ 'message' => 'سرویس مشخص نشده است.' ], 400 );
        }
        $rows = $this->catalog->service_countries( $service );
        if ( empty( $rows ) ) {
            wp_send_json_error( [ 'message' => 'برای این سرویس در حال حاضر شماره‌ای موجود نیست.' ], 404 );
        }
        wp_send_json_success( [ 'countries' => $rows ] );
    }

    /* ══════════════ AJAX: buy a number ══════════════ */

    public function ajax_buy(): void {
        check_ajax_referer( 'ug_front', 'nonce' );
        $user_id = UG_Guard::require_user_or_die();

        $service = isset( $_POST['service'] ) ? sanitize_text_field( wp_unslash( $_POST['service'] ) ) : '';
        $country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
        $label   = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : 'شماره مجازی';

        if ( '' === $service || '' === $country ) {
            wp_send_json_error( [ 'message' => 'سرویس یا کشور انتخاب نشده است.' ], 400 );
        }

        // Authoritative price from the catalogue (never trust the client).
        $q = $this->catalog->quote( $service, $country );
        if ( empty( $q['ok'] ) ) {
            wp_send_json_error( [ 'message' => $q['error'] ?? 'قیمت این سرویس در دسترس نیست.' ], 400 );
        }
        $price = (int) $q['price'];
        if ( $price <= 0 ) {
            wp_send_json_error( [ 'message' => 'محاسبهٔ قیمت ناموفق بود.' ], 400 );
        }

        if ( $this->wallet->balance( $user_id ) < $price ) {
            wp_send_json_error( [
                'message'  => 'موجودی کیف پول کافی نیست.',
                'need'     => $price,
                'balance'  => $this->wallet->balance( $user_id ),
                'recharge' => true,
            ], 402 );
        }

        // Create local order first (pending).
        $order_id = $this->orders->create( [
            'user_id'    => $user_id,
            'product_id' => 0,
            'provider'   => 'herosms',
            'service_id' => $service . '|' . $country,
            'target'     => '',
            'quantity'   => 1,
            'amount'     => $price,
            'currency'   => $this->settings->default_currency(),
            'status'     => 'pending',
        ] );
        if ( ! $order_id ) {
            wp_send_json_error( [ 'message' => 'ثبت سفارش ناموفق بود.' ], 500 );
        }

        // Debit wallet BEFORE reserving the number.
        $desc    = sprintf( 'خرید شمارهٔ مجازی #%d — %s', $order_id, $label );
        $debited = $this->wallet->debit( $user_id, $price, $desc );
        if ( ! $debited ) {
            $this->orders->update( $order_id, [ 'status' => 'failed', 'note' => 'کسر از کیف پول ناموفق' ] );
            wp_send_json_error( [ 'message' => 'کسر از کیف پول ناموفق بود.' ], 402 );
        }

        // Reserve the number from the provider.
        $result = $this->dispatcher->create_order( 'herosms', [
            'service_id' => $service . '|' . $country,
            'extra'      => [ 'local_order' => $order_id, 'label' => $label ],
        ] );

        if ( empty( $result['ok'] ) ) {
            $this->wallet->credit( $user_id, $price, 'بازگشت وجه شمارهٔ #' . $order_id . ' (ناموفق)' );
            $this->orders->update( $order_id, [
                'status' => 'failed',
                'note'   => $result['error'] ?? 'خطای سرویس',
                'extra'  => [ '_refunded' => 1, 'provider_response' => $result['data'] ?? [] ],
            ] );
            wp_send_json_error( [ 'message' => $result['error'] ?: 'دریافت شماره ناموفق بود. وجه بازگردانده شد.' ], 502 );
        }

        $number = (string) ( $result['data']['number'] ?? '' );
        $this->orders->update( $order_id, [
            'provider_order_id' => $result['provider_order_id'],
            'status'            => $result['status'] ?: 'awaiting_otp',
            'extra'             => array_merge( (array) ( $result['data'] ?? [] ), [ 'label' => $label ] ),
        ] );

        wp_send_json_success( [
            'message'  => 'شماره با موفقیت رزرو شد. منتظر دریافت کد بمانید.',
            'order_id' => $order_id,
            'number'   => $number,
            'label'    => $label,
            'price'    => number_format( $price ) . ' تومان',
            'balance'  => $this->wallet->balance_display( $user_id ),
        ] );
    }

    /* ══════════════ AJAX: live status / OTP ══════════════ */

    public function ajax_status(): void {
        check_ajax_referer( 'ug_front', 'nonce' );
        $order = $this->owned_order();

        // Already terminal? return stored data.
        if ( in_array( $order['status'], [ 'completed', 'canceled', 'failed', 'refunded' ], true ) ) {
            wp_send_json_success( $this->status_payload( $order ) );
        }

        $res = $this->dispatcher->order_status( 'herosms', (string) $order['provider_order_id'], $order['extra'] );
        if ( empty( $res['ok'] ) ) {
            wp_send_json_success( $this->status_payload( $order ) ); // transient — keep waiting.
        }

        $new = $res['status'];
        if ( $new !== $order['status'] ) {
            $extra = array_merge( (array) $order['extra'], (array) ( $res['data'] ?? [] ) );
            if ( 'canceled' === $new ) {
                $extra = $this->refund_once( $order, $extra, 'انقضا/لغو شماره' );
            }
            $this->orders->update( (int) $order['id'], [ 'status' => $new, 'extra' => $extra ] );
            $order['status'] = $new;
            $order['extra']  = $extra;
        }
        wp_send_json_success( $this->status_payload( $order ) );
    }

    /* ══════════════ AJAX: cancel (instant refund) ══════════════ */

    public function ajax_cancel(): void {
        check_ajax_referer( 'ug_front', 'nonce' );
        $order = $this->owned_order();

        if ( in_array( $order['status'], [ 'completed', 'canceled', 'failed', 'refunded' ], true ) ) {
            wp_send_json_error( [ 'message' => 'این سفارش قابل لغو نیست.' ], 409 );
        }

        $p = $this->dispatcher->provider( 'herosms' );
        if ( $p instanceof UG_Provider_HeroSMS ) {
            $p->cancel( (string) $order['provider_order_id'] );
        }
        $extra = $this->refund_once( $order, (array) $order['extra'], 'لغو توسط کاربر' );
        $this->orders->update( (int) $order['id'], [ 'status' => 'canceled', 'extra' => $extra ] );

        wp_send_json_success( [
            'message' => 'شماره لغو و وجه به کیف پول بازگردانده شد.',
            'balance' => $this->wallet->balance_display( (int) $order['user_id'] ),
        ] );
    }

    /* ══════════════ helpers ══════════════ */

    private function owned_order(): array {
        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $order    = $order_id ? $this->orders->get( $order_id ) : null;
        if ( ! $order || (int) $order['user_id'] !== get_current_user_id() ) {
            wp_send_json_error( [ 'message' => 'سفارش یافت نشد.' ], 404 );
        }
        return $order;
    }

    /** Refund the wallet exactly once (idempotent via the _refunded flag). */
    private function refund_once( array $order, array $extra, string $reason ): array {
        if ( empty( $extra['_refunded'] ) ) {
            $this->wallet->credit( (int) $order['user_id'], (float) $order['amount'], sprintf( 'بازگشت وجه شمارهٔ #%d (%s)', $order['id'], $reason ) );
            $extra['_refunded'] = 1;
        }
        return $extra;
    }

    private function status_payload( array $order ): array {
        $extra = (array) $order['extra'];
        return [
            'status'       => $order['status'],
            'status_label' => UG_Orders::status_label( $order['status'] ),
            'number'       => $extra['number'] ?? '',
            'otp'          => $extra['otp'] ?? $extra['code'] ?? '',
        ];
    }
}
