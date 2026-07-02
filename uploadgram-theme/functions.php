<?php
/**
 * UploadGram Theme — functions.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'UG_VERSION', '1.0.0' );
define( 'UG_DIR',     get_template_directory() );
define( 'UG_URI',     get_template_directory_uri() );

/* Auto page/menu setup on activation */
require_once UG_DIR . '/inc/setup-pages.php';

/* ══════════════════════════════════════════
   Theme Setup
══════════════════════════════════════════ */
function ug_setup() {
    load_theme_textdomain( 'uploadgram', UG_DIR . '/languages' );

    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'script', 'style' ] );
    add_theme_support( 'custom-logo', [
        'height'      => 60,
        'width'       => 160,
        'flex-height' => true,
        'flex-width'  => true,
    ] );
    add_theme_support( 'woocommerce' );
    add_theme_support( 'wc-product-gallery-zoom' );
    add_theme_support( 'wc-product-gallery-lightbox' );
    add_theme_support( 'wc-product-gallery-slider' );

    register_nav_menus( [
        'primary'  => __( 'منوی اصلی', 'uploadgram' ),
        'footer-1' => __( 'منوی پاورقی خدمات', 'uploadgram' ),
        'footer-2' => __( 'منوی پاورقی راهنما', 'uploadgram' ),
    ] );
}
add_action( 'after_setup_theme', 'ug_setup' );

/* ══════════════════════════════════════════
   Enqueue Styles & Scripts
══════════════════════════════════════════ */
function ug_enqueue_assets() {
    $v = UG_VERSION;

    // Main CSS
    wp_enqueue_style( 'ug-main', UG_URI . '/assets/css/main.css', [], $v );

    // WooCommerce override (if active)
    if ( class_exists( 'WooCommerce' ) ) {
        wp_enqueue_style( 'ug-woo', UG_URI . '/assets/css/woocommerce.css', [ 'ug-main' ], $v );
    }

    // Main JS
    wp_enqueue_script( 'ug-main', UG_URI . '/assets/js/main.js', [], $v, true );

    // Localize script
    wp_localize_script( 'ug-main', 'ugData', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'ug_nonce' ),
        'homeUrl' => home_url( '/' ),
    ] );
}
add_action( 'wp_enqueue_scripts', 'ug_enqueue_assets' );

// Remove WooCommerce default styles
add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );

/* ══════════════════════════════════════════
   Widgets / Sidebars
══════════════════════════════════════════ */
function ug_register_sidebars() {
    register_sidebar( [
        'name'          => __( 'ویجت پاورقی ۱', 'uploadgram' ),
        'id'            => 'footer-1',
        'before_widget' => '<div class="footer-widget">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4>',
        'after_title'   => '</h4>',
    ] );
}
add_action( 'widgets_init', 'ug_register_sidebars' );

/* ══════════════════════════════════════════
   Custom Post Types
══════════════════════════════════════════ */
function ug_register_post_types() {
    // Digital Service CPT
    register_post_type( 'ug_service', [
        'labels'      => [
            'name'          => __( 'خدمات', 'uploadgram' ),
            'singular_name' => __( 'خدمت', 'uploadgram' ),
            'add_new_item'  => __( 'افزودن خدمت جدید', 'uploadgram' ),
        ],
        'public'      => true,
        'has_archive' => true,
        'menu_icon'   => 'dashicons-star-filled',
        'supports'    => [ 'title', 'editor', 'thumbnail', 'custom-fields', 'excerpt' ],
        'rewrite'     => [ 'slug' => 'services' ],
        'show_in_rest' => true,
    ] );

    // FAQ CPT
    register_post_type( 'ug_faq', [
        'labels'      => [
            'name'          => __( 'سوالات متداول', 'uploadgram' ),
            'singular_name' => __( 'سوال', 'uploadgram' ),
        ],
        'public'      => false,
        'show_ui'     => true,
        'menu_icon'   => 'dashicons-editor-help',
        'supports'    => [ 'title', 'editor' ],
        'show_in_rest' => true,
    ] );
}
add_action( 'init', 'ug_register_post_types' );

/* ══════════════════════════════════════════
   Custom Taxonomies
══════════════════════════════════════════ */
function ug_register_taxonomies() {
    register_taxonomy( 'service_cat', 'ug_service', [
        'labels'       => [
            'name'          => __( 'دسته‌بندی خدمات', 'uploadgram' ),
            'singular_name' => __( 'دسته‌بندی', 'uploadgram' ),
        ],
        'hierarchical' => true,
        'rewrite'      => [ 'slug' => 'service-cat' ],
        'show_in_rest' => true,
    ] );
}
add_action( 'init', 'ug_register_taxonomies' );

/* ══════════════════════════════════════════
   Helper Functions
══════════════════════════════════════════ */

/**
 * Render the site logo.
 */
function ug_logo() {
    $logo_url = UG_URI . '/assets/brand/logo-small.png';
    if ( function_exists( 'get_custom_logo' ) && has_custom_logo() ) {
        echo get_custom_logo();
        return;
    }
    echo '<a href="' . esc_url( home_url( '/' ) ) . '" class="logo">';
    echo '<div class="logo-mark"><img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '"></div>';
    echo '<span class="logo-text">آپلود<span>گرام</span></span>';
    echo '</a>';
}

/**
 * Render primary navigation.
 */
function ug_primary_nav( $active_page = '' ) {
    $links = [
        'خانه'        => home_url( '/' ),
        'خرید ممبر'   => home_url( '/member/' ),
        'اکانت پرمیوم' => home_url( '/account/' ),
        'شماره مجازی'  => home_url( '/virtual-number/' ),
        'استارز'      => home_url( '/stars/' ),
        'تخفیف‌ها'    => home_url( '/discounts/' ),
        'تماس با ما'  => home_url( '/contact/' ),
    ];

    echo '<nav class="main-nav"><div class="nav-inner">';
    foreach ( $links as $label => $url ) {
        $active = ( strpos( $url, $active_page ) !== false && $active_page ) ? ' active' : '';
        echo '<a class="nav-link' . $active . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
    }
    echo '</div></nav>';
}

/**
 * Render FAQ items from CPT or array.
 *
 * @param array $items [ ['q' => '...', 'a' => '...'], ... ]
 */
function ug_faq_items( $items = [] ) {
    $chevron = '<svg class="chev" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>';
    echo '<div class="faq-section">';
    echo '<div class="section-title-wrap" style="margin-bottom:16px;"><div class="section-dot" style="background:var(--mint);"></div><div class="section-heading">سوالات متداول</div></div>';
    foreach ( $items as $item ) {
        echo '<div class="faq-item">';
        echo '<div class="faq-q">' . esc_html( $item['q'] ) . $chevron . '</div>';
        echo '<div class="faq-a">' . wp_kses_post( $item['a'] ) . '</div>';
        echo '</div>';
    }
    echo '</div>';
}

/**
 * Render a section heading.
 *
 * @param string $title
 * @param string $dot_color CSS color value
 * @param string $see_all_url Optional link URL
 * @param string $see_all_label Optional link label
 */
function ug_section_head( $title, $dot_color = 'var(--mint)', $see_all_url = '', $see_all_label = 'مشاهده همه' ) {
    echo '<div class="section-head">';
    echo '<div class="section-title-wrap">';
    echo '<div class="section-dot" style="background:' . esc_attr( $dot_color ) . ';"></div>';
    echo '<div class="section-heading">' . esc_html( $title ) . '</div>';
    echo '</div>';
    if ( $see_all_url ) {
        echo '<a class="section-see-all" href="' . esc_url( $see_all_url ) . '">' . esc_html( $see_all_label ) . ' ←</a>';
    }
    echo '</div>';
}

/**
 * Render asset URI helper.
 */
function ug_asset( $path ) {
    return UG_URI . '/assets/' . ltrim( $path, '/' );
}

/**
 * Primary nav walker — outputs flat <a class="nav-link"> items.
 * Defined here (not in header.php) so it is always available before use.
 */
if ( ! class_exists( 'UG_Nav_Walker' ) ) {
    class UG_Nav_Walker extends Walker_Nav_Menu {
        public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
            $classes   = empty( $data_object->classes ) ? [] : (array) $data_object->classes;
            $is_active = in_array( 'current-menu-item', $classes, true ) || in_array( 'current-page-ancestor', $classes, true );
            $output   .= '<a class="nav-link' . ( $is_active ? ' active' : '' ) . '" href="' . esc_url( $data_object->url ) . '">' . esc_html( $data_object->title ) . '</a>';
        }
        public function end_el( &$output, $data_object, $depth = 0, $args = null ) {}
        public function start_lvl( &$output, $depth = 0, $args = null ) {}
        public function end_lvl( &$output, $depth = 0, $args = null ) {}
    }
}

/**
 * Fallback nav when no menu is assigned to the "primary" location —
 * prevents an empty navbar on a fresh install.
 */
function ug_primary_nav_fallback() {
    $current = '';
    if ( is_front_page() ) {
        $current = 'home';
    } elseif ( is_page() ) {
        $current = get_post_field( 'post_name', get_queried_object_id() );
    }

    $links = [
        [ 'label' => 'خانه',         'url' => home_url( '/' ),                'slug' => 'home' ],
        [ 'label' => 'خرید ممبر',    'url' => home_url( '/member/' ),         'slug' => 'member' ],
        [ 'label' => 'اکانت پرمیوم', 'url' => home_url( '/account/' ),        'slug' => 'account' ],
        [ 'label' => 'شماره مجازی',  'url' => home_url( '/virtual-number/' ), 'slug' => 'virtual-number' ],
        [ 'label' => 'استارز',       'url' => '#',                            'slug' => '' ],
        [ 'label' => 'تخفیف‌ها',     'url' => '#',                            'slug' => '' ],
        [ 'label' => 'تماس با ما',   'url' => home_url( '/contact/' ),        'slug' => 'contact' ],
    ];

    foreach ( $links as $l ) {
        $active = ( $l['slug'] && $l['slug'] === $current ) ? ' active' : '';
        echo '<a class="nav-link' . $active . '" href="' . esc_url( $l['url'] ) . '">' . esc_html( $l['label'] ) . '</a>';
    }
}

/**
 * Get current Jalali (Persian) year — lightweight Gregorian→Jalali conversion.
 *
 * @return int
 */
function ug_jalali_year() {
    $gy = (int) current_time( 'Y' );
    $gm = (int) current_time( 'n' );
    $gd = (int) current_time( 'j' );

    $g_d_m = [ 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 ];
    $gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
    $days  = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 )
           + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
    $jy    = -1595 + ( 33 * intdiv( $days, 12053 ) );
    $days %= 12053;
    $jy   += 4 * intdiv( $days, 1461 );
    $days %= 1461;
    if ( $days > 365 ) {
        $jy   += intdiv( $days - 1, 365 );
    }
    return $jy;
}

/* ══════════════════════════════════════════
   WooCommerce Tweaks
══════════════════════════════════════════ */
if ( class_exists( 'WooCommerce' ) ) {
    // Remove WC sidebar
    remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

    // Change add to cart text
    add_filter( 'woocommerce_product_single_add_to_cart_text', function() {
        return __( 'افزودن به سبد خرید', 'uploadgram' );
    } );
    add_filter( 'woocommerce_product_add_to_cart_text', function() {
        return __( 'افزودن به سبد', 'uploadgram' );
    } );

    // Cart count in header
    add_filter( 'wp_nav_menu_items', 'ug_cart_nav_item', 10, 2 );
    function ug_cart_nav_item( $items, $args ) {
        if ( 'primary' === $args->theme_location ) {
            $count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
            $items .= '<li class="cart-nav-item"><a href="' . wc_get_cart_url() . '">🛒 سبد خرید <span class="cart-badge num">' . $count . '</span></a></li>';
        }
        return $items;
    }
}

/* ══════════════════════════════════════════
   Body Classes
══════════════════════════════════════════ */
add_filter( 'body_class', function( $classes ) {
    $classes[] = 'ug-theme';
    if ( is_rtl() ) $classes[] = 'rtl';
    return $classes;
} );

/* ══════════════════════════════════════════
   RTL direction on html element
══════════════════════════════════════════ */
add_action( 'wp_head', function() {
    echo '<meta name="theme-color" content="#0b0d17">';
} );

/* ══════════════════════════════════════════
   Excerpt length
══════════════════════════════════════════ */
add_filter( 'excerpt_length', fn() => 20 );
add_filter( 'excerpt_more',   fn() => '...' );
