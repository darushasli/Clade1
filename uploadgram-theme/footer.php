<?php
/**
 * Elementor Pro custom Footer template overrides ours when assigned.
 */
if ( function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( 'footer' ) ) {
    // Elementor footer rendered — skip our default footer markup.
} else :
?>

<!-- ══ FOOTER ══ -->
<footer>
  <div class="container footer-grid">
    <div>
      <div class="footer-logo">
        <div class="logo-mark"><img src="<?php echo esc_url( ug_asset( 'brand/logo-small.png' ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></div>
        <span>آپلودگرام</span>
      </div>
      <p class="footer-desc">معتبرترین فروشگاه خدمات دیجیتال — ممبر، اکانت پرمیوم، شماره مجازی و استارز با قیمت رقابتی و پشتیبانی 24/7</p>
      <div class="footer-social">
        <a href="#" aria-label="تلگرام"><img loading="lazy" src="<?php echo esc_url( ug_asset( 'iconpack/telegram.svg' ) ); ?>" alt="تلگرام"></a>
        <a href="#" aria-label="اینستاگرام"><img loading="lazy" src="<?php echo esc_url( ug_asset( 'iconpack/instagram.svg' ) ); ?>" alt="اینستاگرام"></a>
        <a href="#" aria-label="واتساپ"><img loading="lazy" src="<?php echo esc_url( ug_asset( 'iconpack/whatsapp.svg' ) ); ?>" alt="واتساپ"></a>
        <a href="#" aria-label="فیسبوک"><img loading="lazy" src="<?php echo esc_url( ug_asset( 'iconpack/facebook.svg' ) ); ?>" alt="فیسبوک"></a>
        <a href="#" aria-label="دیسکورد"><img loading="lazy" src="<?php echo esc_url( ug_asset( 'iconpack/discord.svg' ) ); ?>" alt="دیسکورد"></a>
      </div>
    </div>

    <div>
      <h4>خدمات</h4>
      <?php if ( has_nav_menu( 'footer-1' ) ) : ?>
        <?php wp_nav_menu( [ 'theme_location' => 'footer-1', 'container' => false, 'items_wrap' => '<ul>%3$s</ul>', 'depth' => 1 ] ); ?>
      <?php else : ?>
        <ul>
          <li><a href="<?php echo esc_url( home_url( '/member/' ) ); ?>">ممبر تلگرام</a></li>
          <li><a href="<?php echo esc_url( home_url( '/account/' ) ); ?>">اکانت پرمیوم</a></li>
          <li><a href="<?php echo esc_url( home_url( '/virtual-number/' ) ); ?>">شماره مجازی</a></li>
          <li><a href="<?php echo esc_url( home_url( '/stars/' ) ); ?>">استارز تلگرام</a></li>
        </ul>
      <?php endif; ?>
    </div>

    <div>
      <h4>راهنما</h4>
      <?php if ( has_nav_menu( 'footer-2' ) ) : ?>
        <?php wp_nav_menu( [ 'theme_location' => 'footer-2', 'container' => false, 'items_wrap' => '<ul>%3$s</ul>', 'depth' => 1 ] ); ?>
      <?php else : ?>
        <ul>
          <li><a href="#">نحوه سفارش</a></li>
          <li><a href="#">سؤالات متداول</a></li>
          <li><a href="#">قوانین سایت</a></li>
          <li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">تماس با ما</a></li>
        </ul>
      <?php endif; ?>
    </div>

    <div>
      <h4>پرداخت امن</h4>
      <div class="pay-row">
        <img loading="lazy" class="pic" src="<?php echo esc_url( ug_asset( 'iconpack/paypal.svg' ) ); ?>" alt="پی‌پل">
        <img loading="lazy" class="pic" src="<?php echo esc_url( ug_asset( 'iconpack/binance.svg' ) ); ?>" alt="بایننس">
        <img loading="lazy" class="pic" src="<?php echo esc_url( ug_asset( 'new/amazon-pay.svg' ) ); ?>" alt="آمازون پی">
      </div>
      <p class="footer-desc" style="margin-top:12px;">پرداخت ریالی، کارت‌به‌کارت و رمزارز</p>
    </div>
  </div>

  <div class="container footer-bottom">
    <p>© <span class="num"><?php echo esc_html( ug_jalali_year() ); ?></span> آپلودگرام — تمامی حقوق محفوظ است</p>
    <div class="pay-row" style="color:var(--text-muted);font-size:12px;gap:18px;">
      <span>✓ پرداخت امن</span>
      <span>✓ ضمانت بازگشت</span>
    </div>
  </div>
</footer>
<?php endif; // end Elementor footer fallback ?>

<!-- Floating chat button -->
<a class="float-chat" href="#" title="پشتیبانی تلگرام" aria-label="پشتیبانی تلگرام">
  <svg viewBox="0 0 24 24" fill="none"><path d="M21 12L3 4l3 8-3 8 18-8z" fill="#fff"/></svg>
</a>

<?php wp_footer(); ?>
</body>
</html>
