<?php
/**
 * Customer management — a richer users screen than WP's default:
 * shows phone, wallet balance, orders count, and lets the admin
 * credit/debit a wallet inline.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Users_Admin {

    private UG_Wallet $wallet;
    private UG_Orders $orders;

    public function __construct( UG_Wallet $wallet, UG_Orders $orders ) {
        $this->wallet = $wallet;
        $this->orders = $orders;
        add_action( 'admin_menu', [ $this, 'menu' ], 25 );
        add_action( 'admin_post_ug_user_wallet_adjust', [ $this, 'wallet_adjust' ] );
    }

    public function menu(): void {
        add_submenu_page( 'uploadgram', 'کاربران', 'کاربران', 'manage_options', 'ug-users', [ $this, 'render' ] );
    }

    private function orders_count( int $uid ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'ug_orders';
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE user_id = %d", $uid ) );
    }

    public function render(): void {
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $args   = [ 'number' => 100, 'orderby' => 'registered', 'order' => 'DESC' ];
        if ( '' !== $search ) {
            $args['search']         = '*' . $search . '*';
            $args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
            // Also match phone meta.
            $args['meta_query'] = [ 'relation' => 'OR', [ 'key' => '_ug_phone', 'value' => $search, 'compare' => 'LIKE' ] ];
        }
        $users = get_users( $args );

        // If searching, also merge phone-meta matches (get_users can't OR search+meta easily).
        ?>
        <div class="wrap">
            <h1>کاربران آپلودگرام</h1>
            <form method="get" style="margin:12px 0;">
                <input type="hidden" name="page" value="ug-users">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="نام، ایمیل یا شماره">
                <button class="button">جستجو</button>
            </form>

            <?php if ( isset( $_GET['done'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>کیف پول به‌روزرسانی شد.</p></div>
            <?php endif; ?>

            <table class="widefat striped">
                <thead><tr><th>کاربر</th><th>شماره</th><th>ایمیل</th><th>کیف پول (تومان)</th><th>سفارش‌ها</th><th>تنظیم کیف پول</th></tr></thead>
                <tbody>
                <?php if ( empty( $users ) ) : ?>
                    <tr><td colspan="6">کاربری یافت نشد.</td></tr>
                <?php else : foreach ( $users as $u ) :
                    $phone   = get_user_meta( $u->ID, '_ug_phone', true );
                    $balance = $this->wallet->balance( $u->ID );
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( $u->display_name ); ?></strong><br><span style="color:#888;">#<?php echo (int) $u->ID; ?></span></td>
                        <td><?php echo esc_html( $phone ?: '—' ); ?></td>
                        <td><?php echo esc_html( $u->user_email ); ?></td>
                        <td><b><?php echo esc_html( number_format_i18n( $balance ) ); ?></b></td>
                        <td><?php echo (int) $this->orders_count( $u->ID ); ?></td>
                        <td>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:6px;align-items:center;">
                                <?php wp_nonce_field( 'ug_user_wallet_adjust' ); ?>
                                <input type="hidden" name="action" value="ug_user_wallet_adjust">
                                <input type="hidden" name="user_id" value="<?php echo (int) $u->ID; ?>">
                                <select name="op"><option value="credit">افزایش</option><option value="debit">کاهش</option></select>
                                <input type="number" name="amount" min="0" step="1000" style="width:110px;" placeholder="مبلغ" required>
                                <button class="button button-primary">اعمال</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            <p class="description">نمایش حداکثر ۱۰۰ کاربر اخیر. برای یافتن کاربر خاص از جستجو استفاده کنید.</p>
        </div>
        <?php
    }

    public function wallet_adjust(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'no' ); }
        check_admin_referer( 'ug_user_wallet_adjust' );
        $uid    = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
        $op     = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : 'credit';
        $amount = isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0;

        if ( $uid && $amount > 0 ) {
            if ( 'debit' === $op ) {
                $this->wallet->debit( $uid, $amount, 'تنظیم دستی توسط مدیر' );
            } else {
                $this->wallet->credit( $uid, $amount, 'شارژ دستی توسط مدیر' );
            }
        }
        wp_safe_redirect( admin_url( 'admin.php?page=ug-users&done=1' ) );
        exit;
    }
}
