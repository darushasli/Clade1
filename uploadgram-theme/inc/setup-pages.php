<?php
/**
 * Auto-setup:
 *  - Creates core pages with correct page templates
 *  - Sets the static front page
 *  - Builds the primary navigation menu
 *
 * Runs on theme switch AND on any admin request when the setup version
 * marker doesn't match the current one — so theme UPGRADES (which don't
 * fire after_switch_theme) still pick up new pages like /panel/ and /auth/.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'UG_SETUP_VERSION', '1.3.0' );

add_action( 'after_switch_theme', 'ug_activate_setup' );
add_action( 'admin_init',         'ug_maybe_setup' );

/**
 * On every admin load: run setup ONCE per version bump, then remember it.
 * Cheap: bails immediately when the version matches.
 */
function ug_maybe_setup() {
    if ( get_option( 'ug_setup_done_v' ) === UG_SETUP_VERSION ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return; // avoid running for non-admin AJAX etc.
    }
    ug_activate_setup();
    update_option( 'ug_setup_done_v', UG_SETUP_VERSION );
}

function ug_activate_setup() {

    /* ── 1) Core pages ── */
    $pages = [
        'home' => [
            'title'    => 'خانه',
            'template' => '', // front-page.php auto-detected
            'content'  => '',
        ],
        'member' => [
            'title'    => 'خدمات مجازی',
            'template' => 'page-member.php',
            'content'  => '',
        ],
        'account' => [
            'title'    => 'اکانت پرمیوم',
            'template' => 'page-account.php',
            'content'  => '',
        ],
        'virtual-number' => [
            'title'    => 'شماره مجازی',
            'template' => 'page-virtual-number.php',
            'content'  => '',
        ],
        'contact' => [
            'title'    => 'تماس با ما',
            'template' => '',
            'content'  => 'برای ارتباط با ما در تلگرام پیام دهید.',
        ],
        'auth' => [
            'title'    => 'ورود و ثبت‌نام',
            'template' => 'page-auth.php',
            'content'  => '',
        ],
        'panel' => [
            'title'    => 'پنل کاربری',
            'template' => 'page-panel.php',
            'content'  => '',
        ],
    ];

    $page_ids = [];

    foreach ( $pages as $slug => $data ) {
        $existing = get_page_by_path( $slug );
        if ( $existing ) {
            $page_ids[ $slug ] = $existing->ID;
            // Keep the title in sync when we rename a core page (e.g. member → خدمات مجازی).
            if ( $existing->post_title !== $data['title'] ) {
                wp_update_post( [ 'ID' => $existing->ID, 'post_title' => $data['title'] ] );
            }
            continue;
        }

        $id = wp_insert_post( [
            'post_title'   => $data['title'],
            'post_name'    => $slug,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => $data['content'],
        ] );

        if ( $id && ! is_wp_error( $id ) ) {
            if ( ! empty( $data['template'] ) ) {
                update_post_meta( $id, '_wp_page_template', $data['template'] );
            }
            $page_ids[ $slug ] = $id;
        }
    }

    /* ── 2) Static front page ── */
    if ( isset( $page_ids['home'] ) ) {
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', $page_ids['home'] );
    }

    /* ── 3) Primary menu ── */
    $menu_name = 'منوی اصلی';
    $menu      = wp_get_nav_menu_object( $menu_name );

    if ( ! $menu ) {
        $menu_id = wp_create_nav_menu( $menu_name );

        $items = [
            [ 'title' => 'خانه',         'slug' => 'home' ],
            [ 'title' => 'خدمات مجازی',  'slug' => 'member' ],
            [ 'title' => 'اکانت پرمیوم', 'slug' => 'account' ],
            [ 'title' => 'شماره مجازی',  'slug' => 'virtual-number' ],
            [ 'title' => 'استارز',       'slug' => '',  'url' => '#' ],
            [ 'title' => 'تخفیف‌ها',     'slug' => '',  'url' => '#' ],
            [ 'title' => 'تماس با ما',   'slug' => 'contact' ],
        ];

        foreach ( $items as $item ) {
            if ( ! empty( $item['slug'] ) && isset( $page_ids[ $item['slug'] ] ) ) {
                wp_update_nav_menu_item( $menu_id, 0, [
                    'menu-item-title'     => $item['title'],
                    'menu-item-object'    => 'page',
                    'menu-item-object-id' => $page_ids[ $item['slug'] ],
                    'menu-item-type'      => 'post_type',
                    'menu-item-status'    => 'publish',
                ] );
            } else {
                wp_update_nav_menu_item( $menu_id, 0, [
                    'menu-item-title'  => $item['title'],
                    'menu-item-url'    => isset( $item['url'] ) ? $item['url'] : '#',
                    'menu-item-type'   => 'custom',
                    'menu-item-status' => 'publish',
                ] );
            }
        }

        /* Assign to primary location */
        $locations = get_theme_mod( 'nav_menu_locations', [] );
        $locations['primary'] = $menu_id;
        set_theme_mod( 'nav_menu_locations', $locations );
    }

    /* Flush rewrite so CPT slugs work */
    flush_rewrite_rules();
}
