<?php
/**
 * App-first buying components:
 *   [ug_services_app]  → pick an app → pick a service kind (followers/likes/…)
 *                        → order cards (link + quantity + buy from wallet).
 *                        Includes Telegram uploader-bot member products.
 *   [ug_numbers_app]   → pick an app → list of virtual numbers for that app.
 *
 * Data comes from WooCommerce products tagged with _ug_platform / _ug_kind
 * (set automatically by UG_Sync or by hand on the product).
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_App_Selector {

    private UG_Wallet $wallet;

    /** platform machine → [ label, icon asset ]. */
    private array $apps = [
        'instagram'  => [ 'اینستاگرام', 'iconpack/instagram.svg' ],
        'telegram'   => [ 'تلگرام', 'iconpack/telegram.svg' ],
        'youtube'    => [ 'یوتیوب', 'iconpack/youtube.svg' ],
        'tiktok'     => [ 'تیک‌تاک', 'iconpack/tiktok.svg' ],
        'spotify'    => [ 'اسپاتیفای', 'iconpack/spotify.svg' ],
        'twitter'    => [ 'ایکس', 'iconpack/x.svg' ],
        'facebook'   => [ 'فیسبوک', 'iconpack/facebook.svg' ],
        'soundcloud' => [ 'ساندکلاود', 'iconpack/soundcloud.svg' ],
        'whatsapp'   => [ 'واتساپ', 'iconpack/whatsapp.svg' ],
        'discord'    => [ 'دیسکورد', 'iconpack/discord.svg' ],
        'twitch'     => [ 'توییچ', 'iconpack/twitch.svg' ],
        'other'      => [ 'سایر', 'custom/icon-member-group.png' ],
    ];

    /** kind machine → label. */
    private array $kinds = [
        'members'     => 'ممبر',
        'followers'   => 'فالوور',
        'likes'       => 'لایک',
        'views'       => 'بازدید',
        'comments'    => 'کامنت',
        'subscribers' => 'ساب‌اسکرایبر',
        'plays'       => 'پلی',
        'shares'      => 'اشتراک‌گذاری',
        'reactions'   => 'ری‌اکشن',
        'story'       => 'استوری',
        'saves'       => 'ذخیره',
        'other'       => 'سایر خدمات',
    ];

    public function __construct( UG_Wallet $wallet ) {
        $this->wallet = $wallet;
        add_shortcode( 'ug_services_app', [ $this, 'services_app' ] );
        add_shortcode( 'ug_numbers_app', [ $this, 'numbers_app' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'assets' ] );
    }

    public function assets(): void {
        wp_register_style( 'ug-app', UGC_URL . 'assets/app-selector.css', [], UGC_VERSION );
        wp_register_script( 'ug-app', UGC_URL . 'assets/app-selector.js', [ 'jquery' ], UGC_VERSION, true );
        wp_localize_script( 'ug-app', 'ugApp', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ug_front' ),
            'loggedIn' => is_user_logged_in(),
            'authUrl'  => home_url( '/auth/' ),
        ] );
    }

    private function asset( string $p ): string {
        return function_exists( 'ug_asset' ) ? ug_asset( $p ) : ( get_template_directory_uri() . '/assets/' . ltrim( $p, '/' ) );
    }

    /* ══════════════ Data ══════════════ */

    /**
     * Products grouped platform → kind → [products], for the given providers.
     */
    private function grouped( array $providers ): array {
        $q = new WP_Query( [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                [ 'key' => '_ug_provider', 'value' => $providers, 'compare' => 'IN' ],
            ],
        ] );

        $out = [];
        foreach ( $q->posts as $pid ) {
            $product = wc_get_product( $pid );
            if ( ! $product || 'publish' !== $product->get_status() ) {
                continue;
            }
            $m        = UG_WC_Integration::meta( $pid );
            $platform = $m['platform'] ?: 'other';
            $kind     = $m['kind'] ?: 'other';

            $out[ $platform ][ $kind ][] = [
                'id'    => $pid,
                'name'  => $product->get_name(),
                'price' => (float) $product->get_price(),
                'rate'  => (float) $m['rate'],
                'min'   => (int) $m['min'],
                'max'   => (int) $m['max'],
                'input' => $m['input_type'],
                'fixed' => (bool) $m['fixed'],
            ];
        }
        return $out;
    }

    /* ══════════════ [ug_services_app] ══════════════ */

    public function services_app(): string {
        wp_enqueue_style( 'ug-app' );
        wp_enqueue_script( 'ug-app' );

        $data = $this->grouped( [ 'followeran', 'telegram' ] );
        if ( empty( $data ) ) {
            return '<div class="ug-app-empty">هنوز خدمتی اضافه نشده است. از پیشخوان › آپلودگرام › همگام‌سازی سرویس‌ها استفاده کنید.</div>';
        }

        ob_start(); ?>
        <div class="ug-app" data-mode="services">
          <div class="ug-app-apps">
            <?php $first = true; foreach ( $data as $platform => $kinds ) :
                $meta = $this->apps[ $platform ] ?? $this->apps['other']; ?>
              <button class="ug-app-chip<?php echo $first ? ' active' : ''; ?>" data-platform="<?php echo esc_attr( $platform ); ?>">
                <img src="<?php echo esc_url( $this->asset( $meta[1] ) ); ?>" alt=""><span><?php echo esc_html( $meta[0] ); ?></span>
              </button>
            <?php $first = false; endforeach; ?>
          </div>
          <div class="ug-app-kinds"></div>
          <div class="ug-app-products"></div>
        </div>
        <script type="application/json" class="ug-app-data"><?php
            echo wp_json_encode( $this->encode_services( $data ) );
        ?></script>
        <?php
        return ob_get_clean();
    }

    private function encode_services( array $data ): array {
        $enc = [];
        foreach ( $data as $platform => $kinds ) {
            foreach ( $kinds as $kind => $products ) {
                $enc[ $platform ][] = [
                    'kind'     => $kind,
                    'label'    => $this->kinds[ $kind ] ?? $kind,
                    'products' => array_map( function ( $p ) {
                        return [
                            'id'    => $p['id'],
                            'name'  => $p['name'],
                            'price' => $this->money( $p['price'] ),
                            'rate'  => $p['rate'],
                            'min'   => $p['min'],
                            'max'   => $p['max'],
                            'input' => $p['input'],
                            'fixed' => $p['fixed'],
                        ];
                    }, $products ),
                ];
            }
        }
        return $enc;
    }

    /* ══════════════ [ug_numbers_app] ══════════════ */

    public function numbers_app(): string {
        wp_enqueue_style( 'ug-app' );
        wp_enqueue_script( 'ug-app' );

        $data = $this->grouped( [ 'numberland' ] );
        if ( empty( $data ) ) {
            return '<div class="ug-app-empty">هنوز شماره‌ای اضافه نشده است. از پیشخوان › آپلودگرام › همگام‌سازی سرویس‌ها استفاده کنید.</div>';
        }

        ob_start(); ?>
        <div class="ug-app" data-mode="numbers">
          <div class="ug-app-apps">
            <?php $first = true; foreach ( $data as $platform => $kinds ) :
                $meta = $this->apps[ $platform ] ?? $this->apps['other']; ?>
              <button class="ug-app-chip<?php echo $first ? ' active' : ''; ?>" data-platform="<?php echo esc_attr( $platform ); ?>">
                <img src="<?php echo esc_url( $this->asset( $meta[1] ) ); ?>" alt=""><span><?php echo esc_html( $meta[0] ); ?></span>
              </button>
            <?php $first = false; endforeach; ?>
          </div>
          <div class="ug-app-products ug-numbers-list"></div>
        </div>
        <script type="application/json" class="ug-app-data"><?php
            $enc = [];
            foreach ( $data as $platform => $kinds ) {
                foreach ( $kinds as $kind => $products ) {
                    foreach ( $products as $p ) {
                        $enc[ $platform ][] = [
                            'id'    => $p['id'],
                            'name'  => $p['name'],
                            'price' => $this->money( $p['price'] ),
                        ];
                    }
                }
            }
            echo wp_json_encode( $enc );
        ?></script>
        <?php
        return ob_get_clean();
    }

    private function money( float $v ): string {
        return number_format( $v ) . ' تومان';
    }
}
