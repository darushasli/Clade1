<?php
/**
 * Provider contract — every backend (Followeran / Numberland / Telegram bot)
 * implements this so the dispatcher can treat them uniformly.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface UG_Provider_Interface {

    /**
     * Machine name: followeran | numberland | telegram.
     */
    public function name(): string;

    /**
     * Test connection. Returns [ 'ok' => bool, 'data' => mixed, 'error' => string ].
     */
    public function test(): array;

    /**
     * Place an order.
     *
     * @param array $args {
     *   service_id: string,
     *   target:     string   (link / username / — )
     *   quantity:   int,
     *   extra:      array
     * }
     * @return array [ 'ok' => bool, 'provider_order_id' => string, 'status' => string, 'data' => array, 'error' => string ]
     */
    public function create_order( array $args ): array;

    /**
     * Fetch status of a previously placed order.
     *
     * @param string $provider_order_id
     * @param array  $context  extra info stored on our order row
     * @return array [ 'ok' => bool, 'status' => string, 'data' => array, 'error' => string ]
     */
    public function order_status( string $provider_order_id, array $context = [] ): array;
}
