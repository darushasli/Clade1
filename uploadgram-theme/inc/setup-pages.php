<?php
/**
 * Auto-setup:
 *  - Creates core pages with correct page templates
 *  - Sets the static front page
 *  - Builds the primary navigation menu
 *
 * Runs on theme switch AND on any admin request when the setup version
 * marker doesn't match the current one — so theme UPGRADES (which don't
 * fire after_switch_theme) still pick up new pages like /panel/ and /auth/.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'UG_SETUP_VERSION', '1.5.0' );

add_action( 'after_switch_theme', 'ug_activate_setup' );
add_action( 'admin_init',         'ug_maybe_setup' );

/**
 * On every admin load: run setup ONCE per version bump, then remember it.
 * Cheap: bails immediately when the version matches.
 */
function ug_maybe_setup() {
    if ( get_option( 'ug_setup_done_v' ) === UG_SETUP_VERSION ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return; // avoid running for non-admin AJAX etc.
    }
    ug_activate_setup();
    update_option( 'ug_setup_done_v', UG_SETUP_VERSION );
}

function ug_activate_setup() {

    /* ── 1) Core pages ── */
    $pages = [
        'home' => [
            'title'    => 'خانه',
            'template' => '', // front-page.php auto-detected
            'content'  => '',
        ],
        'member' => [
            'title'    => 'خدمات مجازی',
            'template' => 'page-member.php',
            'content'  => '',
        ],
        'account' => [
            'title'    => 'اکانت پرمیوم',
            'template' => 'page-account.php',
            'content'  => '',
        ],
        'virtual-number' => [
            'title'    => 'شماره مجازی',
            'template' => 'page-virtual-number.php',
            'content'  => '',
        ],
        'contact' => [
            'title'    => 'تماس با ما',
            'template' => '',
            'content'  => "<h2>ارتباط با پشتیبانی آپلودگرام</h2>\n<p>تیم پشتیبانی آپلودگرام به‌صورت ۲۴ ساعته و ۷ روز هفته آمادهٔ پاسخگویی به سؤالات شما دربارهٔ خرید ممبر، اکانت پرمیوم و شماره مجازی است. سریع‌ترین راه ارتباط، ثبت تیکت از پنل کاربری یا گفتگوی آنلاین در همین سایت است.</p>\n<ul>\n<li>پشتیبانی تلگرام: <strong>@YourSupport</strong> (این را با آیدی خود جایگزین کنید)</li>\n<li>ایمیل: <strong>support@uploadgram.ir</strong></li>\n<li>ثبت تیکت: از طریق پنل کاربری › بخش تیکت‌ها</li>\n</ul>\n<p>برای پیگیری سفارش، لطفاً شمارهٔ سفارش خود را آماده داشته باشید.</p>",
        ],
        'faq' => [
            'title'    => 'سوالات متداول',
            'template' => '',
            'content'  => "<p>پاسخ پرتکرارترین سؤالات دربارهٔ خرید ممبر تلگرام و اینستاگرام، اکانت‌های پرمیوم و شماره مجازی را در این صفحه گردآوری کرده‌ایم. اگر پاسخ سؤال خود را نیافتید، از طریق تیکت یا چت آنلاین با ما در تماس باشید.</p>\n[ug_faq set=\"home\"]",
        ],
        'how-to-order' => [
            'title'    => 'نحوه سفارش',
            'template' => '',
            'content'  => "<h2>چگونه در آپلودگرام سفارش دهیم؟</h2>\n<p>خرید از آپلودگرام تنها در چند ثانیه و بدون پیچیدگی انجام می‌شود. مراحل زیر را دنبال کنید:</p>\n<ol>\n<li><strong>ثبت‌نام یا ورود:</strong> با شمارهٔ موبایل یا ایمیل وارد شوید تا کیف پول شما فعال شود.</li>\n<li><strong>شارژ کیف پول:</strong> از بخش کیف پول در پنل کاربری، موجودی خود را افزایش دهید (یا برای اکانت‌های پرمیوم از پرداخت آنلاین استفاده کنید).</li>\n<li><strong>انتخاب خدمت:</strong> در بخش «خدمات مجازی» اپلیکیشن و نوع خدمت (فالوور، لایک، ممبر و…) را انتخاب کنید.</li>\n<li><strong>ثبت سفارش:</strong> لینک یا اطلاعات لازم را وارد کنید و سفارش را ثبت کنید. وضعیت سفارش را در پنل دنبال کنید.</li>\n</ol>\n<p>تحویل اکثر سفارش‌ها آنی است و در صورت هر مشکلی، پشتیبانی ۲۴ ساعته در کنار شماست.</p>",
        ],
        'terms' => [
            'title'    => 'قوانین و مقررات',
            'template' => '',
            'content'  => "<h2>قوانین و مقررات استفاده از آپلودگرام</h2>\n<p>استفاده از خدمات آپلودگرام به‌منزلهٔ پذیرش قوانین زیر است. لطفاً پیش از خرید این موارد را مطالعه کنید:</p>\n<h3>۱. کلیات</h3>\n<p>آپلودگرام ارائه‌دهندهٔ خدمات دیجیتال شامل افزایش ممبر و فالوور، اکانت‌های پرمیوم و شماره مجازی است. کاربر متعهد می‌شود از خدمات صرفاً در چارچوب قوانین جمهوری اسلامی ایران استفاده کند.</p>\n<h3>۲. پرداخت و کیف پول</h3>\n<p>پرداخت‌ها از طریق کیف پول یا درگاه آنلاین انجام می‌شود. موجودی کیف پول قابل برداشت نقدی نیست و صرفاً برای خرید خدمات به کار می‌رود.</p>\n<h3>۳. تحویل و ضمانت</h3>\n<p>در صورت عدم تحویل سفارش، مبلغ به کیف پول بازگردانده می‌شود. اکانت‌های پرمیوم در بازهٔ ضمانت، رایگان جایگزین می‌شوند.</p>\n<h3>۴. حریم خصوصی</h3>\n<p>اطلاعات کاربران محرمانه است و در اختیار اشخاص ثالث قرار نمی‌گیرد. جزئیات در صفحهٔ حریم خصوصی آمده است.</p>",
        ],
        'privacy' => [
            'title'    => 'حریم خصوصی',
            'template' => '',
            'content'  => "<h2>سیاست حریم خصوصی آپلودگرام</h2>\n<p>حفظ حریم خصوصی کاربران برای ما اهمیت بالایی دارد. این صفحه توضیح می‌دهد چه اطلاعاتی جمع‌آوری و چگونه از آن‌ها محافظت می‌کنیم.</p>\n<h3>اطلاعاتی که جمع‌آوری می‌کنیم</h3>\n<p>برای ارائهٔ خدمات، اطلاعاتی مانند شمارهٔ موبایل، ایمیل و تاریخچهٔ سفارش‌ها ذخیره می‌شود. رمز عبور به‌صورت رمزنگاری‌شده نگهداری می‌شود.</p>\n<h3>استفاده از اطلاعات</h3>\n<p>اطلاعات صرفاً برای پردازش سفارش، پشتیبانی و بهبود خدمات استفاده می‌شود و هرگز فروخته یا با اشخاص ثالث به اشتراک گذاشته نمی‌شود.</p>\n<h3>امنیت</h3>\n<p>ما از پروتکل‌های امن و رمزنگاری برای محافظت از داده‌های شما استفاده می‌کنیم.</p>",
        ],
        'about' => [
            'title'    => 'درباره ما',
            'template' => '',
            'content'  => "<h2>دربارهٔ آپلودگرام</h2>\n<p>آپلودگرام یکی از معتبرترین فروشگاه‌های خدمات دیجیتال در ایران است که با هدف ارائهٔ خرید آسان، سریع و مطمئن ممبر تلگرام و اینستاگرام، اکانت‌های پرمیوم اصل و شماره مجازی راه‌اندازی شده است.</p>\n<p>ما با اتصال مستقیم به سرویس‌دهندگان معتبر، قیمت‌های رقابتی و تحویل آنی را تضمین می‌کنیم. تیم پشتیبانی ۲۴ ساعتهٔ ما همواره در کنار شماست تا تجربهٔ خریدی بی‌دغدغه داشته باشید.</p>\n<p>ارزش‌های ما: <strong>اصالت، سرعت، قیمت منصفانه و پشتیبانی واقعی</strong>.</p>",
        ],
        'auth' => [
            'title'    => 'ورود و ثبت‌نام',
            'template' => 'page-auth.php',
            'content'  => '',
        ],
        'panel' => [
            'title'    => 'پنل کاربری',
            'template' => 'page-panel.php',
            'content'  => '',
        ],
    ];

    $page_ids = [];

    foreach ( $pages as $slug => $data ) {
        $existing = get_page_by_path( $slug );
        if ( $existing ) {
            $page_ids[ $slug ] = $existing->ID;
            // Keep the title in sync when we rename a core page (e.g. member → خدمات مجازی).
            if ( $existing->post_title !== $data['title'] ) {
                wp_update_post( [ 'ID' => $existing->ID, 'post_title' => $data['title'] ] );
            }
            continue;
        }

        $id = wp_insert_post( [
            'post_title'   => $data['title'],
            'post_name'    => $slug,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => $data['content'],
        ] );

        if ( $id && ! is_wp_error( $id ) ) {
            if ( ! empty( $data['template'] ) ) {
                update_post_meta( $id, '_wp_page_template', $data['template'] );
            }
            $page_ids[ $slug ] = $id;
        }
    }

    /* ── 2) Static front page ── */
    if ( isset( $page_ids['home'] ) ) {
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', (int) $page_ids['home'] );
        // Make sure the home page isn't also acting as the blog posts page.
        if ( (int) get_option( 'page_for_posts' ) === (int) $page_ids['home'] ) {
            update_option( 'page_for_posts', 0 );
        }
    }

    /* ── 3) Primary menu ── */
    $menu_name = 'منوی اصلی';
    $menu      = wp_get_nav_menu_object( $menu_name );

    if ( ! $menu ) {
        $menu_id = wp_create_nav_menu( $menu_name );

        $items = [
            [ 'title' => 'خانه',         'slug' => 'home' ],
            [ 'title' => 'خدمات مجازی',  'slug' => 'member' ],
            [ 'title' => 'اکانت پرمیوم', 'slug' => 'account' ],
            [ 'title' => 'شماره مجازی',  'slug' => 'virtual-number' ],
            [ 'title' => 'استارز',       'slug' => '',  'url' => '#' ],
            [ 'title' => 'تخفیف‌ها',     'slug' => '',  'url' => '#' ],
            [ 'title' => 'تماس با ما',   'slug' => 'contact' ],
        ];

        foreach ( $items as $item ) {
            if ( ! empty( $item['slug'] ) && isset( $page_ids[ $item['slug'] ] ) ) {
                wp_update_nav_menu_item( $menu_id, 0, [
                    'menu-item-title'     => $item['title'],
                    'menu-item-object'    => 'page',
                    'menu-item-object-id' => $page_ids[ $item['slug'] ],
                    'menu-item-type'      => 'post_type',
                    'menu-item-status'    => 'publish',
                ] );
            } else {
                wp_update_nav_menu_item( $menu_id, 0, [
                    'menu-item-title'  => $item['title'],
                    'menu-item-url'    => isset( $item['url'] ) ? $item['url'] : '#',
                    'menu-item-type'   => 'custom',
                    'menu-item-status' => 'publish',
                ] );
            }
        }

        /* Assign to primary location */
        $locations = get_theme_mod( 'nav_menu_locations', [] );
        $locations['primary'] = $menu_id;
        set_theme_mod( 'nav_menu_locations', $locations );
    }

    /* Flush rewrite so CPT slugs work */
    flush_rewrite_rules();
}
