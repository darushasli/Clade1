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

    /** @var UG_Sms_Kavenegar */
    public $sms;

    /** @var UG_Otp */
    public $otp;

    /** @var UG_Google_Auth */
    public $google;

    /** @var UG_Auth */
    public $auth;

    /** @var UG_Guard */
    public $guard;

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

        // Auth stack.
        $this->sms    = new UG_Sms_Kavenegar( $this->settings );
        $this->otp    = new UG_Otp( $this->sms );
        $this->google = new UG_Google_Auth( $this->settings );
        $this->auth   = new UG_Auth( $this->wallet );
        $this->guard  = new UG_Guard();

        // Integrations.
        new UG_WC_Integration( $this->settings );
        new UG_Ajax( $this->dispatcher, $this->wallet, $this->orders, $this->settings, $this->otp, $this->auth, $this->google );
        new UG_Cron( $this->dispatcher, $this->orders, $this->wallet );
        new UG_Panel( $this->wallet, $this->orders, $this->settings, $this->auth );
        new UG_Shortcodes();
        new UG_Sync( $this->dispatcher, $this->settings );
        new UG_App_Selector( $this->wallet );
        new UG_Elementor();

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
