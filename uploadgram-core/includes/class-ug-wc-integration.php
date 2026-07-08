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

        // Wallet-first model: disable the cart EXCEPT for products explicitly
        // marked for online payment (accounts), which may go through the gateway.
        if ( 'yes' === $this->settings->get( 'disable_cart', 'yes' ) ) {
            add_filter( 'woocommerce_is_purchasable', [ $this, 'is_purchasable' ], 10, 2 );
            // Replace the default add-to-cart with our instant wallet order form.
            remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
            add_action( 'woocommerce_single_product_summary', [ $this, 'single_order_form' ], 30 );
        }

        // Show the brand icon in place of the product image when none is set.
        add_filter( 'woocommerce_single_product_image_thumbnail_html', [ $this, 'brand_image' ], 10, 2 );
        add_filter( 'post_thumbnail_html', [ $this, 'loop_brand_image' ], 10, 5 );
    }

    public function brand_image( $html, $post_id ) {
        if ( has_post_thumbnail( $post_id ) ) {
            return $html;
        }
        $icon = self::icon_url( (int) $post_id );
        if ( ! $icon ) {
            return $html;
        }
        return '<div class="ug-brand-figure"><img src="' . esc_url( $icon ) . '" alt="' . esc_attr( get_the_title( $post_id ) ) . '"></div>';
    }

    public function loop_brand_image( $html, $post_id, $thumb_id, $size, $attr ) {
        if ( 'product' !== get_post_type( $post_id ) || $thumb_id ) {
            return $html;
        }
        $icon = self::icon_url( (int) $post_id );
        return $icon ? '<img class="ug-acc-brand" src="' . esc_url( $icon ) . '" alt="' . esc_attr( get_the_title( $post_id ) ) . '">' : $html;
    }

    /**
     * Only products flagged for online payment stay purchasable via the cart;
     * everything else uses the wallet order form.
     */
    public function is_purchasable( $purchasable, $product ) {
        $pid = is_object( $product ) ? $product->get_id() : 0;
        return $pid && 'yes' === get_post_meta( $pid, '_ug_allow_online', true );
    }

    /**
     * On a product's own page, show the instant wallet-order form for any
     * product connected to a UploadGram service (or a fixed-price account).
     */
    public function single_order_form(): void {
        global $product;
        if ( ! $product instanceof WC_Product ) {
            return;
        }
        $pid = $product->get_id();
        $m   = self::meta( $pid );
        // Only our products (has provider, or explicitly fixed like accounts).
        if ( empty( $m['provider'] ) && ! $m['fixed'] && '' === $m['kind'] ) {
            return;
        }

        echo '<div class="ug-single-buy">';

        // Feature list (from _ug_features).
        if ( ! empty( $m['features'] ) ) {
            echo '<ul class="ug-feature-list">';
            foreach ( $m['features'] as $f ) {
                echo '<li>' . esc_html( $f ) . '</li>';
            }
            echo '</ul>';
        }

        // Wallet order form.
        echo do_shortcode( '[ug_order_form id="' . (int) $pid . '"]' );

        // Online payment (gateway) for flagged products.
        if ( ! empty( $m['allow_online'] ) && function_exists( 'wc_get_checkout_url' ) ) {
            $url = esc_url( add_query_arg( 'add-to-cart', $pid, wc_get_checkout_url() ) );
            echo '<a class="ug-btn ug-btn-secondary ug-pay-online" href="' . $url . '">💳 پرداخت آنلاین (بدون کیف پول)</a>';
        }

        echo '</div>';
    }

    /**
     * Brand icon URL for a product (theme asset path stored in _ug_icon).
     */
    public static function icon_url( int $product_id ): string {
        $icon = get_post_meta( $product_id, '_ug_icon', true );
        if ( ! $icon ) {
            return '';
        }
        if ( function_exists( 'ug_asset' ) ) {
            return ug_asset( $icon );
        }
        return get_template_directory_uri() . '/assets/' . ltrim( $icon, '/' );
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
            'order_type' => get_post_meta( $product_id, '_ug_order_type', true ) ?: 'ethical',
            'platform'   => get_post_meta( $product_id, '_ug_platform', true ),
            'kind'       => get_post_meta( $product_id, '_ug_kind', true ),
            'icon'       => get_post_meta( $product_id, '_ug_icon', true ),
            'features'   => array_values( (array) json_decode( (string) get_post_meta( $product_id, '_ug_features', true ), true ) ),
            'allow_online' => 'yes' === get_post_meta( $product_id, '_ug_allow_online', true ),
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

            woocommerce_wp_select( [
                'id'          => '_ug_order_type',
                'label'       => __( 'نوع سفارش تلگرام', 'uploadgram-core' ),
                'value'       => $m['order_type'],
                'options'     => [
                    'ethical'   => __( 'ممبر اجباری (اخلاقی)', 'uploadgram-core' ),
                    'unethical' => __( 'ممبر مجازی (غیراخلاقی)', 'uploadgram-core' ),
                ],
                'desc_tip'    => true,
                'description' => __( 'فقط برای محصولات «ربات تلگرام» کاربرد دارد.', 'uploadgram-core' ),
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

            woocommerce_wp_text_input( [
                'id'          => '_ug_platform',
                'label'       => __( 'پلتفرم (اپلیکیشن)', 'uploadgram-core' ),
                'value'       => $m['platform'],
                'desc_tip'    => true,
                'description' => __( 'برای دسته‌بندی در «خدمات مجازی/شماره مجازی». مثال: instagram / telegram / youtube / tiktok / spotify / whatsapp', 'uploadgram-core' ),
            ] );

            woocommerce_wp_text_input( [
                'id'          => '_ug_kind',
                'label'       => __( 'نوع خدمت', 'uploadgram-core' ),
                'value'       => $m['kind'],
                'desc_tip'    => true,
                'description' => __( 'مثال: followers (فالوور) / likes (لایک) / views (بازدید) / members (ممبر) / comments (کامنت)', 'uploadgram-core' ),
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
            '_ug_order_type' => 'sanitize_text_field',
            '_ug_platform'   => 'sanitize_text_field',
            '_ug_kind'       => 'sanitize_text_field',
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
