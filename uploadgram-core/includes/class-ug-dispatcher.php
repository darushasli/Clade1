<?php
/**
 * Dispatcher — resolves the right provider and routes orders/status calls.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Dispatcher {

    /** @var UG_Settings */
    private $settings;

    /** @var array<string,UG_Provider_Interface> */
    private $cache = [];

    public function __construct( UG_Settings $settings ) {
        $this->settings = $settings;
    }

    /**
     * Get a provider instance by name.
     */
    public function provider( string $name ): ?UG_Provider_Interface {
        $name = strtolower( $name );
        if ( isset( $this->cache[ $name ] ) ) {
            return $this->cache[ $name ];
        }

        $config = $this->settings->provider( $name );
        $obj    = null;

        switch ( $name ) {
            case 'followeran':
                $obj = new UG_Provider_Followeran( $config );
                break;
            case 'numberland':
                $obj = new UG_Provider_Numberland( $config );
                break;
            case 'telegram':
                $obj = new UG_Provider_Telegram( $config );
                break;
        }

        if ( $obj ) {
            $this->cache[ $name ] = $obj;
        }
        return $obj;
    }

    /**
     * Available provider machine names.
     */
    public function providers(): array {
        return [ 'followeran', 'numberland', 'telegram' ];
    }

    /**
     * Place an order through the correct provider.
     */
    public function create_order( string $provider, array $args ): array {
        $p = $this->provider( $provider );
        if ( ! $p ) {
            return [ 'ok' => false, 'provider_order_id' => '', 'status' => 'failed', 'data' => [], 'error' => 'سرویس نامعتبر: ' . $provider ];
        }
        return $p->create_order( $args );
    }

    /**
     * Fetch order status through the correct provider.
     */
    public function order_status( string $provider, string $provider_order_id, array $context = [] ): array {
        $p = $this->provider( $provider );
        if ( ! $p ) {
            return [ 'ok' => false, 'status' => '', 'data' => [], 'error' => 'سرویس نامعتبر: ' . $provider ];
        }
        return $p->order_status( $provider_order_id, $context );
    }

    public function test( string $provider ): array {
        $p = $this->provider( $provider );
        if ( ! $p ) {
            return [ 'ok' => false, 'error' => 'سرویس نامعتبر: ' . $provider, 'data' => null ];
        }
        return $p->test();
    }
}
