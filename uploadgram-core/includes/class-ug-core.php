<?php
/**
 * Main plugin bootstrap — wires all modules together.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Core {

    /** @var UG_Core|null */
    private static $instance = null;

    /** @var UG_Settings */
    public $settings;

    /** @var UG_Dispatcher */
    public $dispatcher;

    /** @var UG_Wallet */
    public $wallet;

    /** @var UG_Orders */
    public $orders;

    public static function instance(): UG_Core {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Core services.
        $this->settings   = new UG_Settings();
        $this->orders     = new UG_Orders();
        $this->wallet     = new UG_Wallet();
        $this->dispatcher = new UG_Dispatcher( $this->settings );

        // Integrations.
        new UG_WC_Integration( $this->settings );
        new UG_Ajax( $this->dispatcher, $this->wallet, $this->orders, $this->settings );
        new UG_Cron( $this->dispatcher, $this->orders, $this->wallet );
        new UG_Panel( $this->wallet, $this->orders, $this->settings );

        add_action( 'init', [ $this, 'load_textdomain' ] );
    }

    public function load_textdomain(): void {
        load_plugin_textdomain( 'uploadgram-core', false, dirname( UGC_BASENAME ) . '/languages' );
    }

    /**
     * Convenience accessor.
     */
    public static function get( string $prop ) {
        $i = self::instance();
        return $i->$prop ?? null;
    }
}
