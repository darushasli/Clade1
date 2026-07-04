<?php
/**
 * Activation: create the custom orders table & schedule cron.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Install {

    const DB_VERSION = '1.2.0';

    /**
     * Orders table name (without prefix helper).
     */
    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ug_orders';
    }

    public static function activate(): void {
        self::create_tables();

        // Schedule status-sync cron every 2 minutes (custom schedule added in UG_Cron).
        if ( ! wp_next_scheduled( 'ug_sync_orders' ) ) {
            wp_schedule_event( time() + 120, 'ug_two_minutes', 'ug_sync_orders' );
        }

        update_option( 'ugc_db_version', self::DB_VERSION );
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        $ts = wp_next_scheduled( 'ug_sync_orders' );
        if ( $ts ) {
            wp_unschedule_event( $ts, 'ug_sync_orders' );
        }
        flush_rewrite_rules();
    }

    /**
     * Create / upgrade the orders table.
     */
    public static function create_tables(): void {
        global $wpdb;
        $table           = self::table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            provider VARCHAR(32) NOT NULL DEFAULT '',
            service_id VARCHAR(64) NOT NULL DEFAULT '',
            target TEXT NULL,
            quantity BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            currency VARCHAR(8) NOT NULL DEFAULT 'IRT',
            provider_order_id VARCHAR(128) NOT NULL DEFAULT '',
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            extra LONGTEXT NULL,
            note TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY provider (provider),
            KEY status (status),
            KEY provider_order_id (provider_order_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // OTP table (phone-verification codes).
        $otp_table = $wpdb->prefix . 'ug_otp';
        $sql_otp   = "CREATE TABLE {$otp_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            phone VARCHAR(20) NOT NULL DEFAULT '',
            code_hash VARCHAR(255) NOT NULL DEFAULT '',
            purpose VARCHAR(20) NOT NULL DEFAULT 'login',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY phone (phone),
            KEY purpose (purpose),
            KEY expires_at (expires_at)
        ) {$charset_collate};";
        dbDelta( $sql_otp );

        // Wallet transactions table.
        $tx_table = $wpdb->prefix . 'ug_wallet_tx';
        $sql_tx   = "CREATE TABLE {$tx_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            type VARCHAR(10) NOT NULL DEFAULT 'credit',
            amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            balance DECIMAL(18,2) NOT NULL DEFAULT 0,
            description TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY type (type)
        ) {$charset_collate};";
        dbDelta( $sql_tx );
    }

    /**
     * Run table upgrade when db version changes (called on init).
     */
    public static function maybe_upgrade(): void {
        if ( get_option( 'ugc_db_version' ) !== self::DB_VERSION ) {
            self::create_tables();
            update_option( 'ugc_db_version', self::DB_VERSION );
        }
    }
}

add_action( 'plugins_loaded', [ 'UG_Install', 'maybe_upgrade' ], 5 );
