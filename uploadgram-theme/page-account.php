<?php
/**
 * Template Name: صفحه اکانت پرمیوم
 * مطابق تصویر نمای سایت (account)
 */
get_header();
?>

<div class="container">
  <div class="breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">خانه</a><span>/</span><span class="cur">اکانت پرمیوم</span></div>

  <!-- ══ PAGE HERO ══ -->
  <div class="page-hero account">
    <img src="<?php echo esc_url( ug_asset( 'ai/chatgpt.svg' ) ); ?>" alt="" class="page-hero-photo" style="max-width:160px;">
    <div class="page-hero-content">
      <div class="hero-eyebrow">🎧 اکانت پرمیوم اصلی</div>
      <h1>خرید اکانت پرمیوم<br><span>با قیمت استثنایی</span></h1>
      <p>بیش از ۸۰ نوع اکانت پرمیوم با تحویل آنی، تضمین اصالت و پشتیبانی کامل، ارزان‌ترین قیمت در ایران.</p>
      <div class="hero-ctas">
        <a href="#accounts" class="btn-hero">مشاهده اکانت‌ها ←</a>
        <a href="#" class="btn-hero-outline">سفارش در تلگرام</a>
      </div>
      <div class="hero-trust">
        <div><span class="n num">+80</span><span class="l">نوع اکانت</span></div>
        <div><span class="n num">100%</span><span class="l">تضمین اصالت</span></div>
        <div><span class="n num">⚡</span><span class="l">تحویل آنی</span></div>
      </div>
    </div>
  </div>

  <!-- ══ FILTER CHIPS ══ -->
  <div class="filter-tabs" id="accounts">
    <button class="filter-tab active" data-filter="all">🔥 همه</button>
    <button class="filter-tab" data-filter="ai">🤖 هوش مصنوعی</button>
    <button class="filter-tab" data-filter="music">🎵 موسیقی</button>
    <button class="filter-tab" data-filter="video">🎬 ویدئو</button>
    <button class="filter-tab" data-filter="design">🎨 طراحی</button>
  </div>

  <!-- ══ FEATURED PLATFORM CARDS ══ -->
  <div class="featured-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:14px;">
    <a class="feat-card" href="#" style="background:linear-gradient(135deg,#ff0000,#9c0000);min-height:140px;">
      <img class="feat-bg-icon" src="<?php echo esc_url( ug_asset( 'iconpack/youtube.svg' ) ); ?>" alt="">
      <div class="feat-card-overlay"><span class="feat-card-title">یوتیوب پرمیوم</span><span class="feat-card-link">اشتراک اصلی ←</span></div>
    </a>
    <a class="feat-card" href="#" style="background:linear-gradient(135deg,#1db954,#0d7a38);min-height:140px;">
      <img class="feat-bg-icon" src="<?php echo esc_url( ug_asset( 'iconpack/spotify.svg' ) ); ?>" alt="">
      <div class="feat-card-overlay"><span class="feat-card-title">اسپاتیفای پرمیوم</span><span class="feat-card-link">اکانت اورجینال ←</span></div>
    </a>
  </div>
  <div class="featured-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
    <a class="feat-card" href="#" style="background:linear-gradient(135deg,#f97316,#c2410c);min-height:112px;">
      <img class="feat-bg-icon" src="<?php echo esc_url( ug_asset( 'ai/midjourney.svg' ) ); ?>" alt="">
      <div class="feat-card-overlay"><span class="feat-card-title">میدجرنی</span></div>
    </a>
    <a class="feat-card" href="#" style="background:linear-gradient(135deg,#0a66c2,#004182);min-height:112px;">
      <img class="feat-bg-icon" src="<?php echo esc_url( ug_asset( 'iconpack/linkedin.svg' ) ); ?>" alt="">
      <div class="feat-card-overlay"><span class="feat-card-title">لینکدین</span></div>
    </a>
    <a class="feat-card" href="#" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);min-height:112px;">
      <img class="feat-bg-icon" src="<?php echo esc_url( ug_asset( 'new/canva-icon.svg' ) ); ?>" alt="">
      <div class="feat-card-overlay"><span class="feat-card-title">کانوا پرو</span></div>
    </a>
    <a class="feat-card" href="#" style="background:linear-gradient(135deg,#10a37f,#0d7a5a);min-height:112px;">
      <img class="feat-bg-icon" src="<?php echo esc_url( ug_asset( 'ai/chatgpt.svg' ) ); ?>" alt="">
      <div class="feat-card-overlay"><span class="feat-card-title">ChatGPT Plus</span></div>
    </a>
  </div>

  <?php
  /**
   * Account groups by category.
   */
  $account_groups = [
      'ai' => [
          'title' => 'اکانت ابزارهای هوش مصنوعی',
          'items' => [
              [ 'icon' => 'ai/grok.svg',       'name' => 'Grok اشتراک ویژه',    'sub' => 'X Premium',  'price' => '۱۶۰,۰۰۰' ],
              [ 'icon' => 'ai/perplexity.svg', 'name' => 'Perplexity Pro',       'sub' => 'اشتراک سالانه','price' => '۱۴۰,۰۰۰' ],
              [ 'icon' => 'ai/copilot.svg',    'name' => 'Copilot Pro مایکروسافت','sub' => 'یک ماهه',    'price' => '۱۹۰,۰۰۰' ],
              [ 'icon' => 'ai/midjourney.svg', 'name' => 'Midjourney',           'sub' => 'پلن استاندارد','price' => '۲۱۰,۰۰۰' ],
              [ 'icon' => 'ai/gemini.svg',     'name' => 'Gemini Advanced',      'sub' => 'یک ماهه',     'price' => '۱۵۵,۰۰۰' ],
              [ 'icon' => 'ai/claude.svg',     'name' => 'Claude Pro',           'sub' => 'اشتراک ماهانه','price' => '۱۸۰,۰۰۰' ],
              [ 'icon' => 'ai/chatgpt.svg',    'name' => 'ChatGPT Plus',         'sub' => '۱ ماهه',      'price' => '۱۶۵,۰۰۰' ],
          ],
      ],
      'music' => [
          'title' => 'موسیقی و پادکست',
          'items' => [
              [ 'icon' => 'iconpack/youtube.svg',    'name' => 'YouTube Music',  'sub' => 'یک ماهه', 'price' => '۴۷,۰۰۰' ],
              [ 'icon' => 'iconpack/apple.svg',      'name' => 'Apple Music',    'sub' => 'یک ماهه', 'price' => '۵۸,۰۰۰' ],
              [ 'icon' => 'iconpack/soundcloud.svg', 'name' => 'SoundCloud +Go', 'sub' => 'یک ماهه', 'price' => '۶۵,۰۰۰' ],
              [ 'icon' => 'iconpack/spotify.svg',    'name' => 'اسپاتیفای پرمیوم','sub' => 'یک ماهه', 'price' => '۴۵,۰۰۰' ],
          ],
      ],
      'video' => [
          'title' => 'ویدئو و سرگرمی',
          'items' => [
              [ 'icon' => 'new/netflix.svg',      'name' => 'Netflix پرمیوم',  'sub' => 'یک ماهه', 'price' => '۹۰,۰۰۰' ],
              [ 'icon' => 'new/kick.svg',         'name' => 'Kick Subscriber', 'sub' => 'یک ماهه', 'price' => '۵۲,۰۰۰' ],
              [ 'icon' => 'iconpack/twitch.svg',  'name' => 'Twitch Turbo',    'sub' => 'یک ماهه', 'price' => '۷۰,۰۰۰' ],
              [ 'icon' => 'iconpack/youtube.svg', 'name' => 'یوتیوب پرمیوم',   'sub' => 'یک ماهه', 'price' => '۳۸,۰۰۰' ],
          ],
      ],
      'design' => [
          'title' => 'طراحی و خلاقیت',
          'items' => [
              [ 'icon' => 'new/capcut-icon.svg', 'name' => 'CapCut Premium', 'sub' => 'یک ماهه', 'price' => '۴۸,۰۰۰' ],
              [ 'icon' => 'iconpack/zoom.svg',   'name' => 'Zoom Pro',       'sub' => 'یک ماهه', 'price' => '۹۸,۰۰۰' ],
              [ 'icon' => 'iconpack/linkedin.svg','name' => 'لینکدین پرمیوم', 'sub' => 'یک ماهه', 'price' => '۱۲۰,۰۰۰' ],
              [ 'icon' => 'new/canva-icon.svg',  'name' => 'Canva Pro',      'sub' => 'یک ساله', 'price' => '۵۵,۰۰۰' ],
          ],
      ],
  ];

  foreach ( $account_groups as $cat => $group ) : ?>
    <div class="section">
      <?php ug_section_head( $group['title'], 'var(--mint)', '#', 'مشاهده همه' ); ?>
      <div class="accounts-grid">
        <?php foreach ( $group['items'] as $item ) : ?>
          <div class="acc-card" data-cat="<?php echo esc_attr( $cat ); ?>">
            <div class="acc-icon"><img loading="lazy" src="<?php echo esc_url( ug_asset( $item['icon'] ) ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>"></div>
            <div class="acc-name"><?php echo esc_html( $item['name'] ); ?></div>
            <div class="acc-sub"><?php echo esc_html( $item['sub'] ); ?></div>
            <div class="acc-price num"><?php echo esc_html( $item['price'] ); ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- ══ OTHER SERVICES ══ -->
  <div class="section">
    <?php ug_section_head( 'سرویس‌های متنوع دیگر' ); ?>
    <div class="accounts-grid">
      <?php
      $others = [
          [ 'icon' => 'iconpack/x.svg',           'name' => 'X (Twitter) Premium', 'sub' => 'یک ماهه', 'price' => '۸۸,۰۰۰' ],
          [ 'icon' => 'iconpack/discord.svg',     'name' => 'Discord Nitro',       'sub' => 'یک ماهه', 'price' => '۷۲,۰۰۰' ],
          [ 'icon' => 'iconpack/android.svg',     'name' => 'Google Play Pass',    'sub' => 'یک ماهه', 'price' => '۵۵,۰۰۰' ],
          [ 'icon' => 'iconpack/tiktok.svg',      'name' => 'TikTok کوین',         'sub' => 'بسته پایه','price' => '۳۹,۰۰۰' ],
          [ 'icon' => 'iconpack/facebook.svg',    'name' => 'Facebook پرمیوم',     'sub' => 'یک ماهه', 'price' => '۶۲,۰۰۰' ],
          [ 'icon' => 'iconpack/amazon.svg',      'name' => 'Amazon Prime',        'sub' => 'یک ماهه', 'price' => '۹۵,۰۰۰' ],
      ];
      foreach ( $others as $item ) : ?>
        <div class="acc-card" data-cat="other">
          <div class="acc-icon"><img loading="lazy" src="<?php echo esc_url( ug_asset( $item['icon'] ) ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>"></div>
          <div class="acc-name"><?php echo esc_html( $item['name'] ); ?></div>
          <div class="acc-sub"><?php echo esc_html( $item['sub'] ); ?></div>
          <div class="acc-price num"><?php echo esc_html( $item['price'] ); ?> تومان</div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ══ TRUST BANNER ══ -->
  <div class="trust-banner">
    <div class="trust-banner-icon">🤝</div>
    <div>
      <div class="trust-banner-title">اصالت اکانت‌ها، تضمین آپلودگرام</div>
      <div class="trust-banner-desc">تمام اکانت‌های پرمیوم ما به‌صورت اختصاصی و اصلی تحویل داده می‌شوند. پشتیبانی ۲۴/۷ و جایگزینی رایگان در صورت مشکل.</div>
    </div>
  </div>

  <?php
  ug_faq_items( [
      [ 'q' => 'آیا اکانت‌ها اصلی و قانونی هستند؟', 'a' => 'بله، تمام اکانت‌ها به‌صورت اصلی و قانونی از منابع معتبر تهیه می‌شوند.' ],
      [ 'q' => 'آیا امکان تغییر رمز عبور وجود دارد؟', 'a' => 'در اکانت‌های اختصاصی بله، امکان تغییر رمز عبور برای شما فراهم است.' ],
      [ 'q' => 'اگر اکانت غیرفعال شد چه می‌شود؟', 'a' => 'در صورت غیرفعال شدن اکانت در بازه ضمانت، جایگزین رایگان دریافت می‌کنید.' ],
  ] );
  ?>

</div>

<?php get_footer(); ?>
