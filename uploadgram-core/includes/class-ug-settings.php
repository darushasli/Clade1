<?php
/**
 * Admin settings — per-provider API configuration.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Settings {

    const OPTION = 'ugc_settings';

    /** @var array */
    private $data;

    public function __construct() {
        $this->data = get_option( self::OPTION, [] );

        add_action( 'admin_menu', [ $this, 'menu' ] );
        add_action( 'admin_init', [ $this, 'register' ] );
    }

    /* ── Accessors ─────────────────────────── */

    public function get( string $key, $default = '' ) {
        return $this->data[ $key ] ?? $default;
    }

    /**
     * Provider config bundle.
     */
    public function provider( string $name ): array {
        switch ( $name ) {
            case 'followeran':
                return [
                    'endpoint' => $this->get( 'followeran_endpoint', 'https://my.followeran.ir/api/v2' ),
                    'fallback' => $this->get( 'followeran_fallback', 'https://panel.smmflw.com/api/iran' ),
                    'api_key'  => $this->get( 'followeran_key' ),
                    'proxy'    => $this->get( 'followeran_proxy' ),
                ];
            case 'numberland':
                return [
                    'endpoint' => $this->get( 'numberland_endpoint', 'https://api.numberland.ir/v2.php' ),
                    'api_key'  => $this->get( 'numberland_key' ),
                ];
            case 'herosms':
                return [
                    'endpoint' => $this->get( 'herosms_endpoint', 'https://hero-sms.com/stubs/handler_api.php' ),
                    'api_key'  => $this->get( 'herosms_key' ),
                    'proxy'    => $this->get( 'herosms_proxy' ),
                ];
            case 'telegram':
                return [
                    'endpoint' => $this->get( 'telegram_endpoint' ),
                    'api_key'  => $this->get( 'telegram_token' ),
                    'proxy'    => $this->get( 'telegram_proxy' ),
                ];
        }
        return [];
    }

    public function default_currency(): string {
        return $this->get( 'currency', 'IRT' );
    }

    /* ── Admin menu ────────────────────────── */

    public function menu(): void {
        add_menu_page(
            __( 'آپلودگرام', 'uploadgram-core' ),
            __( 'آپلودگرام', 'uploadgram-core' ),
            'manage_options',
            'uploadgram',
            [ $this, 'render_page' ],
            'dashicons-cart',
            56
        );
    }

    public function register(): void {
        register_setting( 'ugc_settings_group', self::OPTION, [ $this, 'sanitize' ] );
    }

    public function sanitize( $input ): array {
        // Merge over existing values: each admin tab submits only its own
        // fields, so a partial save must NOT wipe the other tabs' settings.
        $out = get_option( self::OPTION, [] );
        if ( ! is_array( $out ) ) {
            $out = [];
        }
        foreach ( (array) $input as $k => $v ) {
            $out[ $k ] = is_string( $v ) ? sanitize_text_field( $v ) : $v;
        }
        return $out;
    }

    /* ── Render ────────────────────────────── */

    public function render_page(): void {
        $tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        $tabs = [
            'general'    => 'عمومی',
            'auth'       => 'ورود و ثبت‌نام',
            'followeran' => 'فالوران (SMM)',
            'herosms'    => 'هیرو‌اس‌ام‌اس (شماره مجازی)',
            'telegram'   => 'ربات تلگرام',
            'tools'      => 'ابزار و تست',
        ];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'تنظیمات آپلودگرام', 'uploadgram-core' ); ?></h1>

            <h2 class="nav-tab-wrapper">
                <?php foreach ( $tabs as $key => $label ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=uploadgram&tab=' . $key ) ); ?>"
                       class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <?php if ( 'tools' === $tab ) : ?>
                <?php $this->render_tools(); ?>
            <?php else : ?>
                <form method="post" action="options.php">
                    <?php settings_fields( 'ugc_settings_group' ); ?>
                    <table class="form-table" role="presentation">
                        <?php $this->render_tab_fields( $tab ); ?>
                    </table>
                    <?php submit_button(); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    private function field( string $key, string $label, string $type = 'text', string $hint = '', string $placeholder = '' ): void {
        $name  = self::OPTION . '[' . $key . ']';
        $value = $this->get( $key );
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td>
                <?php if ( 'textarea' === $type ) : ?>
                    <textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="3" class="large-text" placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
                <?php else : ?>
                    <input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name ); ?>"
                           value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off">
                <?php endif; ?>
                <?php if ( $hint ) : ?><p class="description"><?php echo esc_html( $hint ); ?></p><?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /** A standard proxy field + inline validity warning, shared by all providers. */
    private function proxy_field( string $key ): void {
        echo '<tr><td colspan="2"><p class="description"><strong>پروکسی (اختیاری):</strong> اگر این سرویس روی هاست شما فیلتر است، درخواست‌ها از این پروکسی عبور می‌کنند. پشتیبانی: <code>http://user:pass@host:port</code>، <code>https://…</code>، <code>socks5://host:port</code>. برای <strong>VLESS</strong> ابتدا از ابزار «تبدیل VLESS به کانفیگ Xray» (تب ابزار و تست) استفاده کنید و سپس آدرس SOCKS محلی (مثل <code>socks5://127.0.0.1:10808</code>) را این‌جا بگذارید. خالی = اتصال مستقیم.</p></td></tr>';
        $this->field( $key, 'پروکسی', 'text', '', 'socks5://127.0.0.1:10808' );
        $val = trim( (string) $this->get( $key ) );
        if ( '' !== $val && class_exists( 'UG_Proxy' ) ) {
            $warn = UG_Proxy::warning( $val );
            if ( '' !== $warn ) {
                echo '<tr><td colspan="2"><div class="notice notice-warning inline" style="margin:0;padding:8px 12px;"><p style="margin:0;">⚠️ ' . esc_html( $warn ) . '</p></div></td></tr>';
            }
        }
    }

    private function render_tab_fields( string $tab ): void {
        switch ( $tab ) {
            case 'general':
                $this->field( 'currency', 'واحد پول', 'text', 'مثلاً IRT (تومان) — قیمت‌ها بر این مبنا محاسبه می‌شوند', 'IRT' );
                $this->field( 'usd_rate', 'نرخ تبدیل دلار به تومان', 'number', 'برای تبدیل قیمت دلاری فالوران به تومان (مثلاً 70000)', '70000' );
                $this->field( 'profit_percent', 'درصد سود پیش‌فرض', 'number', 'درصدی که روی قیمت خام API اضافه می‌شود (مثلاً 20)', '20' );
                break;

            case 'followeran':
                $this->field( 'followeran_endpoint', 'آدرس API', 'text', 'معمولاً https://my.followeran.ir/api/v2', 'https://my.followeran.ir/api/v2' );
                $this->field( 'followeran_fallback', 'آدرس پشتیبان', 'text', 'اگر آدرس اصلی در دسترس نبود استفاده می‌شود', 'https://panel.smmflw.com/api/iran' );
                $this->field( 'followeran_key', 'کلید API', 'text', 'از پنل فالوران › بخش API دریافت کنید' );
                $this->proxy_field( 'followeran_proxy' );
                break;

            case 'herosms':
                echo '<tr><td colspan="2"><p class="description">هیرو‌اس‌ام‌اس با پروتکل SMS-Activate کار می‌کند. کلید API را از حساب hero-sms.com دریافت کنید. پس از ذخیره، از تب «ابزار و تست» دکمهٔ «تست هیرو‌اس‌ام‌اس» و از پیشخوان › آپلودگرام › همگام‌سازی، دکمهٔ «به‌روزرسانی فهرست شماره‌ها» را بزنید.</p></td></tr>';
                $this->field( 'herosms_key', 'کلید API', 'text', 'کلید API حساب هیرو‌اس‌ام‌اس شما' );
                $this->field( 'herosms_endpoint', 'آدرس API', 'text', 'خالی بگذارید تا مقدار پیش‌فرض استفاده شود (این فیلد را خالی نگذارید ≠ نامعتبر؛ اگر ننویسید خودکار پیش‌فرض می‌شود)', 'https://hero-sms.com/stubs/handler_api.php' );
                $this->proxy_field( 'herosms_proxy' );
                $this->field( 'herosms_usd_rate', 'نرخ تبدیل هر واحد قیمت به تومان', 'number', 'قیمت هیرو‌اس‌ام‌اس بر حسب دلار است؛ نرخ دلار به تومان (خالی = همان نرخ عمومی)', '70000' );
                $this->field( 'herosms_markup', 'درصد سود روی قیمت شماره', 'number', 'درصدی که روی قیمت خام اضافه می‌شود (خالی = درصد سود همگام‌سازی)', '25' );
                break;

            case 'telegram':
                echo '<tr><td colspan="2"><p class="description">فایل <code>bridge/ug-bridge.php</code> را کنار سورس ربات آپلود کنید (راهنما: <code>bridge/README.md</code>). دو مقدار زیر باید با آن فایل یکی باشند.</p></td></tr>';
                $this->field( 'telegram_endpoint', 'آدرس پل ربات', 'text', 'آدرس کامل ug-bridge.php روی هاست ربات', 'https://activemember.shop/6/ug-bridge.php' );
                $this->field( 'telegram_token', 'توکن امنیتی', 'text', 'همان مقدار UG_BRIDGE_SECRET داخل ug-bridge.php' );
                $this->proxy_field( 'telegram_proxy' );
                break;

            case 'auth':
                echo '<tr><th colspan="2"><h2 style="margin:0;">پیامک — کاوه‌نگار</h2></th></tr>';
                $this->field( 'kavenegar_api_key', 'API Key کاوه‌نگار', 'text', 'از پنل کاوه‌نگار › تنظیمات › API' );
                $this->field( 'kavenegar_template', 'نام Template تأیید', 'text', 'نام قالب تأیید در پنل کاوه‌نگار (مثلاً uploadgram-verify)' );

                echo '<tr><th colspan="2"><h2 style="margin:24px 0 0;">ورود با گوگل</h2></th></tr>';
                $this->field( 'google_client_id', 'Google Client ID', 'text', 'از console.cloud.google.com › OAuth 2.0 Client IDs' );

                echo '<tr><th colspan="2"><h2 style="margin:24px 0 0;">قوانین و آدرس‌ها</h2></th></tr>';
                $this->field( 'terms_url', 'آدرس صفحه قوانین و شرایط', 'text', 'لینکی که در چک‌باکس ثبت‌نام نمایش داده می‌شود' );
                break;
        }
    }

    /**
     * Tools tab — connection test buttons (AJAX).
     */
    private function render_tools(): void {
        ?>
        <p class="description"><?php esc_html_e( 'تست اتصال به هر سرویس. نتیجه موجودی/سرویس‌ها را برمی‌گرداند.', 'uploadgram-core' ); ?></p>
        <p>
            <button class="button button-secondary ug-test" data-provider="followeran"><?php esc_html_e( 'تست فالوران', 'uploadgram-core' ); ?></button>
            <button class="button button-secondary ug-test" data-provider="herosms"><?php esc_html_e( 'تست هیرو‌اس‌ام‌اس', 'uploadgram-core' ); ?></button>
            <button class="button button-secondary ug-test" data-provider="telegram"><?php esc_html_e( 'تست ربات', 'uploadgram-core' ); ?></button>
        </p>
        <h2><?php esc_html_e( 'تست ارسال پیامک', 'uploadgram-core' ); ?></h2>
        <p>
            <input type="text" id="ug-test-phone" placeholder="09xxxxxxxxx" style="width:220px;">
            <button class="button button-secondary" id="ug-test-sms"><?php esc_html_e( 'ارسال کد آزمایشی', 'uploadgram-core' ); ?></button>
        </p>
        <pre id="ug-test-result" style="background:#111;color:#0f0;padding:14px;border-radius:8px;max-height:320px;overflow:auto;display:none;"></pre>

        <hr style="margin:28px 0;">
        <h2><?php esc_html_e( 'تبدیل VLESS به کانفیگ Xray (برای پروکسی)', 'uploadgram-core' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'PHP نمی‌تواند مستقیماً به VLESS وصل شود. لینک vless:// خود را این‌جا بچسبانید تا یک کانفیگ آمادهٔ Xray بگیرید؛ آن را در یک کلاینت Xray/sing-box روی همان هاست اجرا کنید تا یک درگاه SOCKS5 محلی باز شود، سپس آدرس آن درگاه (مثل socks5://127.0.0.1:10808) را در فیلد «پروکسی» هر سرویس بگذارید.', 'uploadgram-core' ); ?>
        </p>
        <p>
            <input type="text" id="ug-vless-link" placeholder="vless://uuid@host:port?security=reality&..." style="width:100%;max-width:640px;" dir="ltr">
            <button class="button button-secondary" id="ug-vless-go"><?php esc_html_e( 'ساخت کانفیگ Xray', 'uploadgram-core' ); ?></button>
        </p>
        <pre id="ug-vless-out" style="background:#0b1020;color:#8fd;padding:14px;border-radius:8px;max-height:420px;overflow:auto;display:none;" dir="ltr"></pre>

        <script>
        (function($){
            var n2 = '<?php echo esc_js( wp_create_nonce( 'ug_test' ) ); ?>';
            $('#ug-vless-go').on('click', function(e){
                e.preventDefault();
                var link = $('#ug-vless-link').val() || '';
                var $out = $('#ug-vless-out').show().text('در حال ساخت…');
                $.post(ajaxurl, { action:'ug_vless_xray', link:link, _wpnonce:n2 }, function(res){
                    if (res && res.success) {
                        $out.text('# ' + res.data.hint + '\n\n' + res.data.config);
                    } else {
                        $out.text((res && res.data && res.data.message) || 'لینک VLESS نامعتبر است.');
                    }
                }).fail(function(x){ $out.text('خطا: ' + x.status); });
            });
        })(jQuery);
        </script>
        <script>
        (function($){
            var nonce = '<?php echo esc_js( wp_create_nonce( 'ug_test' ) ); ?>';
            $('.ug-test').on('click', function(e){
                e.preventDefault();
                var provider = $(this).data('provider');
                var $out = $('#ug-test-result').show().text('در حال تست ' + provider + '...');
                $.post(ajaxurl, { action: 'ug_test_provider', provider: provider, _wpnonce: nonce }, function(res){
                    $out.text(JSON.stringify(res, null, 2));
                }).fail(function(x){
                    $out.text('خطا: ' + x.status + ' ' + x.statusText);
                });
            });
            $('#ug-test-sms').on('click', function(e){
                e.preventDefault();
                var phone = $('#ug-test-phone').val() || '';
                var $out = $('#ug-test-result').show().text('در حال ارسال پیامک به ' + phone + ' ...');
                $.post(ajaxurl, { action: 'ug_test_sms', phone: phone, _wpnonce: nonce }, function(res){
                    $out.text(JSON.stringify(res, null, 2));
                }).fail(function(x){
                    var msg = 'خطا: ' + x.status;
                    try { msg += '\n' + x.responseText; } catch(e){}
                    $out.text(msg);
                });
            });
        })(jQuery);
        </script>
        <?php
    }
}
