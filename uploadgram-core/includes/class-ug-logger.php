<?php
/**
 * Lightweight logger — writes to WooCommerce logs (source: uploadgram).
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Logger {

    /**
     * @param string $message
     * @param string $level  emergency|alert|critical|error|warning|notice|info|debug
     * @param array  $context
     */
    public static function log( string $message, string $level = 'info', array $context = [] ): void {
        if ( ! empty( $context ) ) {
            $message .= ' | ' . wp_json_encode( $context, JSON_UNESCAPED_UNICODE );
        }

        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->log( $level, $message, [ 'source' => 'uploadgram' ] );
        } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[uploadgram][' . $level . '] ' . $message );
        }
    }

    public static function error( string $message, array $context = [] ): void {
        self::log( $message, 'error', $context );
    }

    public static function info( string $message, array $context = [] ): void {
        self::log( $message, 'info', $context );
    }
}
