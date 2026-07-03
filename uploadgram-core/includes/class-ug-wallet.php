<?php
/**
 * Wallet bridge — wraps TeraWallet (woo-wallet) with safe fallbacks.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Wallet {

    /**
     * Is a wallet backend available?
     */
    public function available(): bool {
        return function_exists( 'woo_wallet' );
    }

    /**
     * Current balance for a user (raw number).
     */
    public function balance( int $user_id = 0 ): float {
        $user_id = $user_id ?: get_current_user_id();
        if ( ! $user_id ) {
            return 0.0;
        }
        if ( $this->available() ) {
            return (float) woo_wallet()->wallet->get_wallet_balance( $user_id, 'edit' );
        }
        return (float) get_user_meta( $user_id, '_ug_wallet_balance', true );
    }

    /**
     * Formatted balance for display.
     */
    public function balance_display( int $user_id = 0 ): string {
        $bal = $this->balance( $user_id );
        if ( $this->available() && function_exists( 'wc_price' ) ) {
            return wp_strip_all_tags( wc_price( $bal ) );
        }
        return number_format_i18n( $bal ) . ' ' . __( 'تومان', 'uploadgram-core' );
    }

    /**
     * Charge (debit) the wallet. Returns true on success.
     */
    public function debit( int $user_id, float $amount, string $description ): bool {
        if ( $amount <= 0 ) {
            return true;
        }
        if ( $this->balance( $user_id ) < $amount ) {
            return false;
        }

        if ( $this->available() ) {
            $ok = woo_wallet()->wallet->debit( $user_id, $amount, $description );
            return (bool) $ok;
        }

        // Fallback meta wallet.
        $new = $this->balance( $user_id ) - $amount;
        update_user_meta( $user_id, '_ug_wallet_balance', $new );
        return true;
    }

    /**
     * Refund (credit) the wallet — used when an order fails/cancels.
     */
    public function credit( int $user_id, float $amount, string $description ): bool {
        if ( $amount <= 0 ) {
            return true;
        }
        if ( $this->available() ) {
            $ok = woo_wallet()->wallet->credit( $user_id, $amount, $description );
            return (bool) $ok;
        }
        $new = $this->balance( $user_id ) + $amount;
        update_user_meta( $user_id, '_ug_wallet_balance', $new );
        return true;
    }
}
