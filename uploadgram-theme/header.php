<!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0b0d17">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- ══ HEADER ══ -->
<header>
  <div class="header-inner">
    <?php ug_logo(); ?>

    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="7"/>
        <path d="M21 21l-4.3-4.3"/>
      </svg>
      <input type="text" id="site-search" placeholder="<?php echo esc_attr__( 'جستجو در خدمات...', 'uploadgram' ); ?>" autocomplete="off">
    </div>

    <div class="header-actions">
      <?php if ( is_user_logged_in() ) : ?>
        <a href="<?php echo esc_url( get_dashboard_url() ); ?>" class="btn-ghost">
          <?php esc_html_e( 'پنل کاربری', 'uploadgram' ); ?>
        </a>
      <?php else : ?>
        <a href="<?php echo esc_url( wp_login_url() ); ?>" class="btn-ghost">
          <?php esc_html_e( 'ورود | ثبت‌نام', 'uploadgram' ); ?>
        </a>
      <?php endif; ?>

      <?php if ( class_exists( 'WooCommerce' ) ) : ?>
        <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="btn-primary">
          🛒 <?php esc_html_e( 'سبد خرید', 'uploadgram' ); ?>
          <span class="cart-badge num">
            <?php echo WC()->cart ? intval( WC()->cart->get_cart_contents_count() ) : 0; ?>
          </span>
        </a>
      <?php else : ?>
        <button class="btn-primary">🛒 <?php esc_html_e( 'سبد خرید', 'uploadgram' ); ?> <span class="cart-badge num">0</span></button>
      <?php endif; ?>
    </div>
  </div>
</header>

<!-- ══ MAIN NAV ══ -->
<nav class="main-nav">
  <div class="nav-inner">
    <?php
    wp_nav_menu( [
        'theme_location' => 'primary',
        'container'      => false,
        'items_wrap'     => '%3$s',
        'link_before'    => '',
        'link_after'     => '',
        'walker'         => new UG_Nav_Walker(),
    ] );
    ?>
  </div>
</nav>

<?php
/**
 * Simple nav walker — adds .nav-link class and .active on current page.
 */
if ( ! class_exists( 'UG_Nav_Walker' ) ) {
    class UG_Nav_Walker extends Walker_Nav_Menu {
        public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
            $classes  = empty( $data_object->classes ) ? [] : (array) $data_object->classes;
            $is_active = in_array( 'current-menu-item', $classes, true ) || in_array( 'current-page-ancestor', $classes, true );
            $output .= '<a class="nav-link' . ( $is_active ? ' active' : '' ) . '" href="' . esc_url( $data_object->url ) . '">' . esc_html( $data_object->title ) . '</a>';
        }
        public function end_el( &$output, $data_object, $depth = 0, $args = null ) {}
        public function start_lvl( &$output, $depth = 0, $args = null ) {}
        public function end_lvl( &$output, $depth = 0, $args = null ) {}
    }
}
?>
