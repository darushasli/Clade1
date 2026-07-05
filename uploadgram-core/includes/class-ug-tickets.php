<?php
/**
 * Support tickets — panel form (department, priority, related product) +
 * threaded replies, plus an admin screen to answer.
 *
 * Tables (self-installed, version-gated):
 *   {prefix}ug_tickets      id, user_id, department, priority, product_id, subject, status, created_at, updated_at
 *   {prefix}ug_ticket_msgs  id, ticket_id, sender(user|admin), body, created_at
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Tickets {

    const DB_VERSION = '1.1.0';

    public static function departments(): array {
        return [
            'sales'    => 'فروش و پیش‌فروش',
            'support'  => 'پشتیبانی فنی',
            'finance'  => 'مالی و کیف پول',
            'general'  => 'عمومی',
        ];
    }

    public static function priorities(): array {
        return [ 'low' => 'کم', 'normal' => 'متوسط', 'high' => 'زیاد' ];
    }

    public static function statuses(): array {
        return [ 'open' => 'باز', 'answered' => 'پاسخ داده‌شده', 'user_reply' => 'پاسخ کاربر', 'closed' => 'بسته' ];
    }

    public function __construct() {
        add_action( 'init', [ $this, 'maybe_install' ] );
        add_shortcode( 'ug_tickets', [ $this, 'sc_tickets' ] );

        add_action( 'wp_ajax_ug_ticket_create', [ $this, 'ajax_create' ] );
        add_action( 'wp_ajax_ug_ticket_reply', [ $this, 'ajax_reply' ] );

        add_action( 'admin_menu', [ $this, 'admin_menu' ], 30 );
        add_action( 'admin_post_ug_ticket_admin_reply', [ $this, 'admin_reply' ] );
    }

    /* ══════════════ Install ══════════════ */

    public function maybe_install(): void {
        if ( get_option( 'ug_tickets_db' ) === self::DB_VERSION ) {
            return;
        }
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $t  = $wpdb->prefix . 'ug_tickets';
        $tm = $wpdb->prefix . 'ug_ticket_msgs';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( "CREATE TABLE $t (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            department VARCHAR(20) NOT NULL DEFAULT 'general',
            priority VARCHAR(10) NOT NULL DEFAULT 'normal',
            product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            subject VARCHAR(200) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset;" );
        dbDelta( "CREATE TABLE $tm (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id BIGINT UNSIGNED NOT NULL,
            sender VARCHAR(10) NOT NULL DEFAULT 'user',
            body TEXT NOT NULL,
            attachment VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ticket_id (ticket_id)
        ) $charset;" );

        update_option( 'ug_tickets_db', self::DB_VERSION );
    }

    private function t(): string { global $wpdb; return $wpdb->prefix . 'ug_tickets'; }
    private function tm(): string { global $wpdb; return $wpdb->prefix . 'ug_ticket_msgs'; }

    /**
     * Handle an optional file upload on a ticket message. Returns the URL or ''.
     */
    private function handle_upload(): string {
        if ( empty( $_FILES['file']['name'] ) ) {
            return '';
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $mimes = [
            'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
            'pdf' => 'application/pdf', 'zip' => 'application/zip', 'rar' => 'application/x-rar-compressed', 'txt' => 'text/plain',
        ];
        $up = wp_handle_upload( $_FILES['file'], [ 'test_form' => false, 'mimes' => $mimes ] );
        return isset( $up['url'] ) ? esc_url_raw( $up['url'] ) : '';
    }

    private function attachment_html( string $url ): string {
        if ( '' === $url ) {
            return '';
        }
        return '<div class="ug-tmsg-file"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">📎 مشاهده پیوست</a></div>';
    }

    /* ══════════════ Panel shortcode ══════════════ */

    public function sc_tickets(): string {
        if ( ! is_user_logged_in() ) {
            return '<div class="ug-notice">برای مشاهده تیکت‌ها ابتدا وارد شوید.</div>';
        }
        $uid       = get_current_user_id();
        $ticket_id = isset( $_GET['ticket'] ) ? absint( $_GET['ticket'] ) : 0;

        if ( $ticket_id ) {
            return $this->render_thread( $ticket_id, $uid );
        }
        return $this->render_list_and_form( $uid );
    }

    private function render_list_and_form( int $uid ): string {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->t()} WHERE user_id = %d ORDER BY updated_at DESC", $uid ), ARRAY_A );

        // Related product = only products this user has actually ordered.
        $order_tbl = $wpdb->prefix . 'ug_orders';
        $pids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT product_id FROM $order_tbl WHERE user_id = %d AND product_id > 0", $uid ) );
        $products = [];
        foreach ( (array) $pids as $pid ) {
            $p = wc_get_product( (int) $pid );
            if ( $p ) { $products[] = $p; }
        }

        ob_start(); ?>
        <div class="ug-tickets">
          <form class="ug-ticket-form">
            <div class="ug-ticket-row">
              <label class="ug-field"><span>دپارتمان</span>
                <select name="department"><?php foreach ( self::departments() as $k => $v ) echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $v ) . '</option>'; ?></select>
              </label>
              <label class="ug-field"><span>اولویت</span>
                <select name="priority"><?php foreach ( self::priorities() as $k => $v ) echo '<option value="' . esc_attr( $k ) . '"' . selected( 'normal', $k, false ) . '>' . esc_html( $v ) . '</option>'; ?></select>
              </label>
            </div>
            <label class="ug-field"><span>محصول مرتبط (اختیاری)</span>
              <select name="product_id"><option value="0">— بدون محصول —</option>
                <?php foreach ( $products as $p ) echo '<option value="' . esc_attr( $p->get_id() ) . '">' . esc_html( $p->get_name() ) . '</option>'; ?>
              </select>
            </label>
            <label class="ug-field"><span>موضوع</span><input type="text" name="subject" maxlength="200" required></label>
            <label class="ug-field"><span>پیام</span><textarea name="body" rows="4" required></textarea></label>
            <label class="ug-field"><span>پیوست (اختیاری — عکس، PDF، ZIP)</span><input type="file" name="file" accept="image/*,.pdf,.zip,.txt,.rar"></label>
            <button type="submit" class="ug-btn ug-btn-primary">ارسال تیکت</button>
            <div class="ug-form-msg" style="display:none;"></div>
          </form>

          <h3 class="ug-panel-title">تیکت‌های من</h3>
          <?php if ( empty( $rows ) ) : ?>
            <div class="ug-notice">هنوز تیکتی ثبت نکرده‌اید.</div>
          <?php else : ?>
            <table class="ug-orders-table">
              <thead><tr><th>#</th><th>موضوع</th><th>دپارتمان</th><th>وضعیت</th><th>آخرین به‌روزرسانی</th><th></th></tr></thead>
              <tbody>
                <?php $deps = self::departments(); $st = self::statuses(); foreach ( $rows as $r ) : ?>
                  <tr>
                    <td><?php echo (int) $r['id']; ?></td>
                    <td><?php echo esc_html( $r['subject'] ); ?></td>
                    <td><?php echo esc_html( $deps[ $r['department'] ] ?? $r['department'] ); ?></td>
                    <td><span class="ug-status ug-status-<?php echo esc_attr( $r['status'] ); ?>"><?php echo esc_html( $st[ $r['status'] ] ?? $r['status'] ); ?></span></td>
                    <td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $r['updated_at'] ) ); ?></td>
                    <td><a class="ug-btn ug-btn-secondary" href="<?php echo esc_url( add_query_arg( [ 'section' => 'tickets', 'ticket' => $r['id'] ], home_url( '/panel/' ) ) ); ?>">مشاهده</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_thread( int $ticket_id, int $uid ): string {
        global $wpdb;
        $ticket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->t()} WHERE id = %d", $ticket_id ), ARRAY_A );
        if ( ! $ticket || (int) $ticket['user_id'] !== $uid ) {
            return '<div class="ug-notice">تیکت یافت نشد.</div>';
        }
        $msgs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->tm()} WHERE ticket_id = %d ORDER BY id ASC", $ticket_id ), ARRAY_A );
        $back = esc_url( add_query_arg( 'section', 'tickets', home_url( '/panel/' ) ) );

        ob_start(); ?>
        <div class="ug-ticket-thread">
          <a class="ug-btn ug-btn-secondary" href="<?php echo $back; ?>">→ بازگشت به لیست</a>
          <h3 class="ug-panel-title"><?php echo esc_html( $ticket['subject'] ); ?></h3>
          <div class="ug-thread">
            <?php foreach ( $msgs as $m ) : ?>
              <div class="ug-tmsg ug-tmsg-<?php echo esc_attr( $m['sender'] ); ?>">
                <div class="ug-tmsg-who"><?php echo 'admin' === $m['sender'] ? 'پشتیبانی' : 'شما'; ?></div>
                <div class="ug-tmsg-body"><?php echo nl2br( esc_html( $m['body'] ) ); ?></div>
                <?php echo $this->attachment_html( $m['attachment'] ?? '' ); ?>
                <div class="ug-tmsg-time"><?php echo esc_html( mysql2date( 'Y/m/d H:i', $m['created_at'] ) ); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ( 'closed' !== $ticket['status'] ) : ?>
            <form class="ug-ticket-reply" data-ticket="<?php echo (int) $ticket_id; ?>">
              <label class="ug-field"><span>پاسخ شما</span><textarea name="body" rows="3" required></textarea></label>
              <label class="ug-field"><span>پیوست (اختیاری)</span><input type="file" name="file" accept="image/*,.pdf,.zip,.txt,.rar"></label>
              <button type="submit" class="ug-btn ug-btn-primary">ارسال پاسخ</button>
              <div class="ug-form-msg" style="display:none;"></div>
            </form>
          <?php else : ?>
            <div class="ug-notice">این تیکت بسته شده است.</div>
          <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ══════════════ AJAX (user) ══════════════ */

    public function ajax_create(): void {
        check_ajax_referer( 'ug_front', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'ابتدا وارد شوید.' ], 401 );
        }
        global $wpdb;
        $uid     = get_current_user_id();
        $dep     = isset( $_POST['department'] ) ? sanitize_key( $_POST['department'] ) : 'general';
        $pri     = isset( $_POST['priority'] ) ? sanitize_key( $_POST['priority'] ) : 'normal';
        $pid     = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
        $body    = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';

        if ( ! isset( self::departments()[ $dep ] ) ) { $dep = 'general'; }
        if ( ! isset( self::priorities()[ $pri ] ) ) { $pri = 'normal'; }
        if ( '' === $subject || '' === $body ) {
            wp_send_json_error( [ 'message' => 'موضوع و پیام الزامی است.' ], 400 );
        }

        $now = current_time( 'mysql' );
        $att = $this->handle_upload();
        $wpdb->insert( $this->t(), [
            'user_id' => $uid, 'department' => $dep, 'priority' => $pri, 'product_id' => $pid,
            'subject' => $subject, 'status' => 'open', 'created_at' => $now, 'updated_at' => $now,
        ] );
        $tid = (int) $wpdb->insert_id;
        $wpdb->insert( $this->tm(), [ 'ticket_id' => $tid, 'sender' => 'user', 'body' => $body, 'attachment' => $att, 'created_at' => $now ] );

        wp_send_json_success( [ 'message' => 'تیکت شما ثبت شد.', 'redirect' => add_query_arg( [ 'section' => 'tickets', 'ticket' => $tid ], home_url( '/panel/' ) ) ] );
    }

    public function ajax_reply(): void {
        check_ajax_referer( 'ug_front', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'ابتدا وارد شوید.' ], 401 );
        }
        global $wpdb;
        $uid  = get_current_user_id();
        $tid  = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;
        $body = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';

        $ticket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->t()} WHERE id = %d", $tid ), ARRAY_A );
        if ( ! $ticket || (int) $ticket['user_id'] !== $uid ) {
            wp_send_json_error( [ 'message' => 'تیکت یافت نشد.' ], 404 );
        }
        if ( '' === $body ) {
            wp_send_json_error( [ 'message' => 'متن پاسخ خالی است.' ], 400 );
        }
        $now = current_time( 'mysql' );
        $att = $this->handle_upload();
        $wpdb->insert( $this->tm(), [ 'ticket_id' => $tid, 'sender' => 'user', 'body' => $body, 'attachment' => $att, 'created_at' => $now ] );
        $wpdb->update( $this->t(), [ 'status' => 'user_reply', 'updated_at' => $now ], [ 'id' => $tid ] );
        wp_send_json_success( [ 'message' => 'پاسخ ارسال شد.', 'reload' => true ] );
    }

    /* ══════════════ Admin ══════════════ */

    public function admin_menu(): void {
        $open = $this->count_open();
        $label = 'تیکت‌ها' . ( $open ? ' <span class="update-plugins"><span class="plugin-count">' . $open . '</span></span>' : '' );
        add_submenu_page( 'uploadgram', 'تیکت‌های پشتیبانی', $label, 'manage_options', 'ug-tickets', [ $this, 'admin_page' ] );
    }

    private function count_open(): int {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->t()} WHERE status IN ('open','user_reply')" );
    }

    public function admin_page(): void {
        global $wpdb;
        $view = isset( $_GET['ticket'] ) ? absint( $_GET['ticket'] ) : 0;
        echo '<div class="wrap"><h1>تیکت‌های پشتیبانی</h1>';

        if ( $view ) {
            $ticket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->t()} WHERE id = %d", $view ), ARRAY_A );
            if ( ! $ticket ) { echo '<p>یافت نشد.</p></div>'; return; }
            $user = get_userdata( (int) $ticket['user_id'] );
            $msgs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->tm()} WHERE ticket_id = %d ORDER BY id ASC", $view ), ARRAY_A );
            $deps = self::departments();
            echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=ug-tickets' ) ) . '">→ همه تیکت‌ها</a></p>';
            echo '<h2>' . esc_html( $ticket['subject'] ) . '</h2>';
            echo '<p><b>کاربر:</b> ' . esc_html( $user ? $user->display_name : $ticket['user_id'] )
                . ' · <b>دپارتمان:</b> ' . esc_html( $deps[ $ticket['department'] ] ?? '' )
                . ' · <b>اولویت:</b> ' . esc_html( self::priorities()[ $ticket['priority'] ] ?? '' );
            if ( $ticket['product_id'] ) {
                $p = wc_get_product( (int) $ticket['product_id'] );
                if ( $p ) { echo ' · <b>محصول:</b> ' . esc_html( $p->get_name() ); }
            }
            echo '</p><div style="max-width:720px;">';
            foreach ( $msgs as $m ) {
                $who = 'admin' === $m['sender'] ? 'پشتیبانی' : 'کاربر';
                $bg  = 'admin' === $m['sender'] ? '#e7f0ff' : '#f3f3f7';
                $att = ! empty( $m['attachment'] ) ? '<div><a href="' . esc_url( $m['attachment'] ) . '" target="_blank" rel="noopener">📎 پیوست</a></div>' : '';
                echo '<div style="background:' . $bg . ';padding:12px 14px;border-radius:10px;margin:8px 0;">'
                   . '<b>' . esc_html( $who ) . ':</b><br>' . nl2br( esc_html( $m['body'] ) ) . $att
                   . '<div style="color:#888;font-size:11px;margin-top:6px;">' . esc_html( mysql2date( 'Y/m/d H:i', $m['created_at'] ) ) . '</div></div>';
            }
            echo '</div>';
            echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="max-width:720px;margin-top:14px;">';
            wp_nonce_field( 'ug_ticket_admin_reply' );
            echo '<input type="hidden" name="action" value="ug_ticket_admin_reply">';
            echo '<input type="hidden" name="ticket_id" value="' . (int) $view . '">';
            echo '<textarea name="body" rows="4" class="large-text" placeholder="پاسخ..."></textarea>';
            echo '<p><input type="file" name="file"> <label><input type="checkbox" name="close" value="1"> بستن تیکت پس از پاسخ</label></p>';
            submit_button( 'ارسال پاسخ' );
            echo '</form></div>';
            return;
        }

        $rows = $wpdb->get_results( "SELECT t.*, u.display_name FROM {$this->t()} t LEFT JOIN {$wpdb->users} u ON u.ID = t.user_id ORDER BY t.updated_at DESC LIMIT 200", ARRAY_A );
        $deps = self::departments(); $st = self::statuses();
        echo '<table class="widefat striped"><thead><tr><th>#</th><th>موضوع</th><th>کاربر</th><th>دپارتمان</th><th>اولویت</th><th>وضعیت</th><th>به‌روزرسانی</th><th></th></tr></thead><tbody>';
        if ( ! $rows ) {
            echo '<tr><td colspan="8">تیکتی نیست.</td></tr>';
        }
        foreach ( (array) $rows as $r ) {
            echo '<tr><td>' . (int) $r['id'] . '</td><td>' . esc_html( $r['subject'] ) . '</td><td>' . esc_html( $r['display_name'] ) . '</td>'
               . '<td>' . esc_html( $deps[ $r['department'] ] ?? '' ) . '</td><td>' . esc_html( self::priorities()[ $r['priority'] ] ?? '' ) . '</td>'
               . '<td>' . esc_html( $st[ $r['status'] ] ?? $r['status'] ) . '</td><td>' . esc_html( mysql2date( 'Y/m/d H:i', $r['updated_at'] ) ) . '</td>'
               . '<td><a class="button" href="' . esc_url( admin_url( 'admin.php?page=ug-tickets&ticket=' . $r['id'] ) ) . '">مشاهده</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function admin_reply(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'no' ); }
        check_admin_referer( 'ug_ticket_admin_reply' );
        global $wpdb;
        $tid   = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;
        $body  = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
        $close = ! empty( $_POST['close'] );
        $now   = current_time( 'mysql' );
        $att   = $this->handle_upload();
        if ( $tid && ( '' !== $body || '' !== $att ) ) {
            $wpdb->insert( $this->tm(), [ 'ticket_id' => $tid, 'sender' => 'admin', 'body' => $body, 'attachment' => $att, 'created_at' => $now ] );
            $wpdb->update( $this->t(), [ 'status' => $close ? 'closed' : 'answered', 'updated_at' => $now ], [ 'id' => $tid ] );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=ug-tickets&ticket=' . $tid ) );
        exit;
    }
}
