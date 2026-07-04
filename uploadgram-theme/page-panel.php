<?php
/**
 * Template Name: پنل کاربری
 *
 * Renders the plugin's [ug_panel] shortcode (sidebar router with sections).
 */
get_header();

// Guard: guests → auth page.
if ( ! is_user_logged_in() ) {
    if ( class_exists( 'UG_Guard' ) ) {
        wp_safe_redirect( UG_Guard::auth_url( get_permalink() ) );
    } else {
        wp_safe_redirect( wp_login_url( get_permalink() ) );
    }
    exit;
}
?>

<div class="container">
  <?php echo do_shortcode( '[ug_panel]' ); ?>
</div>

<?php get_footer(); ?>
