<?php
/**
 * Purchase gate:
 *  - Provides `ug_purchase_button` filter used by theme templates so guest
 *    users see «ورود برای خرید» pointing to the auth page.
 *  - Provides `ug_require_user()` used by AJAX endpoints to hard-reject
 *    guest requests.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Guard {

    public function __construct() {
        add_filter( 'ug_purchase_button', [ $this, 'filter_button' ], 10, 3 );
        add_action( 'template_redirect', [ $this, 'guard_panel' ] );
    }

    /**
     * Bounce guests away from the panel page (works even when the page has been
     * converted to an Elementor page, i.e. no page-panel.php guard).
     */
    public function guard_panel(): void {
        if ( is_page( 'panel' ) && ! is_user_logged_in() ) {
            wp_safe_redirect( self::auth_url( home_url( '/panel/' ) ) );
            exit;
        }
    }

    /**
     * Path to the auth page (falls back to wp-login).
     */
    public static function auth_url( string $redirect_to = '' ): string {
        $page = get_page_by_path( 'auth' );
        $base = $page ? get_permalink( $page ) : wp_login_url();
        if ( $redirect_to ) {
            $base = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $base );
        }
        return $base;
    }

    /**
     * Render the appropriate purchase button.
     *
     * @param string $default_html original button markup
     * @param int    $product_id
     * @param array  $args         optional {label, class}
     * @return string
     */
    public function filter_button( string $default_html, int $product_id, array $args = [] ): string {
        if ( is_user_logged_in() ) {
            return $default_html;
        }
        $label = $args['label']  ?? 'ورود برای خرید';
        $class = $args['class']  ?? 'plan-btn plan-btn-primary';
        $url   = self::auth_url( get_permalink( $product_id ) ?: '' );
        return sprintf(
            '<a href="%s" class="%s" data-guard="1">%s</a>',
            esc_url( $url ),
            esc_attr( $class ),
            esc_html( $label )
        );
    }

    /**
     * Reject guests from AJAX endpoints. Sends 401 and dies.
     */
    public static function require_user_or_die(): int {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [
                'message'  => 'برای انجام این عملیات ابتدا وارد شوید.',
                'redirect' => self::auth_url(),
            ], 401 );
        }
        return get_current_user_id();
    }
}
