<?php
/**
 * Front Page — Homepage
 * مطابق چیدمان تصویر نمای سایت
 */
get_header();
?>

<div class="container">

  <!-- ══ HERO ══ -->
  <section class="hero">
    <img class="hero-img" src="<?php echo esc_url( ug_asset( 'hero/upload-hero-phone.png' ) ); ?>" alt="آپلودگرام">
    <div class="hero-content">
      <div class="hero-eyebrow">✦ بهترین خدمات دیجیتال در ایران</div>
      <h1>خرید <span>ممبر، اکانت و شماره مجازی</span><br>با تحویل آنی و امن</h1>
      <p>ممبر واقعی تلگرام و اینستاگرام، اکانت پرمیوم اصل، شماره مجازی معتبر از ۳۰+ کشور — سفارش می‌دهید، در همان لحظه تحویل می‌گیرید.</p>
      <div class="hero-ctas">
        <a href="<?php echo esc_url( home_url( '/member/' ) ); ?>" class="btn-hero">مشاهده همه خدمات ←</a>
        <a href="#" class="btn-hero-outline">شروع در تلگرام</a>
      </div>
      <div class="hero-trust">
        <div><span class="n num">24/7</span><span class="l">پشتیبانی آنلاین</span></div>
        <div><span class="n num text-gold">۴.۹ ★</span><span class="l">امتیاز میانگین</span></div>
        <div><span class="n num text-mint">+12K</span><span class="l">مشتری راضی</span></div>
      </div>
    </div>
  </section>

  <!-- ══ QUICK CATEGORY CARDS ══ -->
  <div class="quick-cats">
    <a class="qcat" href="<?php echo esc_url( home_url( '/virtual-number/' ) ); ?>">
      <div class="qcat-icon"><img src="<?php echo esc_url( ug_asset( 'custom/icon-sim.png' ) ); ?>" alt="شماره مجازی"></div>
      <div>
        <div class="qcat-name">شماره مجازی</div>
        <div class="qcat-label">۳۰+ کشور موجود</div>
      </div>
    </a>
    <a class="qcat" href="<?php echo esc_url( home_url( '/account/' ) ); ?>">
      <div class="qcat-icon"><img src="<?php echo esc_url( ug_asset( 'custom/icon-premium.png' ) ); ?>" alt="اکانت پرمیوم"></div>
      <div>
        <div class="qcat-name">اکانت پرمیوم</div>
        <div class="qcat-label">اسپاتیفای، ChatGPT و ۸۰+</div>
      </div>
    </a>
    <a class="qcat" href="<?php echo esc_url( home_url( '/member/' ) ); ?>">
      <div class="qcat-icon"><img src="<?php echo esc_url( ug_asset( 'custom/icon-member-group.png' ) ); ?>" alt="خرید ممبر"></div>
      <div>
        <div class="qcat-name">خرید ممبر</div>
        <div class="qcat-label">افزایش اعضا واقعی</div>
      </div>
    </a>
  </div>

  <!-- ══ STATS ══ -->
  <div class="stats-row">
    <div class="stat-card"><div class="stat-val num">24/7</div><div class="stat-label">پشتیبانی آنلاین</div></div>
    <div class="stat-card"><div class="stat-val gold num">۴.۹ ★</div><div class="stat-label">امتیاز میانگین</div></div>
    <div class="stat-card"><div class="stat-val mint num">+12K</div><div class="stat-label">مشتری راضی</div></div>
  </div>

  <!-- ══ SERVICE CATEGORIES ══ -->
  <div class="section">
    <?php ug_section_head( 'دسته‌بندی اصلی خدمات' ); ?>
    <div class="service-cats-grid">

      <div class="scat">
        <span class="scat-badge">۳۰+ کشور</span>
        <div class="scat-icon" style="background:linear-gradient(135deg,#f97316,#c2410c);"><img src="<?php echo esc_url( ug_asset( 'custom/icon-sim.png' ) ); ?>" alt="شماره مجازی"></div>
        <div class="scat-name">شماره مجازی</div>
        <div class="scat-desc">شماره مجازی از بیش از ۳۰ کشور برای دریافت کد OTP و ثبت‌نام در سرویس‌های مختلف</div>
        <div class="scat-tags">
          <span class="scat-tag">🇺🇸 آمریکا</span>
          <span class="scat-tag">🇬🇧 انگلیس</span>
          <span class="scat-tag">🇩🇪 آلمان</span>
          <span class="scat-tag">۲۷۰ کشور</span>
        </div>
        <a class="scat-link" href="<?php echo esc_url( home_url( '/virtual-number/' ) ); ?>">خرید شماره ←</a>
      </div>

      <div class="scat">
        <span class="scat-badge">پرفروش</span>
        <div class="scat-icon" style="background:linear-gradient(135deg,#0099dd,#0066aa);"><img src="<?php echo esc_url( ug_asset( 'custom/icon-lock-3d.png' ) ); ?>" alt="اکانت پرمیوم"></div>
        <div class="scat-name">اکانت پرمیوم</div>
        <div class="scat-desc">اکانت پرمیوم اصلی تمام سرویس‌های بین‌المللی با قیمت استثنایی و ضمانت اصالت</div>
        <div class="scat-tags">
          <span class="scat-tag">اسپاتیفای</span>
          <span class="scat-tag">ChatGPT</span>
          <span class="scat-tag">یوتیوب</span>
          <span class="scat-tag">۸۰+ سرویس</span>
        </div>
        <a class="scat-link" href="<?php echo esc_url( home_url( '/account/' ) ); ?>">مشاهده اکانت‌ها ←</a>
      </div>

      <div class="scat">
        <span class="scat-badge">ارسال فوری</span>
        <div class="scat-icon" style="background:linear-gradient(135deg,#16C79A,#0d7a5a);"><img src="<?php echo esc_url( ug_asset( 'custom/icon-member-group.png' ) ); ?>" alt="خرید ممبر"></div>
        <div class="scat-name">خرید ممبر</div>
        <div class="scat-desc">افزایش ممبر واقعی و فعال برای کانال و پیج شما در تمام پلتفرم‌ها، بدون ریزش</div>
        <div class="scat-tags">
          <span class="scat-tag">تلگرام</span>
          <span class="scat-tag">اینستاگرام</span>
          <span class="scat-tag">یوتیوب</span>
          <span class="scat-tag">اسپاتیفای</span>
        </div>
        <a class="scat-link" href="<?php echo esc_url( home_url( '/member/' ) ); ?>">مشاهده پلن‌ها ←</a>
      </div>

    </div>
  </div>

  <!-- ══ FEATURED TODAY ══ -->
  <div class="section">
    <?php ug_section_head( 'پیشنهاد ویژه امروز' ); ?>
    <div class="featured-grid">
      <a class="feat-card tall" href="<?php echo esc_url( home_url( '/account/' ) ); ?>" style="background:linear-gradient(135deg,#0088cc,#833ab4);">
        <div class="feat-card-overlay">
          <span class="feat-card-title">ممبر واقعی تلگرام</span>
          <span class="feat-card-link">خرید ممبر تلگرام ←</span>
        </div>
      </a>
      <a class="feat-card" href="<?php echo esc_url( home_url( '/account/' ) ); ?>" style="background:linear-gradient(135deg,#1db954,#0d7a38);">
        <div class="feat-card-overlay">
          <span class="feat-card-title">اسپاتیفای پرمیوم</span>
          <span class="feat-card-link">خرید اسپاتیفای ←</span>
        </div>
      </a>
      <a class="feat-card" href="<?php echo esc_url( home_url( '/account/' ) ); ?>" style="background:linear-gradient(135deg,#10a37f,#0d7a5a);">
        <div class="feat-card-overlay">
          <span class="feat-card-title">تخفیف ویژه ChatGPT Plus</span>
          <span class="feat-card-link">خرید اکانت ←</span>
        </div>
      </a>
      <a class="feat-card" href="<?php echo esc_url( home_url( '/account/' ) ); ?>" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);">
        <div class="feat-card-overlay">
          <span class="feat-card-title">اشتراک کانوا پرو</span>
          <span class="feat-card-link">خرید کانوا ←</span>
        </div>
      </a>
      <a class="feat-card" href="<?php echo esc_url( home_url( '/account/' ) ); ?>" style="background:linear-gradient(135deg,#ff0000,#9c0000);">
        <div class="feat-card-overlay">
          <span class="feat-card-title">لذت تماشا با یوتیوب پرمیوم</span>
          <span class="feat-card-link">خرید اشتراک یوتیوب ←</span>
        </div>
      </a>
    </div>
  </div>

  <!-- ══ PROMO BANNER ══ -->
  <div class="promo-banner">
    <div class="promo-content">
      <div class="promo-eyebrow">📊 سفارش با ما، رسیدنش با خیاله راحت!</div>
      <div class="promo-title">با خرید از آپلودگرام راحت و مطمئن خرید کنید</div>
      <div class="promo-desc">پشتیبانی 24/7 در تلگرام، تحویل فوری، ضمانت بازگشت وجه</div>
    </div>
    <img class="promo-img" src="<?php echo esc_url( ug_asset( 'hero/support-chat.png' ) ); ?>" alt="پشتیبانی">
  </div>

  <!-- ══ WHY US ══ -->
  <div class="section">
    <?php ug_section_head( 'چرا آپلودگرام؟' ); ?>
    <div class="why-grid">
      <div class="why-card"><div class="why-icon y">⚡</div><div><div class="why-title">تحویل فوری</div><div class="why-desc">سفارش‌های پرمیوم و شماره در کمتر از <span class="num">5</span> دقیقه</div></div></div>
      <div class="why-card"><div class="why-icon b">🛡️</div><div><div class="why-title">ضمانت بازگشت وجه</div><div class="why-desc">در صورت عدم رضایت تا 72 ساعت وجه کامل بازگردانده می‌شود</div></div></div>
      <div class="why-card"><div class="why-icon g">💬</div><div><div class="why-title">پشتیبانی 24/7</div><div class="why-desc">تیم پشتیبانی ما همیشه آماده پاسخگویی در تلگرام است</div></div></div>
      <div class="why-card"><div class="why-icon p">💎</div><div><div class="why-title">کمترین قیمت</div><div class="why-desc">با خرید مستقیم از منبع، قیمت‌های رقابتی دریافت می‌کنید</div></div></div>
    </div>
  </div>

  <?php
  // Homepage FAQ
  ug_faq_items( [
      [ 'q' => 'آیا خدمات آپلودگرام تضمینی هستند؟', 'a' => 'بله، تمام خدمات ما با ضمانت اصالت و بازگشت وجه ارائه می‌شوند.' ],
      [ 'q' => 'تحویل سفارش چقدر طول می‌کشد؟', 'a' => 'اکثر سفارش‌ها آنی تا حداکثر ۵ دقیقه تحویل داده می‌شوند.' ],
      [ 'q' => 'چطور می‌توانم سفارش دهم؟', 'a' => 'کافیست محصول موردنظر را به سبد اضافه کرده و از طریق درگاه امن پرداخت کنید.' ],
  ] );
  ?>

</div>

<?php get_footer(); ?>
