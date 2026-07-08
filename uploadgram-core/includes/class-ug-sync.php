<?php
/**
 * Catalogue sync — pulls services from Followeran (SMM) and Numberland
 * (virtual numbers) and creates/updates WooCommerce products automatically,
 * plus ensures the two Telegram uploader-bot member products exist.
 *
 * Products are created PUBLISHED and remain fully editable by hand (they are
 * ordinary WooCommerce products); a re-sync only refreshes price/min/max/stock
 * and never clobbers a manually-edited title/description.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Sync {

    private UG_Dispatcher $dispatcher;
    private UG_Settings $settings;

    const CRON_HOOK = 'ug_sync_catalog';

    /** Top-level product categories. */
    const CAT_SERVICES = 'خدمات مجازی';
    const CAT_NUMBERS  = 'شماره مجازی';
    const CAT_ACCOUNTS = 'اکانت پرمیوم';

    public function __construct( UG_Dispatcher $dispatcher, UG_Settings $settings ) {
        $this->dispatcher = $dispatcher;
        $this->settings   = $settings;

        add_action( 'admin_menu', [ $this, 'menu' ], 20 );
        add_action( 'wp_ajax_ug_sync_run', [ $this, 'ajax_run' ] );
        add_action( 'wp_ajax_ug_sync_bot_products', [ $this, 'ajax_bot_products' ] );
        add_action( 'wp_ajax_ug_seed_accounts', [ $this, 'ajax_seed_accounts' ] );

        add_action( self::CRON_HOOK, [ $this, 'run_all' ] );
        add_action( 'init', [ $this, 'maybe_schedule' ] );
    }

    /* ══════════════ Scheduling ══════════════ */

    public function maybe_schedule(): void {
        $enabled = 'yes' === $this->settings->get( 'sync_daily', 'no' );
        $has     = wp_next_scheduled( self::CRON_HOOK );
        if ( $enabled && ! $has ) {
            wp_schedule_event( time() + 300, 'daily', self::CRON_HOOK );
        } elseif ( ! $enabled && $has ) {
            wp_unschedule_event( $has, self::CRON_HOOK );
        }
    }

    /* ══════════════ Admin page ══════════════ */

    public function menu(): void {
        add_submenu_page(
            'uploadgram',
            __( 'همگام‌سازی سرویس‌ها', 'uploadgram-core' ),
            __( 'همگام‌سازی سرویس‌ها', 'uploadgram-core' ),
            'manage_options',
            'ug-sync',
            [ $this, 'render' ]
        );
    }

    public function render(): void {
        $counts = [
            'followeran' => $this->count_products( 'followeran' ),
            'numberland' => $this->count_products( 'numberland' ),
            'telegram'   => $this->count_products( 'telegram' ),
        ];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'همگام‌سازی سرویس‌ها', 'uploadgram-core' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'کاتالوگ فالوران و نامبرلند را به محصولات ووکامرس تبدیل می‌کند. محصولات به‌صورت منتشرشده ساخته می‌شوند و بعداً قابل ویرایش دستی‌اند. اجرای دوباره فقط قیمت/حداقل/حداکثر را تازه می‌کند و عنوان دستی را خراب نمی‌کند.', 'uploadgram-core' ); ?>
            </p>

            <table class="widefat" style="max-width:520px;margin:16px 0;">
                <tbody>
                    <tr><td>محصولات فالوران (SMM)</td><td><b><?php echo (int) $counts['followeran']; ?></b></td></tr>
                    <tr><td>محصولات نامبرلند (شماره)</td><td><b><?php echo (int) $counts['numberland']; ?></b></td></tr>
                    <tr><td>محصولات ممبر آپلودری (تلگرام)</td><td><b><?php echo (int) $counts['telegram']; ?></b></td></tr>
                </tbody>
            </table>

            <p>
                <button class="button button-primary ug-sync-btn" data-what="all"><?php esc_html_e( 'همگام‌سازی همه', 'uploadgram-core' ); ?></button>
                <button class="button ug-sync-btn" data-what="followeran"><?php esc_html_e( 'فقط فالوران', 'uploadgram-core' ); ?></button>
                <button class="button ug-sync-btn" data-what="numberland"><?php esc_html_e( 'فقط نامبرلند', 'uploadgram-core' ); ?></button>
                <button class="button ug-sync-bot"><?php esc_html_e( 'ساخت محصولات ممبر آپلودری', 'uploadgram-core' ); ?></button>
                <button class="button ug-seed-acc"><?php esc_html_e( 'ساخت اکانت‌های پرمیوم نمونه', 'uploadgram-core' ); ?></button>
            </p>

            <pre id="ug-sync-out" style="background:#111;color:#0f0;padding:14px;border-radius:8px;max-height:360px;overflow:auto;display:none;"></pre>

            <h2><?php esc_html_e( 'قیمت‌گذاری و زمان‌بندی', 'uploadgram-core' ); ?></h2>
            <p class="description"><?php esc_html_e( 'این مقادیر در تنظیمات › عمومی و همین‌جا کنترل می‌شوند.', 'uploadgram-core' ); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields( 'ugc_settings_group' ); ?>
                <table class="form-table" role="presentation">
                    <?php
                    $this->opt_field( 'sync_markup_percent', 'درصد سود روی قیمت خام API', 'number', 'مثلاً 25 → قیمت فروش = قیمت خام × ۱٫۲۵' );
                    $this->opt_field( 'followeran_price_multiplier', 'ضریب قیمت فالوران', 'number', 'اگر نرخ فالوران تومان است 1 بگذارید؛ اگر دلار/واحد دیگر است ضریب تبدیل به تومان' );
                    $this->opt_field( 'numberland_price_multiplier', 'ضریب قیمت نامبرلند', 'number', 'معمولاً 1 (نامبرلند تومان است)' );
                    $this->opt_field( 'sync_daily', 'همگام‌سازی خودکار روزانه؟', 'checkbox', 'یک‌بار در روز کاتالوگ را تازه می‌کند' );
                    ?>
                </table>
                <?php submit_button( __( 'ذخیره تنظیمات', 'uploadgram-core' ) ); ?>
            </form>
        </div>
        <script>
        (function($){
            var nonce = '<?php echo esc_js( wp_create_nonce( 'ug_sync' ) ); ?>';
            function run(action, what, $b){
                var $out = $('#ug-sync-out').show().text('در حال اجرا…');
                $('.ug-sync-btn,.ug-sync-bot,.ug-seed-acc').prop('disabled', true);
                $.post(ajaxurl, { action: action, what: what, _wpnonce: nonce })
                 .done(function(res){ $out.text(JSON.stringify(res.data || res, null, 2)); })
                 .fail(function(x){ $out.text('خطا: ' + x.status + '\n' + (x.responseText||'')); })
                 .always(function(){ $('.ug-sync-btn,.ug-sync-bot,.ug-seed-acc').prop('disabled', false); });
            }
            $('.ug-sync-btn').on('click', function(e){ e.preventDefault(); run('ug_sync_run', $(this).data('what')); });
            $('.ug-sync-bot').on('click', function(e){ e.preventDefault(); run('ug_sync_bot_products'); });
            $('.ug-seed-acc').on('click', function(e){ e.preventDefault(); run('ug_seed_accounts'); });
        })(jQuery);
        </script>
        <?php
    }

    private function opt_field( string $key, string $label, string $type, string $hint ): void {
        $name = UG_Settings::OPTION . '[' . $key . ']';
        $val  = $this->settings->get( $key );
        echo '<tr><th scope="row"><label>' . esc_html( $label ) . '</label></th><td>';
        if ( 'checkbox' === $type ) {
            // Hidden default so unchecking actually clears the value (merge-safe sanitize).
            echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="">';
            echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="yes" ' . checked( 'yes', $val, false ) . '> ' . esc_html( $hint ) . '</label>';
        } else {
            echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" class="regular-text">';
            echo '<p class="description">' . esc_html( $hint ) . '</p>';
        }
        echo '</td></tr>';
    }

    /* ══════════════ AJAX ══════════════ */

    public function ajax_run(): void {
        $this->guard();
        $what = isset( $_POST['what'] ) ? sanitize_key( $_POST['what'] ) : 'all';
        $res  = [];
        if ( 'all' === $what || 'followeran' === $what ) {
            $res['followeran'] = $this->sync_followeran();
        }
        if ( 'all' === $what || 'numberland' === $what ) {
            $res['numberland'] = $this->sync_numberland();
        }
        if ( 'all' === $what ) {
            $res['telegram'] = $this->ensure_bot_products();
        }
        wp_send_json_success( $res );
    }

    public function ajax_bot_products(): void {
        $this->guard();
        wp_send_json_success( [ 'telegram' => $this->ensure_bot_products() ] );
    }

    public function ajax_seed_accounts(): void {
        $this->guard();
        wp_send_json_success( [ 'accounts' => $this->seed_premium_accounts() ] );
    }

    private function guard(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی مجاز نیست.' ], 403 );
        }
        check_ajax_referer( 'ug_sync', '_wpnonce' );
    }

    /* ══════════════ Cron entry ══════════════ */

    public function run_all(): array {
        return [
            'followeran' => $this->sync_followeran(),
            'numberland' => $this->sync_numberland(),
            'telegram'   => $this->ensure_bot_products(),
        ];
    }

    /* ══════════════ Followeran (SMM) ══════════════ */

    public function sync_followeran(): array {
        $p = $this->dispatcher->provider( 'followeran' );
        if ( ! $p || ! method_exists( $p, 'services' ) ) {
            return [ 'ok' => false, 'error' => 'سرویس فالوران در دسترس نیست' ];
        }
        $res = $p->services();
        if ( empty( $res['ok'] ) || ! is_array( $res['data'] ) ) {
            return [ 'ok' => false, 'error' => $res['error'] ?? 'دریافت سرویس‌ها ناموفق بود' ];
        }

        $markup = (float) ( $this->settings->get( 'sync_markup_percent', '25' ) ?: 25 );
        $mult   = (float) ( $this->settings->get( 'followeran_price_multiplier', '1' ) ?: 1 );
        $created = 0; $updated = 0; $skipped = 0;

        foreach ( $res['data'] as $svc ) {
            $sid  = (string) ( $svc['service'] ?? $svc['id'] ?? '' );
            $name = (string) ( $svc['name'] ?? '' );
            if ( '' === $sid || '' === $name ) { $skipped++; continue; }

            $raw_rate = (float) ( $svc['rate'] ?? 0 );
            $rate     = round( $raw_rate * $mult * ( 1 + $markup / 100 ) );
            $cat      = (string) ( $svc['category'] ?? '' );
            $platform = $this->detect_platform( $cat . ' ' . $name );
            $kind     = $this->detect_kind( $cat . ' ' . $name );

            $r = $this->upsert_product( [
                'provider'   => 'followeran',
                'service_id' => $sid,
                'name'       => $name,
                'category'   => self::CAT_SERVICES,
                'platform'   => $platform,
                'kind'       => $kind,
                'price'      => $rate,     // display price ≈ per 1000
                'rate'       => $rate,     // per-1000 rate used by calc_price
                'min'        => (int) ( $svc['min'] ?? 0 ),
                'max'        => (int) ( $svc['max'] ?? 0 ),
                'input_type' => 'link',
                'fixed'      => false,
            ] );
            $r === 'created' ? $created++ : ( $r === 'updated' ? $updated++ : $skipped++ );
        }

        return [ 'ok' => true, 'created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'total' => count( $res['data'] ) ];
    }

    /* ══════════════ Numberland (virtual numbers) ══════════════ */

    public function sync_numberland(): array {
        $p = $this->dispatcher->provider( 'numberland' );
        if ( ! $p || ! method_exists( $p, 'services' ) ) {
            return [ 'ok' => false, 'error' => 'سرویس نامبرلند در دسترس نیست' ];
        }
        $res = $p->services(); // getinfo
        if ( empty( $res['ok'] ) || ! is_array( $res['data'] ) ) {
            return [ 'ok' => false, 'error' => $res['error'] ?? 'دریافت اطلاعات ناموفق بود' ];
        }

        $markup = (float) ( $this->settings->get( 'sync_markup_percent', '25' ) ?: 25 );
        $mult   = (float) ( $this->settings->get( 'numberland_price_multiplier', '1' ) ?: 1 );
        $created = 0; $updated = 0; $skipped = 0;

        foreach ( $res['data'] as $svc ) {
            // Numberland getinfo item fields are uppercase; keys vary, be tolerant.
            $sid = (string) ( $svc['id'] ?? $svc['ID'] ?? '' );
            if ( '' === $sid ) { $skipped++; continue; }

            $svc_name  = (string) ( $svc['service'] ?? $svc['SERVICE'] ?? $svc['name'] ?? $svc['NAME'] ?? '' );
            $country   = (string) ( $svc['country'] ?? $svc['COUNTRY'] ?? $svc['countryname'] ?? '' );
            $raw_price = (float) ( $svc['price'] ?? $svc['PRICE'] ?? $svc['amount'] ?? $svc['AMOUNT'] ?? 0 );
            $price     = round( $raw_price * $mult * ( 1 + $markup / 100 ) );
            $title     = trim( sprintf( 'شماره %s %s', $svc_name, $country ) );
            if ( '' === trim( $title ) ) { $title = 'شماره مجازی #' . $sid; }

            $r = $this->upsert_product( [
                'provider'   => 'numberland',
                'service_id' => $sid,
                'name'       => $title,
                'category'   => self::CAT_NUMBERS,
                'platform'   => $this->detect_platform( $svc_name ),
                'kind'       => 'number',
                'price'      => $price ?: 1000,
                'rate'       => 0,
                'min'        => 0,
                'max'        => 0,
                'input_type' => 'none',
                'fixed'      => true,
            ] );
            $r === 'created' ? $created++ : ( $r === 'updated' ? $updated++ : $skipped++ );
        }

        return [ 'ok' => true, 'created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'total' => count( $res['data'] ) ];
    }

    /* ══════════════ Telegram uploader-bot member products ══════════════ */

    public function ensure_bot_products(): array {
        $defs = [
            [
                'service_id' => 'bot-ethical',
                'order_type' => 'ethical',
                'name'       => '🌟 ممبر ویژه آپلودری',
                'rate'       => 400,
                'desc'       => 'ممبر ویژهٔ آپلودری با جوین اجباری — واقعی‌ترین و باکیفیت‌ترین روش ممبرگیری کانال تلگرام. شروع خودکار پس از افزودن ربات بررسی به‌عنوان ادمین کانال. 🚀',
            ],
            [
                'service_id' => 'bot-normal',
                'order_type' => 'unethical',
                'name'       => '👥 ممبر معمولی آپلودری',
                'rate'       => 300,
                'desc'       => 'ممبر معمولی آپلودری — اقتصادی و سریع برای رشد عددی کانال تلگرام. شروع خودکار پس از افزودن ربات بررسی به‌عنوان ادمین کانال. ⚡',
            ],
        ];
        $created = 0; $updated = 0;
        foreach ( $defs as $d ) {
            $r = $this->upsert_product( [
                'provider'    => 'telegram',
                'service_id'  => $d['service_id'],
                'name'        => $d['name'],
                'description' => $d['desc'],
                'category'    => self::CAT_SERVICES,
                'platform'    => 'telegram',
                'kind'        => 'members',
                'order_type'  => $d['order_type'],
                'price'       => $d['rate'],
                'rate'        => $d['rate'],
                'min'         => 1000,
                'max'         => 100000,
                'input_type'  => 'link',
                'fixed'       => false,
                'keep_desc'   => true,
            ] );
            $r === 'created' ? $created++ : $updated++;
        }
        return [ 'ok' => true, 'created' => $created, 'updated' => $updated ];
    }

    /* ══════════════ Premium accounts seed (no API) ══════════════ */

    public function seed_premium_accounts(): array {
        if ( ! class_exists( 'UG_Account_Seed' ) ) {
            return [ 'ok' => false, 'error' => 'فایل نمونه اکانت یافت نشد' ];
        }
        $items  = UG_Account_Seed::items();
        $groups = UG_Account_Seed::groups();
        $created = 0; $skipped = 0;
        foreach ( $items as $it ) {
            $group_label = $groups[ $it['group'] ] ?? self::CAT_ACCOUNTS;
            $r = $this->upsert_product( [
                'provider'    => '',              // manual — no API
                'service_id'  => 'acc-' . $it['slug'],
                'name'        => $it['name'],
                'description' => $it['desc'],
                'category'    => $group_label,
                'parent_cat'  => self::CAT_ACCOUNTS,
                'platform'    => $it['platform'] ?? '',
                'kind'        => 'account',
                'price'       => $it['price'],
                'rate'        => 0,
                'min'         => 0,
                'max'         => 0,
                'input_type'  => 'none',
                'fixed'       => true,
                'keep_desc'   => true,
                'icon'        => $it['icon'] ?? '',
                'features'    => $it['features'] ?? [],
                'allow_online'=> true,
            ] );
            $r === 'skipped' ? $skipped++ : $created++;
        }
        return [ 'ok' => true, 'created' => $created, 'skipped' => $skipped ];
    }

    /* ══════════════ Core upsert ══════════════ */

    /**
     * Create or update a WC product keyed by provider+service_id.
     * Returns 'created' | 'updated' | 'skipped'.
     */
    private function upsert_product( array $a ): string {
        $existing = $this->find_product( $a['provider'], $a['service_id'] );

        if ( $existing ) {
            $product = wc_get_product( $existing );
            if ( ! $product ) { return 'skipped'; }
            // Refresh price/limits only — never clobber a hand-edited title/desc.
            if ( isset( $a['price'] ) ) {
                $product->set_regular_price( (string) $a['price'] );
                $product->set_price( (string) $a['price'] );
            }
            $product->save();
            $this->write_meta( $existing, $a );
            return 'updated';
        }

        $product = new WC_Product_Simple();
        $product->set_name( $a['name'] );
        $product->set_status( 'publish' );
        $product->set_catalog_visibility( 'visible' );
        $product->set_regular_price( (string) ( $a['price'] ?? 0 ) );
        $product->set_price( (string) ( $a['price'] ?? 0 ) );
        if ( ! empty( $a['description'] ) ) {
            $product->set_description( $a['description'] );
            $product->set_short_description( $a['description'] );
        }
        $product->set_sold_individually( true );
        $product_id = $product->save();
        if ( ! $product_id ) { return 'skipped'; }

        if ( ! empty( $a['parent_cat'] ) ) {
            $this->assign_category( $product_id, $a['category'], $a['parent_cat'] );
        } else {
            $this->assign_category( $product_id, $a['category'] );
            if ( ! empty( $a['platform'] ) ) {
                $this->assign_category( $product_id, $this->platform_label( $a['platform'] ), $a['category'] );
            }
        }
        $this->write_meta( $product_id, $a );
        return 'created';
    }

    private function write_meta( int $product_id, array $a ): void {
        update_post_meta( $product_id, '_ug_provider', $a['provider'] );
        update_post_meta( $product_id, '_ug_service_id', $a['service_id'] );
        update_post_meta( $product_id, '_ug_input_type', $a['input_type'] );
        update_post_meta( $product_id, '_ug_min', (int) $a['min'] );
        update_post_meta( $product_id, '_ug_max', (int) $a['max'] );
        update_post_meta( $product_id, '_ug_rate', (float) $a['rate'] );
        update_post_meta( $product_id, '_ug_fixed', ! empty( $a['fixed'] ) ? 'yes' : 'no' );
        update_post_meta( $product_id, '_ug_platform', $a['platform'] ?? '' );
        update_post_meta( $product_id, '_ug_kind', $a['kind'] ?? '' );
        if ( ! empty( $a['order_type'] ) ) {
            update_post_meta( $product_id, '_ug_order_type', $a['order_type'] );
        }
        if ( isset( $a['icon'] ) ) {
            update_post_meta( $product_id, '_ug_icon', $a['icon'] );
        }
        if ( isset( $a['features'] ) && is_array( $a['features'] ) ) {
            update_post_meta( $product_id, '_ug_features', wp_json_encode( array_values( $a['features'] ), JSON_UNESCAPED_UNICODE ) );
        }
        if ( ! empty( $a['allow_online'] ) ) {
            update_post_meta( $product_id, '_ug_allow_online', 'yes' );
        }
    }

    /* ══════════════ Lookup / helpers ══════════════ */

    private function find_product( string $provider, string $service_id ) {
        $q = new WP_Query( [
            'post_type'      => 'product',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => '_ug_provider', 'value' => $provider ],
                [ 'key' => '_ug_service_id', 'value' => $service_id ],
            ],
        ] );
        return $q->have_posts() ? (int) $q->posts[0] : 0;
    }

    private function count_products( string $provider ): int {
        $q = new WP_Query( [
            'post_type'      => 'product',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [ [ 'key' => '_ug_provider', 'value' => $provider ] ],
        ] );
        return (int) $q->found_posts;
    }

    private function assign_category( int $product_id, string $name, string $parent_name = '' ): void {
        $parent_id = 0;
        if ( $parent_name ) {
            $parent = get_term_by( 'name', $parent_name, 'product_cat' );
            if ( ! $parent ) {
                $t = wp_insert_term( $parent_name, 'product_cat' );
                $parent_id = is_wp_error( $t ) ? 0 : (int) $t['term_id'];
            } else {
                $parent_id = (int) $parent->term_id;
            }
        }
        $term = get_term_by( 'name', $name, 'product_cat' );
        if ( ! $term ) {
            $t = wp_insert_term( $name, 'product_cat', $parent_id ? [ 'parent' => $parent_id ] : [] );
            if ( is_wp_error( $t ) ) { return; }
            $term_id = (int) $t['term_id'];
        } else {
            $term_id = (int) $term->term_id;
        }
        wp_set_object_terms( $product_id, [ $term_id ], 'product_cat', true );
    }

    private function platform_label( string $platform ): string {
        $map = [
            'instagram' => 'اینستاگرام', 'telegram' => 'تلگرام', 'youtube' => 'یوتیوب',
            'tiktok' => 'تیک‌تاک', 'spotify' => 'اسپاتیفای', 'twitter' => 'ایکس (توییتر)',
            'facebook' => 'فیسبوک', 'soundcloud' => 'ساندکلاود', 'whatsapp' => 'واتساپ',
            'discord' => 'دیسکورد', 'twitch' => 'توییچ', 'aparat' => 'آپارات',
            'rubika' => 'روبیکا', 'eitaa' => 'ایتا', 'google' => 'گوگل',
        ];
        return $map[ $platform ] ?? ucfirst( $platform );
    }

    private function detect_platform( string $text ): string {
        $t = mb_strtolower( $text );
        $map = [
            'instagram'  => [ 'instagram', 'اینستا', 'اینستاگرام', 'insta' ],
            'telegram'   => [ 'telegram', 'تلگرام', 'تلگ' ],
            'youtube'    => [ 'youtube', 'یوتیوب', 'یوتوب' ],
            'tiktok'     => [ 'tiktok', 'تیک تاک', 'تیک‌تاک', 'تیکتاک' ],
            'spotify'    => [ 'spotify', 'اسپاتیفای', 'اسپاتی' ],
            'twitter'    => [ 'twitter', 'توییتر', 'توئیتر', ' x ', 'ایکس' ],
            'facebook'   => [ 'facebook', 'فیسبوک', 'فیس بوک' ],
            'soundcloud' => [ 'soundcloud', 'ساندکلاود', 'سان کلو', 'سان‌کلو' ],
            'whatsapp'   => [ 'whatsapp', 'واتساپ', 'واتس اپ' ],
            'discord'    => [ 'discord', 'دیسکورد' ],
            'twitch'     => [ 'twitch', 'توییچ', 'توئیچ' ],
            'aparat'     => [ 'aparat', 'آپارات' ],
            'rubika'     => [ 'rubika', 'روبیکا' ],
            'eitaa'      => [ 'eitaa', 'ایتا' ],
            'google'     => [ 'google', 'گوگل', 'voice' ],
        ];
        foreach ( $map as $platform => $needles ) {
            foreach ( $needles as $n ) {
                if ( mb_strpos( $t, $n ) !== false ) {
                    return $platform;
                }
            }
        }
        return 'other';
    }

    private function detect_kind( string $text ): string {
        $t = mb_strtolower( $text );
        $map = [
            'followers'   => [ 'follower', 'فالوور', 'فالووئر', 'دنبال' ],
            'members'     => [ 'member', 'ممبر', 'عضو', 'اعضا' ],
            'likes'       => [ 'like', 'لایک', 'پسند' ],
            'views'       => [ 'view', 'بازدید', 'ویو', 'نمایش', 'تماشا' ],
            'comments'    => [ 'comment', 'کامنت', 'نظر', 'دیدگاه' ],
            'subscribers' => [ 'subscriber', 'ساب', 'مشترک', 'سابسکرایب' ],
            'plays'       => [ 'play', 'پلی', 'پخش' ],
            'shares'      => [ 'share', 'اشتراک', 'بازنشر' ],
            'reactions'   => [ 'reaction', 'ری اکشن', 'ری‌اکشن', 'واکنش' ],
            'story'       => [ 'story', 'استوری' ],
            'saves'       => [ 'save', 'سیو', 'ذخیره' ],
        ];
        foreach ( $map as $kind => $needles ) {
            foreach ( $needles as $n ) {
                if ( mb_strpos( $t, $n ) !== false ) {
                    return $kind;
                }
            }
        }
        return 'other';
    }
}
