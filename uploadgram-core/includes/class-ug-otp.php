<?php
/**
 * OTP (one-time password) manager — issues, stores and verifies 6-digit codes
 * for phone-based auth (register / login).
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Otp {

    const TTL_SECONDS      = 300;   // 5 min
    const RESEND_COOLDOWN  = 120;   // 2 min between sends
    const MAX_PER_HOUR     = 6;     // per phone
    const MAX_ATTEMPTS     = 5;     // per code

    private UG_Sms_Kavenegar $sms;

    public function __construct( UG_Sms_Kavenegar $sms ) {
        $this->sms = $sms;
    }

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ug_otp';
    }

    /**
     * Send a code.
     *
     * @return array {ok:bool, error?:string, wait?:int}
     */
    public function send( string $phone, string $purpose = 'login' ): array {
        $phone   = self::normalize_phone( $phone );
        $purpose = in_array( $purpose, [ 'login', 'register' ], true ) ? $purpose : 'login';

        if ( ! self::is_valid_phone( $phone ) ) {
            return [ 'ok' => false, 'error' => 'شماره موبایل معتبر نیست.' ];
        }

        global $wpdb;
        $table = self::table();

        // Cooldown: last unused code in the last RESEND_COOLDOWN seconds?
        $last = $wpdb->get_row( $wpdb->prepare(
            "SELECT created_at FROM {$table} WHERE phone = %s ORDER BY id DESC LIMIT 1",
            $phone
        ) );
        if ( $last ) {
            $elapsed = time() - strtotime( $last->created_at );
            if ( $elapsed < self::RESEND_COOLDOWN ) {
                return [ 'ok' => false, 'error' => 'قبل از درخواست کد جدید کمی صبر کنید.', 'wait' => self::RESEND_COOLDOWN - $elapsed ];
            }
        }

        // Hourly cap.
        $recent = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE phone = %s AND created_at > (NOW() - INTERVAL 1 HOUR)",
            $phone
        ) );
        if ( $recent >= self::MAX_PER_HOUR ) {
            return [ 'ok' => false, 'error' => 'تعداد درخواست کد در ساعت اخیر بیش از حد مجاز است.' ];
        }

        // Generate + store.
        $code = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
        $ok   = $wpdb->insert( $table, [
            'phone'      => $phone,
            'code_hash'  => password_hash( $code, PASSWORD_DEFAULT ),
            'purpose'    => $purpose,
            'ip'         => self::client_ip(),
            'expires_at' => gmdate( 'Y-m-d H:i:s', time() + self::TTL_SECONDS ),
            'created_at' => current_time( 'mysql' ),
        ] );
        if ( ! $ok ) {
            return [ 'ok' => false, 'error' => 'خطا در ثبت کد. دوباره تلاش کنید.' ];
        }

        // Send via Kavenegar.
        $res = $this->sms->send_verify( $phone, $code );
        if ( empty( $res['ok'] ) ) {
            UG_Logger::error( 'Kavenegar send failed', [ 'phone' => $phone, 'err' => $res['error'] ?? '' ] );
            return [ 'ok' => false, 'error' => 'ارسال پیامک ناموفق بود: ' . ( $res['error'] ?? '' ) ];
        }

        return [ 'ok' => true, 'wait' => self::RESEND_COOLDOWN ];
    }

    /**
     * Verify a code. Returns {ok, error?}.
     */
    public function verify( string $phone, string $code, string $purpose = 'login' ): array {
        $phone = self::normalize_phone( $phone );
        $code  = preg_replace( '/\D/', '', (string) $code );

        if ( strlen( $code ) !== 6 ) {
            return [ 'ok' => false, 'error' => 'کد وارد شده نامعتبر است.' ];
        }

        global $wpdb;
        $table = self::table();

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table}
              WHERE phone = %s AND purpose = %s AND used_at IS NULL
              ORDER BY id DESC LIMIT 1",
            $phone, $purpose
        ) );

        if ( ! $row ) {
            return [ 'ok' => false, 'error' => 'کدی برای این شماره صادر نشده. ابتدا کد را درخواست کنید.' ];
        }
        if ( strtotime( $row->expires_at ) < time() ) {
            return [ 'ok' => false, 'error' => 'مهلت کد به پایان رسیده. کد جدید بگیرید.' ];
        }
        if ( (int) $row->attempts >= self::MAX_ATTEMPTS ) {
            return [ 'ok' => false, 'error' => 'تعداد تلاش‌های نادرست بیش از حد. کد جدید بگیرید.' ];
        }

        // increment attempts atomically.
        $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET attempts = attempts + 1 WHERE id = %d", $row->id ) );

        if ( ! password_verify( $code, $row->code_hash ) ) {
            return [ 'ok' => false, 'error' => 'کد وارد شده نادرست است.' ];
        }

        // Mark used.
        $wpdb->update( $table, [ 'used_at' => current_time( 'mysql' ) ], [ 'id' => $row->id ] );

        return [ 'ok' => true ];
    }

    /* ══════════════ Helpers ══════════════ */

    public static function normalize_phone( string $phone ): string {
        $phone = trim( $phone );
        $is_intl = str_starts_with( $phone, '+' );
        $digits  = preg_replace( '/\D/', '', $phone );

        if ( $is_intl ) {
            // Iran in E.164 → local 09xxxxxxxxx for a single canonical form.
            if ( str_starts_with( $digits, '98' ) && strlen( $digits ) === 12 ) {
                return '0' . substr( $digits, 2 );
            }
            return '+' . $digits;
        }

        // No plus → treat as Iranian local.
        if ( str_starts_with( $digits, '0098' ) ) {
            return '0' . substr( $digits, 4 );
        }
        if ( str_starts_with( $digits, '98' ) && strlen( $digits ) === 12 ) {
            return '0' . substr( $digits, 2 );
        }
        if ( str_starts_with( $digits, '9' ) && strlen( $digits ) === 10 ) {
            return '0' . $digits;
        }
        return $digits;
    }

    public static function is_valid_phone( string $phone ): bool {
        if ( preg_match( '/^09\d{9}$/', $phone ) ) {
            return true; // Iran local
        }
        if ( preg_match( '/^\+\d{8,15}$/', $phone ) ) {
            return true; // international E.164
        }
        return false;
    }

    private static function client_ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = trim( explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0] );
        }
        return substr( $ip, 0, 45 );
    }
}
