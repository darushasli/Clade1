<?php
/**
 * Account primitives: create-or-find users by phone / email / Google claims,
 * sign them in, activate their wallet.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Auth {

    private UG_Wallet $wallet;

    public function __construct( UG_Wallet $wallet ) {
        $this->wallet = $wallet;
    }

    /* ══════════════ Public API ══════════════ */

    /**
     * Look up a user by phone; create one if missing.
     *
     * @return array {ok:bool, user_id?:int, created?:bool, error?:string}
     */
    public function find_or_create_by_phone( string $phone, string $name = '', string $email = '', string $password = '' ): array {
        $phone = UG_Otp::normalize_phone( $phone );

        $existing = $this->find_user_by_phone( $phone );
        if ( $existing ) {
            return [ 'ok' => true, 'user_id' => $existing, 'created' => false ];
        }

        // If email + password given (register form), use them; otherwise
        // create a phone-only account with a random password.
        if ( '' !== $email && ! is_email( $email ) ) {
            return [ 'ok' => false, 'error' => 'ایمیل نامعتبر است.' ];
        }
        if ( '' !== $email && email_exists( $email ) ) {
            return [ 'ok' => false, 'error' => 'این ایمیل قبلاً ثبت شده است. لطفاً وارد شوید.' ];
        }

        $username = $this->unique_username( 'u_' . $phone );
        $password = $password ?: wp_generate_password( 12 );
        $email    = $email ?: ( $phone . '@phone.uploadgram.local' );

        $user_id = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            return [ 'ok' => false, 'error' => $user_id->get_error_message() ];
        }

        $this->populate_user( (int) $user_id, [
            'name'  => $name,
            'phone' => $phone,
        ] );
        $this->activate_wallet( (int) $user_id );

        return [ 'ok' => true, 'user_id' => (int) $user_id, 'created' => true ];
    }

    /**
     * Look up or create a user from verified Google claims.
     */
    public function find_or_create_by_google( array $claims ): array {
        $sub   = (string) ( $claims['sub'] ?? '' );
        $email = (string) ( $claims['email'] ?? '' );
        if ( '' === $sub || '' === $email ) {
            return [ 'ok' => false, 'error' => 'اطلاعات گوگل ناقص است.' ];
        }

        // 1) By google sub.
        $u = get_users( [
            'meta_key'   => '_ug_google_sub',
            'meta_value' => $sub,
            'number'     => 1,
            'fields'     => 'ID',
        ] );
        if ( ! empty( $u ) ) {
            return [ 'ok' => true, 'user_id' => (int) $u[0], 'created' => false ];
        }

        // 2) By email.
        $by_email = get_user_by( 'email', $email );
        if ( $by_email ) {
            update_user_meta( $by_email->ID, '_ug_google_sub', $sub );
            return [ 'ok' => true, 'user_id' => (int) $by_email->ID, 'created' => false ];
        }

        // 3) Create.
        $username = $this->unique_username( sanitize_user( strstr( $email, '@', true ), true ) ?: 'user' );
        $user_id  = wp_create_user( $username, wp_generate_password( 16 ), $email );
        if ( is_wp_error( $user_id ) ) {
            return [ 'ok' => false, 'error' => $user_id->get_error_message() ];
        }

        $name = trim( ( $claims['given_name'] ?? '' ) . ' ' . ( $claims['family_name'] ?? '' ) ) ?: ( $claims['name'] ?? '' );
        $this->populate_user( (int) $user_id, [ 'name' => $name ] );
        update_user_meta( $user_id, '_ug_google_sub', $sub );
        if ( ! empty( $claims['picture'] ) ) {
            update_user_meta( $user_id, '_ug_avatar', esc_url_raw( $claims['picture'] ) );
        }
        $this->activate_wallet( (int) $user_id );

        return [ 'ok' => true, 'user_id' => (int) $user_id, 'created' => true ];
    }

    /**
     * Sign a user in (sets auth cookies).
     */
    public function sign_in( int $user_id, bool $remember = true ): void {
        wp_clear_auth_cookie();
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, $remember, is_ssl() );
        do_action( 'wp_login', get_userdata( $user_id )->user_login, get_userdata( $user_id ) );
    }

    /* ══════════════ Helpers ══════════════ */

    private function find_user_by_phone( string $phone ): int {
        $q = get_users( [
            'meta_key'   => '_ug_phone',
            'meta_value' => $phone,
            'number'     => 1,
            'fields'     => 'ID',
        ] );
        return $q ? (int) $q[0] : 0;
    }

    private function unique_username( string $base ): string {
        $base = sanitize_user( $base, true ) ?: 'user';
        $u    = $base;
        $i    = 1;
        while ( username_exists( $u ) ) {
            $u = $base . $i++;
        }
        return $u;
    }

    /**
     * Populate a fresh user's profile fields.
     */
    private function populate_user( int $user_id, array $data ): void {
        $name = trim( (string) ( $data['name'] ?? '' ) );
        if ( '' !== $name ) {
            $parts = preg_split( '/\s+/', $name, 2 );
            wp_update_user( [
                'ID'           => $user_id,
                'display_name' => $name,
                'first_name'   => $parts[0] ?? '',
                'last_name'    => $parts[1] ?? '',
                'role'         => 'customer',
            ] );
        } else {
            wp_update_user( [ 'ID' => $user_id, 'role' => 'customer' ] );
        }
        if ( ! empty( $data['phone'] ) ) {
            update_user_meta( $user_id, '_ug_phone', $data['phone'] );
        }
    }

    /**
     * "Activate" the wallet — inserts a 0-value activation transaction so
     * the balance row exists and the panel shows the wallet as ready.
     */
    private function activate_wallet( int $user_id ): void {
        if ( 0.0 === $this->wallet->balance( $user_id ) ) {
            // Setting the meta to 0 explicitly guarantees the row exists.
            update_user_meta( $user_id, UG_Wallet::META_BALANCE, 0 );
        }
        do_action( 'ug_user_registered', $user_id );
    }
}
