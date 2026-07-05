<?php
/**
 * UploadGram Theme — functions.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'UG_VERSION', '1.3.0' );
define( 'UG_DIR',     get_template_directory() );
define( 'UG_URI',     get_template_directory_uri() );

/* Auto page/menu setup on activation */
require_once UG_DIR . '/inc/setup-pages.php';

/* ══════════════════════════════════════════
   Multi-language (UI switcher)
   Persian default + 5 international languages.
   Deep content translation → use Polylang; this
   handles the theme's own chrome + <html dir/lang>.
══════════════════════════════════════════ */

/**
 * Supported languages.
 */
function ug_languages() {
    return [
        'fa' => [ 'name' => 'فارسی',    'flag' => '🇮🇷', 'dir' => 'rtl', 'locale' => 'fa_IR' ],
        'en' => [ 'name' => 'English',  'flag' => '🇬🇧', 'dir' => 'ltr', 'locale' => 'en_US' ],
        'ar' => [ 'name' => 'العربية',  'flag' => '🇸🇦', 'dir' => 'rtl', 'locale' => 'ar' ],
        'tr' => [ 'name' => 'Türkçe',   'flag' => '🇹🇷', 'dir' => 'ltr', 'locale' => 'tr_TR' ],
        'ru' => [ 'name' => 'Русский',  'flag' => '🇷🇺', 'dir' => 'ltr', 'locale' => 'ru_RU' ],
        'es' => [ 'name' => 'Español',  'flag' => '🇪🇸', 'dir' => 'ltr', 'locale' => 'es_ES' ],
    ];
}

/**
 * Current language code — from ?lang= (sets cookie) or cookie, default fa.
 */
function ug_current_lang() {
    static $lang = null;
    if ( null !== $lang ) {
        return $lang;
    }
    $langs = ug_languages();

    if ( isset( $_GET['lang'] ) && isset( $langs[ sanitize_key( $_GET['lang'] ) ] ) ) {
        $lang = sanitize_key( $_GET['lang'] );
        if ( ! headers_sent() ) {
            setcookie( 'ug_lang', $lang, time() + YEAR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN );
        }
        return $lang;
    }
    if ( isset( $_COOKIE['ug_lang'] ) && isset( $langs[ sanitize_key( $_COOKIE['ug_lang'] ) ] ) ) {
        $lang = sanitize_key( $_COOKIE['ug_lang'] );
        return $lang;
    }
    $lang = 'fa';
    return $lang;
}

function ug_dir() {
    $langs = ug_languages();
    return $langs[ ug_current_lang() ]['dir'] ?? 'rtl';
}

function ug_lang_flag( $code ) {
    $langs = ug_languages();
    return $langs[ $code ]['flag'] ?? '🌐';
}

/**
 * Switch WordPress locale to the chosen language.
 */
add_filter( 'locale', function ( $locale ) {
    if ( is_admin() ) {
        return $locale;
    }
    $langs = ug_languages();
    $cur   = ug_current_lang();
    return $langs[ $cur ]['locale'] ?? $locale;
} );

/**
 * Theme UI string dictionary (chrome only). Deep content → Polylang.
 */
function ug_strings() {
    return [
        'search_ph'    => [ 'fa' => 'جستجو در خدمات...', 'en' => 'Search services...', 'ar' => 'ابحث في الخدمات...', 'tr' => 'Hizmetlerde ara...', 'ru' => 'Поиск услуг...', 'es' => 'Buscar servicios...' ],
        'login'        => [ 'fa' => 'ورود', 'en' => 'Login', 'ar' => 'دخول', 'tr' => 'Giriş', 'ru' => 'Вход', 'es' => 'Entrar' ],
        'signup'       => [ 'fa' => 'ثبت‌نام', 'en' => 'Sign up', 'ar' => 'تسجيل', 'tr' => 'Kayıt ol', 'ru' => 'Регистрация', 'es' => 'Registrarse' ],
        'auth'         => [ 'fa' => 'ورود / ثبت‌نام', 'en' => 'Login / Sign up', 'ar' => 'دخول / تسجيل', 'tr' => 'Giriş / Kayıt', 'ru' => 'Вход / Регистрация', 'es' => 'Entrar / Registrarse' ],
        'panel'        => [ 'fa' => 'پنل', 'en' => 'Panel', 'ar' => 'اللوحة', 'tr' => 'Panel', 'ru' => 'Панель', 'es' => 'Panel' ],
        'wallet'       => [ 'fa' => 'کیف پول', 'en' => 'Wallet', 'ar' => 'المحفظة', 'tr' => 'Cüzdan', 'ru' => 'Кошелёк', 'es' => 'Cartera' ],
        'nav_home'     => [ 'fa' => 'خانه', 'en' => 'Home', 'ar' => 'الرئيسية', 'tr' => 'Anasayfa', 'ru' => 'Главная', 'es' => 'Inicio' ],
        'nav_member'   => [ 'fa' => 'خدمات مجازی', 'en' => 'Virtual Services', 'ar' => 'الخدمات الافتراضية', 'tr' => 'Sanal Hizmetler', 'ru' => 'Виртуальные услуги', 'es' => 'Servicios virtuales' ],
        'nav_account'  => [ 'fa' => 'اکانت پرمیوم', 'en' => 'Premium Accounts', 'ar' => 'حسابات بريميوم', 'tr' => 'Premium Hesap', 'ru' => 'Премиум аккаунты', 'es' => 'Cuentas premium' ],
        'nav_number'   => [ 'fa' => 'شماره مجازی', 'en' => 'Virtual Number', 'ar' => 'رقم افتراضي', 'tr' => 'Sanal Numara', 'ru' => 'Виртуальный номер', 'es' => 'Número virtual' ],
        'nav_stars'    => [ 'fa' => 'استارز', 'en' => 'Stars', 'ar' => 'ستارز', 'tr' => 'Stars', 'ru' => 'Stars', 'es' => 'Stars' ],
        'nav_discount' => [ 'fa' => 'تخفیف‌ها', 'en' => 'Discounts', 'ar' => 'خصومات', 'tr' => 'İndirimler', 'ru' => 'Скидки', 'es' => 'Descuentos' ],
        'nav_contact'  => [ 'fa' => 'تماس با ما', 'en' => 'Contact', 'ar' => 'اتصل بنا', 'tr' => 'İletişim', 'ru' => 'Контакты', 'es' => 'Contacto' ],

        /* Homepage hero */
        'hero_eyebrow' => [ 'fa' => '✦ بهترین خدمات دیجیتال در ایران', 'en' => '✦ The best digital services', 'ar' => '✦ أفضل الخدمات الرقمية', 'tr' => '✦ En iyi dijital hizmetler', 'ru' => '✦ Лучшие цифровые услуги', 'es' => '✦ Los mejores servicios digitales' ],
        'hero_h1a'     => [ 'fa' => 'خرید ممبر، اکانت و شماره مجازی', 'en' => 'Members, accounts & virtual numbers', 'ar' => 'أعضاء وحسابات وأرقام افتراضية', 'tr' => 'Üye, hesap ve sanal numara', 'ru' => 'Подписчики, аккаунты и виртуальные номера', 'es' => 'Miembros, cuentas y números virtuales' ],
        'hero_h1b'     => [ 'fa' => 'با تحویل آنی و امن', 'en' => 'delivered instantly & securely', 'ar' => 'بتسليم فوري وآمن', 'tr' => 'anında ve güvenli teslimat', 'ru' => 'мгновенно и безопасно', 'es' => 'entrega instantánea y segura' ],
        'hero_sub'     => [ 'fa' => 'ممبر واقعی تلگرام و اینستاگرام، اکانت پرمیوم اصل، شماره مجازی معتبر از ۳۰+ کشور — سفارش می‌دهید، در همان لحظه تحویل می‌گیرید.', 'en' => 'Real Telegram & Instagram members, genuine premium accounts, verified virtual numbers from 30+ countries — order and receive instantly.', 'ar' => 'أعضاء حقيقيون لتيليجرام وإنستغرام، حسابات بريميوم أصلية، أرقام افتراضية موثوقة من أكثر من 30 دولة — اطلب واستلم فورًا.', 'tr' => 'Gerçek Telegram ve Instagram üyeleri, orijinal premium hesaplar, 30+ ülkeden doğrulanmış sanal numaralar — sipariş verin, anında alın.', 'ru' => 'Реальные подписчики Telegram и Instagram, оригинальные премиум-аккаунты, проверенные виртуальные номера из 30+ стран — заказывайте и получайте мгновенно.', 'es' => 'Miembros reales de Telegram e Instagram, cuentas premium genuinas, números virtuales verificados de más de 30 países: pide y recibe al instante.' ],
        'hero_cta1'    => [ 'fa' => 'مشاهده همه خدمات ←', 'en' => 'View all services ←', 'ar' => 'كل الخدمات ←', 'tr' => 'Tüm hizmetler ←', 'ru' => 'Все услуги ←', 'es' => 'Ver servicios ←' ],
        'hero_cta2'    => [ 'fa' => 'شروع در تلگرام', 'en' => 'Start on Telegram', 'ar' => 'ابدأ في تيليجرام', 'tr' => "Telegram'da başla", 'ru' => 'Начать в Telegram', 'es' => 'Empezar en Telegram' ],
        'trust_support'=> [ 'fa' => 'پشتیبانی آنلاین', 'en' => 'Online support', 'ar' => 'دعم مباشر', 'tr' => 'Çevrimiçi destek', 'ru' => 'Онлайн-поддержка', 'es' => 'Soporte en línea' ],
        'trust_rating' => [ 'fa' => 'امتیاز میانگین', 'en' => 'Average rating', 'ar' => 'متوسط التقييم', 'tr' => 'Ortalama puan', 'ru' => 'Средний рейтинг', 'es' => 'Valoración media' ],
        'trust_clients'=> [ 'fa' => 'مشتری راضی', 'en' => 'Happy clients', 'ar' => 'عملاء سعداء', 'tr' => 'Mutlu müşteri', 'ru' => 'Довольных клиентов', 'es' => 'Clientes felices' ],
    ];
}

/**
 * Translate a chrome key to the current language.
 */
function ug_t( $key ) {
    $strings = ug_strings();
    $lang    = ug_current_lang();
    if ( isset( $strings[ $key ][ $lang ] ) ) {
        return $strings[ $key ][ $lang ];
    }
    return $strings[ $key ]['fa'] ?? $key;
}

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

    // Editor / block styles so Elementor & Gutenberg get full-width canvases.
    add_theme_support( 'align-wide' );
    add_theme_support( 'responsive-embeds' );
}
add_action( 'after_setup_theme', 'ug_setup' );

/* ══════════════════════════════════════════
   Elementor Pro — Theme Builder locations
   (lets Elementor Pro override header/footer/
    single/archive; falls back to our templates)
══════════════════════════════════════════ */
add_action( 'elementor/theme/register_locations', function ( $manager ) {
    $manager->register_all_core_location();
} );

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

    // Support chat widget (site-wide)
    wp_enqueue_style( 'ug-chat', UG_URI . '/assets/css/chat.css', [ 'ug-main' ], $v );
    wp_enqueue_script( 'ug-chat', UG_URI . '/assets/js/chat.js', [], $v, true );

    // Localize script
    wp_localize_script( 'ug-main', 'ugData', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'ug_nonce' ),
        'homeUrl' => home_url( '/' ),
    ] );
}

/**
 * Support Telegram username for the chat widget / footer.
 * Set via: add_option or Customizer option 'ug_support_telegram', or filter.
 */
function ug_support_telegram() {
    return apply_filters( 'ug_support_telegram', get_option( 'ug_support_telegram', '' ) );
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
        'خدمات مجازی'  => home_url( '/member/' ),
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
        [ 'label' => ug_t( 'nav_home' ),     'url' => home_url( '/' ),                'slug' => 'home' ],
        [ 'label' => ug_t( 'nav_member' ),   'url' => home_url( '/member/' ),         'slug' => 'member' ],
        [ 'label' => ug_t( 'nav_account' ),  'url' => home_url( '/account/' ),        'slug' => 'account' ],
        [ 'label' => ug_t( 'nav_number' ),   'url' => home_url( '/virtual-number/' ), 'slug' => 'virtual-number' ],
        [ 'label' => ug_t( 'nav_stars' ),    'url' => '#',                            'slug' => '' ],
        [ 'label' => ug_t( 'nav_discount' ), 'url' => '#',                            'slug' => '' ],
        [ 'label' => ug_t( 'nav_contact' ),  'url' => home_url( '/contact/' ),        'slug' => 'contact' ],
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

    // (Cart is disabled — wallet model only — so no cart item in the nav.)
}

/* ══════════════════════════════════════════
   Body Classes
══════════════════════════════════════════ */
add_filter( 'body_class', function( $classes ) {
    $classes[] = 'ug-theme';
    if ( is_rtl() ) $classes[] = 'rtl';
    if ( is_page_template( 'page-panel.php' ) ) {
        $classes[] = 'ug-in-panel';
    }
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

/* ══════════════════════════════════════════
   Performance / speed (esp. Iran hosts)
   The single biggest win: WordPress's emoji script fetches Twemoji from
   s.w.org, which is blocked/throttled in Iran and makes the page loading
   bar stall near the end. We remove it and other external/needless requests.
══════════════════════════════════════════ */
add_action( 'init', function () {
    // 1) Kill wp-emoji (removes the blocked s.w.org request → no more stalled loading).
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
    add_filter( 'emoji_svg_url', '__return_false' );
    add_filter( 'tiny_mce_plugins', function ( $plugins ) {
        return is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : $plugins;
    } );

    // 2) Trim wp_head bloat.
    remove_action( 'wp_head', 'rsd_link' );
    remove_action( 'wp_head', 'wlwmanifest_link' );
    remove_action( 'wp_head', 'wp_generator' );
    remove_action( 'wp_head', 'wp_shortlink_wp_head' );
    remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );

    // 3) Disable oEmbed discovery + its front-end JS.
    remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
    remove_action( 'wp_head', 'wp_oembed_add_host_js' );
    add_filter( 'embed_oembed_discover', '__return_false' );

    // 4) Disable XML-RPC (attack surface + overhead).
    add_filter( 'xmlrpc_enabled', '__return_false' );
}, 20 );

// 5) Throttle the Heartbeat API (eases admin-ajax load → snappier dashboard).
add_filter( 'heartbeat_settings', function ( $s ) {
    $s['interval'] = 60;
    return $s;
} );

// 6) Remove the classic-theme inline styles + block library CSS on non-Woo,
//    non-block front pages (smaller payload). Kept for WooCommerce pages.
add_action( 'wp_enqueue_scripts', function () {
    if ( is_admin() ) {
        return;
    }
    $is_woo = function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() );
    if ( ! $is_woo ) {
        wp_dequeue_style( 'wp-block-library' );
        wp_dequeue_style( 'wp-block-library-theme' );
        wp_dequeue_style( 'classic-theme-styles' );
        wp_dequeue_style( 'global-styles' );
    }
}, 100 );
