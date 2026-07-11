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
                ];
            case 'telegram':
                return [
                    'endpoint' => $this->get( 'telegram_endpoint' ),
                    'api_key'  => $this->get( 'telegram_token' ),
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
                break;

            case 'herosms':
                echo '<tr><td colspan="2"><p class="description">هیرو‌اس‌ام‌اس با پروتکل SMS-Activate کار می‌کند. کلید API را از حساب hero-sms.com دریافت کنید. پس از ذخیره، از تب «ابزار و تست» دکمهٔ «تست هیرو‌اس‌ام‌اس» و از پیشخوان › آپلودگرام › همگام‌سازی، دکمهٔ «به‌روزرسانی فهرست شماره‌ها» را بزنید.</p></td></tr>';
                $this->field( 'herosms_key', 'کلید API', 'text', 'کلید API حساب هیرو‌اس‌ام‌اس شما' );
                $this->field( 'herosms_endpoint', 'آدرس API', 'text', 'پیش‌فرض درست است؛ فقط در صورت تغییر دامنه ویرایش کنید', 'https://hero-sms.com/stubs/handler_api.php' );
                $this->field( 'herosms_usd_rate', 'نرخ تبدیل هر واحد قیمت به تومان', 'number', 'قیمت هیرو‌اس‌ام‌اس بر حسب دلار است؛ نرخ دلار به تومان (خالی = همان نرخ عمومی)', '70000' );
                $this->field( 'herosms_markup', 'درصد سود روی قیمت شماره', 'number', 'درصدی که روی قیمت خام اضافه می‌شود (خالی = درصد سود همگام‌سازی)', '25' );
                break;

            case 'telegram':
                echo '<tr><td colspan="2"><p class="description">فایل <code>bridge/ug-bridge.php</code> را کنار سورس ربات آپلود کنید (راهنما: <code>bridge/README.md</code>). دو مقدار زیر باید با آن فایل یکی باشند.</p></td></tr>';
                $this->field( 'telegram_endpoint', 'آدرس پل ربات', 'text', 'آدرس کامل ug-bridge.php روی هاست ربات', 'https://activemember.shop/6/ug-bridge.php' );
                $this->field( 'telegram_token', 'توکن امنیتی', 'text', 'همان مقدار UG_BRIDGE_SECRET داخل ug-bridge.php' );
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
