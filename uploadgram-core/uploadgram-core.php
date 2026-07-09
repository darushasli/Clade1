<?php
/**
 * Plugin Name: UploadGram Core
 * Plugin URI:  https://uploadgram.ir
 * Description: هسته فروشگاه آپلودگرام — اتصال خدمات به API فالوران، نامبرلند و ربات تلگرام، کیف پول و پنل کاربری بدون سبد خرید.
 * Version:     2.6.0
 * Author:      UploadGram Team
 * Text Domain: uploadgram-core
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * WC requires at least: 8.0
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'UGC_VERSION', '2.6.0' );
define( 'UGC_FILE', __FILE__ );
define( 'UGC_DIR', plugin_dir_path( __FILE__ ) );
define( 'UGC_URL', plugin_dir_url( __FILE__ ) );
define( 'UGC_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoload includes.
 */
require_once UGC_DIR . 'includes/class-ug-install.php';
require_once UGC_DIR . 'includes/class-ug-logger.php';
require_once UGC_DIR . 'includes/class-ug-settings.php';
require_once UGC_DIR . 'includes/providers/interface-ug-provider.php';
require_once UGC_DIR . 'includes/providers/class-ug-provider-followeran.php';
require_once UGC_DIR . 'includes/providers/class-ug-provider-numberland.php';
require_once UGC_DIR . 'includes/providers/class-ug-provider-telegram.php';
require_once UGC_DIR . 'includes/class-ug-dispatcher.php';
require_once UGC_DIR . 'includes/class-ug-wallet.php';
require_once UGC_DIR . 'includes/class-ug-orders.php';
require_once UGC_DIR . 'includes/class-ug-wc-integration.php';
require_once UGC_DIR . 'includes/class-ug-sms-kavenegar.php';
require_once UGC_DIR . 'includes/class-ug-otp.php';
require_once UGC_DIR . 'includes/class-ug-google-auth.php';
require_once UGC_DIR . 'includes/class-ug-auth.php';
require_once UGC_DIR . 'includes/class-ug-guard.php';
require_once UGC_DIR . 'includes/class-ug-ajax.php';
require_once UGC_DIR . 'includes/class-ug-cron.php';
require_once UGC_DIR . 'includes/class-ug-panel.php';
require_once UGC_DIR . 'includes/class-ug-shortcodes.php';
require_once UGC_DIR . 'includes/class-ug-account-seed.php';
require_once UGC_DIR . 'includes/class-ug-sync.php';
require_once UGC_DIR . 'includes/class-ug-app-selector.php';
require_once UGC_DIR . 'includes/class-ug-tickets.php';
require_once UGC_DIR . 'includes/class-ug-users-admin.php';
require_once UGC_DIR . 'includes/class-ug-elementor.php';
require_once UGC_DIR . 'includes/class-ug-core.php';

/**
 * Activation / deactivation.
 */
register_activation_hook( __FILE__, [ 'UG_Install', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'UG_Install', 'deactivate' ] );

/**
 * Boot the plugin after all plugins are loaded (so WooCommerce is available).
 */
add_action( 'plugins_loaded', function () {

    // Require WooCommerce.
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'افزونه UploadGram Core برای کار به ووکامرس نیاز دارد. لطفاً ابتدا ووکامرس را نصب و فعال کنید.', 'uploadgram-core' );
            echo '</p></div>';
        } );
        return;
    }

    UG_Core::instance();

}, 20 );

/**
 * Declare HPOS (High-Performance Order Storage) compatibility.
 */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', UGC_FILE, true );
    }
} );
