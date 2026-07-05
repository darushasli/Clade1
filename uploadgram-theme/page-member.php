<?php
/**
 * Template Name: صفحه خدمات مجازی
 * مطابق تصویر نمای سایت (member)
 */
get_header();
?>

<div class="container">
  <div class="breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">خانه</a><span>/</span><span class="cur">خدمات مجازی</span></div>

  <!-- ══ PAGE HERO ══ -->
  <div class="page-hero member">
    <img src="<?php echo esc_url( ug_asset( 'custom/icon-member-group.png' ) ); ?>" alt="" class="page-hero-photo" style="max-width:180px;">
    <div class="page-hero-content">
      <div class="hero-eyebrow">👥 رشد واقعی شبکه‌های اجتماعی</div>
      <h1>فروش ممبر <span>واقعی و فعال</span><br>برای تمام پلتفرم‌ها</h1>
      <p>با ممبرهای واقعی و بدون ریزش، کانال تلگرام، پیج اینستاگرام، کانال یوتیوب، پروفایل سان‌کلو و اسپاتیفای خود را رشد دهید.</p>
      <div class="hero-ctas">
        <a href="#plans" class="btn-hero">مشاهده پلن‌ها ←</a>
        <a href="#" class="btn-hero-outline">سفارش در تلگرام</a>
      </div>
      <div class="hero-trust">
        <div><span class="n num">+2M</span><span class="l">ممبر تحویل داده شده</span></div>
        <div><span class="n num">0%</span><span class="l">ریزش ممبر</span></div>
        <div><span class="n num">5</span><span class="l">پلتفرم پشتیبانی‌شده</span></div>
      </div>
    </div>
  </div>

  <!-- ══ SERVICES SHOWCASE (per-platform tariffs — buy happens in the panel) ══ -->
  <div class="section">
    <?php ug_section_head( 'خدمات مجازی ما — تعرفهٔ هر پلتفرم' ); ?>
    <?php echo do_shortcode( '[ug_services_app view="showcase"]' ); ?>
  </div>

  <!-- ══ QUICK PICK — انواع پکیج ممبر ══ -->
  <div class="section">
    <?php ug_section_head( 'انواع پکیج ممبر — برای خرید سریع انتخاب کنید' ); ?>
    <div class="member-types-grid">
      <?php
      $member_types = [
          [ 'icon' => 'custom/icon-member-group.png', 'name' => 'ممبر ایرانی<br>واقعی',   'price' => '۱۸,۰۰۰' ],
          [ 'icon' => 'iconpack/telegram.svg',        'name' => 'ممبر خارجی<br>ترکیبی',   'price' => '۲۲,۵۰۰' ],
          [ 'icon' => 'custom/icon-stars.png',        'name' => 'ممبر کانال<br>فعال',     'price' => '۳۲,۰۰۰' ],
          [ 'icon' => 'custom/icon-member-group.png', 'name' => 'ممبر گروه<br>تلگرام',    'price' => '۲۰,۰۰۰' ],
          [ 'icon' => 'custom/icon-vip10k.png',       'name' => 'بسته VIP<br><span class="num">10K</span> ممبر', 'price' => '۱۹۵,۰۰۰' ],
          [ 'icon' => 'custom/icon-premium.png',      'name' => 'ممبر فوری<br><span class="num">24</span> ساعته', 'price' => '۲۸,۰۰۰' ],
      ];
      foreach ( $member_types as $mt ) : ?>
        <a class="member-type-card" href="#plans" data-tab-jump="tg">
          <div class="mtc-img"><img loading="lazy" src="<?php echo esc_url( ug_asset( $mt['icon'] ) ); ?>" alt=""></div>
          <div class="mtc-name"><?php echo wp_kses_post( $mt['name'] ); ?></div>
          <div class="mtc-price num"><?php echo esc_html( $mt['price'] ); ?> تومان</div>
          <?php echo apply_filters( 'ug_purchase_button', '<button class="mtc-btn" type="button">افزودن به سبد</button>', 0, [ 'label' => 'ورود برای خرید', 'class' => 'mtc-btn' ] ); ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ══ PLATFORM TABS ══ -->
  <div class="tab-row" id="plans" data-tab-group="platform">
    <button class="tab-btn active" data-tab-target="tg"><img loading="lazy" src="<?php echo esc_url( ug_asset( 'iconpack/telegram.svg' ) ); ?>" alt="">تلگرام</button>
    <button class="tab-btn" data-tab-target="ig"><img loading="lazy" src="<?php echo esc_url( ug_asset( 'iconpack/instagram.svg' ) ); ?>" alt="">اینستاگرام</button>
    <button class="tab-btn" data-tab-target="yt"><img loading="lazy" src="<?php echo esc_url( ug_asset( 'iconpack/youtube.svg' ) ); ?>" alt="">یوتیوب</button>
    <button class="tab-btn" data-tab-target="sc"><img loading="lazy" src="<?php echo esc_url( ug_asset( 'iconpack/soundcloud.svg' ) ); ?>" alt="">سان‌کلو</button>
    <button class="tab-btn" data-tab-target="sp"><img loading="lazy" src="<?php echo esc_url( ug_asset( 'iconpack/spotify.svg' ) ); ?>" alt="">اسپاتیفای</button>
  </div>

  <?php
  /**
   * Platform data — strips + plans.
   */
  $platforms = [
      'tg' => [
          'cls'  => 'ps-tg', 'icon' => 'iconpack/telegram.svg', 'show' => true,
          'title' => 'افزایش ممبر تلگرام',
          'desc'  => 'ممبرهای واقعی و فعال ایرانی و خارجی برای کانال و گروه تلگرام شما، با افزایش تدریجی و طبیعی برای جلوگیری از محدودیت.',
          'tags'  => [ '✅ ممبر واقعی', '📈 افزایش تدریجی', '🛡️ ضمانت جایگزینی', '⚡ شروع در ۱ ساعت' ],
          'plans' => [
              [ 'amount' => '۵۰۰',   'unit' => 'ممبر تلگرام', 'f' => [ 'ایرانی و خارجی', 'شروع در ۱ ساعت', 'ضمانت ۳۰ روزه' ],  'orig' => '۲۵,۰۰۰',  'price' => '۱۸,۰۰۰',  'pop' => false ],
              [ 'amount' => '۱,۰۰۰', 'unit' => 'ممبر تلگرام', 'f' => [ 'ایرانی و خارجی', 'شروع در ۳۰ دقیقه', 'ضمانت ۶۰ روزه' ], 'orig' => '۴۵,۰۰۰',  'price' => '۳۲,۰۰۰',  'pop' => true ],
              [ 'amount' => '۵,۰۰۰', 'unit' => 'ممبر تلگرام', 'f' => [ 'ایرانی و خارجی', 'شروع فوری', 'ضمانت ۹۰ روزه' ],       'orig' => '۱۴۵,۰۰۰', 'price' => '۱۱۰,۰۰۰', 'pop' => false ],
              [ 'amount' => '۱۰,۰۰۰','unit' => 'ممبر تلگرام', 'f' => [ 'ایرانی و خارجی', 'شروع فوری', 'ضمانت ۱۸۰ روزه' ],      'orig' => '۲۶۰,۰۰۰', 'price' => '۱۹۵,۰۰۰', 'pop' => false ],
          ],
      ],
      'ig' => [
          'cls'  => 'ps-ig', 'icon' => 'iconpack/instagram.svg', 'show' => false,
          'title' => 'افزایش فالوور اینستاگرام',
          'desc'  => 'فالوور واقعی و فعال ایرانی و خارجی برای پیج اینستاگرام؛ کاملاً طبیعی و بدون خطر محدودیت پیج.',
          'tags'  => [ '✅ فالوور واقعی', '🔒 امن برای پیج', '🛡️ ضمانت جایگزینی', '📈 رشد تدریجی' ],
          'plans' => [
              [ 'amount' => '۱,۰۰۰',  'unit' => 'فالوور اینستاگرام', 'f' => [ 'واقعی و فعال', 'بدون ریزش', 'ضمانت ۳۰ روزه' ],  'orig' => '۳۵,۰۰۰',    'price' => '۲۵,۰۰۰',   'pop' => false ],
              [ 'amount' => '۵,۰۰۰',  'unit' => 'فالوور اینستاگرام', 'f' => [ 'واقعی و فعال', 'بدون ریزش', 'ضمانت ۶۰ روزه' ],  'orig' => '۱۵۰,۰۰۰',   'price' => '۱۱۰,۰۰۰',  'pop' => true ],
              [ 'amount' => '۱۰,۰۰۰', 'unit' => 'فالوور اینستاگرام', 'f' => [ 'واقعی و فعال', 'بدون ریزش', 'ضمانت ۹۰ روزه' ],  'orig' => '۲۸۰,۰۰۰',   'price' => '۲۰۰,۰۰۰',  'pop' => false ],
              [ 'amount' => '۵۰,۰۰۰', 'unit' => 'فالوور اینستاگرام', 'f' => [ 'واقعی و فعال', 'بدون ریزش', 'ضمانت ۱۸۰ روزه' ], 'orig' => '۱,۱۰۰,۰۰۰', 'price' => '۸۵۰,۰۰۰',  'pop' => false ],
          ],
      ],
      'yt' => [
          'cls'  => 'ps-yt', 'icon' => 'iconpack/youtube.svg', 'show' => false,
          'title' => 'افزایش ساب‌اسکرایبر یوتیوب',
          'desc'  => 'ساب‌اسکرایبر واقعی برای کانال یوتیوب؛ کمک به رسیدن به حد یوتیوب پارتنر و درآمدزایی از کانال.',
          'tags'  => [ '✅ ساب واقعی', '💰 کمک به مانیتایز', '🛡️ ضمانت جایگزینی', '⚡ شروع در ۲۴ ساعت' ],
          'plans' => [
              [ 'amount' => '۵۰۰',    'unit' => 'ساب یوتیوب', 'f' => [ 'واقعی و فعال', 'شروع در ۲۴ ساعت', 'ضمانت ۳۰ روزه' ], 'orig' => '۶۰,۰۰۰',  'price' => '۴۵,۰۰۰',  'pop' => false ],
              [ 'amount' => '۱,۰۰۰',  'unit' => 'ساب یوتیوب', 'f' => [ 'واقعی و فعال', 'شروع در ۱۲ ساعت', 'ضمانت ۶۰ روزه' ], 'orig' => '۱۱۰,۰۰۰', 'price' => '۸۰,۰۰۰',  'pop' => true ],
              [ 'amount' => '۴,۰۰۰',  'unit' => 'ساب یوتیوب', 'f' => [ 'واقعی و فعال', 'شروع در ۶ ساعت', 'ضمانت ۹۰ روزه' ],  'orig' => '۳۸۰,۰۰۰', 'price' => '۲۸۰,۰۰۰', 'pop' => false ],
              [ 'amount' => '۱۰,۰۰۰', 'unit' => 'ساب یوتیوب', 'f' => [ 'واقعی و فعال', 'شروع فوری', 'ضمانت ۱۸۰ روزه' ],     'orig' => '۸۵۰,۰۰۰', 'price' => '۶۵۰,۰۰۰', 'pop' => false ],
          ],
      ],
      'sc' => [
          'cls'  => 'ps-sc', 'icon' => 'iconpack/soundcloud.svg', 'show' => false,
          'title' => 'افزایش فالوور سان‌کلو',
          'desc'  => 'فالوور و پلی واقعی برای پروفایل SoundCloud شما؛ مناسب هنرمندان و موزیسین‌هایی که می‌خواهند دیده شوند.',
          'tags'  => [ '✅ فالوور واقعی', '🎵 پلی افزایشی', '🛡️ ضمانت جایگزینی', '⚡ شروع سریع' ],
          'plans' => [
              [ 'amount' => '۵۰۰',   'unit' => 'فالوور SoundCloud', 'f' => [ 'فالوور واقعی', 'شروع در ۶ ساعت', 'ضمانت ۳۰ روزه' ], 'orig' => '۴۰,۰۰۰',  'price' => '۲۸,۰۰۰',  'pop' => false ],
              [ 'amount' => '۵,۰۰۰', 'unit' => 'پلی SoundCloud',    'f' => [ 'پلی واقعی', 'شروع در ۲ ساعت', 'ضمانت ۶۰ روزه' ],    'orig' => '۸۵,۰۰۰',  'price' => '۶۰,۰۰۰',  'pop' => true ],
              [ 'amount' => '۱,۰۰۰', 'unit' => 'لایک SoundCloud',   'f' => [ 'لایک واقعی', 'شروع در ۳ ساعت', 'ضمانت ۳۰ روزه' ],   'orig' => '۵۵,۰۰۰',  'price' => '۳۸,۰۰۰',  'pop' => false ],
              [ 'amount' => '۲,۰۰۰', 'unit' => 'فالوور SoundCloud', 'f' => [ 'فالوور واقعی', 'شروع سریع', 'ضمانت ۹۰ روزه' ],      'orig' => '۱۵۰,۰۰۰', 'price' => '۱۰۸,۰۰۰', 'pop' => false ],
          ],
      ],
      'sp' => [
          'cls'  => 'ps-sp', 'icon' => 'iconpack/spotify.svg', 'show' => false,
          'title' => 'افزایش فالوور اسپاتیفای',
          'desc'  => 'فالوور، پلی‌لیست فالوور و پلی واقعی برای هنرمندان اسپاتیفای؛ رشد واقعی و بدون ریزش.',
          'tags'  => [ '✅ فالوور واقعی', '🎶 پلی واقعی', '🛡️ ضمانت جایگزینی', '⚡ شروع سریع' ],
          'plans' => [
              [ 'amount' => '۵۰۰',    'unit' => 'فالوور Spotify',   'f' => [ 'فالوور آرتیست', 'شروع در ۶ ساعت', 'ضمانت ۳۰ روزه' ], 'orig' => '۵۰,۰۰۰',  'price' => '۳۵,۰۰۰',  'pop' => false ],
              [ 'amount' => '۱۰,۰۰۰', 'unit' => 'پلی Spotify',      'f' => [ 'پلی واقعی', 'شروع در ۱ ساعت', 'ضمانت ۶۰ روزه' ],     'orig' => '۱۱۰,۰۰۰', 'price' => '۷۵,۰۰۰',  'pop' => true ],
              [ 'amount' => '۵۰۰',    'unit' => 'پلی‌لیست فالوور',  'f' => [ 'فالوور پلی‌لیست', 'شروع در ۴ ساعت', 'ضمانت ۳۰ روزه' ],'orig' => '۶۵,۰۰۰',  'price' => '۴۵,۰۰۰',  'pop' => false ],
              [ 'amount' => '۲,۰۰۰',  'unit' => 'فالوور Spotify',   'f' => [ 'فالوور آرتیست', 'شروع سریع', 'ضمانت ۹۰ روزه' ],      'orig' => '۱۸۰,۰۰۰', 'price' => '۱۳۰,۰۰۰', 'pop' => false ],
          ],
      ],
  ];

  foreach ( $platforms as $key => $p ) : ?>
    <div class="tab-panel<?php echo $p['show'] ? ' show' : ''; ?>" data-tab-panel-group="platform" data-tab-panel="<?php echo esc_attr( $key ); ?>">
      <div class="platform-strip <?php echo esc_attr( $p['cls'] ); ?>">
        <div class="platform-strip-icon"><img loading="lazy" src="<?php echo esc_url( ug_asset( $p['icon'] ) ); ?>" alt=""></div>
        <div>
          <h2><?php echo esc_html( $p['title'] ); ?></h2>
          <p><?php echo esc_html( $p['desc'] ); ?></p>
          <div class="platform-strip-tags">
            <?php foreach ( $p['tags'] as $tag ) : ?>
              <span class="ps-tag"><?php echo esc_html( $tag ); ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="plans-grid">
        <?php foreach ( $p['plans'] as $plan ) : ?>
          <div class="plan-card<?php echo $plan['pop'] ? ' popular' : ''; ?>">
            <?php if ( $plan['pop'] ) : ?><span class="popular-badge">⭐ پرفروش</span><?php endif; ?>
            <div class="plan-amount"><?php echo esc_html( $plan['amount'] ); ?></div>
            <div class="plan-unit"><?php echo esc_html( $plan['unit'] ); ?></div>
            <ul class="plan-features">
              <?php foreach ( $plan['f'] as $feat ) : ?><li><?php echo esc_html( $feat ); ?></li><?php endforeach; ?>
            </ul>
            <div class="plan-price"><span class="orig num"><?php echo esc_html( $plan['orig'] ); ?></span><span class="num"><?php echo esc_html( $plan['price'] ); ?></span> تومان</div>
            <?php
            $btn_html = '<button class="plan-btn ' . ( $plan['pop'] ? 'plan-btn-primary' : 'plan-btn-default' ) . '">افزودن به سبد</button>';
            echo apply_filters( 'ug_purchase_button', $btn_html, 0, [
                'label' => 'ورود برای خرید',
                'class' => 'plan-btn ' . ( $plan['pop'] ? 'plan-btn-primary' : 'plan-btn-default' ),
            ] );
            ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- ══ WHY US ══ -->
  <div class="section">
    <?php ug_section_head( 'چرا ممبر آپلودگرام؟' ); ?>
    <div class="why-grid">
      <div class="why-card"><div class="why-icon g">⚡</div><div><div class="why-title">شروع سریع</div><div class="why-desc">سفارش‌ها در کمتر از <span class="num">1</span> ساعت شروع به اضافه‌شدن می‌کنند</div></div></div>
      <div class="why-card"><div class="why-icon b">🛡️</div><div><div class="why-title">ضمانت جایگزینی</div><div class="why-desc">در صورت ریزش بیش از حد، ممبر جایگزین رایگان دریافت می‌کنید</div></div></div>
      <div class="why-card"><div class="why-icon y">💬</div><div><div class="why-title">پشتیبانی 24/7</div><div class="why-desc">تیم پشتیبانی همیشه پاسخگوی سؤالات شما در تلگرام است</div></div></div>
      <div class="why-card"><div class="why-icon p">✅</div><div><div class="why-title">۱۰۰٪ امن</div><div class="why-desc">بدون نیاز به رمز عبور؛ فقط با لینک یا یوزرنیم کانال شما</div></div></div>
    </div>
  </div>

  <?php
  ug_faq_items( [
      [ 'q' => 'آیا ممبرها واقعی هستند؟', 'a' => 'بله، تمام ممبرهایی که ارائه می‌دهیم واقعی و از اکانت‌های فعال هستند. از bot و اکانت fake استفاده نمی‌کنیم.' ],
      [ 'q' => 'آیا ریزش ممبر دارد؟', 'a' => 'ریزش طبیعی همیشه وجود دارد اما ضمانت جایگزینی برای مدت مشخص داریم. در صورت ریزش بیش از ۱۰٪، ممبر جایگزین ارائه می‌شود.' ],
      [ 'q' => 'چقدر طول می‌کشد تا ممبرها اضافه شوند؟', 'a' => 'بسته به پلن انتخابی، شروع فرآیند از ۳۰ دقیقه تا ۲۴ ساعت پس از پرداخت آغاز می‌شود.' ],
      [ 'q' => 'آیا نیاز به رمز عبور اکانت من است؟', 'a' => 'خیر، فقط کافیست لینک یا یوزرنیم عمومی کانال/پیج خود را ارائه دهید.' ],
  ] );
  ?>

</div>

<?php get_footer(); ?>
