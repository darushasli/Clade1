<?php
/**
 * Display shortcodes — every reusable site block, so pages can be built /
 * edited in Elementor. Markup mirrors the theme templates and reuses the
 * theme's CSS classes + JS (tabs, FAQ) which are loaded site-wide.
 *
 * All shortcodes:
 *   [ug_service_cats]      homepage 3 main category cards
 *   [ug_quick_cats]        quick category cards
 *   [ug_stats]             stat row (24/7 · rating · clients)
 *   [ug_featured]          "featured today" brand cards
 *   [ug_promo]             promo banner
 *   [ug_why_us]            4 why-us cards
 *   [ug_member_types]      member package cards
 *   [ug_member_plans]      platform tabs + plan cards (interactive)
 *   [ug_steps]             how-it-works 4 steps
 *   [ug_service_icons]     supported-service icon grid
 *   [ug_price_table]       virtual-number price table
 *   [ug_account_grid cat="ai"]  premium-account grid by category
 *   [ug_trust_banner icon="🤝" title="..." text="..."]
 *   [ug_faq set="member"]  FAQ accordion (member|account|number|home)
 *   [ug_section_heading title="..."]  section heading bar
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Shortcodes {

    public function __construct() {
        $map = [
            'ug_service_cats'   => 'service_cats',
            'ug_quick_cats'     => 'quick_cats',
            'ug_stats'          => 'stats',
            'ug_featured'       => 'featured',
            'ug_promo'          => 'promo',
            'ug_why_us'         => 'why_us',
            'ug_member_types'   => 'member_types',
            'ug_member_plans'   => 'member_plans',
            'ug_steps'          => 'steps',
            'ug_service_icons'  => 'service_icons',
            'ug_price_table'    => 'price_table',
            'ug_account_grid'   => 'account_grid',
            'ug_trust_banner'   => 'trust_banner',
            'ug_faq'            => 'faq',
            'ug_section_heading'=> 'section_heading',
        ];
        foreach ( $map as $tag => $method ) {
            add_shortcode( $tag, [ $this, 'sc_' . $method ] );
        }
    }

    /* ── helpers ── */

    private function asset( string $path ): string {
        if ( function_exists( 'ug_asset' ) ) {
            return ug_asset( $path );
        }
        return get_template_directory_uri() . '/assets/' . ltrim( $path, '/' );
    }

    private function home( string $path = '' ): string {
        return esc_url( home_url( $path ) );
    }

    private function heading( string $title ): string {
        return '<div class="section-head"><div class="section-title-wrap">'
            . '<div class="section-dot"></div>'
            . '<div class="section-heading">' . esc_html( $title ) . '</div>'
            . '</div></div>';
    }

    private function wrap( string $inner ): string {
        // Ensures blocks look right whether or not the theme .container wraps them.
        return '<div class="ug-sc">' . $inner . '</div>';
    }

    /* ══════════════ Homepage blocks ══════════════ */

    public function sc_section_heading( $atts ): string {
        $a = shortcode_atts( [ 'title' => '' ], $atts );
        return $this->heading( $a['title'] );
    }

    public function sc_service_cats(): string {
        ob_start(); ?>
        <div class="service-cats-grid">
          <div class="scat">
            <span class="scat-badge">۳۰+ کشور</span>
            <div class="scat-icon" style="background:linear-gradient(135deg,#f97316,#c2410c);"><img src="<?php echo esc_url( $this->asset( 'custom/icon-sim.png' ) ); ?>" alt="شماره مجازی"></div>
            <div class="scat-name">شماره مجازی</div>
            <div class="scat-desc">شماره مجازی از بیش از ۳۰ کشور برای دریافت کد OTP و ثبت‌نام در سرویس‌های مختلف</div>
            <div class="scat-tags"><span class="scat-tag">🇺🇸 آمریکا</span><span class="scat-tag">🇬🇧 انگلیس</span><span class="scat-tag">🇩🇪 آلمان</span></div>
            <a class="scat-link" href="<?php echo $this->home( '/virtual-number/' ); ?>">خرید شماره ←</a>
          </div>
          <div class="scat">
            <span class="scat-badge">پرفروش</span>
            <div class="scat-icon" style="background:linear-gradient(135deg,#0099dd,#0066aa);"><img src="<?php echo esc_url( $this->asset( 'custom/icon-lock-3d.png' ) ); ?>" alt="اکانت پرمیوم"></div>
            <div class="scat-name">اکانت پرمیوم</div>
            <div class="scat-desc">اکانت پرمیوم اصلی تمام سرویس‌های بین‌المللی با قیمت استثنایی و ضمانت اصالت</div>
            <div class="scat-tags"><span class="scat-tag">اسپاتیفای</span><span class="scat-tag">ChatGPT</span><span class="scat-tag">۸۰+ سرویس</span></div>
            <a class="scat-link" href="<?php echo $this->home( '/account/' ); ?>">مشاهده اکانت‌ها ←</a>
          </div>
          <div class="scat">
            <span class="scat-badge">ارسال فوری</span>
            <div class="scat-icon" style="background:linear-gradient(135deg,#3b5bdb,#5b4fe0);"><img src="<?php echo esc_url( $this->asset( 'custom/icon-member-group.png' ) ); ?>" alt="خرید ممبر"></div>
            <div class="scat-name">خدمات مجازی</div>
            <div class="scat-desc">افزایش ممبر واقعی و فعال برای کانال و پیج شما در تمام پلتفرم‌ها، بدون ریزش</div>
            <div class="scat-tags"><span class="scat-tag">تلگرام</span><span class="scat-tag">اینستاگرام</span><span class="scat-tag">یوتیوب</span></div>
            <a class="scat-link" href="<?php echo $this->home( '/member/' ); ?>">مشاهده پلن‌ها ←</a>
          </div>
        </div>
        <?php return $this->wrap( ob_get_clean() );
    }

    public function sc_quick_cats(): string {
        ob_start(); ?>
        <div class="quick-cats">
          <a class="qcat" href="<?php echo $this->home( '/virtual-number/' ); ?>">
            <div class="qcat-icon"><img src="<?php echo esc_url( $this->asset( 'custom/icon-sim.png' ) ); ?>" alt=""></div>
            <div><div class="qcat-name">شماره مجازی</div><div class="qcat-label">۳۰+ کشور موجود</div></div>
          </a>
          <a class="qcat" href="<?php echo $this->home( '/account/' ); ?>">
            <div class="qcat-icon"><img src="<?php echo esc_url( $this->asset( 'custom/icon-premium.png' ) ); ?>" alt=""></div>
            <div><div class="qcat-name">اکانت پرمیوم</div><div class="qcat-label">اسپاتیفای، ChatGPT و ۸۰+</div></div>
          </a>
          <a class="qcat" href="<?php echo $this->home( '/member/' ); ?>">
            <div class="qcat-icon"><img src="<?php echo esc_url( $this->asset( 'custom/icon-member-group.png' ) ); ?>" alt=""></div>
            <div><div class="qcat-name">خدمات مجازی</div><div class="qcat-label">افزایش اعضا واقعی</div></div>
          </a>
        </div>
        <?php return $this->wrap( ob_get_clean() );
    }

    public function sc_stats(): string {
        ob_start(); ?>
        <div class="stats-row">
          <div class="stat-card"><div class="stat-val num">24/7</div><div class="stat-label">پشتیبانی آنلاین</div></div>
          <div class="stat-card"><div class="stat-val gold num">۴.۹ ★</div><div class="stat-label">امتیاز میانگین</div></div>
          <div class="stat-card"><div class="stat-val mint num">+12K</div><div class="stat-label">مشتری راضی</div></div>
        </div>
        <?php return $this->wrap( ob_get_clean() );
    }

    public function sc_featured(): string {
        $cards = [
            [ 'tg', 'ممبر واقعی تلگرام', 'خرید ممبر تلگرام ←', 'iconpack/telegram.svg', 'rgba(0,136,204,.22)', '/member/', true ],
            [ 'sp', 'اسپاتیفای پرمیوم', 'خرید اسپاتیفای ←', 'iconpack/spotify.svg', 'rgba(29,185,84,.20)', '/account/', false ],
            [ 'gpt', 'تخفیف ویژه ChatGPT Plus', 'خرید اکانت ←', 'ai/chatgpt.svg', 'rgba(16,163,127,.20)', '/account/', false ],
            [ 'cv', 'اشتراک کانوا پرو', 'خرید کانوا ←', 'new/canva-icon.svg', 'rgba(139,92,246,.20)', '/account/', false ],
            [ 'yt', 'لذت تماشا با یوتیوب پرمیوم', 'خرید اشتراک یوتیوب ←', 'iconpack/youtube.svg', 'rgba(255,0,0,.18)', '/account/', false ],
        ];
        ob_start(); ?>
        <div class="featured-grid">
          <?php foreach ( $cards as $c ) : ?>
            <a class="feat-card<?php echo $c[6] ? ' tall' : ''; ?>" href="<?php echo $this->home( $c[5] ); ?>" style="--fc:<?php echo esc_attr( $c[4] ); ?>;">
              <div class="feat-icon"><img src="<?php echo esc_url( $this->asset( $c[3] ) ); ?>" alt=""></div>
              <div class="feat-text"><span class="feat-card-title"><?php echo esc_html( $c[1] ); ?></span><span class="feat-card-link"><?php echo esc_html( $c[2] ); ?></span></div>
            </a>
          <?php endforeach; ?>
        </div>
        <?php return $this->wrap( ob_get_clean() );
    }

    public function sc_promo( $atts ): string {
        $a = shortcode_atts( [
            'eyebrow' => '📊 سفارش با ما، رسیدنش با خیاله راحت!',
            'title'   => 'با خرید از آپلودگرام راحت و مطمئن خرید کنید',
            'desc'    => 'پشتیبانی 24/7 در تلگرام، تحویل فوری، ضمانت بازگشت وجه',
        ], $atts );
        ob_start(); ?>
        <div class="promo-banner">
          <div class="promo-content">
            <div class="promo-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></div>
            <div class="promo-title"><?php echo esc_html( $a['title'] ); ?></div>
            <div class="promo-desc"><?php echo esc_html( $a['desc'] ); ?></div>
          </div>
          <img class="promo-img" src="<?php echo esc_url( $this->asset( 'hero/support-chat.png' ) ); ?>" alt="">
        </div>
        <?php return $this->wrap( ob_get_clean() );
    }

    public function sc_why_us(): string {
        $items = [
            [ 'y', '⚡', 'تحویل فوری', 'سفارش‌های پرمیوم و شماره در کمتر از ۵ دقیقه' ],
            [ 'b', '🛡️', 'ضمانت بازگشت وجه', 'در صورت عدم رضایت تا 72 ساعت وجه کامل بازگردانده می‌شود' ],
            [ 'g', '💬', 'پشتیبانی 24/7', 'تیم پشتیبانی ما همیشه آماده پاسخگویی در تلگرام است' ],
            [ 'p', '💎', 'کمترین قیمت', 'با خرید مستقیم از منبع، قیمت‌های رقابتی دریافت می‌کنید' ],
        ];
        ob_start(); ?>
        <div class="why-grid">
          <?php foreach ( $items as $w ) : ?>
            <div class="why-card"><div class="why-icon <?php echo esc_attr( $w[0] ); ?>"><?php echo $w[1]; ?></div>
            <div><div class="why-title"><?php echo esc_html( $w[2] ); ?></div><div class="why-desc"><?php echo esc_html( $w[3] ); ?></div></div></div>
          <?php endforeach; ?>
        </div>
        <?php return $this->wrap( ob_get_clean() );
    }

    /* ══════════════ Member blocks ══════════════ */

    public function sc_member_types(): string {
        $types = [
            [ 'custom/icon-member-group.png', 'ممبر ایرانی<br>واقعی', '۱۸,۰۰۰' ],
            [ 'iconpack/telegram.svg', 'ممبر خارجی<br>ترکیبی', '۲۲,۵۰۰' ],
            [ 'custom/icon-stars.png', 'ممبر کانال<br>فعال', '۳۲,۰۰۰' ],
            [ 'custom/icon-member-group.png', 'ممبر گروه<br>تلگرام', '۲۰,۰۰۰' ],
            [ 'custom/icon-vip10k.png', 'بسته VIP<br><span class="num">10K</span> ممبر', '۱۹۵,۰۰۰' ],
            [ 'custom/icon-premium.png', 'ممبر فوری<br><span class="num">24</span> ساعته', '۲۸,۰۰۰' ],
        ];
        ob_start(); ?>
        <div class="member-types-grid">
          <?php foreach ( $types as $t ) : ?>
            <a class="member-type-card" href="#plans">
              <div class="mtc-img"><img loading="lazy" src="<?php echo esc_url( $this->asset( $t[0] ) ); ?>" alt=""></div>
              <div class="mtc-name"><?php echo wp_kses_post( $t[1] ); ?></div>
              <div class="mtc-price num"><?php echo esc_html( $t[2] ); ?> تومان</div>
              <?php echo apply_filters( 'ug_purchase_button', '<button class="mtc-btn" type="button">افزودن به سبد</button>', 0, [ 'label' => 'ورود برای خرید', 'class' => 'mtc-btn' ] ); ?>
            </a>
          <?php endforeach; ?>
        </div>
        <?php return $this->wrap( ob_get_clean() );
    }

    public function sc_member_plans(): string {
        $platforms = $this->member_platform_data();
        ob_start(); ?>
        <div class="tab-row" id="plans" data-tab-group="platform">
          <?php $first = true; foreach ( $platforms as $key => $p ) : ?>
            <button class="tab-btn<?php echo $first ? ' active' : ''; ?>" data-tab-target="<?php echo esc_attr( $key ); ?>"><img loading="lazy" src="<?php echo esc_url( $this->asset( $p['icon'] ) ); ?>" alt=""><?php echo esc_html( $p['tab'] ); ?></button>
          <?php $first = false; endforeach; ?>
        </div>
        <?php $first = true; foreach ( $platforms as $key => $p ) : ?>
          <div class="tab-panel<?php echo $first ? ' show' : ''; ?>" data-tab-panel-group="platform" data-tab-panel="<?php echo esc_attr( $key ); ?>">
            <div class="platform-strip <?php echo esc_attr( $p['cls'] ); ?>">
              <div class="platform-strip-icon"><img loading="lazy" src="<?php echo esc_url( $this->asset( $p['icon'] ) ); ?>" alt=""></div>
              <div>
                <h2><?php echo esc_html( $p['title'] ); ?></h2>
                <p><?php echo esc_html( $p['desc'] ); ?></p>
                <div class="platform-strip-tags"><?php foreach ( $p['tags'] as $tag ) : ?><span class="ps-tag"><?php echo esc_html( $tag ); ?></span><?php endforeach; ?></div>
              </div>
            </div>
            <div class="plans-grid">
              <?php foreach ( $p['plans'] as $plan ) : ?>
                <div class="plan-card<?php echo $plan['pop'] ? ' popular' : ''; ?>">
                  <?php if ( $plan['pop'] ) : ?><span class="popular-badge">⭐ پرفروش</span><?php endif; ?>
                  <div class="plan-amount"><?php echo esc_html( $plan['amount'] ); ?></div>
                  <div class="plan-unit"><?php echo esc_html( $plan['unit'] ); ?></div>
                  <ul class="plan-features"><?php foreach ( $plan['f'] as $feat ) : ?><li><?php echo esc_html( $feat ); ?></li><?php endforeach; ?></ul>
                  <div class="plan-price"><span class="orig num"><?php echo esc_html( $plan['orig'] ); ?></span><span class="num"><?php echo esc_html( $plan['price'] ); ?></span> تومان</div>
                  <?php echo apply_filters( 'ug_purchase_button', '<button class="plan-btn ' . ( $plan['pop'] ? 'plan-btn-primary' : 'plan-btn-default' ) . '">افزودن به سبد</button>', 0, [ 'label' => 'ورود برای خرید', 'class' => 'plan-btn ' . ( $plan['pop'] ? 'plan-btn-primary' : 'plan-btn-default' ) ] ); ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php $first = false; endforeach; ?>
        <?php return $this->wrap( ob_get_clean() );
    }

    /* ══════════════ Virtual-number blocks ══════════════ */

    public function sc_steps(): string {
        $steps = [
            [ '1', '🌐', 'انتخاب کشور', 'کشور مورد نظر و سرویس مقصد را انتخاب کنید' ],
            [ '2', '💳', 'پرداخت', 'مبلغ را از طریق درگاه امن پرداخت کنید' ],
            [ '3', '📱', 'دریافت شماره', 'شماره مجازی فوراً در پنل شما نمایش داده می‌شود' ],
            [ '4', '✅', 'دریافت OTP', 'کد تأیید در لحظه دریافت و نمایش داده می‌شود' ],
        ];
        ob_start(); ?>
        <div class="steps-row">
          <?php foreach ( $steps as $s ) : ?>
            <div class="step-card"><div class="step-num num"><?php echo esc_html( $s[0] ); ?></div>
            <div class="step-icon"><?php echo $s[1]; ?></div>
            <div class="step-title"><?php echo esc_html( $s[2] ); ?></div>
            <div class="step-desc"><?php echo esc_html( $s[3] ); ?></div></div>
          <?php endforeach; ?>
        </div>
        <?php return $this->wrap( ob_get_clean() );
    }

    public function sc_service_icons(): string {
        $services = [
            [ 'iconpack/telegram.svg', 'تلگرام' ], [ 'iconpack/instagram.svg', 'اینستاگرام' ],
            [ 'iconpack/whatsapp.svg', 'واتساپ' ], [ 'iconpack/facebook.svg', 'فیسبوک' ],
            [ 'iconpack/x.svg', 'ایکس (توییتر)' ], [ 'ai/chatgpt.svg', 'ChatGPT' ],
            [ 'iconpack/spotify.svg', 'اسپاتیفای' ], [ 'iconpack/youtube.svg', 'یوتیوب' ],
            [ 'iconpack/discord.svg', 'دیسکورد' ], [ 'iconpack/signal.svg', 'سیگنال' ],
            [ 'new/google-voice.svg', 'گوگل ویس' ], [ 'iconpack/tiktok.svg', 'تیک‌تاک' ],
        ];
        ob_start(); ?>
        <div class="accounts-grid">
          <?php foreach ( $services as $s ) : ?>
            <div class="acc-card"><div class="acc-icon"><img loading="lazy" src="<?php echo esc_url( $this->asset( $s[0] ) ); ?>" alt=""></div><div class="acc-name"><?php echo esc_html( $s[1] ); ?></div></div>
          <?php endforeach; ?>
        </div>
        <?php return $this->wrap( ob_get_clean() );
    }

    public function sc_price_table(): string {
        $rows = [
            [ '🇷🇺', 'روسیه', '+7', 'تلگرام', 'یکبارمصرف', '۵,۵۰۰' ],
            [ '🇺🇸', 'آمریکا', '+1', 'ChatGPT', 'یکبارمصرف', '۸,۵۰۰' ],
            [ '🇬🇧', 'انگلستان', '+44', 'اینستاگرام', 'یکبارمصرف', '۱۱,۰۰۰' ],
            [ '🇩🇪', 'آلمان', '+49', 'واتساپ', 'یکبارمصرف', '۱۰,۵۰۰' ],
            [ '🇺🇦', 'اوکراین', '+380', 'تلگرام', 'یکبارمصرف', '۴,۸۰۰' ],
            [ '🇹🇷', 'ترکیه', '+90', 'توییتر', 'یکبارمصرف', '۹,۸۰۰' ],
            [ '🇫🇷', 'فرانسه', '+33', 'اینستاگرام', 'یکبارمصرف', '۹,۵۰۰' ],
            [ '🇮🇹', 'ایتالیا', '+39', 'تلگرام', 'یکبارمصرف', '۹,۲۰۰' ],
        ];
        ob_start(); ?>
        <div class="price-table-wrap">
          <table class="price-table">
            <thead><tr><th>کشور</th><th>پیش‌شماره</th><th>سرویس</th><th>نوع</th><th>قیمت</th><th>خرید</th></tr></thead>
            <tbody>
              <?php foreach ( $rows as $r ) : ?>
                <tr>
                  <td><span class="flag"><?php echo $r[0]; ?></span><span class="country-name"><?php echo esc_html( $r[1] ); ?></span></td>
                  <td class="num"><?php echo esc_html( $r[2] ); ?></td>
                  <td><?php echo esc_html( $r[3] ); ?></td>
                  <td><?php echo esc_html( $r[4] ); ?></td>
                  <td class="price-val num"><?php echo esc_html( $r[5] ); ?> تومان</td>
                  <td><?php echo apply_filters( 'ug_purchase_button', '<button class="buy-btn">خرید</button>', 0, [ 'label' => 'ورود', 'class' => 'buy-btn' ] ); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php return $this->wrap( ob_get_clean() );
    }

    /* ══════════════ Account blocks ══════════════ */

    public function sc_account_grid( $atts ): string {
        $a = shortcode_atts( [ 'cat' => 'ai' ], $atts );
        $groups = $this->account_groups();
        $cat    = isset( $groups[ $a['cat'] ] ) ? $a['cat'] : 'ai';
        $items  = $groups[ $cat ];
        ob_start(); ?>
        <div class="accounts-grid">
          <?php foreach ( $items as $item ) : ?>
            <div class="acc-card">
              <div class="acc-icon"><img loading="lazy" src="<?php echo esc_url( $this->asset( $item[0] ) ); ?>" alt=""></div>
              <div class="acc-name"><?php echo esc_html( $item[1] ); ?></div>
              <div class="acc-sub"><?php echo esc_html( $item[2] ); ?></div>
              <div class="acc-price num"><?php echo esc_html( $item[3] ); ?> تومان</div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php return $this->wrap( ob_get_clean() );
    }

    public function sc_trust_banner( $atts ): string {
        $a = shortcode_atts( [
            'icon'  => '🤝',
            'title' => 'اصالت اکانت‌ها، تضمین آپلودگرام',
            'text'  => 'تمام اکانت‌های پرمیوم ما به‌صورت اختصاصی و اصلی تحویل داده می‌شوند. پشتیبانی ۲۴/۷ و جایگزینی رایگان در صورت مشکل.',
        ], $atts );
        return $this->wrap(
            '<div class="trust-banner"><div class="trust-banner-icon">' . esc_html( $a['icon'] ) . '</div>'
            . '<div><div class="trust-banner-title">' . esc_html( $a['title'] ) . '</div>'
            . '<div class="trust-banner-desc">' . esc_html( $a['text'] ) . '</div></div></div>'
        );
    }

    /* ══════════════ FAQ ══════════════ */

    public function sc_faq( $atts ): string {
        $a = shortcode_atts( [ 'set' => 'home' ], $atts );
        $sets = $this->faq_sets();
        $items = $sets[ $a['set'] ] ?? $sets['home'];
        $chev = '<svg class="chev" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>';
        ob_start(); ?>
        <div class="faq-section">
          <div class="section-title-wrap" style="margin-bottom:16px;"><div class="section-dot"></div><div class="section-heading">سوالات متداول</div></div>
          <?php foreach ( $items as $it ) : ?>
            <div class="faq-item">
              <div class="faq-q"><?php echo esc_html( $it[0] ); ?> <?php echo $chev; ?></div>
              <div class="faq-a"><?php echo esc_html( $it[1] ); ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php return $this->wrap( ob_get_clean() );
    }

    /* ══════════════ Data ══════════════ */

    private function faq_sets(): array {
        return [
            'home' => [
                [ 'آیا خدمات آپلودگرام تضمینی هستند؟', 'بله، تمام خدمات ما با ضمانت اصالت و بازگشت وجه ارائه می‌شوند.' ],
                [ 'تحویل سفارش چقدر طول می‌کشد؟', 'اکثر سفارش‌ها آنی تا حداکثر ۵ دقیقه تحویل داده می‌شوند.' ],
                [ 'چطور می‌توانم سفارش دهم؟', 'کافیست محصول موردنظر را انتخاب و از طریق کیف پول پرداخت کنید.' ],
            ],
            'member' => [
                [ 'آیا ممبرها واقعی هستند؟', 'بله، تمام ممبرها واقعی و از اکانت‌های فعال هستند.' ],
                [ 'آیا ریزش ممبر دارد؟', 'ضمانت جایگزینی داریم؛ در صورت ریزش بیش از ۱۰٪، جایگزین ارائه می‌شود.' ],
                [ 'چقدر طول می‌کشد تا ممبرها اضافه شوند؟', 'بسته به پلن، از ۳۰ دقیقه تا ۲۴ ساعت پس از پرداخت.' ],
                [ 'آیا نیاز به رمز عبور اکانت من است؟', 'خیر، فقط لینک یا یوزرنیم عمومی کافیست.' ],
            ],
            'account' => [
                [ 'آیا اکانت‌ها اصلی و قانونی هستند؟', 'بله، تمام اکانت‌ها اصلی و از منابع معتبر تهیه می‌شوند.' ],
                [ 'آیا امکان تغییر رمز عبور وجود دارد؟', 'در اکانت‌های اختصاصی بله، امکان تغییر رمز فراهم است.' ],
                [ 'اگر اکانت غیرفعال شد چه می‌شود؟', 'در بازه ضمانت، جایگزین رایگان دریافت می‌کنید.' ],
            ],
            'number' => [
                [ 'شماره مجازی چیست و چطور کار می‌کند؟', 'شماره‌ای آنلاین برای دریافت کد تأیید سرویس‌های مختلف.' ],
                [ 'آیا می‌توانم از یک شماره برای چند سرویس استفاده کنم؟', 'شماره‌های یکبارمصرف فقط یک سرویس؛ برای چند سرویس شماره اجاره‌ای بگیرید.' ],
                [ 'اگر کد OTP نرسید چه کنم؟', 'مبلغ به کیف پول بازمی‌گردد یا شماره جدید رایگان دریافت می‌کنید.' ],
            ],
        ];
    }

    private function account_groups(): array {
        return [
            'ai' => [
                [ 'ai/grok.svg', 'Grok اشتراک ویژه', 'X Premium', '۱۶۰,۰۰۰' ],
                [ 'ai/perplexity.svg', 'Perplexity Pro', 'اشتراک سالانه', '۱۴۰,۰۰۰' ],
                [ 'ai/copilot.svg', 'Copilot Pro', 'یک ماهه', '۱۹۰,۰۰۰' ],
                [ 'ai/midjourney.svg', 'Midjourney', 'پلن استاندارد', '۲۱۰,۰۰۰' ],
                [ 'ai/gemini.svg', 'Gemini Advanced', 'یک ماهه', '۱۵۵,۰۰۰' ],
                [ 'ai/claude.svg', 'Claude Pro', 'اشتراک ماهانه', '۱۸۰,۰۰۰' ],
                [ 'ai/chatgpt.svg', 'ChatGPT Plus', '۱ ماهه', '۱۶۵,۰۰۰' ],
            ],
            'music' => [
                [ 'iconpack/youtube.svg', 'YouTube Music', 'یک ماهه', '۴۷,۰۰۰' ],
                [ 'iconpack/apple.svg', 'Apple Music', 'یک ماهه', '۵۸,۰۰۰' ],
                [ 'iconpack/soundcloud.svg', 'SoundCloud +Go', 'یک ماهه', '۶۵,۰۰۰' ],
                [ 'iconpack/spotify.svg', 'اسپاتیفای پرمیوم', 'یک ماهه', '۴۵,۰۰۰' ],
            ],
            'video' => [
                [ 'new/netflix.svg', 'Netflix پرمیوم', 'یک ماهه', '۹۰,۰۰۰' ],
                [ 'new/kick.svg', 'Kick Subscriber', 'یک ماهه', '۵۲,۰۰۰' ],
                [ 'iconpack/twitch.svg', 'Twitch Turbo', 'یک ماهه', '۷۰,۰۰۰' ],
                [ 'iconpack/youtube.svg', 'یوتیوب پرمیوم', 'یک ماهه', '۳۸,۰۰۰' ],
            ],
            'design' => [
                [ 'new/capcut-icon.svg', 'CapCut Premium', 'یک ماهه', '۴۸,۰۰۰' ],
                [ 'iconpack/zoom.svg', 'Zoom Pro', 'یک ماهه', '۹۸,۰۰۰' ],
                [ 'iconpack/linkedin.svg', 'لینکدین پرمیوم', 'یک ماهه', '۱۲۰,۰۰۰' ],
                [ 'new/canva-icon.svg', 'Canva Pro', 'یک ساله', '۵۵,۰۰۰' ],
            ],
            'other' => [
                [ 'iconpack/x.svg', 'X (Twitter) Premium', 'یک ماهه', '۸۸,۰۰۰' ],
                [ 'iconpack/discord.svg', 'Discord Nitro', 'یک ماهه', '۷۲,۰۰۰' ],
                [ 'iconpack/android.svg', 'Google Play Pass', 'یک ماهه', '۵۵,۰۰۰' ],
                [ 'iconpack/tiktok.svg', 'TikTok کوین', 'بسته پایه', '۳۹,۰۰۰' ],
                [ 'iconpack/facebook.svg', 'Facebook پرمیوم', 'یک ماهه', '۶۲,۰۰۰' ],
                [ 'iconpack/amazon.svg', 'Amazon Prime', 'یک ماهه', '۹۵,۰۰۰' ],
            ],
        ];
    }

    private function member_platform_data(): array {
        return [
            'tg' => [
                'tab' => 'تلگرام', 'cls' => 'ps-tg', 'icon' => 'iconpack/telegram.svg',
                'title' => 'افزایش ممبر تلگرام',
                'desc'  => 'ممبرهای واقعی و فعال ایرانی و خارجی برای کانال و گروه تلگرام شما، با افزایش تدریجی و طبیعی.',
                'tags'  => [ '✅ ممبر واقعی', '📈 افزایش تدریجی', '🛡️ ضمانت جایگزینی', '⚡ شروع در ۱ ساعت' ],
                'plans' => [
                    [ 'amount' => '۵۰۰', 'unit' => 'ممبر تلگرام', 'f' => [ 'ایرانی و خارجی', 'شروع در ۱ ساعت', 'ضمانت ۳۰ روزه' ], 'orig' => '۲۵,۰۰۰', 'price' => '۱۸,۰۰۰', 'pop' => false ],
                    [ 'amount' => '۱,۰۰۰', 'unit' => 'ممبر تلگرام', 'f' => [ 'ایرانی و خارجی', 'شروع در ۳۰ دقیقه', 'ضمانت ۶۰ روزه' ], 'orig' => '۴۵,۰۰۰', 'price' => '۳۲,۰۰۰', 'pop' => true ],
                    [ 'amount' => '۵,۰۰۰', 'unit' => 'ممبر تلگرام', 'f' => [ 'ایرانی و خارجی', 'شروع فوری', 'ضمانت ۹۰ روزه' ], 'orig' => '۱۴۵,۰۰۰', 'price' => '۱۱۰,۰۰۰', 'pop' => false ],
                    [ 'amount' => '۱۰,۰۰۰', 'unit' => 'ممبر تلگرام', 'f' => [ 'ایرانی و خارجی', 'شروع فوری', 'ضمانت ۱۸۰ روزه' ], 'orig' => '۲۶۰,۰۰۰', 'price' => '۱۹۵,۰۰۰', 'pop' => false ],
                ],
            ],
            'ig' => [
                'tab' => 'اینستاگرام', 'cls' => 'ps-ig', 'icon' => 'iconpack/instagram.svg',
                'title' => 'افزایش فالوور اینستاگرام',
                'desc'  => 'فالوور واقعی و فعال برای پیج اینستاگرام؛ کاملاً طبیعی و بدون خطر محدودیت.',
                'tags'  => [ '✅ فالوور واقعی', '🔒 امن برای پیج', '🛡️ ضمانت جایگزینی', '📈 رشد تدریجی' ],
                'plans' => [
                    [ 'amount' => '۱,۰۰۰', 'unit' => 'فالوور اینستاگرام', 'f' => [ 'واقعی و فعال', 'بدون ریزش', 'ضمانت ۳۰ روزه' ], 'orig' => '۳۵,۰۰۰', 'price' => '۲۵,۰۰۰', 'pop' => false ],
                    [ 'amount' => '۵,۰۰۰', 'unit' => 'فالوور اینستاگرام', 'f' => [ 'واقعی و فعال', 'بدون ریزش', 'ضمانت ۶۰ روزه' ], 'orig' => '۱۵۰,۰۰۰', 'price' => '۱۱۰,۰۰۰', 'pop' => true ],
                    [ 'amount' => '۱۰,۰۰۰', 'unit' => 'فالوور اینستاگرام', 'f' => [ 'واقعی و فعال', 'بدون ریزش', 'ضمانت ۹۰ روزه' ], 'orig' => '۲۸۰,۰۰۰', 'price' => '۲۰۰,۰۰۰', 'pop' => false ],
                    [ 'amount' => '۵۰,۰۰۰', 'unit' => 'فالوور اینستاگرام', 'f' => [ 'واقعی و فعال', 'بدون ریزش', 'ضمانت ۱۸۰ روزه' ], 'orig' => '۱,۱۰۰,۰۰۰', 'price' => '۸۵۰,۰۰۰', 'pop' => false ],
                ],
            ],
            'yt' => [
                'tab' => 'یوتیوب', 'cls' => 'ps-yt', 'icon' => 'iconpack/youtube.svg',
                'title' => 'افزایش ساب‌اسکرایبر یوتیوب',
                'desc'  => 'ساب‌اسکرایبر واقعی برای کانال یوتیوب؛ کمک به مانیتایز و درآمدزایی.',
                'tags'  => [ '✅ ساب واقعی', '💰 کمک به مانیتایز', '🛡️ ضمانت جایگزینی', '⚡ شروع در ۲۴ ساعت' ],
                'plans' => [
                    [ 'amount' => '۵۰۰', 'unit' => 'ساب یوتیوب', 'f' => [ 'واقعی و فعال', 'شروع در ۲۴ ساعت', 'ضمانت ۳۰ روزه' ], 'orig' => '۶۰,۰۰۰', 'price' => '۴۵,۰۰۰', 'pop' => false ],
                    [ 'amount' => '۱,۰۰۰', 'unit' => 'ساب یوتیوب', 'f' => [ 'واقعی و فعال', 'شروع در ۱۲ ساعت', 'ضمانت ۶۰ روزه' ], 'orig' => '۱۱۰,۰۰۰', 'price' => '۸۰,۰۰۰', 'pop' => true ],
                    [ 'amount' => '۴,۰۰۰', 'unit' => 'ساب یوتیوب', 'f' => [ 'واقعی و فعال', 'شروع در ۶ ساعت', 'ضمانت ۹۰ روزه' ], 'orig' => '۳۸۰,۰۰۰', 'price' => '۲۸۰,۰۰۰', 'pop' => false ],
                    [ 'amount' => '۱۰,۰۰۰', 'unit' => 'ساب یوتیوب', 'f' => [ 'واقعی و فعال', 'شروع فوری', 'ضمانت ۱۸۰ روزه' ], 'orig' => '۸۵۰,۰۰۰', 'price' => '۶۵۰,۰۰۰', 'pop' => false ],
                ],
            ],
            'sc' => [
                'tab' => 'سان‌کلو', 'cls' => 'ps-sc', 'icon' => 'iconpack/soundcloud.svg',
                'title' => 'افزایش فالوور سان‌کلو',
                'desc'  => 'فالوور و پلی واقعی برای پروفایل SoundCloud؛ مناسب هنرمندان.',
                'tags'  => [ '✅ فالوور واقعی', '🎵 پلی افزایشی', '🛡️ ضمانت جایگزینی', '⚡ شروع سریع' ],
                'plans' => [
                    [ 'amount' => '۵۰۰', 'unit' => 'فالوور SoundCloud', 'f' => [ 'فالوور واقعی', 'شروع در ۶ ساعت', 'ضمانت ۳۰ روزه' ], 'orig' => '۴۰,۰۰۰', 'price' => '۲۸,۰۰۰', 'pop' => false ],
                    [ 'amount' => '۵,۰۰۰', 'unit' => 'پلی SoundCloud', 'f' => [ 'پلی واقعی', 'شروع در ۲ ساعت', 'ضمانت ۶۰ روزه' ], 'orig' => '۸۵,۰۰۰', 'price' => '۶۰,۰۰۰', 'pop' => true ],
                    [ 'amount' => '۱,۰۰۰', 'unit' => 'لایک SoundCloud', 'f' => [ 'لایک واقعی', 'شروع در ۳ ساعت', 'ضمانت ۳۰ روزه' ], 'orig' => '۵۵,۰۰۰', 'price' => '۳۸,۰۰۰', 'pop' => false ],
                    [ 'amount' => '۲,۰۰۰', 'unit' => 'فالوور SoundCloud', 'f' => [ 'فالوور واقعی', 'شروع سریع', 'ضمانت ۹۰ روزه' ], 'orig' => '۱۵۰,۰۰۰', 'price' => '۱۰۸,۰۰۰', 'pop' => false ],
                ],
            ],
            'sp' => [
                'tab' => 'اسپاتیفای', 'cls' => 'ps-sp', 'icon' => 'iconpack/spotify.svg',
                'title' => 'افزایش فالوور اسپاتیفای',
                'desc'  => 'فالوور، پلی‌لیست فالوور و پلی واقعی برای هنرمندان اسپاتیفای.',
                'tags'  => [ '✅ فالوور واقعی', '🎶 پلی واقعی', '🛡️ ضمانت جایگزینی', '⚡ شروع سریع' ],
                'plans' => [
                    [ 'amount' => '۵۰۰', 'unit' => 'فالوور Spotify', 'f' => [ 'فالوور آرتیست', 'شروع در ۶ ساعت', 'ضمانت ۳۰ روزه' ], 'orig' => '۵۰,۰۰۰', 'price' => '۳۵,۰۰۰', 'pop' => false ],
                    [ 'amount' => '۱۰,۰۰۰', 'unit' => 'پلی Spotify', 'f' => [ 'پلی واقعی', 'شروع در ۱ ساعت', 'ضمانت ۶۰ روزه' ], 'orig' => '۱۱۰,۰۰۰', 'price' => '۷۵,۰۰۰', 'pop' => true ],
                    [ 'amount' => '۵۰۰', 'unit' => 'پلی‌لیست فالوور', 'f' => [ 'فالوور پلی‌لیست', 'شروع در ۴ ساعت', 'ضمانت ۳۰ روزه' ], 'orig' => '۶۵,۰۰۰', 'price' => '۴۵,۰۰۰', 'pop' => false ],
                    [ 'amount' => '۲,۰۰۰', 'unit' => 'فالوور Spotify', 'f' => [ 'فالوور آرتیست', 'شروع سریع', 'ضمانت ۹۰ روزه' ], 'orig' => '۱۸۰,۰۰۰', 'price' => '۱۳۰,۰۰۰', 'pop' => false ],
                ],
            ],
        ];
    }
}
