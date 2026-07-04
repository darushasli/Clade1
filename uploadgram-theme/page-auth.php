<?php
/**
 * Template Name: صفحه ورود / ثبت‌نام
 *
 * Renders the plugin's [ug_auth] shortcode in a clean full-page layout.
 */
get_header();
?>

<div class="container" style="min-height:calc(100vh - 240px);display:flex;align-items:center;justify-content:center;">
  <?php echo do_shortcode( '[ug_auth]' ); ?>
</div>

<?php get_footer(); ?>
