<?php
/**
 * User panel — shortcodes for wallet, orders, profile, and the auth card.
 *
 * Shortcodes:
 *   [ug_panel]         full dashboard (sidebar router)
 *   [ug_dashboard]     welcome + wallet card + latest orders
 *   [ug_wallet]        wallet balance + quick topup button
 *   [ug_topup_form]    amount input + quick-pick chips → checkout
 *   [ug_wallet_tx]     transactions table
 *   [ug_order_form]    instant order form for a product
 *   [ug_my_orders]     order history with live OTP polling
 *   [ug_profile]       edit name/email + change password
 *   [ug_auth]          register/login card (SMS OTP + email/pass + Google)
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
    private UG_Auth $auth;

    public function __construct( UG_Wallet $wallet, UG_Orders $orders, UG_Settings $settings, UG_Auth $auth ) {
        $this->wallet   = $wallet;
        $this->orders   = $orders;
        $this->settings = $settings;
        $this->auth     = $auth;

        add_shortcode( 'ug_panel',        [ $this, 'sc_panel' ] );
        add_shortcode( 'ug_dashboard',    [ $this, 'sc_dashboard' ] );
        add_shortcode( 'ug_wallet',       [ $this, 'sc_wallet' ] );
        add_shortcode( 'ug_topup_form',   [ $this, 'sc_topup_form' ] );
        add_shortcode( 'ug_wallet_tx',    [ $this, 'sc_wallet_tx' ] );
        add_shortcode( 'ug_order_form',   [ $this, 'sc_order_form' ] );
        add_shortcode( 'ug_my_orders',    [ $this, 'sc_my_orders' ] );
        add_shortcode( 'ug_profile',      [ $this, 'sc_profile' ] );
        add_shortcode( 'ug_auth',         [ $this, 'sc_auth' ] );

        add_action( 'wp_enqueue_scripts', [ $this, 'assets' ] );
    }

    /* ══════════════ Assets ══════════════ */

    public function assets(): void {
        wp_register_style( 'ug-panel', UGC_URL . 'assets/panel.css', [], UGC_VERSION );
        wp_register_style( 'ug-auth',  UGC_URL . 'assets/auth.css',  [], UGC_VERSION );

        wp_register_script( 'ug-panel', UGC_URL . 'assets/panel.js', [ 'jquery' ], UGC_VERSION, true );
        wp_register_script( 'ug-auth',  UGC_URL . 'assets/auth.js',  [ 'jquery' ], UGC_VERSION, true );

        // Google Identity Services — only registered; only enqueued on auth pages.
        wp_register_script( 'ug-gsi',   'https://accounts.google.com/gsi/client', [], null, true );

        $data = [
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'ug_front' ),
            'panelUrl'       => home_url( '/panel/' ),
            'authUrl'        => home_url( '/auth/' ),
            'googleClientId' => $this->settings->get( 'google_client_id' ),
        ];
        wp_localize_script( 'ug-panel', 'ugPanel', $data );
        wp_localize_script( 'ug-auth',  'ugPanel', $data );

        wp_enqueue_style( 'ug-panel' );
        wp_enqueue_script( 'ug-panel' );
    }

    /**
     * Inline SVG icons for the panel nav (no emoji → renders everywhere,
     * including Iran hosts where the emoji CDN is blocked).
     */
    public static function nav_icon( string $key ): string {
        $p = [
            'dashboard' => '<path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/>',
            'services'  => '<path d="M13 2L3 14h7l-1 8 10-12h-7z"/>',
            'numbers'   => '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/>',
            'accounts'  => '<path d="M12 2l3 6 6 .5-4.5 4 1.5 6-6-3.5-6 3.5 1.5-6L3 8.5 9 8z"/>',
            'orders'    => '<path d="M4 4h4l2 12h9"/><circle cx="10" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M8 8h13l-1.5 6H10"/>',
            'wallet'    => '<rect x="3" y="6" width="18" height="13" rx="3"/><path d="M16 12h3"/><path d="M3 9h18"/>',
            'tx'        => '<path d="M3 3v18h18"/><path d="M7 14l3-4 3 3 4-6"/>',
            'profile'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
            'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        ];
        $d = $p[ $key ] ?? $p['dashboard'];
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">' . $d . '</svg>';
    }

    private function login_notice(): string {
        $url = UG_Guard::auth_url( get_permalink() );
        return '<div class="ug-notice">برای مشاهده این بخش ابتدا <a href="' . esc_url( $url ) . '">وارد شوید</a>.</div>';
    }

    /* ══════════════ [ug_wallet] ══════════════ */

    public function sc_wallet(): string {
        if ( ! is_user_logged_in() ) {
            return $this->login_notice();
        }
        ob_start();
        ?>
        <div class="ug-wallet-card">
            <div class="ug-wallet-label">موجودی کیف پول</div>
            <div class="ug-wallet-balance" id="ug-wallet-balance"><?php echo esc_html( $this->wallet->balance_display() ); ?></div>
            <a class="ug-btn ug-btn-primary" href="<?php echo esc_url( add_query_arg( 'section', 'wallet', home_url( '/panel/' ) ) ); ?>">شارژ کیف پول</a>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ══════════════ [ug_topup_form] ══════════════ */

    public function sc_topup_form(): string {
        if ( ! is_user_logged_in() ) {
            return $this->login_notice();
        }
        $min = UG_Wallet::MIN_TOPUP;
        ob_start();
        ?>
        <form class="ug-topup-form">
            <div class="ug-field">
                <span>مبلغ (تومان)</span>
                <input type="number" name="amount" min="<?php echo esc_attr( $min ); ?>" step="1000" placeholder="حداقل <?php echo esc_html( number_format_i18n( $min ) ); ?>" required>
            </div>
            <div class="ug-topup-chips">
                <button type="button" class="ug-chip" data-amount="50000">۵۰,۰۰۰</button>
                <button type="button" class="ug-chip" data-amount="100000">۱۰۰,۰۰۰</button>
                <button type="button" class="ug-chip" data-amount="200000">۲۰۰,۰۰۰</button>
                <button type="button" class="ug-chip" data-amount="500000">۵۰۰,۰۰۰</button>
                <button type="button" class="ug-chip" data-amount="1000000">۱,۰۰۰,۰۰۰</button>
            </div>
            <button type="submit" class="ug-btn ug-btn-primary">پرداخت و شارژ</button>
            <div class="ug-form-msg" style="display:none;"></div>
        </form>
        <?php
        return ob_get_clean();
    }

    /* ══════════════ [ug_wallet_tx] ══════════════ */

    public function sc_wallet_tx(): string {
        if ( ! is_user_logged_in() ) {
            return $this->login_notice();
        }
        $txs = $this->wallet->transactions( get_current_user_id(), 30 );
        ob_start();
        ?>
        <div class="ug-tx-wrap">
        <?php if ( empty( $txs ) ) : ?>
            <div class="ug-notice">هنوز تراکنشی ثبت نشده است.</div>
        <?php else : ?>
            <table class="ug-orders-table">
                <thead><tr><th>تاریخ</th><th>نوع</th><th>مبلغ</th><th>موجودی</th><th>توضیح</th></tr></thead>
                <tbody>
                    <?php foreach ( $txs as $t ) : ?>
                        <tr>
                            <td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $t['created_at'] ) ); ?></td>
                            <td>
                                <?php if ( 'credit' === $t['type'] ) : ?>
                                    <span class="ug-status ug-status-completed">شارژ</span>
                                <?php else : ?>
                                    <span class="ug-status ug-status-failed">برداشت</span>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?php echo esc_html( number_format_i18n( (float) $t['amount'] ) ); ?></td>
                            <td class="num"><?php echo esc_html( number_format_i18n( (float) $t['balance'] ) ); ?></td>
                            <td><?php echo esc_html( $t['description'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ══════════════ [ug_order_form] ══════════════ */

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

    /* ══════════════ [ug_my_orders] ══════════════ */

    public function sc_my_orders( $atts = [] ): string {
        if ( ! is_user_logged_in() ) {
            return $this->login_notice();
        }
        $atts   = shortcode_atts( [ 'limit' => 50 ], $atts );
        $orders = $this->orders->for_user( get_current_user_id(), (int) $atts['limit'] );
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

    /* ══════════════ [ug_profile] ══════════════ */

    public function sc_profile(): string {
        if ( ! is_user_logged_in() ) {
            return $this->login_notice();
        }
        $user  = wp_get_current_user();
        $phone = get_user_meta( $user->ID, '_ug_phone', true );
        ob_start();
        ?>
        <form class="ug-profile-form">
            <label class="ug-field">
                <span>نام و نام‌خانوادگی</span>
                <input type="text" name="name" value="<?php echo esc_attr( $user->display_name ); ?>">
            </label>
            <label class="ug-field">
                <span>ایمیل</span>
                <input type="email" name="email" value="<?php echo esc_attr( $user->user_email ); ?>">
            </label>
            <label class="ug-field">
                <span>شماره تلفن</span>
                <input type="text" value="<?php echo esc_attr( $phone ); ?>" disabled>
            </label>
            <hr class="ug-hr">
            <label class="ug-field">
                <span>رمز فعلی (برای تغییر رمز)</span>
                <input type="password" name="current_password" autocomplete="current-password">
            </label>
            <label class="ug-field">
                <span>رمز جدید</span>
                <input type="password" name="new_password" autocomplete="new-password">
            </label>
            <button type="submit" class="ug-btn ug-btn-primary">ذخیره تغییرات</button>
            <div class="ug-form-msg" style="display:none;"></div>
        </form>
        <?php
        return ob_get_clean();
    }

    /* ══════════════ [ug_dashboard] ══════════════ */

    public function sc_dashboard(): string {
        if ( ! is_user_logged_in() ) {
            return $this->login_notice();
        }
        $user   = wp_get_current_user();
        $orders = $this->orders->for_user( $user->ID, 5 );
        $first  = $user->first_name ?: $user->display_name;
        ob_start();
        ?>
        <div class="ug-dashboard">
            <div class="ug-hello">سلام <strong><?php echo esc_html( $first ); ?></strong> 👋 خوش آمدید!</div>
            <?php echo $this->sc_wallet(); ?>
            <h3 class="ug-panel-title">آخرین سفارش‌ها</h3>
            <?php echo $this->sc_my_orders( [ 'limit' => 5 ] ); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ══════════════ [ug_panel] — main dashboard router ══════════════ */

    public function sc_panel(): string {
        if ( ! is_user_logged_in() ) {
            return $this->login_notice();
        }
        $section = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'dashboard';
        $sections = [
            'dashboard' => [ 'label' => 'داشبورد',      'icon' => '🏠' ],
            'services'  => [ 'label' => 'خدمات مجازی',  'icon' => '🚀' ],
            'numbers'   => [ 'label' => 'شماره مجازی',  'icon' => '📱' ],
            'accounts'  => [ 'label' => 'اکانت پرمیوم', 'icon' => '💎' ],
            'orders'    => [ 'label' => 'سفارش‌ها',     'icon' => '📦' ],
            'wallet'    => [ 'label' => 'کیف پول',      'icon' => '💳' ],
            'tx'        => [ 'label' => 'تراکنش‌ها',    'icon' => '📊' ],
            'profile'   => [ 'label' => 'پروفایل',     'icon' => '👤' ],
        ];
        if ( ! isset( $sections[ $section ] ) ) {
            $section = 'dashboard';
        }
        $user   = wp_get_current_user();
        $avatar = get_user_meta( $user->ID, '_ug_avatar', true );

        ob_start();
        ?>
        <div class="ug-panel">
            <aside class="ug-panel-sidebar">
                <div class="ug-panel-user">
                    <?php if ( $avatar ) : ?>
                        <img class="ug-avatar" src="<?php echo esc_url( $avatar ); ?>" alt="">
                    <?php else : ?>
                        <div class="ug-avatar-initial"><?php echo esc_html( mb_substr( $user->display_name, 0, 1 ) ); ?></div>
                    <?php endif; ?>
                    <div class="ug-user-name"><?php echo esc_html( $user->display_name ); ?></div>
                    <div class="ug-user-email"><?php echo esc_html( $user->user_email ); ?></div>
                </div>
                <nav class="ug-panel-nav">
                    <?php foreach ( $sections as $key => $s ) :
                        $active = $key === $section ? ' active' : '';
                        $url    = add_query_arg( 'section', $key, home_url( '/panel/' ) );
                        ?>
                        <a class="ug-nav-item<?php echo esc_attr( $active ); ?>" href="<?php echo esc_url( $url ); ?>">
                            <span class="ug-nav-icon"><?php echo self::nav_icon( $key ); ?></span>
                            <span><?php echo esc_html( $s['label'] ); ?></span>
                        </a>
                    <?php endforeach; ?>
                    <a class="ug-nav-item ug-nav-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
                        <span class="ug-nav-icon"><?php echo self::nav_icon( 'logout' ); ?></span><span>خروج از حساب</span>
                    </a>
                </nav>
            </aside>
            <main class="ug-panel-content">
                <h2 class="ug-panel-heading"><?php echo esc_html( $sections[ $section ]['label'] ); ?></h2>
                <?php echo $this->render_section( $section ); ?>
            </main>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_section( string $section ): string {
        switch ( $section ) {
            case 'services':
                return '<p class="ug-panel-lead">اپلیکیشن موردنظر را انتخاب کنید، سپس نوع خدمت را برگزینید و همین‌جا سفارش دهید.</p>'
                    . do_shortcode( '[ug_services_app]' );
            case 'numbers':
                return '<p class="ug-panel-lead">اپلیکیشن موردنظر را انتخاب کنید تا شماره‌های مناسب آن نمایش داده شوند.</p>'
                    . do_shortcode( '[ug_numbers_app]' );
            case 'accounts':
                return $this->accounts_grid();
            case 'orders':
                return $this->sc_my_orders();
            case 'wallet':
                return $this->sc_wallet() . $this->sc_topup_form();
            case 'tx':
                return $this->sc_wallet_tx();
            case 'profile':
                return $this->sc_profile();
            default:
                return $this->sc_dashboard();
        }
    }

    /**
     * Premium-account cards → each links to its own product page.
     */
    private function accounts_grid(): string {
        $q = new WP_Query( [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [ [ 'key' => '_ug_kind', 'value' => 'account' ] ],
        ] );
        if ( ! $q->have_posts() ) {
            return '<div class="ug-notice">هنوز اکانتی اضافه نشده است. از پیشخوان › آپلودگرام › همگام‌سازی سرویس‌ها › «ساخت اکانت‌های پرمیوم نمونه» استفاده کنید.</div>';
        }
        ob_start();
        echo '<p class="ug-panel-lead">روی هر اکانت بزنید تا جزئیات و خرید آن باز شود.</p>';
        echo '<div class="ug-acc-grid">';
        foreach ( $q->posts as $pid ) {
            $product = wc_get_product( $pid );
            if ( ! $product ) {
                continue;
            }
            $img = $product->get_image( 'woocommerce_thumbnail' );
            printf(
                '<a class="ug-acc-item" href="%s"><div class="ug-acc-thumb">%s</div><div class="ug-acc-info"><div class="ug-acc-title">%s</div><div class="ug-acc-price">%s</div></div></a>',
                esc_url( get_permalink( $pid ) ),
                $img,
                esc_html( $product->get_name() ),
                wp_kses_post( wc_price( $product->get_price() ) )
            );
        }
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Country dial-codes for the phone selector (Iran default).
     */
    public static function countries(): array {
        return [
            [ '98', 'ایران', '🇮🇷' ], [ '1', 'آمریکا/کانادا', '🇺🇸' ], [ '44', 'انگلستان', '🇬🇧' ],
            [ '49', 'آلمان', '🇩🇪' ], [ '33', 'فرانسه', '🇫🇷' ], [ '39', 'ایتالیا', '🇮🇹' ],
            [ '34', 'اسپانیا', '🇪🇸' ], [ '7', 'روسیه', '🇷🇺' ], [ '90', 'ترکیه', '🇹🇷' ],
            [ '971', 'امارات', '🇦🇪' ], [ '966', 'عربستان', '🇸🇦' ], [ '974', 'قطر', '🇶🇦' ],
            [ '965', 'کویت', '🇰🇼' ], [ '968', 'عمان', '🇴🇲' ], [ '973', 'بحرین', '🇧🇭' ],
            [ '964', 'عراق', '🇮🇶' ], [ '90392', 'قبرس شمالی', '🇹🇷' ], [ '93', 'افغانستان', '🇦🇫' ],
            [ '92', 'پاکستان', '🇵🇰' ], [ '91', 'هند', '🇮🇳' ], [ '86', 'چین', '🇨🇳' ],
            [ '81', 'ژاپن', '🇯🇵' ], [ '82', 'کره جنوبی', '🇰🇷' ], [ '60', 'مالزی', '🇲🇾' ],
            [ '62', 'اندونزی', '🇮🇩' ], [ '66', 'تایلند', '🇹🇭' ], [ '65', 'سنگاپور', '🇸🇬' ],
            [ '61', 'استرالیا', '🇦🇺' ], [ '64', 'نیوزیلند', '🇳🇿' ], [ '20', 'مصر', '🇪🇬' ],
            [ '212', 'مراکش', '🇲🇦' ], [ '213', 'الجزایر', '🇩🇿' ], [ '216', 'تونس', '🇹🇳' ],
            [ '27', 'آفریقای جنوبی', '🇿🇦' ], [ '234', 'نیجریه', '🇳🇬' ], [ '31', 'هلند', '🇳🇱' ],
            [ '32', 'بلژیک', '🇧🇪' ], [ '41', 'سوئیس', '🇨🇭' ], [ '43', 'اتریش', '🇦🇹' ],
            [ '46', 'سوئد', '🇸🇪' ], [ '47', 'نروژ', '🇳🇴' ], [ '45', 'دانمارک', '🇩🇰' ],
            [ '358', 'فنلاند', '🇫🇮' ], [ '48', 'لهستان', '🇵🇱' ], [ '30', 'یونان', '🇬🇷' ],
            [ '351', 'پرتغال', '🇵🇹' ], [ '353', 'ایرلند', '🇮🇪' ], [ '380', 'اوکراین', '🇺🇦' ],
            [ '994', 'آذربایجان', '🇦🇿' ], [ '995', 'گرجستان', '🇬🇪' ], [ '374', 'ارمنستان', '🇦🇲' ],
            [ '55', 'برزیل', '🇧🇷' ], [ '52', 'مکزیک', '🇲🇽' ], [ '54', 'آرژانتین', '🇦🇷' ],
            [ '57', 'کلمبیا', '🇨🇴' ], [ '84', 'ویتنام', '🇻🇳' ], [ '63', 'فیلیپین', '🇵🇭' ],
        ];
    }

    /* ══════════════ [ug_auth] — login/register card ══════════════ */

    public function sc_auth(): string {
        if ( is_user_logged_in() ) {
            return '<div class="ug-notice">شما وارد شده‌اید. <a href="' . esc_url( home_url( '/panel/' ) ) . '">رفتن به پنل</a></div>';
        }

        wp_enqueue_style( 'ug-auth' );
        wp_enqueue_script( 'ug-auth' );
        if ( $this->settings->get( 'google_client_id' ) ) {
            wp_enqueue_script( 'ug-gsi' );
        }

        $client_id  = esc_attr( $this->settings->get( 'google_client_id' ) );
        $terms_url  = esc_url( $this->settings->get( 'terms_url', '#' ) );

        ob_start();
        ?>
        <div class="ug-auth-card" id="ug-auth">
            <div class="ug-auth-head">
                <div class="ug-auth-logo">آپلود<span>گرام</span></div>
                <div class="ug-auth-sub" id="ug-auth-sub">ورود یا ثبت‌نام با شماره موبایل</div>
            </div>

            <?php if ( $client_id ) : ?>
                <div class="ug-google-wrap"><div id="ug-google-btn" data-client-id="<?php echo $client_id; ?>"></div></div>
                <div class="ug-auth-divider"><span>یا</span></div>
            <?php endif; ?>

            <!-- STEP 1 — phone -->
            <form class="ug-auth-form" data-step="phone">
                <label class="ug-field">
                    <span>شماره موبایل</span>
                    <div class="ug-phone-row">
                        <select class="ug-country" name="dial">
                            <?php foreach ( self::countries() as $c ) : ?>
                                <option value="<?php echo esc_attr( $c[0] ); ?>" data-flag="<?php echo esc_attr( $c[2] ); ?>"<?php echo '98' === $c[0] ? ' selected' : ''; ?>>
                                    <?php echo $c[2] . ' ' . esc_html( $c[1] ) . ' +' . esc_html( $c[0] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="tel" name="phone" placeholder="9xxxxxxxxx" required inputmode="numeric" maxlength="14" autocomplete="tel">
                    </div>
                </label>
                <button type="submit" class="ug-btn ug-btn-primary">ادامه</button>
                <div class="ug-form-msg" style="display:none;"></div>
            </form>

            <!-- STEP 2a — existing user: choose method -->
            <div class="ug-auth-step" data-step="choose" style="display:none;">
                <div class="ug-auth-phone-badge"><span class="ug-auth-phone"></span> <button type="button" class="ug-auth-edit">تغییر</button></div>
                <div class="ug-method-grid">
                    <button type="button" class="ug-btn ug-btn-primary ug-method" data-method="otp">ورود با کد پیامکی</button>
                    <button type="button" class="ug-btn ug-btn-secondary ug-method" data-method="password">ورود با رمز عبور</button>
                </div>
            </div>

            <!-- STEP 2b — existing user: OTP login -->
            <form class="ug-auth-form" data-step="login-otp" style="display:none;">
                <div class="ug-auth-phone-badge"><span class="ug-auth-phone"></span> <button type="button" class="ug-auth-edit">تغییر</button></div>
                <label class="ug-field ug-code-field">
                    <span>کد ۶ رقمی پیامک‌شده</span>
                    <input type="text" name="code" inputmode="numeric" maxlength="6" placeholder="------">
                </label>
                <button type="button" class="ug-btn ug-btn-secondary ug-resend" data-purpose="login">ارسال مجدد کد</button>
                <button type="submit" class="ug-btn ug-btn-primary">ورود</button>
                <div class="ug-form-msg" style="display:none;"></div>
            </form>

            <!-- STEP 2c — existing user: password login -->
            <form class="ug-auth-form" data-step="login-password" style="display:none;">
                <div class="ug-auth-phone-badge"><span class="ug-auth-phone"></span> <button type="button" class="ug-auth-edit">تغییر</button></div>
                <input type="hidden" name="login" value="">
                <label class="ug-field">
                    <span>رمز عبور</span>
                    <input type="password" name="password" autocomplete="current-password" placeholder="رمز عبور شما">
                </label>
                <button type="button" class="ug-btn ug-btn-secondary ug-switch-otp">فراموشی رمز؟ ورود با کد پیامکی</button>
                <button type="submit" class="ug-btn ug-btn-primary">ورود</button>
                <div class="ug-form-msg" style="display:none;"></div>
            </form>

            <!-- STEP 3 — new user: register -->
            <form class="ug-auth-form" data-step="register" style="display:none;">
                <div class="ug-auth-phone-badge"><span class="ug-auth-phone"></span> <button type="button" class="ug-auth-edit">تغییر</button></div>
                <label class="ug-field">
                    <span>نام و نام‌خانوادگی</span>
                    <input type="text" name="name" autocomplete="name">
                </label>
                <label class="ug-field">
                    <span>ایمیل</span>
                    <input type="email" name="email" autocomplete="email">
                </label>
                <label class="ug-field">
                    <span>رمز عبور (حداقل ۶ کاراکتر)</span>
                    <input type="password" name="password" autocomplete="new-password" minlength="6">
                </label>
                <label class="ug-field ug-code-field">
                    <span>کد ۶ رقمی پیامک‌شده</span>
                    <input type="text" name="code" inputmode="numeric" maxlength="6" placeholder="------">
                </label>
                <button type="button" class="ug-btn ug-btn-secondary ug-resend" data-purpose="register">ارسال مجدد کد</button>
                <label class="ug-terms">
                    <input type="checkbox" name="terms">
                    <span><a href="<?php echo $terms_url; ?>" target="_blank">قوانین و شرایط استفاده</a> را می‌پذیرم</span>
                </label>
                <button type="submit" class="ug-btn ug-btn-primary">تکمیل ثبت‌نام</button>
                <div class="ug-form-msg" style="display:none;"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
