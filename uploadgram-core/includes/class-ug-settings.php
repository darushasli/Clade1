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
                    'api_key'  => $this->get( 'followeran_key' ),
                ];
            case 'numberland':
                return [
                    'endpoint' => $this->get( 'numberland_endpoint', 'https://api.numberland.ir/v2.php' ),
                    'api_key'  => $this->get( 'numberland_key' ),
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
        $out = [];
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
            'followeran' => 'فالوران (SMM)',
            'numberland' => 'نامبرلند (شماره مجازی)',
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
                $this->field( 'followeran_key', 'کلید API', 'text', 'از پنل فالوران › بخش API دریافت کنید' );
                break;

            case 'numberland':
                $this->field( 'numberland_endpoint', 'آدرس API', 'text', 'آدرس endpoint نامبرلند طبق مستندات', 'https://api.numberland.ir/v2.php' );
                $this->field( 'numberland_key', 'کلید API', 'text', 'کلید API نامبرلند' );
                break;

            case 'telegram':
                $this->field( 'telegram_endpoint', 'آدرس وب‌هوک ربات', 'text', 'آدرسی که ربات شما سفارش‌ها را روی آن دریافت می‌کند', 'https://your-bot.example.com/order' );
                $this->field( 'telegram_token', 'توکن امنیتی', 'text', 'توکن مشترک بین سایت و ربات برای احراز هویت درخواست‌ها' );
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
            <button class="button button-secondary ug-test" data-provider="numberland"><?php esc_html_e( 'تست نامبرلند', 'uploadgram-core' ); ?></button>
            <button class="button button-secondary ug-test" data-provider="telegram"><?php esc_html_e( 'تست ربات', 'uploadgram-core' ); ?></button>
        </p>
        <pre id="ug-test-result" style="background:#111;color:#0f0;padding:14px;border-radius:8px;max-height:320px;overflow:auto;display:none;"></pre>
        <script>
        (function($){
            $('.ug-test').on('click', function(e){
                e.preventDefault();
                var provider = $(this).data('provider');
                var $out = $('#ug-test-result').show().text('در حال تست ' + provider + '...');
                $.post(ajaxurl, {
                    action: 'ug_test_provider',
                    provider: provider,
                    _wpnonce: '<?php echo esc_js( wp_create_nonce( 'ug_test' ) ); ?>'
                }, function(res){
                    $out.text(JSON.stringify(res, null, 2));
                }).fail(function(x){
                    $out.text('خطا: ' + x.status + ' ' + x.statusText);
                });
            });
        })(jQuery);
        </script>
        <?php
    }
}
