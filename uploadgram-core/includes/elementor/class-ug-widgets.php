<?php
/**
 * UploadGram Elementor widget classes. Loaded only inside
 * elementor/widgets/register, so \Elementor\Widget_Base exists.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Base: renders a UploadGram shortcode. Subclasses set name/title/icon/tag.
 */
abstract class UG_Widget_Base extends \Elementor\Widget_Base {

    /** Shortcode tag this widget renders (without brackets). */
    protected string $ug_tag = '';
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

    /** Build the shortcode attribute string from settings. Override as needed. */
    protected function ug_atts( array $s ): string {
        return '';
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        echo do_shortcode( '[' . $this->ug_tag . $this->ug_atts( (array) $s ) . ']' );
    }
}

/**
 * Central registry + concrete widget classes.
 */
class UG_Elementor_Widgets {

    /**
     * @return string[] widget class names to register.
     */
    public static function classes(): array {
        return [
            // App-first buying (the stars of the panel)
            'UG_W_Services_App', 'UG_W_Numbers_App',
            // Homepage / marketing blocks
            'UG_W_Service_Cats', 'UG_W_Quick_Cats', 'UG_W_Stats', 'UG_W_Featured',
            'UG_W_Promo', 'UG_W_Why_Us', 'UG_W_Section_Heading',
            // Members
            'UG_W_Member_Types', 'UG_W_Member_Plans',
            // Numbers
            'UG_W_Steps', 'UG_W_Service_Icons', 'UG_W_Price_Table',
            // Accounts
            'UG_W_Account_Grid', 'UG_W_Trust_Banner',
            // FAQ
            'UG_W_Faq',
            // Functional (live app)
            'UG_W_Auth', 'UG_W_Panel', 'UG_W_Dashboard', 'UG_W_Wallet',
            'UG_W_Topup', 'UG_W_Wallet_Tx', 'UG_W_My_Orders', 'UG_W_Profile',
        ];
    }
}

/* ══════════════ App-first ══════════════ */

class UG_W_Services_App extends UG_Widget_Base {
    protected string $ug_tag = 'ug_services_app';
    protected string $ug_title = 'خدمات مجازی (انتخاب اپ)';
    protected string $ug_icon = 'eicon-apps';
    public function get_name(): string { return 'ug-services-app'; }
}

class UG_W_Numbers_App extends UG_Widget_Base {
    protected string $ug_tag = 'ug_numbers_app';
    protected string $ug_title = 'شماره مجازی (انتخاب اپ)';
    protected string $ug_icon = 'eicon-tel-field';
    public function get_name(): string { return 'ug-numbers-app'; }
}

/* ══════════════ Marketing blocks ══════════════ */

class UG_W_Service_Cats extends UG_Widget_Base {
    protected string $ug_tag = 'ug_service_cats';
    protected string $ug_title = 'کارت‌های دسته اصلی';
    protected string $ug_icon = 'eicon-gallery-grid';
    public function get_name(): string { return 'ug-service-cats'; }
}

class UG_W_Quick_Cats extends UG_Widget_Base {
    protected string $ug_tag = 'ug_quick_cats';
    protected string $ug_title = 'دسترسی سریع';
    protected string $ug_icon = 'eicon-navigation-horizontal';
    public function get_name(): string { return 'ug-quick-cats'; }
}

class UG_W_Stats extends UG_Widget_Base {
    protected string $ug_tag = 'ug_stats';
    protected string $ug_title = 'نوار آمار';
    protected string $ug_icon = 'eicon-counter';
    public function get_name(): string { return 'ug-stats'; }
}

class UG_W_Featured extends UG_Widget_Base {
    protected string $ug_tag = 'ug_featured';
    protected string $ug_title = 'پیشنهاد ویژه';
    protected string $ug_icon = 'eicon-featured-image';
    public function get_name(): string { return 'ug-featured'; }
}

class UG_W_Why_Us extends UG_Widget_Base {
    protected string $ug_tag = 'ug_why_us';
    protected string $ug_title = 'چرا ما؟';
    protected string $ug_icon = 'eicon-info-circle-o';
    public function get_name(): string { return 'ug-why-us'; }
}

class UG_W_Member_Types extends UG_Widget_Base {
    protected string $ug_tag = 'ug_member_types';
    protected string $ug_title = 'پکیج‌های ممبر';
    protected string $ug_icon = 'eicon-price-list';
    public function get_name(): string { return 'ug-member-types'; }
}

class UG_W_Member_Plans extends UG_Widget_Base {
    protected string $ug_tag = 'ug_member_plans';
    protected string $ug_title = 'تب پلتفرم‌ها + پلن‌ها';
    protected string $ug_icon = 'eicon-price-table';
    public function get_name(): string { return 'ug-member-plans'; }
}

class UG_W_Steps extends UG_Widget_Base {
    protected string $ug_tag = 'ug_steps';
    protected string $ug_title = 'مراحل کار';
    protected string $ug_icon = 'eicon-number-field';
    public function get_name(): string { return 'ug-steps'; }
}

class UG_W_Service_Icons extends UG_Widget_Base {
    protected string $ug_tag = 'ug_service_icons';
    protected string $ug_title = 'آیکن سرویس‌ها';
    protected string $ug_icon = 'eicon-icon-box';
    public function get_name(): string { return 'ug-service-icons'; }
}

class UG_W_Price_Table extends UG_Widget_Base {
    protected string $ug_tag = 'ug_price_table';
    protected string $ug_title = 'جدول قیمت شماره';
    protected string $ug_icon = 'eicon-table';
    public function get_name(): string { return 'ug-price-table'; }
}

/* ══════════════ Parametric widgets (editable controls) ══════════════ */

class UG_W_Section_Heading extends UG_Widget_Base {
    protected string $ug_tag = 'ug_section_heading';
    protected string $ug_title = 'عنوان بخش';
    protected string $ug_icon = 'eicon-heading';
    public function get_name(): string { return 'ug-section-heading'; }

    protected function register_controls(): void {
        $this->start_controls_section( 'sec', [ 'label' => 'محتوا' ] );
        $this->add_control( 'title', [
            'label'   => 'عنوان',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'عنوان بخش',
        ] );
        $this->end_controls_section();
    }

    protected function ug_atts( array $s ): string {
        return ' title="' . esc_attr( $s['title'] ?? '' ) . '"';
    }
}

class UG_W_Promo extends UG_Widget_Base {
    protected string $ug_tag = 'ug_promo';
    protected string $ug_title = 'بنر تبلیغاتی';
    protected string $ug_icon = 'eicon-banner';
    public function get_name(): string { return 'ug-promo'; }

    protected function register_controls(): void {
        $this->start_controls_section( 'sec', [ 'label' => 'محتوا' ] );
        $this->add_control( 'eyebrow', [ 'label' => 'متن کوچک', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '📊 سفارش با ما، رسیدنش با خیاله راحت!' ] );
        $this->add_control( 'title', [ 'label' => 'عنوان', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'با خرید از آپلودگرام راحت و مطمئن خرید کنید' ] );
        $this->add_control( 'desc', [ 'label' => 'توضیح', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'پشتیبانی 24/7 در تلگرام، تحویل فوری، ضمانت بازگشت وجه' ] );
        $this->end_controls_section();
    }

    protected function ug_atts( array $s ): string {
        return ' eyebrow="' . esc_attr( $s['eyebrow'] ?? '' ) . '"'
             . ' title="' . esc_attr( $s['title'] ?? '' ) . '"'
             . ' desc="' . esc_attr( $s['desc'] ?? '' ) . '"';
    }
}

class UG_W_Trust_Banner extends UG_Widget_Base {
    protected string $ug_tag = 'ug_trust_banner';
    protected string $ug_title = 'بنر اعتماد';
    protected string $ug_icon = 'eicon-shield';
    public function get_name(): string { return 'ug-trust-banner'; }

    protected function register_controls(): void {
        $this->start_controls_section( 'sec', [ 'label' => 'محتوا' ] );
        $this->add_control( 'icon', [ 'label' => 'ایموجی', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '🤝' ] );
        $this->add_control( 'title', [ 'label' => 'عنوان', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'اصالت اکانت‌ها، تضمین آپلودگرام' ] );
        $this->add_control( 'text', [ 'label' => 'متن', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'تمام اکانت‌های پرمیوم ما اختصاصی و اصلی تحویل داده می‌شوند. پشتیبانی ۲۴/۷ و جایگزینی رایگان در صورت مشکل.' ] );
        $this->end_controls_section();
    }

    protected function ug_atts( array $s ): string {
        return ' icon="' . esc_attr( $s['icon'] ?? '' ) . '"'
             . ' title="' . esc_attr( $s['title'] ?? '' ) . '"'
             . ' text="' . esc_attr( $s['text'] ?? '' ) . '"';
    }
}

class UG_W_Account_Grid extends UG_Widget_Base {
    protected string $ug_tag = 'ug_account_grid';
    protected string $ug_title = 'گرید اکانت‌ها';
    protected string $ug_icon = 'eicon-gallery-grid';
    public function get_name(): string { return 'ug-account-grid'; }

    protected function register_controls(): void {
        $this->start_controls_section( 'sec', [ 'label' => 'دسته' ] );
        $this->add_control( 'cat', [
            'label'   => 'دسته اکانت',
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => 'ai',
            'options' => [
                'ai'     => 'هوش مصنوعی',
                'music'  => 'موزیک',
                'video'  => 'ویدیو',
                'design' => 'طراحی / بهره‌وری',
                'other'  => 'سایر',
            ],
        ] );
        $this->end_controls_section();
    }

    protected function ug_atts( array $s ): string {
        return ' cat="' . esc_attr( $s['cat'] ?? 'ai' ) . '"';
    }
}

class UG_W_Faq extends UG_Widget_Base {
    protected string $ug_tag = 'ug_faq';
    protected string $ug_title = 'سوالات متداول';
    protected string $ug_icon = 'eicon-help-o';
    public function get_name(): string { return 'ug-faq'; }

    protected function register_controls(): void {
        $this->start_controls_section( 'sec', [ 'label' => 'مجموعه سوالات' ] );
        $this->add_control( 'set', [
            'label'   => 'مجموعه آماده',
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => 'home',
            'options' => [
                'home'    => 'خانه',
                'member'  => 'ممبر',
                'account' => 'اکانت',
                'number'  => 'شماره مجازی',
            ],
        ] );
        $this->end_controls_section();
    }

    protected function ug_atts( array $s ): string {
        return ' set="' . esc_attr( $s['set'] ?? 'home' ) . '"';
    }
}

/* ══════════════ Functional (live app) ══════════════ */

class UG_W_Auth extends UG_Widget_Base {
    protected string $ug_tag = 'ug_auth';
    protected string $ug_title = 'فرم ورود / ثبت‌نام';
    protected string $ug_icon = 'eicon-lock-user';
    public function get_name(): string { return 'ug-auth'; }
}

class UG_W_Panel extends UG_Widget_Base {
    protected string $ug_tag = 'ug_panel';
    protected string $ug_title = 'پنل کاربری';
    protected string $ug_icon = 'eicon-dashboard';
    public function get_name(): string { return 'ug-panel'; }
}

class UG_W_Dashboard extends UG_Widget_Base {
    protected string $ug_tag = 'ug_dashboard';
    protected string $ug_title = 'داشبورد کاربر';
    protected string $ug_icon = 'eicon-device-desktop';
    public function get_name(): string { return 'ug-dashboard'; }
}

class UG_W_Wallet extends UG_Widget_Base {
    protected string $ug_tag = 'ug_wallet';
    protected string $ug_title = 'کارت کیف پول';
    protected string $ug_icon = 'eicon-price-list';
    public function get_name(): string { return 'ug-wallet'; }
}

class UG_W_Topup extends UG_Widget_Base {
    protected string $ug_tag = 'ug_topup_form';
    protected string $ug_title = 'فرم شارژ کیف پول';
    protected string $ug_icon = 'eicon-cart-medium';
    public function get_name(): string { return 'ug-topup'; }
}

class UG_W_Wallet_Tx extends UG_Widget_Base {
    protected string $ug_tag = 'ug_wallet_tx';
    protected string $ug_title = 'تراکنش‌های کیف پول';
    protected string $ug_icon = 'eicon-table';
    public function get_name(): string { return 'ug-wallet-tx'; }
}

class UG_W_My_Orders extends UG_Widget_Base {
    protected string $ug_tag = 'ug_my_orders';
    protected string $ug_title = 'سفارش‌های من';
    protected string $ug_icon = 'eicon-bullet-list';
    public function get_name(): string { return 'ug-my-orders'; }
}

class UG_W_Profile extends UG_Widget_Base {
    protected string $ug_tag = 'ug_profile';
    protected string $ug_title = 'ویرایش پروفایل';
    protected string $ug_icon = 'eicon-user-circle-o';
    public function get_name(): string { return 'ug-profile'; }
}
