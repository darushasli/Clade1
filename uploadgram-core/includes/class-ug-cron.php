<?php
/**
 * Cron — periodically syncs order statuses from providers and
 * fetches OTPs for virtual numbers. Refunds failed/canceled orders.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Cron {

    private UG_Dispatcher $dispatcher;
    private UG_Orders $orders;
    private UG_Wallet $wallet;

    public function __construct( UG_Dispatcher $dispatcher, UG_Orders $orders, UG_Wallet $wallet ) {
        $this->dispatcher = $dispatcher;
        $this->orders     = $orders;
        $this->wallet     = $wallet;

        add_filter( 'cron_schedules', [ $this, 'schedule' ] );
        add_action( 'ug_sync_orders', [ $this, 'sync' ] );
    }

    public function schedule( array $schedules ): array {
        $schedules['ug_two_minutes'] = [
            'interval' => 120,
            'display'  => __( 'هر ۲ دقیقه (آپلودگرام)', 'uploadgram-core' ),
        ];
        return $schedules;
    }

    /**
     * Poll all in-flight orders.
     */
    public function sync(): void {
        $orders = $this->orders->pending_sync( 40 );

        foreach ( $orders as $order ) {
            $res = $this->dispatcher->order_status(
                $order['provider'],
                $order['provider_order_id'],
                $order['extra']
            );

            if ( empty( $res['ok'] ) ) {
                continue; // transient error; try again next run.
            }

            $new_status = $res['status'];
            if ( $new_status === $order['status'] ) {
                // Still merge fresh data (e.g. remains) but skip heavy logic.
                if ( ! empty( $res['data'] ) ) {
                    $this->orders->update( (int) $order['id'], [ 'extra' => array_merge( $order['extra'], $res['data'] ) ] );
                }
                continue;
            }

            $update = [
                'status' => $new_status,
                'extra'  => array_merge( $order['extra'], $res['data'] ?? [] ),
            ];
            $this->orders->update( (int) $order['id'], $update );

            // Auto-refund on terminal failure/cancel.
            if ( in_array( $new_status, [ 'canceled', 'failed', 'refunded' ], true ) ) {
                $this->wallet->credit(
                    (int) $order['user_id'],
                    (float) $order['amount'],
                    sprintf( 'بازگشت وجه سفارش #%d (%s)', $order['id'], UG_Orders::status_label( $new_status ) )
                );
            }
        }
    }
}
