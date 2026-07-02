<?php
/**
 * 404 template.
 */
get_header();
?>

<div class="container">
  <div class="section" style="text-align:center;padding:80px 20px;">
    <div style="font-size:72px;font-weight:900;font-family:'Inter',sans-serif;color:var(--mint);">404</div>
    <h1 class="section-heading" style="font-size:24px;margin:12px 0;">صفحه‌ای که دنبالش بودید پیدا نشد</h1>
    <p class="text-muted" style="margin-bottom:24px;">ممکن است آدرس اشتباه باشد یا صفحه حذف شده باشد.</p>
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn-hero">بازگشت به خانه ←</a>
  </div>
</div>

<?php get_footer(); ?>
