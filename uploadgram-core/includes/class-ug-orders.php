<?php
/**
 * Orders repository — CRUD over the custom ug_orders table.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Orders {

    private string $table;

    public function __construct() {
        $this->table = UG_Install::table();
    }

    /**
     * Insert a new order row. Returns insert id or 0.
     */
    public function create( array $data ): int {
        global $wpdb;

        $row = wp_parse_args( $data, [
            'user_id'           => get_current_user_id(),
            'product_id'        => 0,
            'provider'          => '',
            'service_id'        => '',
            'target'            => '',
            'quantity'          => 0,
            'amount'            => 0,
            'currency'          => 'IRT',
            'provider_order_id' => '',
            'status'            => 'pending',
            'extra'             => [],
            'note'              => '',
        ] );

        if ( is_array( $row['extra'] ) ) {
            $row['extra'] = wp_json_encode( $row['extra'], JSON_UNESCAPED_UNICODE );
        }
        $row['created_at'] = current_time( 'mysql' );
        $row['updated_at'] = current_time( 'mysql' );

        $ok = $wpdb->insert( $this->table, $row );
        return $ok ? (int) $wpdb->insert_id : 0;
    }

    /**
     * Update fields on an order.
     */
    public function update( int $id, array $fields ): bool {
        global $wpdb;
        if ( isset( $fields['extra'] ) && is_array( $fields['extra'] ) ) {
            $fields['extra'] = wp_json_encode( $fields['extra'], JSON_UNESCAPED_UNICODE );
        }
        $fields['updated_at'] = current_time( 'mysql' );
        return false !== $wpdb->update( $this->table, $fields, [ 'id' => $id ] );
    }

    public function get( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ), ARRAY_A );
        return $row ? $this->hydrate( $row ) : null;
    }

    /**
     * Orders for a user (most recent first).
     */
    public function for_user( int $user_id, int $limit = 50, int $offset = 0 ): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d",
                $user_id, $limit, $offset
            ),
            ARRAY_A
        );
        return array_map( [ $this, 'hydrate' ], $rows ?: [] );
    }

    /**
     * Orders that still need a status poll.
     */
    public function pending_sync( int $limit = 40 ): array {
        global $wpdb;
        $statuses = [ 'pending', 'processing', 'partial', 'awaiting_otp' ];
        $in       = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $sql      = "SELECT * FROM {$this->table} WHERE status IN ($in) AND provider_order_id <> '' ORDER BY id ASC LIMIT %d";
        $rows     = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $statuses, [ $limit ] ) ), ARRAY_A );
        return array_map( [ $this, 'hydrate' ], $rows ?: [] );
    }

    /**
     * Decode JSON columns.
     */
    private function hydrate( array $row ): array {
        $row['extra'] = ! empty( $row['extra'] ) ? (array) json_decode( $row['extra'], true ) : [];
        return $row;
    }

    /**
     * Human-readable status label (fa).
     */
    public static function status_label( string $status ): string {
        $labels = [
            'pending'      => 'در انتظار',
            'processing'   => 'در حال انجام',
            'partial'      => 'ناقص',
            'completed'    => 'تکمیل شد',
            'awaiting_otp' => 'در انتظار کد',
            'canceled'     => 'لغو شد',
            'refunded'     => 'بازگشت وجه',
            'failed'       => 'ناموفق',
        ];
        return $labels[ $status ] ?? $status;
    }
}
