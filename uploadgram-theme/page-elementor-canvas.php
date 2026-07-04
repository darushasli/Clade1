<?php
/**
 * Template Name: المنتور — بوم خالی
 *
 * Blank canvas WITHOUT the theme header/footer — a clean slate for
 * building a full custom page (e.g. a landing page) entirely in Elementor.
 * Still loads wp_head()/wp_footer() so Elementor + theme styles work.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?><!DOCTYPE html>
<html lang="<?php echo esc_attr( function_exists( 'ug_current_lang' ) ? ug_current_lang() : 'fa' ); ?>" dir="<?php echo esc_attr( function_exists( 'ug_dir' ) ? ug_dir() : 'rtl' ); ?>">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>(function(){try{var t=localStorage.getItem('ug-theme')||'light';document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<?php wp_head(); ?>
</head>
<body <?php body_class( 'ug-canvas' ); ?>>
<?php wp_body_open(); ?>

<?php
while ( have_posts() ) :
    the_post();
    the_content();
endwhile;
?>

<?php wp_footer(); ?>
</body>
</html>
