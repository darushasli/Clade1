<?php
/**
 * Native Elementor widgets — every UploadGram section becomes a real
 * drag-and-drop widget under the "آپلودگرام" category, so pages can be built
 * visually without shortcodes. Widgets that accept parameters expose editable
 * controls; content-heavy sections render their block and can be styled with
 * Elementor's own layout/spacing controls.
 *
 * Only loads when Elementor is active.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Elementor {

    public function __construct() {
        add_action( 'elementor/elements/categories_registered', [ $this, 'category' ] );
        add_action( 'elementor/widgets/register', [ $this, 'register' ] );
        add_action( 'wp_ajax_ug_seed_elementor', [ $this, 'ajax_seed_home' ] );
    }

    /** Is Elementor active? */
    public static function active(): bool {
        return did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' );
    }

    /* ── One-click: build a fully-editable Elementor home page ── */

    private function node( string $type, string $widget = '', array $settings = [], array $children = [] ): array {
        $n = [
            'id'       => substr( md5( uniqid( '', true ) ), 0, 7 ),
            'elType'   => $type,
            'settings' => $settings,
            'elements' => $children,
        ];
        if ( 'widget' === $type ) {
            $n['widgetType'] = $widget;
        }
        return $n;
    }

    private function widget( string $w, array $settings = [] ): array {
        return $this->node( 'widget', $w, $settings );
    }

    private function section( array $widgets ): array {
        $col = $this->node( 'column', '', [ '_column_size' => 100 ], $widgets );
        return $this->node( 'section', '', [ 'padding' => [ 'unit' => 'px', 'top' => '24', 'bottom' => '24', 'isLinked' => false ] ], [ $col ] );
    }

    /**
     * Prepare a single page for Elementor: create if missing, set the Elementor
     * full-width template + edit mode, and seed the layout ONLY if the page has
     * no Elementor data yet (never clobbers existing work).
     *
     * @return array|null { id, title, edit, view }
     */
    private function seed_page( string $slug, string $title, array $data, bool $front = false ): ?array {
        $existing = get_page_by_path( $slug );
        $page_id  = $existing ? $existing->ID : wp_insert_post( [
            'post_title'  => $title,
            'post_name'   => $slug,
            'post_status' => 'publish',
            'post_type'   => 'page',
        ] );
        if ( ! $page_id || is_wp_error( $page_id ) ) {
            return null;
        }

        $has = get_post_meta( $page_id, '_elementor_data', true );
        if ( empty( $has ) || '[]' === $has ) {
            update_post_meta( $page_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
        }
        update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
        if ( defined( 'ELEMENTOR_VERSION' ) ) {
            update_post_meta( $page_id, '_elementor_version', ELEMENTOR_VERSION );
        }
        update_post_meta( $page_id, '_wp_page_template', 'page-elementor-full.php' );

        if ( $front ) {
            update_option( 'show_on_front', 'page' );
            update_option( 'page_on_front', $page_id );
        }

        return [
            'id'    => $page_id,
            'title' => $title,
            'edit'  => admin_url( 'post.php?post=' . $page_id . '&action=elementor' ),
            'view'  => get_permalink( $page_id ),
        ];
    }

    /**
     * AJAX: prepare ALL main pages (home, services, accounts, numbers) as
     * fully-editable Elementor pages. Safe — Elementor-only, never clobbers a
     * page that already has Elementor data.
     */
    public function ajax_seed_home(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی مجاز نیست.' ], 403 );
        }
        check_ajax_referer( 'ug_sync', '_wpnonce' );

        if ( ! self::active() ) {
            wp_send_json_error( [ 'message' => 'ابتدا افزونهٔ المنتور را نصب و فعال کنید.' ], 400 );
        }

        $pages = [];

        // 1) Home — seed onto the real front page so it lives at the site root.
        $pages[] = $this->seed_page( 'home', 'صفحه اصلی', [
            $this->section( [ $this->widget( 'ug-hero' ) ] ),
            $this->section( [ $this->widget( 'ug-quick-cats' ) ] ),
            $this->section( [ $this->widget( 'ug-section-heading', [ 'title' => 'دسته‌بندی خدمات آپلودگرام' ] ), $this->widget( 'ug-service-cats' ) ] ),
            $this->section( [ $this->widget( 'ug-stats' ) ] ),
            $this->section( [ $this->widget( 'ug-section-heading', [ 'title' => 'پیشنهادهای ویژه' ] ), $this->widget( 'ug-featured' ) ] ),
            $this->section( [ $this->widget( 'ug-section-heading', [ 'title' => 'چرا آپلودگرام؟' ] ), $this->widget( 'ug-why-us' ) ] ),
            $this->section( [ $this->widget( 'ug-promo' ) ] ),
            $this->section( [ $this->widget( 'ug-faq' ) ] ),
        ], true );

        // 2) خدمات مجازی
        $pages[] = $this->seed_page( 'member', 'خدمات مجازی', [
            $this->section( [ $this->widget( 'ug-page-hero', [ 'variant' => 'member' ] ) ] ),
            $this->section( [ $this->widget( 'ug-section-heading', [ 'title' => 'سفارش سریع خدمات' ] ), $this->widget( 'ug-services-app', [ 'view' => 'showcase' ] ) ] ),
            $this->section( [ $this->widget( 'ug-section-heading', [ 'title' => 'انواع پکیج ممبر' ] ), $this->widget( 'ug-member-types' ) ] ),
            $this->section( [ $this->widget( 'ug-member-plans' ) ] ),
            $this->section( [ $this->widget( 'ug-section-heading', [ 'title' => 'چرا ممبر آپلودگرام؟' ] ), $this->widget( 'ug-why-us' ) ] ),
            $this->section( [ $this->widget( 'ug-faq' ) ] ),
        ] );

        // 3) اکانت پرمیوم
        $pages[] = $this->seed_page( 'account', 'اکانت پرمیوم', [
            $this->section( [ $this->widget( 'ug-page-hero', [ 'variant' => 'account' ] ) ] ),
            $this->section( [ $this->widget( 'ug-trust-banner' ) ] ),
            $this->section( [ $this->widget( 'ug-section-heading', [ 'title' => 'اکانت‌های هوش مصنوعی' ] ), $this->widget( 'ug-account-grid', [ 'cat' => 'ai' ] ) ] ),
            $this->section( [ $this->widget( 'ug-section-heading', [ 'title' => 'اکانت‌های موزیک' ] ), $this->widget( 'ug-account-grid', [ 'cat' => 'music' ] ) ] ),
            $this->section( [ $this->widget( 'ug-section-heading', [ 'title' => 'اکانت‌های ویدیو' ] ), $this->widget( 'ug-account-grid', [ 'cat' => 'video' ] ) ] ),
            $this->section( [ $this->widget( 'ug-section-heading', [ 'title' => 'طراحی و بهره‌وری' ] ), $this->widget( 'ug-account-grid', [ 'cat' => 'design' ] ) ] ),
            $this->section( [ $this->widget( 'ug-faq' ) ] ),
        ] );

        // 4) شماره مجازی
        $pages[] = $this->seed_page( 'virtual-number', 'شماره مجازی', [
            $this->section( [ $this->widget( 'ug-page-hero', [ 'variant' => 'virtual' ] ) ] ),
            $this->section( [ $this->widget( 'ug-section-heading', [ 'title' => 'خرید سریع شماره مجازی' ] ), $this->widget( 'ug-numbers-app' ) ] ),
            $this->section( [ $this->widget( 'ug-steps' ) ] ),
            $this->section( [ $this->widget( 'ug-section-heading', [ 'title' => 'سرویس‌های پشتیبانی‌شده' ] ), $this->widget( 'ug-service-icons' ) ] ),
            $this->section( [ $this->widget( 'ug-section-heading', [ 'title' => 'جدول قیمت شماره' ] ), $this->widget( 'ug-price-table' ) ] ),
            $this->section( [ $this->widget( 'ug-faq' ) ] ),
        ] );

        // 5) ورود / ثبت‌نام
        $pages[] = $this->seed_page( 'auth', 'ورود / ثبت‌نام', [
            $this->section( [ $this->widget( 'ug-auth' ) ] ),
        ] );

        // 6) پنل کاربری
        $pages[] = $this->seed_page( 'panel', 'پنل کاربری', [
            $this->section( [ $this->widget( 'ug-panel' ) ] ),
        ] );

        $pages = array_values( array_filter( $pages ) );
        if ( empty( $pages ) ) {
            wp_send_json_error( [ 'message' => 'ساخت صفحات ناموفق بود.' ], 500 );
        }

        wp_send_json_success( [
            'message' => 'همهٔ صفحات اصلی برای ویرایش با المنتور آماده شدند. روی «ویرایش» هر صفحه بزنید.',
            'pages'   => $pages,
        ] );
    }

    public function category( $mgr ): void {
        $mgr->add_category( 'uploadgram', [
            'title' => __( 'آپلودگرام', 'uploadgram-core' ),
            'icon'  => 'fa fa-shopping-cart',
        ] );
    }

    public function register( $mgr ): void {
        if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
            return;
        }
        require_once UGC_DIR . 'includes/elementor/class-ug-widgets.php';

        foreach ( UG_Elementor_Widgets::classes() as $class ) {
            if ( class_exists( $class ) ) {
                $mgr->register( new $class() );
            }
        }
    }
}
