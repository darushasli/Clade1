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
      <?php
      $auth_url  = class_exists( 'UG_Guard' ) ? UG_Guard::auth_url() : wp_login_url();
      $panel_url = home_url( '/panel/' );
      $wallet_display = '';
      if ( is_user_logged_in() && class_exists( 'UG_Core' ) && UG_Core::instance()->wallet ) {
          $wallet_display = UG_Core::instance()->wallet->balance_display();
      }
      ?>
      <?php if ( is_user_logged_in() ) : ?>
        <?php if ( $wallet_display ) : ?>
          <a href="<?php echo esc_url( add_query_arg( 'section', 'wallet', $panel_url ) ); ?>" class="btn-ghost" title="کیف پول">
            💳 <span class="num"><?php echo esc_html( $wallet_display ); ?></span>
          </a>
        <?php endif; ?>
        <a href="<?php echo esc_url( $panel_url ); ?>" class="btn-primary">
          <?php echo esc_html( wp_get_current_user()->first_name ?: wp_get_current_user()->display_name ); ?> · پنل
        </a>
      <?php else : ?>
        <a href="<?php echo esc_url( $auth_url ); ?>" class="btn-ghost"><?php esc_html_e( 'ورود', 'uploadgram' ); ?></a>
        <a href="<?php echo esc_url( $auth_url ); ?>" class="btn-primary"><?php esc_html_e( 'ثبت‌نام', 'uploadgram' ); ?></a>
      <?php endif; ?>
    </div>
  </div>
</header>

<!-- ══ MAIN NAV ══ -->
<nav class="main-nav">
  <div class="nav-inner">
    <?php
    if ( has_nav_menu( 'primary' ) ) {
        wp_nav_menu( [
            'theme_location' => 'primary',
            'container'      => false,
            'items_wrap'     => '%3$s',
            'link_before'    => '',
            'link_after'     => '',
            'walker'         => new UG_Nav_Walker(),
        ] );
    } else {
        ug_primary_nav_fallback();
    }
    ?>
  </div>
</nav>
