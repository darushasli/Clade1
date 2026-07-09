<?php
/**
 * UploadGram Elementor widget classes. Loaded only inside
 * elementor/widgets/register, so \Elementor\Widget_Base exists.
 *
 * Display widgets are FULLY editable: every text, image (media library) and
 * repeater item is exposed as an Elementor control, so pages can be designed
 * visually without shortcodes. Functional widgets (auth/panel/live app) render
 * their dynamic block.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Controls_Manager as CM;
use Elementor\Repeater;

/* ═══════════════════════════════════════════════════════════
 * Base classes
 * ═══════════════════════════════════════════════════════════ */

abstract class UG_Widget_Base extends \Elementor\Widget_Base {

    protected string $ug_title = '';
    protected string $ug_icon = 'eicon-posts-grid';

    public function get_categories(): array {
        return [ 'uploadgram' ];
    }
    public function get_icon(): string {
        return $this->ug_icon;
    }
    public function get_title(): string {
        return $this->ug_title;
    }
    public function get_keywords(): array {
        return [ 'uploadgram', 'آپلودگرام', 'ug' ];
    }

    /** Theme asset URL (used for control defaults). */
    protected function asset( string $p ): string {
        return function_exists( 'ug_asset' ) ? ug_asset( $p ) : ( get_template_directory_uri() . '/assets/' . ltrim( $p, '/' ) );
    }

    /** Extract a URL from an Elementor MEDIA control value. */
    protected function url( $media ): string {
        if ( is_array( $media ) ) {
            return $media['url'] ?? '';
        }
        return (string) $media;
    }

    /** A MEDIA (image) control definition with a default asset URL. */
    protected function media_ctrl( string $default_asset ): array {
        return [
            'type'    => CM::MEDIA,
            'default' => [ 'url' => $this->asset( $default_asset ) ],
        ];
    }

    protected function heading_html( string $title ): string {
        if ( '' === $title ) {
            return '';
        }
        return '<div class="section-head"><div class="section-title-wrap"><div class="section-dot"></div><div class="section-heading">' . esc_html( $title ) . '</div></div></div>';
    }

    /* ── Reusable STYLE controls (live, via Elementor selectors) ── */

    /** Open a Style tab section. */
    protected function ug_style_start( string $id = 'ug_style', string $label = 'استایل و اندازه‌ها' ): void {
        $this->start_controls_section( $id, [ 'label' => $label, 'tab' => CM::TAB_STYLE ] );
    }
    protected function ug_style_end(): void {
        $this->end_controls_section();
    }

    /** Image/icon size slider targeting one or more selectors. */
    protected function ug_ctrl_img( string $selector, int $default = 44, int $max = 220, string $id = 'ug_img' ): void {
        $this->add_responsive_control( $id, [
            'label'      => 'اندازهٔ عکس/آیکن',
            'type'       => CM::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 16, 'max' => $max ] ],
            'default'    => [ 'unit' => 'px', 'size' => $default ],
            'selectors'  => [ '{{WRAPPER}} ' . $selector => 'width:{{SIZE}}{{UNIT}};height:{{SIZE}}{{UNIT}};object-fit:contain;' ],
        ] );
    }

    /** Gap between grid/flex items. */
    protected function ug_ctrl_gap( string $selector, int $default = 16, string $id = 'ug_gap' ): void {
        $this->add_responsive_control( $id, [
            'label'      => 'فاصلهٔ بین آیتم‌ها',
            'type'       => CM::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
            'default'    => [ 'unit' => 'px', 'size' => $default ],
            'selectors'  => [ '{{WRAPPER}} ' . $selector => 'gap:{{SIZE}}{{UNIT}};' ],
        ] );
    }

    /** Number of columns for a grid container. */
    protected function ug_ctrl_cols( string $selector, string $id = 'ug_cols' ): void {
        $this->add_responsive_control( $id, [
            'label'     => 'تعداد ستون',
            'type'      => CM::SELECT,
            'options'   => [ '' => 'خودکار', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶' ],
            'default'   => '',
            'selectors' => [ '{{WRAPPER}} ' . $selector => 'display:grid;grid-template-columns:repeat({{VALUE}},minmax(0,1fr));' ],
        ] );
    }

    /** Max-width for a large image (keeps aspect ratio). */
    protected function ug_ctrl_width( string $selector, int $default = 340, int $max = 700, string $id = 'ug_w' ): void {
        $this->add_responsive_control( $id, [
            'label'      => 'اندازهٔ تصویر',
            'type'       => CM::SLIDER,
            'size_units' => [ 'px', '%' ],
            'range'      => [ 'px' => [ 'min' => 80, 'max' => $max ], '%' => [ 'min' => 10, 'max' => 100 ] ],
            'default'    => [ 'unit' => 'px', 'size' => $default ],
            'selectors'  => [ '{{WRAPPER}} ' . $selector => 'width:{{SIZE}}{{UNIT}};max-width:100%;height:auto;' ],
        ] );
    }

    /** Padding for the inner cards. */
    protected function ug_ctrl_pad( string $selector, string $id = 'ug_pad' ): void {
        $this->add_responsive_control( $id, [
            'label'      => 'فاصلهٔ داخلی باکس',
            'type'       => CM::DIMENSIONS,
            'size_units' => [ 'px' ],
            'selectors'  => [ '{{WRAPPER}} ' . $selector => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ] );
    }
}

/**
 * Base for functional widgets that render a dynamic shortcode (live app).
 */
abstract class UG_Dyn_Widget extends UG_Widget_Base {
    protected string $ug_tag = '';
    protected function ug_atts( array $s ): string { return ''; }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        echo do_shortcode( '[' . $this->ug_tag . $this->ug_atts( (array) $s ) . ']' );
    }
}

/* ═══════════════════════════════════════════════════════════
 * Registry
 * ═══════════════════════════════════════════════════════════ */

class UG_Elementor_Widgets {
    public static function classes(): array {
        return [
            // Heroes
            'UG_W_Hero', 'UG_W_Page_Hero',
            // Fully-editable marketing widgets
            'UG_W_Section_Heading', 'UG_W_Service_Cats', 'UG_W_Quick_Cats', 'UG_W_Stats',
            'UG_W_Featured', 'UG_W_Promo', 'UG_W_Why_Us', 'UG_W_Member_Types',
            'UG_W_Steps', 'UG_W_Service_Icons', 'UG_W_Price_Table', 'UG_W_Trust_Banner', 'UG_W_Faq',
            // Dynamic (live app / data-driven)
            'UG_W_Services_App', 'UG_W_Numbers_App', 'UG_W_Member_Plans', 'UG_W_Account_Grid',
            'UG_W_Auth', 'UG_W_Panel', 'UG_W_Dashboard', 'UG_W_Wallet',
            'UG_W_Topup', 'UG_W_Wallet_Tx', 'UG_W_My_Orders', 'UG_W_Profile',
        ];
    }
}

/* ═══════════════════════════════════════════════════════════
 * Editable widgets
 * ═══════════════════════════════════════════════════════════ */

class UG_W_Hero extends UG_Widget_Base {
    protected string $ug_title = 'هیرو صفحه اصلی';
    protected string $ug_icon = 'eicon-slides';
    public function get_name(): string { return 'ug-hero'; }
    protected function register_controls(): void {
        $this->start_controls_section( 's', [ 'label' => 'محتوای هیرو' ] );
        $this->add_control( 'image', [ 'label' => 'تصویر' ] + $this->media_ctrl( 'hero/upload-hero-phone.png' ) );
        $this->add_control( 'eyebrow', [ 'label' => 'متن بالای عنوان', 'type' => CM::TEXT, 'default' => '✦ بهترین خدمات دیجیتال در ایران' ] );
        $this->add_control( 'title_hl', [ 'label' => 'عنوان (بخش رنگی)', 'type' => CM::TEXT, 'default' => 'خرید ممبر، اکانت و شماره مجازی' ] );
        $this->add_control( 'title_rest', [ 'label' => 'عنوان (خط دوم)', 'type' => CM::TEXT, 'default' => 'با تحویل آنی و امن' ] );
        $this->add_control( 'sub', [ 'label' => 'زیرعنوان', 'type' => CM::TEXTAREA, 'default' => 'ممبر واقعی، اکانت پرمیوم اصل و شماره مجازی از ۳۰+ کشور — سفارش می‌دهید، در همان لحظه تحویل می‌گیرید.' ] );
        $this->add_control( 'cta1', [ 'label' => 'دکمهٔ اول', 'type' => CM::TEXT, 'default' => 'مشاهده همه خدمات ←' ] );
        $this->add_control( 'cta1_link', [ 'label' => 'لینک دکمهٔ اول', 'type' => CM::URL, 'default' => [ 'url' => home_url( '/member/' ) ] ] );
        $this->add_control( 'cta2', [ 'label' => 'دکمهٔ دوم', 'type' => CM::TEXT, 'default' => 'شروع در تلگرام' ] );
        $this->add_control( 'cta2_link', [ 'label' => 'لینک دکمهٔ دوم', 'type' => CM::URL, 'default' => [ 'url' => '#' ] ] );
        $r = new Repeater();
        $r->add_control( 'val', [ 'label' => 'مقدار', 'type' => CM::TEXT, 'default' => '24/7' ] );
        $r->add_control( 'label', [ 'label' => 'برچسب', 'type' => CM::TEXT, 'default' => 'پشتیبانی' ] );
        $this->add_control( 'trust', [ 'type' => CM::REPEATER, 'fields' => $r->get_controls(), 'title_field' => '{{{ val }}}', 'default' => [
            [ 'val' => '24/7', 'label' => 'پشتیبانی آنلاین' ],
            [ 'val' => '۴.۹ ★', 'label' => 'امتیاز میانگین' ],
            [ 'val' => '+12K', 'label' => 'مشتری راضی' ],
        ] ] );
        $this->end_controls_section();
        $this->ug_style_start();
        $this->ug_ctrl_width( '.hero-img', 340, 700, 'ug_hero_img' );
        $this->ug_style_end();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        echo '<section class="hero">';
        echo '<img class="hero-img" src="' . esc_url( $this->url( $s['image'] ?? '' ) ) . '" alt="">';
        echo '<div class="hero-content">';
        if ( ! empty( $s['eyebrow'] ) ) { echo '<div class="hero-eyebrow">' . esc_html( $s['eyebrow'] ) . '</div>'; }
        echo '<h1><span>' . esc_html( $s['title_hl'] ?? '' ) . '</span><br>' . esc_html( $s['title_rest'] ?? '' ) . '</h1>';
        echo '<p>' . esc_html( $s['sub'] ?? '' ) . '</p>';
        echo '<div class="hero-ctas"><a href="' . esc_url( $s['cta1_link']['url'] ?? '#' ) . '" class="btn-hero">' . esc_html( $s['cta1'] ?? '' ) . '</a><a href="' . esc_url( $s['cta2_link']['url'] ?? '#' ) . '" class="btn-hero-outline">' . esc_html( $s['cta2'] ?? '' ) . '</a></div>';
        echo '<div class="hero-trust">';
        foreach ( (array) ( $s['trust'] ?? [] ) as $t ) {
            echo '<div><span class="n num">' . esc_html( $t['val'] ?? '' ) . '</span><span class="l">' . esc_html( $t['label'] ?? '' ) . '</span></div>';
        }
        echo '</div></div></section>';
    }
}

class UG_W_Page_Hero extends UG_Widget_Base {
    protected string $ug_title = 'هیرو صفحه داخلی';
    protected string $ug_icon = 'eicon-image-box';
    public function get_name(): string { return 'ug-page-hero'; }
    protected function register_controls(): void {
        $this->start_controls_section( 's', [ 'label' => 'محتوا' ] );
        $this->add_control( 'variant', [ 'label' => 'رنگ‌بندی', 'type' => CM::SELECT, 'default' => 'member', 'options' => [ 'member' => 'خدمات مجازی', 'account' => 'اکانت', 'virtual' => 'شماره مجازی' ] ] );
        $this->add_control( 'image', [ 'label' => 'تصویر' ] + $this->media_ctrl( 'custom/icon-member-group.png' ) );
        $this->add_control( 'eyebrow', [ 'label' => 'متن بالای عنوان', 'type' => CM::TEXT, 'default' => '👥 رشد واقعی شبکه‌های اجتماعی' ] );
        $this->add_control( 'title', [ 'label' => 'عنوان', 'type' => CM::TEXT, 'default' => 'فروش خدمات مجازی' ] );
        $this->add_control( 'title_hl', [ 'label' => 'عنوان (بخش رنگی)', 'type' => CM::TEXT, 'default' => 'واقعی و مطمئن' ] );
        $this->add_control( 'sub', [ 'label' => 'زیرعنوان', 'type' => CM::TEXTAREA, 'default' => 'با بهترین کیفیت و قیمت، کانال و پیج خود را رشد دهید.' ] );
        $this->add_control( 'cta1', [ 'label' => 'دکمهٔ اول', 'type' => CM::TEXT, 'default' => 'مشاهده پلن‌ها ←' ] );
        $this->add_control( 'cta1_link', [ 'label' => 'لینک', 'type' => CM::URL, 'default' => [ 'url' => '#plans' ] ] );
        $this->add_control( 'cta2', [ 'label' => 'دکمهٔ دوم', 'type' => CM::TEXT, 'default' => 'سفارش در تلگرام' ] );
        $this->add_control( 'cta2_link', [ 'label' => 'لینک', 'type' => CM::URL, 'default' => [ 'url' => '#' ] ] );
        $r = new Repeater();
        $r->add_control( 'val', [ 'label' => 'مقدار', 'type' => CM::TEXT, 'default' => '+2M' ] );
        $r->add_control( 'label', [ 'label' => 'برچسب', 'type' => CM::TEXT, 'default' => 'تحویل‌شده' ] );
        $this->add_control( 'trust', [ 'type' => CM::REPEATER, 'fields' => $r->get_controls(), 'title_field' => '{{{ val }}}', 'default' => [
            [ 'val' => '+2M', 'label' => 'سفارش موفق' ],
            [ 'val' => '0%', 'label' => 'ریزش' ],
            [ 'val' => '5', 'label' => 'پلتفرم' ],
        ] ] );
        $this->end_controls_section();
        $this->ug_style_start();
        $this->ug_ctrl_width( '.page-hero-photo', 180, 400, 'ug_ph_img' );
        $this->ug_style_end();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        echo '<div class="page-hero ' . esc_attr( $s['variant'] ?? 'member' ) . '">';
        echo '<img class="page-hero-photo" src="' . esc_url( $this->url( $s['image'] ?? '' ) ) . '" alt="">';
        echo '<div class="page-hero-content">';
        if ( ! empty( $s['eyebrow'] ) ) { echo '<div class="hero-eyebrow">' . esc_html( $s['eyebrow'] ) . '</div>'; }
        echo '<h1>' . esc_html( $s['title'] ?? '' ) . ' <span>' . esc_html( $s['title_hl'] ?? '' ) . '</span></h1>';
        echo '<p>' . esc_html( $s['sub'] ?? '' ) . '</p>';
        echo '<div class="hero-ctas"><a href="' . esc_url( $s['cta1_link']['url'] ?? '#' ) . '" class="btn-hero">' . esc_html( $s['cta1'] ?? '' ) . '</a><a href="' . esc_url( $s['cta2_link']['url'] ?? '#' ) . '" class="btn-hero-outline">' . esc_html( $s['cta2'] ?? '' ) . '</a></div>';
        echo '<div class="hero-trust">';
        foreach ( (array) ( $s['trust'] ?? [] ) as $t ) {
            echo '<div><span class="n num">' . esc_html( $t['val'] ?? '' ) . '</span><span class="l">' . esc_html( $t['label'] ?? '' ) . '</span></div>';
        }
        echo '</div></div></div>';
    }
}

class UG_W_Section_Heading extends UG_Widget_Base {
    protected string $ug_title = 'عنوان بخش';
    protected string $ug_icon = 'eicon-heading';
    public function get_name(): string { return 'ug-section-heading'; }
    protected function register_controls(): void {
        $this->start_controls_section( 's', [ 'label' => 'محتوا' ] );
        $this->add_control( 'title', [ 'label' => 'عنوان', 'type' => CM::TEXT, 'default' => 'عنوان بخش' ] );
        $this->end_controls_section();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        echo $this->heading_html( $s['title'] ?? '' );
    }
}

class UG_W_Service_Cats extends UG_Widget_Base {
    protected string $ug_title = 'کارت‌های دسته اصلی';
    protected string $ug_icon = 'eicon-gallery-grid';
    public function get_name(): string { return 'ug-service-cats'; }

    protected function register_controls(): void {
        $this->start_controls_section( 's', [ 'label' => 'کارت‌ها' ] );
        $r = new Repeater();
        $r->add_control( 'badge', [ 'label' => 'برچسب', 'type' => CM::TEXT, 'default' => 'پرفروش' ] );
        $r->add_control( 'image', [ 'label' => 'آیکن' ] + $this->media_ctrl( 'custom/icon-sim.png' ) );
        $r->add_control( 'icon_bg', [ 'label' => 'رنگ پس‌زمینه آیکن', 'type' => CM::TEXT, 'default' => 'linear-gradient(135deg,#f97316,#c2410c)' ] );
        $r->add_control( 'name', [ 'label' => 'عنوان', 'type' => CM::TEXT, 'default' => 'شماره مجازی' ] );
        $r->add_control( 'desc', [ 'label' => 'توضیح', 'type' => CM::TEXTAREA, 'default' => 'توضیح کوتاه این دسته' ] );
        $r->add_control( 'tags', [ 'label' => 'برچسب‌ها (با , جدا کنید)', 'type' => CM::TEXT, 'default' => 'گزینه ۱, گزینه ۲' ] );
        $r->add_control( 'link', [ 'label' => 'لینک', 'type' => CM::URL, 'default' => [ 'url' => '#' ] ] );
        $r->add_control( 'linktext', [ 'label' => 'متن لینک', 'type' => CM::TEXT, 'default' => 'مشاهده ←' ] );

        $this->add_control( 'items', [
            'type'        => CM::REPEATER,
            'fields'      => $r->get_controls(),
            'title_field' => '{{{ name }}}',
            'default'     => [
                [ 'badge' => '۳۰+ کشور', 'name' => 'شماره مجازی', 'icon_bg' => 'linear-gradient(135deg,#f97316,#c2410c)', 'desc' => 'شماره مجازی از بیش از ۳۰ کشور برای دریافت کد و ثبت‌نام', 'tags' => '🇺🇸 آمریکا, 🇬🇧 انگلیس, 🇩🇪 آلمان', 'linktext' => 'خرید شماره ←', 'image' => [ 'url' => $this->asset( 'custom/icon-sim.png' ) ], 'link' => [ 'url' => home_url( '/virtual-number/' ) ] ],
                [ 'badge' => 'پرفروش', 'name' => 'اکانت پرمیوم', 'icon_bg' => 'linear-gradient(135deg,#0099dd,#0066aa)', 'desc' => 'اکانت پرمیوم اصل تمام سرویس‌های بین‌المللی با قیمت استثنایی', 'tags' => 'اسپاتیفای, ChatGPT, ۸۰+ سرویس', 'linktext' => 'مشاهده اکانت‌ها ←', 'image' => [ 'url' => $this->asset( 'custom/icon-lock-3d.png' ) ], 'link' => [ 'url' => home_url( '/account/' ) ] ],
                [ 'badge' => 'ارسال فوری', 'name' => 'خدمات مجازی', 'icon_bg' => 'linear-gradient(135deg,#3b5bdb,#5b4fe0)', 'desc' => 'افزایش ممبر و فالوور واقعی برای تمام پلتفرم‌ها بدون ریزش', 'tags' => 'تلگرام, اینستاگرام, یوتیوب', 'linktext' => 'مشاهده ←', 'image' => [ 'url' => $this->asset( 'custom/icon-member-group.png' ) ], 'link' => [ 'url' => home_url( '/member/' ) ] ],
            ],
        ] );
        $this->end_controls_section();

        $this->ug_style_start();
        $this->ug_ctrl_cols( '.service-cats-grid' );
        $this->ug_ctrl_gap( '.service-cats-grid' );
        $this->ug_ctrl_img( '.scat-icon img', 40 );
        $this->ug_ctrl_pad( '.scat' );
        $this->ug_style_end();
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        echo '<div class="ug-sc"><div class="service-cats-grid">';
        foreach ( (array) ( $s['items'] ?? [] ) as $c ) {
            $tags = array_filter( array_map( 'trim', explode( ',', (string) ( $c['tags'] ?? '' ) ) ) );
            echo '<div class="scat">';
            if ( ! empty( $c['badge'] ) ) { echo '<span class="scat-badge">' . esc_html( $c['badge'] ) . '</span>'; }
            echo '<div class="scat-icon" style="background:' . esc_attr( $c['icon_bg'] ?? '' ) . ';"><img src="' . esc_url( $this->url( $c['image'] ?? '' ) ) . '" alt=""></div>';
            echo '<div class="scat-name">' . esc_html( $c['name'] ?? '' ) . '</div>';
            echo '<div class="scat-desc">' . esc_html( $c['desc'] ?? '' ) . '</div>';
            if ( $tags ) {
                echo '<div class="scat-tags">';
                foreach ( $tags as $t ) { echo '<span class="scat-tag">' . esc_html( $t ) . '</span>'; }
                echo '</div>';
            }
            $url = $c['link']['url'] ?? '#';
            echo '<a class="scat-link" href="' . esc_url( $url ) . '">' . esc_html( $c['linktext'] ?? '' ) . '</a>';
            echo '</div>';
        }
        echo '</div></div>';
    }
}

class UG_W_Quick_Cats extends UG_Widget_Base {
    protected string $ug_title = 'دسترسی سریع';
    protected string $ug_icon = 'eicon-navigation-horizontal';
    public function get_name(): string { return 'ug-quick-cats'; }
    protected function register_controls(): void {
        $this->start_controls_section( 's', [ 'label' => 'کارت‌ها' ] );
        $r = new Repeater();
        $r->add_control( 'image', [ 'label' => 'آیکن' ] + $this->media_ctrl( 'custom/icon-sim.png' ) );
        $r->add_control( 'name', [ 'label' => 'عنوان', 'type' => CM::TEXT, 'default' => 'عنوان' ] );
        $r->add_control( 'label', [ 'label' => 'زیرعنوان', 'type' => CM::TEXT, 'default' => 'توضیح' ] );
        $r->add_control( 'link', [ 'label' => 'لینک', 'type' => CM::URL, 'default' => [ 'url' => '#' ] ] );
        $this->add_control( 'items', [ 'type' => CM::REPEATER, 'fields' => $r->get_controls(), 'title_field' => '{{{ name }}}', 'default' => [
            [ 'name' => 'شماره مجازی', 'label' => '۳۰+ کشور موجود', 'image' => [ 'url' => $this->asset( 'custom/icon-sim.png' ) ], 'link' => [ 'url' => home_url( '/virtual-number/' ) ] ],
            [ 'name' => 'اکانت پرمیوم', 'label' => 'اسپاتیفای، ChatGPT و ۸۰+', 'image' => [ 'url' => $this->asset( 'custom/icon-premium.png' ) ], 'link' => [ 'url' => home_url( '/account/' ) ] ],
            [ 'name' => 'خدمات مجازی', 'label' => 'افزایش اعضا واقعی', 'image' => [ 'url' => $this->asset( 'custom/icon-member-group.png' ) ], 'link' => [ 'url' => home_url( '/member/' ) ] ],
        ] ] );
        $this->end_controls_section();
        $this->ug_style_start();
        $this->ug_ctrl_cols( '.quick-cats' );
        $this->ug_ctrl_gap( '.quick-cats' );
        $this->ug_ctrl_img( '.qcat-icon img', 40 );
        $this->ug_style_end();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        echo '<div class="ug-sc"><div class="quick-cats">';
        foreach ( (array) ( $s['items'] ?? [] ) as $c ) {
            echo '<a class="qcat" href="' . esc_url( $c['link']['url'] ?? '#' ) . '"><div class="qcat-icon"><img src="' . esc_url( $this->url( $c['image'] ?? '' ) ) . '" alt=""></div><div><div class="qcat-name">' . esc_html( $c['name'] ?? '' ) . '</div><div class="qcat-label">' . esc_html( $c['label'] ?? '' ) . '</div></div></a>';
        }
        echo '</div></div>';
    }
}

class UG_W_Stats extends UG_Widget_Base {
    protected string $ug_title = 'نوار آمار';
    protected string $ug_icon = 'eicon-counter';
    public function get_name(): string { return 'ug-stats'; }
    protected function register_controls(): void {
        $this->start_controls_section( 's', [ 'label' => 'آمار' ] );
        $r = new Repeater();
        $r->add_control( 'val', [ 'label' => 'مقدار', 'type' => CM::TEXT, 'default' => '24/7' ] );
        $r->add_control( 'label', [ 'label' => 'برچسب', 'type' => CM::TEXT, 'default' => 'پشتیبانی' ] );
        $this->add_control( 'items', [ 'type' => CM::REPEATER, 'fields' => $r->get_controls(), 'title_field' => '{{{ val }}}', 'default' => [
            [ 'val' => '24/7', 'label' => 'پشتیبانی آنلاین' ],
            [ 'val' => '۴.۹ ★', 'label' => 'امتیاز میانگین' ],
            [ 'val' => '+12K', 'label' => 'مشتری راضی' ],
        ] ] );
        $this->end_controls_section();
        $this->ug_style_start();
        $this->ug_ctrl_cols( '.stats-row' );
        $this->ug_ctrl_gap( '.stats-row' );
        $this->ug_ctrl_pad( '.stat-card' );
        $this->ug_style_end();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        echo '<div class="ug-sc"><div class="stats-row">';
        foreach ( (array) ( $s['items'] ?? [] ) as $c ) {
            echo '<div class="stat-card"><div class="stat-val num">' . esc_html( $c['val'] ?? '' ) . '</div><div class="stat-label">' . esc_html( $c['label'] ?? '' ) . '</div></div>';
        }
        echo '</div></div>';
    }
}

class UG_W_Featured extends UG_Widget_Base {
    protected string $ug_title = 'پیشنهاد ویژه';
    protected string $ug_icon = 'eicon-featured-image';
    public function get_name(): string { return 'ug-featured'; }
    protected function register_controls(): void {
        $this->start_controls_section( 's', [ 'label' => 'کارت‌ها' ] );
        $r = new Repeater();
        $r->add_control( 'image', [ 'label' => 'آیکن' ] + $this->media_ctrl( 'iconpack/telegram.svg' ) );
        $r->add_control( 'title', [ 'label' => 'عنوان', 'type' => CM::TEXT, 'default' => 'عنوان کارت' ] );
        $r->add_control( 'linktext', [ 'label' => 'متن لینک', 'type' => CM::TEXT, 'default' => 'مشاهده ←' ] );
        $r->add_control( 'link', [ 'label' => 'لینک', 'type' => CM::URL, 'default' => [ 'url' => '#' ] ] );
        $r->add_control( 'color', [ 'label' => 'رنگ هالهٔ آیکن', 'type' => CM::TEXT, 'default' => 'rgba(0,136,204,.22)' ] );
        $this->add_control( 'items', [ 'type' => CM::REPEATER, 'fields' => $r->get_controls(), 'title_field' => '{{{ title }}}', 'default' => [
            [ 'title' => 'ممبر واقعی تلگرام', 'linktext' => 'خرید ممبر تلگرام ←', 'color' => 'rgba(0,136,204,.22)', 'image' => [ 'url' => $this->asset( 'iconpack/telegram.svg' ) ], 'link' => [ 'url' => home_url( '/member/' ) ] ],
            [ 'title' => 'اسپاتیفای پرمیوم', 'linktext' => 'خرید اسپاتیفای ←', 'color' => 'rgba(29,185,84,.20)', 'image' => [ 'url' => $this->asset( 'iconpack/spotify.svg' ) ], 'link' => [ 'url' => home_url( '/account/' ) ] ],
            [ 'title' => 'ChatGPT Plus', 'linktext' => 'خرید اکانت ←', 'color' => 'rgba(16,163,127,.20)', 'image' => [ 'url' => $this->asset( 'ai/chatgpt.svg' ) ], 'link' => [ 'url' => home_url( '/account/' ) ] ],
            [ 'title' => 'یوتیوب پرمیوم', 'linktext' => 'خرید اشتراک ←', 'color' => 'rgba(255,0,0,.18)', 'image' => [ 'url' => $this->asset( 'iconpack/youtube.svg' ) ], 'link' => [ 'url' => home_url( '/account/' ) ] ],
        ] ] );
        $this->end_controls_section();
        $this->ug_style_start();
        $this->ug_ctrl_cols( '.featured-grid' );
        $this->ug_ctrl_gap( '.featured-grid' );
        $this->ug_ctrl_img( '.feat-icon img', 40 );
        $this->ug_ctrl_pad( '.feat-card' );
        $this->ug_style_end();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        echo '<div class="ug-sc"><div class="featured-grid">';
        foreach ( (array) ( $s['items'] ?? [] ) as $c ) {
            echo '<a class="feat-card" href="' . esc_url( $c['link']['url'] ?? '#' ) . '" style="--fc:' . esc_attr( $c['color'] ?? '' ) . ';"><div class="feat-icon"><img src="' . esc_url( $this->url( $c['image'] ?? '' ) ) . '" alt=""></div><div class="feat-text"><span class="feat-card-title">' . esc_html( $c['title'] ?? '' ) . '</span><span class="feat-card-link">' . esc_html( $c['linktext'] ?? '' ) . '</span></div></a>';
        }
        echo '</div></div>';
    }
}

class UG_W_Promo extends UG_Widget_Base {
    protected string $ug_title = 'بنر تبلیغاتی';
    protected string $ug_icon = 'eicon-banner';
    public function get_name(): string { return 'ug-promo'; }
    protected function register_controls(): void {
        $this->start_controls_section( 's', [ 'label' => 'محتوا' ] );
        $this->add_control( 'eyebrow', [ 'label' => 'متن کوچک', 'type' => CM::TEXT, 'default' => '📊 سفارش با ما، رسیدنش با خیاله راحت!' ] );
        $this->add_control( 'title', [ 'label' => 'عنوان', 'type' => CM::TEXT, 'default' => 'با خرید از آپلودگرام راحت و مطمئن خرید کنید' ] );
        $this->add_control( 'desc', [ 'label' => 'توضیح', 'type' => CM::TEXTAREA, 'default' => 'پشتیبانی ۲۴/۷ در تلگرام، تحویل فوری، ضمانت بازگشت وجه' ] );
        $this->add_control( 'image', [ 'label' => 'تصویر' ] + $this->media_ctrl( 'hero/support-chat.png' ) );
        $this->end_controls_section();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        echo '<div class="ug-sc"><div class="promo-banner"><div class="promo-content"><div class="promo-eyebrow">' . esc_html( $s['eyebrow'] ?? '' ) . '</div><div class="promo-title">' . esc_html( $s['title'] ?? '' ) . '</div><div class="promo-desc">' . esc_html( $s['desc'] ?? '' ) . '</div></div><img class="promo-img" src="' . esc_url( $this->url( $s['image'] ?? '' ) ) . '" alt=""></div></div>';
    }
}

class UG_W_Why_Us extends UG_Widget_Base {
    protected string $ug_title = 'چرا ما؟';
    protected string $ug_icon = 'eicon-info-circle-o';
    public function get_name(): string { return 'ug-why-us'; }
    protected function register_controls(): void {
        $this->start_controls_section( 's', [ 'label' => 'کارت‌ها' ] );
        $r = new Repeater();
        $r->add_control( 'emoji', [ 'label' => 'ایموجی', 'type' => CM::TEXT, 'default' => '⚡' ] );
        $r->add_control( 'color', [ 'label' => 'کلاس رنگ (y/b/g/p)', 'type' => CM::TEXT, 'default' => 'y' ] );
        $r->add_control( 'title', [ 'label' => 'عنوان', 'type' => CM::TEXT, 'default' => 'ویژگی' ] );
        $r->add_control( 'desc', [ 'label' => 'توضیح', 'type' => CM::TEXTAREA, 'default' => 'توضیح ویژگی' ] );
        $this->add_control( 'items', [ 'type' => CM::REPEATER, 'fields' => $r->get_controls(), 'title_field' => '{{{ title }}}', 'default' => [
            [ 'emoji' => '⚡', 'color' => 'y', 'title' => 'تحویل فوری', 'desc' => 'سفارش‌ها در کمتر از ۵ دقیقه' ],
            [ 'emoji' => '🛡️', 'color' => 'b', 'title' => 'ضمانت بازگشت وجه', 'desc' => 'تا ۷۲ ساعت وجه کامل بازمی‌گردد' ],
            [ 'emoji' => '💬', 'color' => 'g', 'title' => 'پشتیبانی ۲۴/۷', 'desc' => 'همیشه در تلگرام پاسخگوییم' ],
            [ 'emoji' => '💎', 'color' => 'p', 'title' => 'کمترین قیمت', 'desc' => 'خرید مستقیم از منبع' ],
        ] ] );
        $this->end_controls_section();
        $this->ug_style_start();
        $this->ug_ctrl_cols( '.why-grid' );
        $this->ug_ctrl_gap( '.why-grid' );
        $this->ug_ctrl_pad( '.why-card' );
        $this->ug_style_end();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        echo '<div class="ug-sc"><div class="why-grid">';
        foreach ( (array) ( $s['items'] ?? [] ) as $c ) {
            echo '<div class="why-card"><div class="why-icon ' . esc_attr( $c['color'] ?? 'y' ) . '">' . esc_html( $c['emoji'] ?? '' ) . '</div><div><div class="why-title">' . esc_html( $c['title'] ?? '' ) . '</div><div class="why-desc">' . esc_html( $c['desc'] ?? '' ) . '</div></div></div>';
        }
        echo '</div></div>';
    }
}

class UG_W_Member_Types extends UG_Widget_Base {
    protected string $ug_title = 'پکیج‌های ممبر';
    protected string $ug_icon = 'eicon-price-list';
    public function get_name(): string { return 'ug-member-types'; }
    protected function register_controls(): void {
        $this->start_controls_section( 's', [ 'label' => 'کارت‌ها' ] );
        $r = new Repeater();
        $r->add_control( 'image', [ 'label' => 'آیکن' ] + $this->media_ctrl( 'custom/icon-member-group.png' ) );
        $r->add_control( 'name', [ 'label' => 'عنوان', 'type' => CM::TEXT, 'default' => 'ممبر' ] );
        $r->add_control( 'price', [ 'label' => 'قیمت', 'type' => CM::TEXT, 'default' => '۱۸,۰۰۰' ] );
        $r->add_control( 'link', [ 'label' => 'لینک', 'type' => CM::URL, 'default' => [ 'url' => '#plans' ] ] );
        $this->add_control( 'items', [ 'type' => CM::REPEATER, 'fields' => $r->get_controls(), 'title_field' => '{{{ name }}}', 'default' => [
            [ 'name' => 'ممبر ایرانی واقعی', 'price' => '۱۸,۰۰۰', 'image' => [ 'url' => $this->asset( 'custom/icon-member-group.png' ) ] ],
            [ 'name' => 'ممبر خارجی ترکیبی', 'price' => '۲۲,۵۰۰', 'image' => [ 'url' => $this->asset( 'iconpack/telegram.svg' ) ] ],
            [ 'name' => 'ممبر کانال فعال', 'price' => '۳۲,۰۰۰', 'image' => [ 'url' => $this->asset( 'custom/icon-stars.png' ) ] ],
            [ 'name' => 'بستهٔ VIP', 'price' => '۱۹۵,۰۰۰', 'image' => [ 'url' => $this->asset( 'custom/icon-vip10k.png' ) ] ],
        ] ] );
        $this->end_controls_section();
        $this->ug_style_start();
        $this->ug_ctrl_cols( '.member-types-grid' );
        $this->ug_ctrl_gap( '.member-types-grid' );
        $this->ug_ctrl_img( '.mtc-img img', 54 );
        $this->ug_ctrl_pad( '.member-type-card' );
        $this->ug_style_end();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        echo '<div class="ug-sc"><div class="member-types-grid">';
        foreach ( (array) ( $s['items'] ?? [] ) as $c ) {
            echo '<a class="member-type-card" href="' . esc_url( $c['link']['url'] ?? '#plans' ) . '"><div class="mtc-img"><img loading="lazy" src="' . esc_url( $this->url( $c['image'] ?? '' ) ) . '" alt=""></div><div class="mtc-name">' . esc_html( $c['name'] ?? '' ) . '</div><div class="mtc-price num">' . esc_html( $c['price'] ?? '' ) . ' تومان</div>';
            echo apply_filters( 'ug_purchase_button', '<button class="mtc-btn" type="button">افزودن به سبد</button>', 0, [ 'label' => 'ورود برای خرید', 'class' => 'mtc-btn' ] );
            echo '</a>';
        }
        echo '</div></div>';
    }
}

class UG_W_Steps extends UG_Widget_Base {
    protected string $ug_title = 'مراحل کار';
    protected string $ug_icon = 'eicon-number-field';
    public function get_name(): string { return 'ug-steps'; }
    protected function register_controls(): void {
        $this->start_controls_section( 's', [ 'label' => 'مراحل' ] );
        $r = new Repeater();
        $r->add_control( 'num', [ 'label' => 'شماره', 'type' => CM::TEXT, 'default' => '1' ] );
        $r->add_control( 'emoji', [ 'label' => 'ایموجی', 'type' => CM::TEXT, 'default' => '🌐' ] );
        $r->add_control( 'title', [ 'label' => 'عنوان', 'type' => CM::TEXT, 'default' => 'مرحله' ] );
        $r->add_control( 'desc', [ 'label' => 'توضیح', 'type' => CM::TEXTAREA, 'default' => 'توضیح مرحله' ] );
        $this->add_control( 'items', [ 'type' => CM::REPEATER, 'fields' => $r->get_controls(), 'title_field' => '{{{ title }}}', 'default' => [
            [ 'num' => '1', 'emoji' => '🌐', 'title' => 'انتخاب', 'desc' => 'سرویس مورد نظر را انتخاب کنید' ],
            [ 'num' => '2', 'emoji' => '💳', 'title' => 'پرداخت', 'desc' => 'از کیف پول یا درگاه پرداخت کنید' ],
            [ 'num' => '3', 'emoji' => '📱', 'title' => 'دریافت', 'desc' => 'سفارش در پنل شما فعال می‌شود' ],
            [ 'num' => '4', 'emoji' => '✅', 'title' => 'تکمیل', 'desc' => 'نتیجه را در لحظه دریافت کنید' ],
        ] ] );
        $this->end_controls_section();
        $this->ug_style_start();
        $this->ug_ctrl_cols( '.steps-row' );
        $this->ug_ctrl_gap( '.steps-row' );
        $this->ug_ctrl_pad( '.step-card' );
        $this->ug_style_end();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        echo '<div class="ug-sc"><div class="steps-row">';
        foreach ( (array) ( $s['items'] ?? [] ) as $c ) {
            echo '<div class="step-card"><div class="step-num num">' . esc_html( $c['num'] ?? '' ) . '</div><div class="step-icon">' . esc_html( $c['emoji'] ?? '' ) . '</div><div class="step-title">' . esc_html( $c['title'] ?? '' ) . '</div><div class="step-desc">' . esc_html( $c['desc'] ?? '' ) . '</div></div>';
        }
        echo '</div></div>';
    }
}

class UG_W_Service_Icons extends UG_Widget_Base {
    protected string $ug_title = 'آیکن سرویس‌ها';
    protected string $ug_icon = 'eicon-icon-box';
    public function get_name(): string { return 'ug-service-icons'; }
    protected function register_controls(): void {
        $this->start_controls_section( 's', [ 'label' => 'آیکن‌ها' ] );
        $r = new Repeater();
        $r->add_control( 'image', [ 'label' => 'آیکن' ] + $this->media_ctrl( 'iconpack/telegram.svg' ) );
        $r->add_control( 'name', [ 'label' => 'نام', 'type' => CM::TEXT, 'default' => 'سرویس' ] );
        $defaults = [
            [ 'iconpack/telegram.svg', 'تلگرام' ], [ 'iconpack/instagram.svg', 'اینستاگرام' ], [ 'iconpack/whatsapp.svg', 'واتساپ' ],
            [ 'ai/chatgpt.svg', 'ChatGPT' ], [ 'iconpack/spotify.svg', 'اسپاتیفای' ], [ 'iconpack/youtube.svg', 'یوتیوب' ],
        ];
        $def = [];
        foreach ( $defaults as $d ) { $def[] = [ 'name' => $d[1], 'image' => [ 'url' => $this->asset( $d[0] ) ] ]; }
        $this->add_control( 'items', [ 'type' => CM::REPEATER, 'fields' => $r->get_controls(), 'title_field' => '{{{ name }}}', 'default' => $def ] );
        $this->end_controls_section();
        $this->ug_style_start();
        $this->ug_ctrl_cols( '.accounts-grid' );
        $this->ug_ctrl_gap( '.accounts-grid' );
        $this->ug_ctrl_img( '.acc-icon img', 40 );
        $this->ug_ctrl_pad( '.acc-card' );
        $this->ug_style_end();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        echo '<div class="ug-sc"><div class="accounts-grid">';
        foreach ( (array) ( $s['items'] ?? [] ) as $c ) {
            echo '<div class="acc-card"><div class="acc-icon"><img loading="lazy" src="' . esc_url( $this->url( $c['image'] ?? '' ) ) . '" alt=""></div><div class="acc-name">' . esc_html( $c['name'] ?? '' ) . '</div></div>';
        }
        echo '</div></div>';
    }
}

class UG_W_Price_Table extends UG_Widget_Base {
    protected string $ug_title = 'جدول قیمت';
    protected string $ug_icon = 'eicon-table';
    public function get_name(): string { return 'ug-price-table'; }
    protected function register_controls(): void {
        $this->start_controls_section( 's', [ 'label' => 'ردیف‌ها' ] );
        $r = new Repeater();
        $r->add_control( 'flag', [ 'label' => 'پرچم', 'type' => CM::TEXT, 'default' => '🇷🇺' ] );
        $r->add_control( 'country', [ 'label' => 'کشور', 'type' => CM::TEXT, 'default' => 'روسیه' ] );
        $r->add_control( 'code', [ 'label' => 'پیش‌شماره', 'type' => CM::TEXT, 'default' => '+7' ] );
        $r->add_control( 'service', [ 'label' => 'سرویس', 'type' => CM::TEXT, 'default' => 'تلگرام' ] );
        $r->add_control( 'price', [ 'label' => 'قیمت', 'type' => CM::TEXT, 'default' => '۵,۵۰۰' ] );
        $this->add_control( 'items', [ 'type' => CM::REPEATER, 'fields' => $r->get_controls(), 'title_field' => '{{{ country }}}', 'default' => [
            [ 'flag' => '🇷🇺', 'country' => 'روسیه', 'code' => '+7', 'service' => 'تلگرام', 'price' => '۵,۵۰۰' ],
            [ 'flag' => '🇺🇸', 'country' => 'آمریکا', 'code' => '+1', 'service' => 'ChatGPT', 'price' => '۸,۵۰۰' ],
            [ 'flag' => '🇬🇧', 'country' => 'انگلستان', 'code' => '+44', 'service' => 'اینستاگرام', 'price' => '۱۱,۰۰۰' ],
        ] ] );
        $this->end_controls_section();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        echo '<div class="ug-sc"><div class="price-table-wrap"><table class="price-table"><thead><tr><th>کشور</th><th>پیش‌شماره</th><th>سرویس</th><th>قیمت</th><th>خرید</th></tr></thead><tbody>';
        foreach ( (array) ( $s['items'] ?? [] ) as $c ) {
            echo '<tr><td><span class="flag">' . esc_html( $c['flag'] ?? '' ) . '</span><span class="country-name">' . esc_html( $c['country'] ?? '' ) . '</span></td><td class="num">' . esc_html( $c['code'] ?? '' ) . '</td><td>' . esc_html( $c['service'] ?? '' ) . '</td><td class="price-val num">' . esc_html( $c['price'] ?? '' ) . ' تومان</td><td>' . apply_filters( 'ug_purchase_button', '<button class="buy-btn">خرید</button>', 0, [ 'label' => 'ورود', 'class' => 'buy-btn' ] ) . '</td></tr>';
        }
        echo '</tbody></table></div></div>';
    }
}

class UG_W_Trust_Banner extends UG_Widget_Base {
    protected string $ug_title = 'بنر اعتماد';
    protected string $ug_icon = 'eicon-shield';
    public function get_name(): string { return 'ug-trust-banner'; }
    protected function register_controls(): void {
        $this->start_controls_section( 's', [ 'label' => 'محتوا' ] );
        $this->add_control( 'icon', [ 'label' => 'ایموجی', 'type' => CM::TEXT, 'default' => '🤝' ] );
        $this->add_control( 'title', [ 'label' => 'عنوان', 'type' => CM::TEXT, 'default' => 'اصالت اکانت‌ها، تضمین آپلودگرام' ] );
        $this->add_control( 'text', [ 'label' => 'متن', 'type' => CM::TEXTAREA, 'default' => 'تمام اکانت‌های پرمیوم ما اصل و اختصاصی تحویل داده می‌شوند. پشتیبانی ۲۴/۷ و جایگزینی رایگان در صورت مشکل.' ] );
        $this->end_controls_section();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        echo '<div class="ug-sc"><div class="trust-banner"><div class="trust-banner-icon">' . esc_html( $s['icon'] ?? '' ) . '</div><div><div class="trust-banner-title">' . esc_html( $s['title'] ?? '' ) . '</div><div class="trust-banner-desc">' . esc_html( $s['text'] ?? '' ) . '</div></div></div></div>';
    }
}

class UG_W_Faq extends UG_Widget_Base {
    protected string $ug_title = 'سوالات متداول';
    protected string $ug_icon = 'eicon-help-o';
    public function get_name(): string { return 'ug-faq'; }
    protected function register_controls(): void {
        $this->start_controls_section( 's', [ 'label' => 'سوالات' ] );
        $this->add_control( 'title', [ 'label' => 'عنوان بخش', 'type' => CM::TEXT, 'default' => 'سوالات متداول' ] );
        $r = new Repeater();
        $r->add_control( 'q', [ 'label' => 'سوال', 'type' => CM::TEXT, 'default' => 'سوال شما؟' ] );
        $r->add_control( 'a', [ 'label' => 'پاسخ', 'type' => CM::TEXTAREA, 'default' => 'پاسخ سوال.' ] );
        $this->add_control( 'items', [ 'type' => CM::REPEATER, 'fields' => $r->get_controls(), 'title_field' => '{{{ q }}}', 'default' => [
            [ 'q' => 'آیا خدمات تضمینی هستند؟', 'a' => 'بله، تمام خدمات با ضمانت اصالت و بازگشت وجه ارائه می‌شوند.' ],
            [ 'q' => 'تحویل چقدر طول می‌کشد؟', 'a' => 'اکثر سفارش‌ها آنی تا حداکثر چند دقیقه تحویل می‌شوند.' ],
            [ 'q' => 'چطور سفارش دهم؟', 'a' => 'محصول را انتخاب و از کیف پول یا درگاه پرداخت کنید.' ],
        ] ] );
        $this->end_controls_section();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $chev = '<svg class="chev" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>';
        echo '<div class="ug-sc"><div class="faq-section"><div class="section-title-wrap" style="margin-bottom:16px;"><div class="section-dot"></div><div class="section-heading">' . esc_html( $s['title'] ?? 'سوالات متداول' ) . '</div></div>';
        foreach ( (array) ( $s['items'] ?? [] ) as $c ) {
            echo '<div class="faq-item"><div class="faq-q">' . esc_html( $c['q'] ?? '' ) . ' ' . $chev . '</div><div class="faq-a">' . esc_html( $c['a'] ?? '' ) . '</div></div>';
        }
        echo '</div></div>';
    }
}

/* ═══════════════════════════════════════════════════════════
 * Dynamic widgets (live app / data-driven — render shortcode)
 * ═══════════════════════════════════════════════════════════ */

class UG_W_Services_App extends UG_Dyn_Widget {
    protected string $ug_tag = 'ug_services_app';
    protected string $ug_title = 'خدمات مجازی (انتخاب اپ)';
    protected string $ug_icon = 'eicon-apps';
    public function get_name(): string { return 'ug-services-app'; }
    protected function register_controls(): void {
        $this->start_controls_section( 's', [ 'label' => 'حالت' ] );
        $this->add_control( 'view', [ 'label' => 'نمایش', 'type' => CM::SELECT, 'default' => 'buy', 'options' => [ 'buy' => 'خرید (پنل)', 'showcase' => 'نمایشی (تعرفه)' ] ] );
        $this->end_controls_section();
    }
    protected function ug_atts( array $s ): string { return ' view="' . esc_attr( $s['view'] ?? 'buy' ) . '"'; }
}

class UG_W_Numbers_App extends UG_Dyn_Widget {
    protected string $ug_tag = 'ug_numbers_app';
    protected string $ug_title = 'شماره مجازی (انتخاب اپ)';
    protected string $ug_icon = 'eicon-tel-field';
    public function get_name(): string { return 'ug-numbers-app'; }
}

class UG_W_Member_Plans extends UG_Dyn_Widget {
    protected string $ug_tag = 'ug_member_plans';
    protected string $ug_title = 'تب پلتفرم‌ها + پلن‌ها';
    protected string $ug_icon = 'eicon-price-table';
    public function get_name(): string { return 'ug-member-plans'; }
}

class UG_W_Account_Grid extends UG_Dyn_Widget {
    protected string $ug_tag = 'ug_account_grid';
    protected string $ug_title = 'گرید اکانت‌ها';
    protected string $ug_icon = 'eicon-gallery-grid';
    public function get_name(): string { return 'ug-account-grid'; }
    protected function register_controls(): void {
        $this->start_controls_section( 's', [ 'label' => 'دسته' ] );
        $this->add_control( 'cat', [ 'label' => 'دسته', 'type' => CM::SELECT, 'default' => 'ai', 'options' => [ 'ai' => 'هوش مصنوعی', 'music' => 'موزیک', 'video' => 'ویدیو', 'design' => 'طراحی', 'other' => 'سایر' ] ] );
        $this->end_controls_section();
    }
    protected function ug_atts( array $s ): string { return ' cat="' . esc_attr( $s['cat'] ?? 'ai' ) . '"'; }
}

class UG_W_Auth extends UG_Dyn_Widget {
    protected string $ug_tag = 'ug_auth'; protected string $ug_title = 'فرم ورود / ثبت‌نام'; protected string $ug_icon = 'eicon-lock-user';
    public function get_name(): string { return 'ug-auth'; }
}
class UG_W_Panel extends UG_Dyn_Widget {
    protected string $ug_tag = 'ug_panel'; protected string $ug_title = 'پنل کاربری'; protected string $ug_icon = 'eicon-dashboard';
    public function get_name(): string { return 'ug-panel'; }
}
class UG_W_Dashboard extends UG_Dyn_Widget {
    protected string $ug_tag = 'ug_dashboard'; protected string $ug_title = 'داشبورد کاربر'; protected string $ug_icon = 'eicon-device-desktop';
    public function get_name(): string { return 'ug-dashboard'; }
}
class UG_W_Wallet extends UG_Dyn_Widget {
    protected string $ug_tag = 'ug_wallet'; protected string $ug_title = 'کارت کیف پول'; protected string $ug_icon = 'eicon-price-list';
    public function get_name(): string { return 'ug-wallet'; }
}
class UG_W_Topup extends UG_Dyn_Widget {
    protected string $ug_tag = 'ug_topup_form'; protected string $ug_title = 'فرم شارژ کیف پول'; protected string $ug_icon = 'eicon-cart-medium';
    public function get_name(): string { return 'ug-topup'; }
}
class UG_W_Wallet_Tx extends UG_Dyn_Widget {
    protected string $ug_tag = 'ug_wallet_tx'; protected string $ug_title = 'تراکنش‌های کیف پول'; protected string $ug_icon = 'eicon-table';
    public function get_name(): string { return 'ug-wallet-tx'; }
}
class UG_W_My_Orders extends UG_Dyn_Widget {
    protected string $ug_tag = 'ug_my_orders'; protected string $ug_title = 'سفارش‌های من'; protected string $ug_icon = 'eicon-bullet-list';
    public function get_name(): string { return 'ug-my-orders'; }
}
class UG_W_Profile extends UG_Dyn_Widget {
    protected string $ug_tag = 'ug_profile'; protected string $ug_title = 'ویرایش پروفایل'; protected string $ug_icon = 'eicon-user-circle-o';
    public function get_name(): string { return 'ug-profile'; }
}
