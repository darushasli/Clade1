<?php
/**
 * Native Elementor widgets — every UploadGram section becomes a real
 * drag-and-drop widget under the "آپلودگرام" category, so pages can be built
 * visually without shortcodes. Widgets that accept parameters expose editable
 * controls; content-heavy sections render their block and can be styled with
 * Elementor's own layout/spacing controls.
 *
 * Only loads when Elementor is active.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Elementor {

    public function __construct() {
        add_action( 'elementor/elements/categories_registered', [ $this, 'category' ] );
        add_action( 'elementor/widgets/register', [ $this, 'register' ] );
    }

    public function category( $mgr ): void {
        $mgr->add_category( 'uploadgram', [
            'title' => __( 'آپلودگرام', 'uploadgram-core' ),
            'icon'  => 'fa fa-shopping-cart',
        ] );
    }

    public function register( $mgr ): void {
        if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
            return;
        }
        require_once UGC_DIR . 'includes/elementor/class-ug-widgets.php';

        foreach ( UG_Elementor_Widgets::classes() as $class ) {
            if ( class_exists( $class ) ) {
                $mgr->register( new $class() );
            }
        }
    }
}
