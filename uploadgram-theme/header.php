<!DOCTYPE html>
<html lang="<?php echo esc_attr( ug_current_lang() ); ?>" dir="<?php echo esc_attr( ug_dir() ); ?>">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#ffffff">
<script>
/* Apply saved theme before paint to avoid flash. Default = light. */
(function(){try{var t=localStorage.getItem('ug-theme')||'light';document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
</script>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
/**
 * If Elementor Pro has a custom Header template assigned, render it and
 * skip our built-in header entirely. Otherwise fall through to ours.
 */
if ( function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( 'header' ) ) {
    return;
}
?>

<!-- ══ HEADER ══ -->
<header>
  <div class="header-inner">
    <?php ug_logo(); ?>

    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="7"/>
        <path d="M21 21l-4.3-4.3"/>
      </svg>
      <input type="text" id="site-search" placeholder="<?php echo esc_attr( ug_t( 'search_ph' ) ); ?>" autocomplete="off">
    </div>

    <div class="header-actions">
      <?php if ( function_exists( 'ug_is_panel_page' ) && ug_is_panel_page() ) : ?>
        <!-- Panel: site menu is hidden by default; this reveals it -->
        <button type="button" class="icon-btn" id="ug-sitenav-toggle" title="منوی سایت" aria-label="نمایش منوی سایت">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
      <?php endif; ?>
      <?php
      $auth_url  = class_exists( 'UG_Guard' ) ? UG_Guard::auth_url() : wp_login_url();
      $panel_url = home_url( '/panel/' );
      $wallet_display = '';
      if ( is_user_logged_in() && class_exists( 'UG_Core' ) && UG_Core::instance()->wallet ) {
          $wallet_display = UG_Core::instance()->wallet->balance_display();
      }
      ?>

      <!-- Theme toggle -->
      <button type="button" class="icon-btn" id="ug-theme-toggle" title="حالت روشن / تاریک" aria-label="تغییر حالت نمایش">
        <svg class="ico-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4"/></svg>
        <svg class="ico-moon" viewBox="0 0 24 24" fill="currentColor"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
      </button>

      <!-- Language switcher -->
      <div class="lang-switch" id="ug-lang-switch">
        <button type="button" class="icon-btn lang-current" aria-label="تغییر زبان">
          <span class="lang-flag"><?php echo ug_lang_flag( ug_current_lang() ); ?></span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <div class="lang-menu">
          <?php foreach ( ug_languages() as $code => $lang ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'lang', $code ) ); ?>" class="lang-item<?php echo ug_current_lang() === $code ? ' active' : ''; ?>" data-lang="<?php echo esc_attr( $code ); ?>">
              <span class="lang-flag"><?php echo $lang['flag']; ?></span>
              <span><?php echo esc_html( $lang['name'] ); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ( is_user_logged_in() ) : ?>
        <?php if ( $wallet_display ) : ?>
          <a href="<?php echo esc_url( add_query_arg( 'section', 'wallet', $panel_url ) ); ?>" class="btn-ghost" title="<?php echo esc_attr( ug_t( 'wallet' ) ); ?>">
            💳 <span class="num"><?php echo esc_html( $wallet_display ); ?></span>
          </a>
        <?php endif; ?>
        <a href="<?php echo esc_url( $panel_url ); ?>" class="btn-primary">
          <?php echo esc_html( wp_get_current_user()->first_name ?: wp_get_current_user()->display_name ); ?> · <?php echo esc_html( ug_t( 'panel' ) ); ?>
        </a>
      <?php else : ?>
        <a href="<?php echo esc_url( $auth_url ); ?>" class="btn-primary"><?php echo esc_html( ug_t( 'auth' ) ); ?></a>
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
