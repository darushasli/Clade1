<?php
/**
 * WooCommerce integration:
 *  - Adds a "UploadGram Service" panel to each product (provider, service id,
 *    input type, min/max, rate) so a product knows which API to call.
 *  - Turns the store into an instant-order / wallet model (cart disabled).
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_WC_Integration {

    /** @var UG_Settings */
    private $settings;

    public function __construct( UG_Settings $settings ) {
        $this->settings = $settings;

        // Product data panel.
        add_filter( 'woocommerce_product_data_tabs', [ $this, 'product_tab' ] );
        add_action( 'woocommerce_product_data_panels', [ $this, 'product_panel' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_product' ] );

        // Disable cart / checkout flow — everything is instant wallet order.
        if ( 'yes' === $this->settings->get( 'disable_cart', 'yes' ) ) {
            add_filter( 'woocommerce_is_purchasable', '__return_false' );
            add_filter( 'woocommerce_add_to_cart_validation', '__return_false' );
        }
    }

    /* ── Product meta helpers ──────────────── */

    public static function meta( int $product_id ): array {
        return [
            'provider'   => get_post_meta( $product_id, '_ug_provider', true ),
            'service_id' => get_post_meta( $product_id, '_ug_service_id', true ),
            'input_type' => get_post_meta( $product_id, '_ug_input_type', true ) ?: 'link',
            'min'        => (int) get_post_meta( $product_id, '_ug_min', true ),
            'max'        => (int) get_post_meta( $product_id, '_ug_max', true ),
            'rate'       => (float) get_post_meta( $product_id, '_ug_rate', true ),
            'fixed'      => 'yes' === get_post_meta( $product_id, '_ug_fixed', true ),
        ];
    }

    /* ── Admin product panel ───────────────── */

    public function product_tab( array $tabs ): array {
        $tabs['uploadgram'] = [
            'label'    => __( 'سرویس آپلودگرام', 'uploadgram-core' ),
            'target'   => 'ug_product_data',
            'class'    => [],
            'priority' => 65,
        ];
        return $tabs;
    }

    public function product_panel(): void {
        global $post;
        $m = self::meta( $post->ID );
        ?>
        <div id="ug_product_data" class="panel woocommerce_options_panel">
            <?php
            woocommerce_wp_select( [
                'id'      => '_ug_provider',
                'label'   => __( 'سرویس اتصال', 'uploadgram-core' ),
                'value'   => $m['provider'],
                'options' => [
                    ''           => __( '— بدون اتصال (دستی) —', 'uploadgram-core' ),
                    'followeran' => 'فالوران (SMM)',
                    'numberland' => 'نامبرلند (شماره مجازی)',
                    'telegram'   => 'ربات تلگرام (ممبر آپلودی)',
                ],
                'desc_tip' => true,
                'description' => __( 'این محصول به کدام API متصل شود؟', 'uploadgram-core' ),
            ] );

            woocommerce_wp_text_input( [
                'id'          => '_ug_service_id',
                'label'       => __( 'شناسه سرویس (Service ID)', 'uploadgram-core' ),
                'value'       => $m['service_id'],
                'desc_tip'    => true,
                'description' => __( 'شناسه سرویس در سمت API (برای نامبرلند می‌توانید service:country بگذارید).', 'uploadgram-core' ),
            ] );

            woocommerce_wp_select( [
                'id'      => '_ug_input_type',
                'label'   => __( 'ورودی کاربر', 'uploadgram-core' ),
                'value'   => $m['input_type'],
                'options' => [
                    'link'     => __( 'لینک', 'uploadgram-core' ),
                    'username' => __( 'یوزرنیم', 'uploadgram-core' ),
                    'none'     => __( 'بدون ورودی (مثل شماره مجازی/اکانت)', 'uploadgram-core' ),
                ],
            ] );

            woocommerce_wp_text_input( [
                'id'    => '_ug_min',
                'label' => __( 'حداقل تعداد', 'uploadgram-core' ),
                'type'  => 'number',
                'value' => $m['min'],
            ] );
            woocommerce_wp_text_input( [
                'id'    => '_ug_max',
                'label' => __( 'حداکثر تعداد', 'uploadgram-core' ),
                'type'  => 'number',
                'value' => $m['max'],
            ] );

            woocommerce_wp_checkbox( [
                'id'          => '_ug_fixed',
                'label'       => __( 'قیمت ثابت؟', 'uploadgram-core' ),
                'value'       => $m['fixed'] ? 'yes' : 'no',
                'description' => __( 'اگر تیک بخورد، قیمت محصول ثابت است (مثل شماره/اکانت). در غیر این صورت بر اساس تعداد × نرخ محاسبه می‌شود.', 'uploadgram-core' ),
            ] );

            woocommerce_wp_text_input( [
                'id'          => '_ug_rate',
                'label'       => __( 'نرخ هر ۱۰۰۰ (تومان)', 'uploadgram-core' ),
                'type'        => 'number',
                'value'       => $m['rate'],
                'desc_tip'    => true,
                'description' => __( 'برای خدمات تعدادی: قیمت به‌ازای هر ۱۰۰۰ واحد. قیمت نهایی = تعداد ÷ ۱۰۰۰ × نرخ.', 'uploadgram-core' ),
            ] );
            ?>
        </div>
        <?php
    }

    public function save_product( int $product_id ): void {
        $fields = [
            '_ug_provider'   => 'sanitize_text_field',
            '_ug_service_id' => 'sanitize_text_field',
            '_ug_input_type' => 'sanitize_text_field',
            '_ug_min'        => 'absint',
            '_ug_max'        => 'absint',
            '_ug_rate'       => 'floatval',
        ];
        foreach ( $fields as $key => $cb ) {
            $val = isset( $_POST[ $key ] ) ? call_user_func( $cb, wp_unslash( $_POST[ $key ] ) ) : '';
            update_post_meta( $product_id, $key, $val );
        }
        update_post_meta( $product_id, '_ug_fixed', isset( $_POST['_ug_fixed'] ) ? 'yes' : 'no' );
    }

    /* ── Price calculation ─────────────────── */

    /**
     * Compute the wallet price for an order of $quantity of $product_id.
     */
    public static function calc_price( int $product_id, int $quantity ): float {
        $m = self::meta( $product_id );

        if ( $m['fixed'] || 'none' === $m['input_type'] ) {
            $product = wc_get_product( $product_id );
            return $product ? (float) $product->get_price() : 0.0;
        }

        if ( $m['rate'] > 0 ) {
            return round( ( $quantity / 1000 ) * $m['rate'] );
        }

        // Fallback: unit price × quantity.
        $product = wc_get_product( $product_id );
        return $product ? (float) $product->get_price() * $quantity : 0.0;
    }
}
