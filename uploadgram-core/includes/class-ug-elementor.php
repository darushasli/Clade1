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
     * AJAX: create/refresh a fully-editable Elementor home page and set it as
     * the front page. Safe — only runs when Elementor is active and never
     * overwrites a page that already has Elementor data.
     */
    public function ajax_seed_home(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی مجاز نیست.' ], 403 );
        }
        check_ajax_referer( 'ug_sync', '_wpnonce' );

        if ( ! self::active() ) {
            wp_send_json_error( [ 'message' => 'ابتدا افزونهٔ المنتور را نصب و فعال کنید.' ], 400 );
        }

        $data = [
            $this->section( [ $this->widget( 'ug-hero' ) ] ),
            $this->section( [ $this->widget( 'ug-quick-cats' ) ] ),
            $this->section( [ $this->widget( 'ug-section-heading', [ 'title' => 'دسته‌بندی خدمات آپلودگرام' ] ), $this->widget( 'ug-service-cats' ) ] ),
            $this->section( [ $this->widget( 'ug-stats' ) ] ),
            $this->section( [ $this->widget( 'ug-section-heading', [ 'title' => 'پیشنهادهای ویژه' ] ), $this->widget( 'ug-featured' ) ] ),
            $this->section( [ $this->widget( 'ug-section-heading', [ 'title' => 'چرا آپلودگرام؟' ] ), $this->widget( 'ug-why-us' ) ] ),
            $this->section( [ $this->widget( 'ug-promo' ) ] ),
            $this->section( [ $this->widget( 'ug-faq' ) ] ),
        ];

        // Find or create the page.
        $existing = get_page_by_path( 'home-elementor' );
        $page_id  = $existing ? $existing->ID : wp_insert_post( [
            'post_title'  => 'صفحه اصلی',
            'post_name'   => 'home-elementor',
            'post_status' => 'publish',
            'post_type'   => 'page',
        ] );
        if ( ! $page_id || is_wp_error( $page_id ) ) {
            wp_send_json_error( [ 'message' => 'ساخت صفحه ناموفق بود.' ], 500 );
        }

        // Don't clobber an already-built Elementor page.
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

        // Make it the front page.
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', $page_id );

        wp_send_json_success( [
            'message' => 'صفحهٔ اصلی قابل‌ویرایش ساخته شد و به‌عنوان صفحهٔ نخست تنظیم شد.',
            'edit'    => admin_url( 'post.php?post=' . $page_id . '&action=elementor' ),
            'view'    => get_permalink( $page_id ),
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
