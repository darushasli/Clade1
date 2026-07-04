<?php
/**
 * Template Name: صفحه شماره مجازی
 * مطابق تصویر نمای سایت (virtual number)
 */
get_header();
?>

<div class="container">
  <div class="breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">خانه</a><span>/</span><span class="cur">شماره مجازی</span></div>

  <!-- ══ PAGE HERO ══ -->
  <div class="page-hero virtual">
    <img src="<?php echo esc_url( ug_asset( 'custom/icon-sim.png' ) ); ?>" alt="" class="page-hero-photo" style="max-width:170px;">
    <div class="page-hero-content">
      <div class="hero-eyebrow">🌍 پوشش ۳۰+ کشور جهان</div>
      <h1>خرید شماره مجازی<br><span>برای دریافت کد OTP</span></h1>
      <p>با شماره مجازی ما در هر سرویسی ثبت‌نام کنید؛ تحویل فوری، استفاده آسان و قیمت مناسب.</p>
      <div class="hero-ctas">
        <a href="#price-table" class="btn-hero">انتخاب کشور ←</a>
        <a href="#" class="btn-hero-outline">سفارش در تلگرام</a>
      </div>
      <div class="hero-trust">
        <div><span class="n num">+30</span><span class="l">کشور پشتیبانی</span></div>
        <div><span class="n num">+200</span><span class="l">سرویس قابل استفاده</span></div>
        <div><span class="n num">⚡</span><span class="l">تحویل آنی</span></div>
      </div>
    </div>
  </div>

  <!-- ══ HOW IT WORKS — 4 STEPS ══ -->
  <div class="steps-row">
    <div class="step-card">
      <div class="step-num num">1</div>
      <div class="step-icon">🌐</div>
      <div class="step-title">انتخاب کشور</div>
      <div class="step-desc">کشور مورد نظر و سرویس مقصد را انتخاب کنید</div>
    </div>
    <div class="step-card">
      <div class="step-num num">2</div>
      <div class="step-icon">💳</div>
      <div class="step-title">پرداخت</div>
      <div class="step-desc">مبلغ را از طریق درگاه امن پرداخت کنید</div>
    </div>
    <div class="step-card">
      <div class="step-num num">3</div>
      <div class="step-icon">📱</div>
      <div class="step-title">دریافت شماره</div>
      <div class="step-desc">شماره مجازی فوراً در پنل شما نمایش داده می‌شود</div>
    </div>
    <div class="step-card">
      <div class="step-num num">4</div>
      <div class="step-icon">✅</div>
      <div class="step-title">دریافت OTP</div>
      <div class="step-desc">کد تأیید در لحظه دریافت و نمایش داده می‌شود</div>
    </div>
  </div>

  <!-- ══ SERVICE SEARCH + CHIPS ══ -->
  <div class="section">
    <div class="search-box" style="max-width:100%;margin-bottom:14px;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      <input type="text" id="country-search" placeholder="جستجو کشور...">
    </div>
    <div class="service-chips">
      <span class="service-chip active">همه سرویس‌ها</span>
      <span class="service-chip"><img src="<?php echo esc_url( ug_asset( 'iconpack/telegram.svg' ) ); ?>" alt="">تلگرام</span>
      <span class="service-chip"><img src="<?php echo esc_url( ug_asset( 'iconpack/instagram.svg' ) ); ?>" alt="">اینستاگرام</span>
      <span class="service-chip"><img src="<?php echo esc_url( ug_asset( 'iconpack/x.svg' ) ); ?>" alt="">توییتر/X</span>
      <span class="service-chip"><img src="<?php echo esc_url( ug_asset( 'iconpack/facebook.svg' ) ); ?>" alt="">فیسبوک</span>
      <span class="service-chip"><img src="<?php echo esc_url( ug_asset( 'iconpack/whatsapp.svg' ) ); ?>" alt="">واتساپ</span>
      <span class="service-chip"><img src="<?php echo esc_url( ug_asset( 'ai/chatgpt.svg' ) ); ?>" alt="">ChatGPT</span>
      <span class="service-chip"><img src="<?php echo esc_url( ug_asset( 'iconpack/spotify.svg' ) ); ?>" alt="">اسپاتیفای</span>
    </div>
  </div>

  <!-- ══ SUPPORTED SERVICES ICONS ══ -->
  <div class="section">
    <?php ug_section_head( 'سرویس‌های پشتیبانی‌شده' ); ?>
    <div class="accounts-grid">
      <?php
      $services = [
          [ 'icon' => 'iconpack/telegram.svg',   'name' => 'تلگرام' ],
          [ 'icon' => 'iconpack/instagram.svg',  'name' => 'اینستاگرام' ],
          [ 'icon' => 'iconpack/whatsapp.svg',   'name' => 'واتساپ' ],
          [ 'icon' => 'iconpack/facebook.svg',   'name' => 'فیسبوک' ],
          [ 'icon' => 'iconpack/x.svg',          'name' => 'ایکس (توییتر)' ],
          [ 'icon' => 'ai/chatgpt.svg',          'name' => 'ChatGPT' ],
          [ 'icon' => 'iconpack/spotify.svg',    'name' => 'اسپاتیفای' ],
          [ 'icon' => 'iconpack/youtube.svg',    'name' => 'یوتیوب' ],
          [ 'icon' => 'iconpack/discord.svg',    'name' => 'دیسکورد' ],
          [ 'icon' => 'iconpack/signal.svg',     'name' => 'سیگنال' ],
          [ 'icon' => 'new/google-voice.svg',    'name' => 'گوگل ویس' ],
          [ 'icon' => 'iconpack/tiktok.svg',     'name' => 'تیک‌تاک' ],
      ];
      foreach ( $services as $s ) : ?>
        <div class="acc-card">
          <div class="acc-icon"><img loading="lazy" src="<?php echo esc_url( ug_asset( $s['icon'] ) ); ?>" alt="<?php echo esc_attr( $s['name'] ); ?>"></div>
          <div class="acc-name"><?php echo esc_html( $s['name'] ); ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ══ PRICE TABLE ══ -->
  <div class="section" id="price-table">
    <?php ug_section_head( 'جدول قیمت شماره مجازی' ); ?>
    <div class="price-table-wrap">
      <table class="price-table">
        <thead>
          <tr>
            <th>کشور</th>
            <th>پیش‌شماره</th>
            <th>سرویس</th>
            <th>نوع</th>
            <th>قیمت</th>
            <th>خرید</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $rows = [
              [ '🇷🇺', 'روسیه',   '+7',   'تلگرام',     'یکبارمصرف', '۵,۵۰۰' ],
              [ '🇺🇸', 'آمریکا',  '+1',   'ChatGPT',    'یکبارمصرف', '۸,۵۰۰' ],
              [ '🇬🇧', 'انگلستان','+44',  'اینستاگرام', 'یکبارمصرف', '۱۱,۰۰۰' ],
              [ '🇩🇪', 'آلمان',   '+49',  'واتساپ',     'یکبارمصرف', '۱۰,۵۰۰' ],
              [ '🇺🇦', 'اوکراین', '+380', 'تلگرام',     'یکبارمصرف', '۴,۸۰۰' ],
              [ '🇹🇷', 'ترکیه',   '+90',  'توییتر',     'یکبارمصرف', '۹,۸۰۰' ],
              [ '🇫🇷', 'فرانسه',  '+33',  'اینستاگرام', 'یکبارمصرف', '۹,۵۰۰' ],
              [ '🇮🇹', 'ایتالیا', '+39',  'تلگرام',     'یکبارمصرف', '۹,۲۰۰' ],
          ];
          foreach ( $rows as $r ) : ?>
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
    <p class="text-muted" style="font-size:12.5px;margin-top:10px;">۵۰+ کشور — برای مشاهده همه اسکرول کنید ↓</p>
  </div>

  <!-- ══ TRUST BANNER ══ -->
  <div class="trust-banner">
    <div class="trust-banner-icon">📲</div>
    <div>
      <div class="trust-banner-title">کد OTP نرسید؟ نگران نباشید</div>
      <div class="trust-banner-desc">در صورت عدم دریافت کد، شماره جدید رایگان دریافت می‌کنید یا مبلغ به کیف پول شما برمی‌گردد. پشتیبانی ۲۴/۷ در تلگرام.</div>
    </div>
  </div>

  <?php
  ug_faq_items( [
      [ 'q' => 'شماره مجازی چیست و چطور کار می‌کند؟', 'a' => 'شماره مجازی یک شماره تلفن آنلاین است که برای دریافت کد تأیید سرویس‌های مختلف استفاده می‌شود.' ],
      [ 'q' => 'آیا می‌توانم از یک شماره برای چند سرویس استفاده کنم؟', 'a' => 'شماره‌های یکبارمصرف فقط برای یک سرویس هستند؛ برای چند سرویس شماره اجاره‌ای تهیه کنید.' ],
      [ 'q' => 'اگر کد OTP نرسید چه کنم؟', 'a' => 'در صورت عدم دریافت کد، مبلغ به کیف پول شما بازگردانده می‌شود یا شماره جدید رایگان دریافت می‌کنید.' ],
      [ 'q' => 'چقدر زمان می‌برد تا شماره را دریافت کنم؟', 'a' => 'شماره بلافاصله پس از پرداخت در پنل کاربری شما نمایش داده می‌شود.' ],
  ] );
  ?>

</div>

<?php get_footer(); ?>
