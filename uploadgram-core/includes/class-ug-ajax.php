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
    private UG_Otp $otp;
    private UG_Auth $auth;
    private UG_Google_Auth $google;

    public function __construct( UG_Dispatcher $dispatcher, UG_Wallet $wallet, UG_Orders $orders, UG_Settings $settings, UG_Otp $otp, UG_Auth $auth, UG_Google_Auth $google ) {
        $this->dispatcher = $dispatcher;
        $this->wallet     = $wallet;
        $this->orders     = $orders;
        $this->settings   = $settings;
        $this->otp        = $otp;
        $this->auth       = $auth;
        $this->google     = $google;

        add_action( 'wp_ajax_ug_place_order', [ $this, 'place_order' ] );
        add_action( 'wp_ajax_ug_order_status', [ $this, 'order_status' ] );
        add_action( 'wp_ajax_ug_test_provider', [ $this, 'test_provider' ] );
        add_action( 'wp_ajax_ug_test_sms', [ $this, 'test_sms' ] );
        add_action( 'wp_ajax_ug_vless_xray', [ $this, 'vless_xray' ] );
        add_action( 'wp_ajax_ug_topup', [ $this, 'topup' ] );
        add_action( 'wp_ajax_ug_update_profile', [ $this, 'update_profile' ] );
        add_action( 'wp_ajax_nopriv_ug_register', [ $this, 'register' ] );
        add_action( 'wp_ajax_nopriv_ug_login', [ $this, 'login' ] );
        add_action( 'wp_ajax_nopriv_ug_check_phone', [ $this, 'check_phone' ] );
        add_action( 'wp_ajax_nopriv_ug_send_otp', [ $this, 'send_otp' ] );
        add_action( 'wp_ajax_ug_send_otp', [ $this, 'send_otp' ] );
        add_action( 'wp_ajax_nopriv_ug_verify_otp_register', [ $this, 'verify_otp_register' ] );
        add_action( 'wp_ajax_nopriv_ug_verify_otp_login', [ $this, 'verify_otp_login' ] );
        add_action( 'wp_ajax_nopriv_ug_google_signin', [ $this, 'google_signin' ] );
    }

    /* ── Detect if a phone belongs to an existing user ── */

    public function check_phone(): void {
        check_ajax_referer( 'ug_front', 'nonce' );
        $phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $phone = UG_Otp::normalize_phone( $phone );

        if ( ! UG_Otp::is_valid_phone( $phone ) ) {
            wp_send_json_error( [ 'message' => 'شماره موبایل معتبر نیست.' ], 400 );
        }

        $users  = get_users( [ 'meta_key' => '_ug_phone', 'meta_value' => $phone, 'number' => 1, 'fields' => 'ID' ] );
        $exists = ! empty( $users );

        // Does the existing user have a usable password? (phone-only accounts may not.)
        $has_password = false;
        if ( $exists ) {
            $u = get_userdata( (int) $users[0] );
            // Accounts created via phone get a random password, so password login
            // is always technically possible; expose the option regardless.
            $has_password = (bool) $u;
        }

        wp_send_json_success( [
            'exists'       => $exists,
            'has_password' => $has_password,
            'phone'        => $phone,
        ] );
    }

    /* ── OTP: send code ─────────────── */

    public function send_otp(): void {
        check_ajax_referer( 'ug_front', 'nonce' );
        $phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $purpose = isset( $_POST['purpose'] ) ? sanitize_key( wp_unslash( $_POST['purpose'] ) ) : 'login';

        $res = $this->otp->send( $phone, $purpose );
        if ( empty( $res['ok'] ) ) {
            wp_send_json_error( [ 'message' => $res['error'], 'wait' => $res['wait'] ?? 0 ], 400 );
        }
        wp_send_json_success( [ 'message' => 'کد تأیید ارسال شد.', 'wait' => $res['wait'] ?? UG_Otp::RESEND_COOLDOWN ] );
    }

    /* ── OTP: verify + register (creates account) ── */

    public function verify_otp_register(): void {
        check_ajax_referer( 'ug_front', 'nonce' );

        $phone = isset( $_POST['phone'] )    ? sanitize_text_field( wp_unslash( $_POST['phone'] ) )    : '';
        $code  = isset( $_POST['code'] )     ? sanitize_text_field( wp_unslash( $_POST['code'] ) )     : '';
        $name  = isset( $_POST['name'] )     ? sanitize_text_field( wp_unslash( $_POST['name'] ) )     : '';
        $email = isset( $_POST['email'] )    ? sanitize_email( wp_unslash( $_POST['email'] ) )         : '';
        $pass  = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] )               : '';
        $terms = ! empty( $_POST['terms'] );

        if ( ! $terms ) {
            wp_send_json_error( [ 'message' => 'برای ثبت‌نام باید قوانین را بپذیرید.' ], 400 );
        }
        if ( '' === $name ) {
            wp_send_json_error( [ 'message' => 'نام و نام‌خانوادگی الزامی است.' ], 400 );
        }
        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => 'ایمیل معتبر وارد کنید.' ], 400 );
        }
        if ( strlen( $pass ) < 6 ) {
            wp_send_json_error( [ 'message' => 'رمز عبور باید حداقل ۶ کاراکتر باشد.' ], 400 );
        }

        // 1) Verify OTP.
        $v = $this->otp->verify( $phone, $code, 'register' );
        if ( empty( $v['ok'] ) ) {
            wp_send_json_error( [ 'message' => $v['error'] ], 400 );
        }

        // 2) Create user.
        $r = $this->auth->find_or_create_by_phone( $phone, $name, $email, $pass );
        if ( empty( $r['ok'] ) ) {
            wp_send_json_error( [ 'message' => $r['error'] ], 400 );
        }
        if ( empty( $r['created'] ) ) {
            wp_send_json_error( [ 'message' => 'این شماره قبلاً ثبت شده. لطفاً وارد شوید.' ], 409 );
        }

        $this->auth->sign_in( (int) $r['user_id'] );
        wp_send_json_success( [ 'message' => 'ثبت‌نام موفق! خوش آمدید.', 'redirect' => home_url( '/panel/' ) ] );
    }

    /* ── OTP: verify + login (existing user) ── */

    public function verify_otp_login(): void {
        check_ajax_referer( 'ug_front', 'nonce' );

        $phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $code  = isset( $_POST['code'] )  ? sanitize_text_field( wp_unslash( $_POST['code'] ) )  : '';

        $v = $this->otp->verify( $phone, $code, 'login' );
        if ( empty( $v['ok'] ) ) {
            wp_send_json_error( [ 'message' => $v['error'] ], 400 );
        }

        $phone_n = UG_Otp::normalize_phone( $phone );
        $users   = get_users( [ 'meta_key' => '_ug_phone', 'meta_value' => $phone_n, 'number' => 1, 'fields' => 'ID' ] );
        if ( empty( $users ) ) {
            wp_send_json_error( [ 'message' => 'حساب کاربری با این شماره پیدا نشد. ابتدا ثبت‌نام کنید.' ], 404 );
        }

        $this->auth->sign_in( (int) $users[0] );
        wp_send_json_success( [ 'message' => 'ورود موفق! خوش آمدید.', 'redirect' => home_url( '/panel/' ) ] );
    }

    /* ── Google Sign-In ── */

    public function google_signin(): void {
        check_ajax_referer( 'ug_front', 'nonce' );
        $token = isset( $_POST['credential'] ) ? (string) wp_unslash( $_POST['credential'] ) : '';
        if ( '' === $token ) {
            wp_send_json_error( [ 'message' => 'توکن گوگل ارسال نشد.' ], 400 );
        }

        $claims = $this->google->verify_id_token( $token );
        if ( ! $claims ) {
            wp_send_json_error( [ 'message' => 'اعتبارسنجی توکن گوگل ناموفق بود.' ], 401 );
        }

        $r = $this->auth->find_or_create_by_google( $claims );
        if ( empty( $r['ok'] ) ) {
            wp_send_json_error( [ 'message' => $r['error'] ], 400 );
        }
        $this->auth->sign_in( (int) $r['user_id'] );
        wp_send_json_success( [ 'message' => 'با گوگل وارد شدید.', 'redirect' => home_url( '/panel/' ) ] );
    }

    /* ── Profile update ── */

    public function update_profile(): void {
        check_ajax_referer( 'ug_front', 'nonce' );
        $user_id = UG_Guard::require_user_or_die();

        $name = isset( $_POST['name'] )  ? sanitize_text_field( wp_unslash( $_POST['name'] ) )  : '';
        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) )     : '';
        $new_pass = isset( $_POST['new_password'] ) ? (string) wp_unslash( $_POST['new_password'] ) : '';
        $current_pass = isset( $_POST['current_password'] ) ? (string) wp_unslash( $_POST['current_password'] ) : '';

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            wp_send_json_error( [ 'message' => 'کاربر یافت نشد.' ], 404 );
        }

        $update = [ 'ID' => $user_id ];
        if ( '' !== $name ) {
            $parts = preg_split( '/\s+/', $name, 2 );
            $update['display_name'] = $name;
            $update['first_name']   = $parts[0] ?? '';
            $update['last_name']    = $parts[1] ?? '';
        }
        if ( '' !== $email && $email !== $user->user_email ) {
            if ( ! is_email( $email ) ) {
                wp_send_json_error( [ 'message' => 'ایمیل نامعتبر.' ], 400 );
            }
            if ( email_exists( $email ) ) {
                wp_send_json_error( [ 'message' => 'این ایمیل قبلاً استفاده شده.' ], 409 );
            }
            $update['user_email'] = $email;
        }
        if ( '' !== $new_pass ) {
            if ( strlen( $new_pass ) < 6 ) {
                wp_send_json_error( [ 'message' => 'رمز جدید حداقل ۶ کاراکتر باشد.' ], 400 );
            }
            if ( '' === $current_pass || ! wp_check_password( $current_pass, $user->user_pass, $user_id ) ) {
                wp_send_json_error( [ 'message' => 'رمز فعلی اشتباه است.' ], 400 );
            }
            $update['user_pass'] = $new_pass;
        }
        $r = wp_update_user( $update );
        if ( is_wp_error( $r ) ) {
            wp_send_json_error( [ 'message' => $r->get_error_message() ], 500 );
        }
        wp_send_json_success( [ 'message' => 'اطلاعات با موفقیت به‌روزرسانی شد.' ] );
    }

    /* ── Admin: SMS test ── */

    public function test_sms(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی مجاز نیست.' ], 403 );
        }
        check_ajax_referer( 'ug_test', '_wpnonce' );
        $phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $r     = $this->otp->send( UG_Otp::normalize_phone( $phone ), 'login' );
        wp_send_json( $r );
    }

    /* ── Wallet top-up: build checkout & redirect ── */

    public function topup(): void {
        check_ajax_referer( 'ug_front', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'ابتدا وارد شوید.' ], 401 );
        }
        $amount = isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0;
        $res    = $this->wallet->start_topup( $amount );
        if ( empty( $res['ok'] ) ) {
            wp_send_json_error( [ 'message' => $res['error'] ?? 'خطا در شروع شارژ' ], 400 );
        }
        wp_send_json_success( [ 'redirect' => $res['redirect'] ] );
    }

    /* ── Registration ── */

    public function register(): void {
        check_ajax_referer( 'ug_front', 'nonce' );

        $name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $pass  = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => 'ایمیل معتبر وارد کنید.' ], 400 );
        }
        if ( strlen( $pass ) < 6 ) {
            wp_send_json_error( [ 'message' => 'رمز عبور باید حداقل ۶ کاراکتر باشد.' ], 400 );
        }
        if ( email_exists( $email ) ) {
            wp_send_json_error( [ 'message' => 'این ایمیل قبلاً ثبت شده است. وارد شوید.' ], 409 );
        }

        // Unique username from the email prefix.
        $base     = sanitize_user( strstr( $email, '@', true ), true ) ?: 'user';
        $username = $base;
        $i        = 1;
        while ( username_exists( $username ) ) {
            $username = $base . $i++;
        }

        $user_id = wp_create_user( $username, $pass, $email );
        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( [ 'message' => $user_id->get_error_message() ], 500 );
        }

        wp_update_user( [ 'ID' => $user_id, 'display_name' => $name ?: $username, 'role' => 'customer' ] );

        // Auto login.
        $signon = wp_signon( [ 'user_login' => $username, 'user_password' => $pass, 'remember' => true ], is_ssl() );
        if ( is_wp_error( $signon ) ) {
            wp_send_json_success( [ 'message' => 'ثبت‌نام انجام شد. اکنون وارد شوید.', 'redirect' => home_url( '/auth/' ) ] );
        }

        wp_send_json_success( [ 'message' => 'خوش آمدید!', 'redirect' => home_url( '/panel/' ) ] );
    }

    /* ── Login (accepts email or username) ── */

    public function login(): void {
        check_ajax_referer( 'ug_front', 'nonce' );

        $login = isset( $_POST['login'] ) ? sanitize_text_field( wp_unslash( $_POST['login'] ) ) : '';
        $pass  = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

        if ( '' === $login || '' === $pass ) {
            wp_send_json_error( [ 'message' => 'ایمیل و رمز عبور را وارد کنید.' ], 400 );
        }

        if ( is_email( $login ) ) {
            $user  = get_user_by( 'email', $login );
            $login = $user ? $user->user_login : $login;
        } else {
            // Maybe it's a phone number → resolve to username.
            $maybe_phone = UG_Otp::normalize_phone( $login );
            if ( UG_Otp::is_valid_phone( $maybe_phone ) ) {
                $users = get_users( [ 'meta_key' => '_ug_phone', 'meta_value' => $maybe_phone, 'number' => 1, 'fields' => 'ID' ] );
                if ( ! empty( $users ) ) {
                    $login = get_userdata( (int) $users[0] )->user_login;
                }
            }
        }

        $signon = wp_signon( [ 'user_login' => $login, 'user_password' => $pass, 'remember' => true ], is_ssl() );
        if ( is_wp_error( $signon ) ) {
            wp_send_json_error( [ 'message' => 'ایمیل یا رمز عبور اشتباه است.' ], 401 );
        }

        wp_send_json_success( [ 'message' => 'خوش آمدید!', 'redirect' => home_url( '/panel/' ) ] );
    }

    /* ── Place an instant order ─────────────── */

    public function place_order(): void {
        check_ajax_referer( 'ug_front', 'nonce' );

        $user_id    = UG_Guard::require_user_or_die();
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

        // Telegram member orders: the check-bot must already be an admin of the
        // customer's channel BEFORE we charge — otherwise money is taken but the
        // service can't run. Verify first (no charge on failure).
        $assigned_bot = 0;
        if ( 'telegram' === $m['provider'] ) {
            $tg = $this->dispatcher->provider( 'telegram' );
            if ( $tg instanceof UG_Provider_Telegram ) {
                $q = $tg->quote( $m['order_type'], $quantity );
                if ( empty( $q['ok'] ) ) {
                    wp_send_json_error( [ 'message' => $q['error'] ?: 'اتصال به ربات ناموفق بود.' ], 502 );
                }
                $assigned_bot = (int) ( $q['data']['assigned_bot'] ?? 0 );
                $check_bot    = $q['data']['check_bot'] ?? '';
                if ( ! $assigned_bot || ! $tg->check_membership( $assigned_bot, $target ) ) {
                    wp_send_json_error( [
                        'message'    => $check_bot
                            ? sprintf( 'ابتدا ربات @%s را ادمین کانال خود کنید، سپس دوباره ثبت کنید.', $check_bot )
                            : 'ابتدا ربات بررسی را ادمین کانال خود کنید.',
                        'need_admin' => true,
                        'check_bot'  => $check_bot,
                    ], 409 );
                }
            }
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
        $extra = [ 'local_order' => $order_id ];
        if ( 'telegram' === $m['provider'] ) {
            $extra['order_type']   = $m['order_type'];
            $extra['assigned_bot'] = $assigned_bot;
            $extra['web_user_id']  = $user_id;
        }
        $result = $this->dispatcher->create_order( $m['provider'], [
            'service_id' => $m['service_id'],
            'target'     => $target,
            'quantity'   => $quantity,
            'extra'      => $extra,
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

    /* ── Admin: turn a vless:// link into a ready Xray client config ── */

    public function vless_xray(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی مجاز نیست.' ], 403 );
        }
        check_ajax_referer( 'ug_test', '_wpnonce' );
        $link   = isset( $_POST['link'] ) ? trim( (string) wp_unslash( $_POST['link'] ) ) : '';
        $config = UG_Proxy::vless_to_xray( $link );
        if ( null === $config ) {
            wp_send_json_error( [ 'message' => 'لینک VLESS نامعتبر است. باید با vless:// شروع شود.' ], 400 );
        }
        wp_send_json_success( [
            'hint'   => 'این را در فایل config.json کلاینت Xray بگذارید و اجرا کنید؛ سپس در فیلد «پروکسی» بنویسید: socks5://127.0.0.1:10808',
            'config' => wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
        ] );
    }

    /**
     * Whitelist which provider fields are exposed to the browser (e.g. OTP, number).
     */
    private function public_order_data( array $data ): array {
        $allow = [ 'number', 'otp', 'code', 'start_count', 'remains' ];
        return array_intersect_key( $data, array_flip( $allow ) );
    }
}
