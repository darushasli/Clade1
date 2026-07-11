<?php
/**
 * HeroSMS catalogue — caches the remote services / countries / prices and
 * turns them into ready-to-render data for the virtual-number UI.
 *
 * Because HeroSMS (SMS-Activate protocol) offers hundreds of services across
 * hundreds of countries, we do NOT create a WooCommerce product per
 * combination. Instead this catalogue is cached in transients and the number
 * is bought on-demand at purchase time.
 *
 * Icons / Persian names are matched by the human-readable service & country
 * NAMES returned by the API (robust), with a graceful generic fallback so
 * EVERY service and country the provider offers remains sellable.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_HeroSMS_Catalog {

    const T_SERVICES  = 'ug_hero_services';   // transient: normalised services
    const T_COUNTRIES = 'ug_hero_countries';  // transient: normalised countries
    const T_PRICES    = 'ug_hero_prices_';    // transient prefix per-service
    const TTL         = 6 * HOUR_IN_SECONDS;

    private UG_Dispatcher $dispatcher;
    private UG_Settings $settings;

    public function __construct( UG_Dispatcher $dispatcher, UG_Settings $settings ) {
        $this->dispatcher = $dispatcher;
        $this->settings   = $settings;
    }

    private function provider(): ?UG_Provider_HeroSMS {
        $p = $this->dispatcher->provider( 'herosms' );
        return $p instanceof UG_Provider_HeroSMS ? $p : null;
    }

    /* ══════════════ Pricing ══════════════ */

    /** Convert a raw provider cost (USD) to the Toman sale price. */
    public function sale_price( float $cost ): int {
        $rate   = (float) ( $this->settings->get( 'herosms_usd_rate', '' ) ?: $this->settings->get( 'usd_rate', '70000' ) ?: 70000 );
        $markup = (float) ( $this->settings->get( 'herosms_markup', '' ) !== '' ? $this->settings->get( 'herosms_markup' ) : ( $this->settings->get( 'sync_markup_percent', '25' ) ?: 25 ) );
        $toman  = $cost * $rate * ( 1 + $markup / 100 );
        // Round up to the nearest 500 Toman for tidy prices.
        return (int) ( ceil( $toman / 500 ) * 500 ) ?: 500;
    }

    /* ══════════════ Services ══════════════ */

    /** Normalised services: [ { code, name, fa, icon, featured } ], featured first. */
    public function services( bool $force = false ): array {
        if ( ! $force ) {
            $cached = get_transient( self::T_SERVICES );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }
        $p = $this->provider();
        if ( ! $p ) {
            return [];
        }
        $res = $p->services_list( 'en' );
        if ( empty( $res['ok'] ) ) {
            return [];
        }

        $out  = [];
        $seen = [];
        foreach ( $res['data'] as $svc ) {
            $code = (string) ( $svc['code'] ?? '' );
            $name = (string) ( $svc['name'] ?? $code );
            if ( '' === $code ) {
                continue;
            }
            [ $fa, $icon, $platform, $featured ] = $this->match_service( $name, $code );
            // Only the recognised (featured) apps are offered, and each brand is
            // shown once — collapse duplicate service codes that map to the same
            // Persian name (e.g. several Google/WhatsApp codes → one card).
            if ( ! $featured ) {
                continue;
            }
            if ( isset( $seen[ $fa ] ) ) {
                continue;
            }
            $seen[ $fa ] = true;
            $out[] = [
                'code'     => $code,
                'name'     => $name,
                'fa'       => $fa,
                'icon'     => $icon,
                'platform' => $platform,
                'featured' => true,
            ];
        }

        // Alphabetical by Persian name.
        usort( $out, function ( $a, $b ) {
            return strcasecmp( $a['fa'] ?: $a['name'], $b['fa'] ?: $b['name'] );
        } );

        set_transient( self::T_SERVICES, $out, self::TTL );
        return $out;
    }

    /* ══════════════ Countries ══════════════ */

    /** Normalised countries keyed by id: [ id => { id, name, fa, flag } ]. */
    public function countries( bool $force = false ): array {
        if ( ! $force ) {
            $cached = get_transient( self::T_COUNTRIES );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }
        $p = $this->provider();
        if ( ! $p ) {
            return [];
        }
        $res = $p->countries();
        if ( empty( $res['ok'] ) ) {
            return [];
        }

        $out = [];
        foreach ( $res['data'] as $c ) {
            $id  = (string) ( $c['id'] ?? '' );
            if ( '' === $id ) {
                continue;
            }
            $eng = (string) ( $c['eng'] ?? $c['rus'] ?? ( 'کشور ' . $id ) );
            [ $fa, $flag ] = $this->match_country( $eng );
            $out[ $id ] = [ 'id' => $id, 'name' => $eng, 'fa' => $fa, 'flag' => $flag, 'pop' => $this->country_pop( $eng ) ];
        }

        set_transient( self::T_COUNTRIES, $out, self::TTL );
        return $out;
    }

    /* ══════════════ Per-service countries + price ══════════════ */

    /**
     * For a single service: the list of countries where it's available with
     * live price (Toman) and stock. [ { id, fa, flag, name, price, price_fmt, count } ].
     */
    public function service_countries( string $service, bool $force = false ): array {
        $service = trim( $service );
        if ( '' === $service ) {
            return [];
        }
        $tk = self::T_PRICES . sanitize_key( $service );
        if ( ! $force ) {
            $cached = get_transient( $tk );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }
        $p = $this->provider();
        if ( ! $p ) {
            return [];
        }
        $res = $p->prices( $service, null );
        if ( empty( $res['ok'] ) ) {
            return [];
        }

        $countries = $this->countries();
        $rows      = [];
        foreach ( $res['data'] as $row ) {
            $cid   = (string) ( $row['country'] ?? '' );
            $cost  = (float) ( $row['cost'] ?? 0 );
            $count = (int) ( $row['count'] ?? 0 );
            if ( '' === $cid || $count <= 0 || $cost <= 0 ) {
                continue;
            }
            $meta  = $countries[ $cid ] ?? [ 'fa' => 'کشور ' . $cid, 'flag' => '🌐', 'name' => $cid, 'pop' => 999 ];
            $price = $this->sale_price( $cost );
            $rows[] = [
                'id'        => $cid,
                'fa'        => $meta['fa'],
                'flag'      => $meta['flag'],
                'name'      => $meta['name'],
                'price'     => $price,
                'price_fmt' => number_format( $price ) . ' تومان',
                'count'     => $count,
                'pop'       => (int) ( $meta['pop'] ?? 999 ),
            ];
        }

        // Default order: popularity, then most stock.
        usort( $rows, function ( $a, $b ) {
            if ( $a['pop'] === $b['pop'] ) {
                return $b['count'] <=> $a['count'];
            }
            return $a['pop'] <=> $b['pop'];
        } );

        set_transient( $tk, $rows, self::TTL );
        return $rows;
    }

    /** Look up a single price (Toman) for service+country, live (no cache reuse). */
    public function quote( string $service, string $country ): array {
        $p = $this->provider();
        if ( ! $p ) {
            return [ 'ok' => false, 'error' => 'سرویس شماره مجازی در دسترس نیست' ];
        }
        // Prefer the cached per-service list (fast); fall back to a live call.
        foreach ( $this->service_countries( $service ) as $row ) {
            if ( (string) $row['id'] === (string) $country ) {
                return [ 'ok' => true, 'price' => $row['price'], 'count' => $row['count'], 'meta' => $row ];
            }
        }
        $res = $p->prices( $service, $country );
        if ( empty( $res['ok'] ) || empty( $res['data'] ) ) {
            return [ 'ok' => false, 'error' => 'قیمت این سرویس یافت نشد' ];
        }
        $row = $res['data'][0];
        return [ 'ok' => true, 'price' => $this->sale_price( (float) ( $row['cost'] ?? 0 ) ), 'count' => (int) ( $row['count'] ?? 0 ), 'meta' => $row ];
    }

    /* ══════════════ Refresh (admin / cron) ══════════════ */

    public function refresh(): array {
        delete_transient( self::T_SERVICES );
        delete_transient( self::T_COUNTRIES );
        $services  = $this->services( true );
        $countries = $this->countries( true );
        // Warm the price cache for the featured services only (keeps it fast).
        $warmed = 0;
        foreach ( $services as $s ) {
            if ( ! empty( $s['featured'] ) ) {
                $this->service_countries( $s['code'], true );
                if ( ++$warmed >= 12 ) {
                    break;
                }
            }
        }
        return [ 'ok' => ! empty( $services ), 'services' => count( $services ), 'countries' => count( $countries ), 'warmed' => $warmed ];
    }

    /* ══════════════ Matching maps ══════════════ */

    /**
     * Match a service by its human name → [ fa, icon_asset, platform, featured ].
     * Falls back to a generic icon + the API name for anything unrecognised.
     */
    private function match_service( string $name, string $code ): array {
        $n = mb_strtolower( $name );
        // keyword => [ fa, icon, platform ]
        $map = [
            'telegram'   => [ 'تلگرام', 'iconpack/telegram.svg', 'telegram' ],
            'whatsapp'   => [ 'واتساپ', 'iconpack/whatsapp.svg', 'whatsapp' ],
            'instagram'  => [ 'اینستاگرام', 'iconpack/instagram.svg', 'instagram' ],
            'threads'    => [ 'تردز', 'iconpack/threads.svg', 'instagram' ],
            'youtube'    => [ 'یوتیوب', 'iconpack/youtube.svg', 'youtube' ],
            'google'     => [ 'گوگل / جیمیل', 'ai/gemini.svg', 'google' ],
            'gmail'      => [ 'جیمیل', 'ai/gemini.svg', 'google' ],
            'facebook'   => [ 'فیسبوک', 'iconpack/facebook.svg', 'facebook' ],
            'twitter'    => [ 'ایکس (توییتر)', 'iconpack/x.svg', 'twitter' ],
            'tiktok'     => [ 'تیک‌تاک', 'iconpack/tiktok.svg', 'tiktok' ],
            'discord'    => [ 'دیسکورد', 'iconpack/discord.svg', 'discord' ],
            'signal'     => [ 'سیگنال', 'iconpack/signal.svg', 'other' ],
            'spotify'    => [ 'اسپاتیفای', 'iconpack/spotify.svg', 'spotify' ],
            'soundcloud' => [ 'ساندکلاود', 'iconpack/soundcloud.svg', 'soundcloud' ],
            'twitch'     => [ 'توییچ', 'iconpack/twitch.svg', 'twitch' ],
            'microsoft'  => [ 'مایکروسافت', 'iconpack/microsoft-store.svg', 'other' ],
            'openai'     => [ 'ChatGPT', 'ai/chatgpt.svg', 'other' ],
            'chatgpt'    => [ 'ChatGPT', 'ai/chatgpt.svg', 'other' ],
            'amazon'     => [ 'آمازون', 'iconpack/amazon.svg', 'other' ],
            'linkedin'   => [ 'لینکدین', 'iconpack/linkedin.svg', 'other' ],
            'paypal'     => [ 'پی‌پال', 'iconpack/paypal.svg', 'other' ],
            'apple'      => [ 'اپل', 'iconpack/apple.svg', 'other' ],
            'binance'    => [ 'بایننس', 'iconpack/binance.svg', 'other' ],
            'steam'      => [ 'استیم', 'iconpack/steam.svg', 'other' ],
            'playstation'=> [ 'پلی‌استیشن', 'iconpack/playstation.svg', 'other' ],
            'shopify'    => [ 'شاپیفای', 'iconpack/shopify.svg', 'other' ],
            'zoom'       => [ 'زوم', 'iconpack/zoom.svg', 'other' ],
            'android'    => [ 'اندروید', 'iconpack/android.svg', 'other' ],
        ];
        foreach ( $map as $kw => $info ) {
            if ( false !== mb_strpos( ' ' . $n . ' ', $kw ) ) {
                return [ $info[0], $info[1], $info[2], true ];
            }
        }
        return [ $name, 'custom/icon-sim.png', 'other', false ];
    }

    /** Popularity rank for a country (lower = more popular). Used for sorting. */
    private function country_pop( string $eng ): int {
        $n     = mb_strtolower( trim( $eng ) );
        $order = [
            'united states', 'usa', 'united kingdom', 'england', 'russia', 'germany',
            'france', 'canada', 'netherlands', 'ukraine', 'india', 'indonesia',
            'kazakhstan', 'turkey', 'poland', 'spain', 'italy', 'brazil',
            'philippines', 'vietnam', 'malaysia', 'united arab', 'saudi',
        ];
        foreach ( $order as $i => $kw ) {
            if ( false !== mb_strpos( $n, $kw ) ) {
                return $i + 1;
            }
        }
        return 999;
    }

    /**
     * Match a country by its English name → [ fa, flag_emoji ].
     */
    private function match_country( string $eng ): array {
        $n = mb_strtolower( trim( $eng ) );
        $map = [
            'russia'        => [ 'روسیه', '🇷🇺' ],
            'ukraine'       => [ 'اوکراین', '🇺🇦' ],
            'kazakhstan'    => [ 'قزاقستان', '🇰🇿' ],
            'united states' => [ 'آمریکا', '🇺🇸' ],
            'usa'           => [ 'آمریکا', '🇺🇸' ],
            'united kingdom'=> [ 'انگلستان', '🇬🇧' ],
            'england'       => [ 'انگلستان', '🇬🇧' ],
            'germany'       => [ 'آلمان', '🇩🇪' ],
            'france'        => [ 'فرانسه', '🇫🇷' ],
            'netherlands'   => [ 'هلند', '🇳🇱' ],
            'spain'         => [ 'اسپانیا', '🇪🇸' ],
            'italy'         => [ 'ایتالیا', '🇮🇹' ],
            'poland'        => [ 'لهستان', '🇵🇱' ],
            'sweden'        => [ 'سوئد', '🇸🇪' ],
            'finland'       => [ 'فنلاند', '🇫🇮' ],
            'turkey'        => [ 'ترکیه', '🇹🇷' ],
            'georgia'       => [ 'گرجستان', '🇬🇪' ],
            'armenia'       => [ 'ارمنستان', '🇦🇲' ],
            'azerbaijan'    => [ 'آذربایجان', '🇦🇿' ],
            'india'         => [ 'هند', '🇮🇳' ],
            'indonesia'     => [ 'اندونزی', '🇮🇩' ],
            'philippines'   => [ 'فیلیپین', '🇵🇭' ],
            'vietnam'       => [ 'ویتنام', '🇻🇳' ],
            'malaysia'      => [ 'مالزی', '🇲🇾' ],
            'thailand'      => [ 'تایلند', '🇹🇭' ],
            'china'         => [ 'چین', '🇨🇳' ],
            'brazil'        => [ 'برزیل', '🇧🇷' ],
            'argentina'     => [ 'آرژانتین', '🇦🇷' ],
            'mexico'        => [ 'مکزیک', '🇲🇽' ],
            'colombia'      => [ 'کلمبیا', '🇨🇴' ],
            'canada'        => [ 'کانادا', '🇨🇦' ],
            'egypt'         => [ 'مصر', '🇪🇬' ],
            'nigeria'       => [ 'نیجریه', '🇳🇬' ],
            'south africa'  => [ 'آفریقای جنوبی', '🇿🇦' ],
            'kenya'         => [ 'کنیا', '🇰🇪' ],
            'morocco'       => [ 'مراکش', '🇲🇦' ],
            'saudi'         => [ 'عربستان', '🇸🇦' ],
            'united arab'   => [ 'امارات', '🇦🇪' ],
            'iraq'          => [ 'عراق', '🇮🇶' ],
            'pakistan'      => [ 'پاکستان', '🇵🇰' ],
            'bangladesh'    => [ 'بنگلادش', '🇧🇩' ],
            'uzbekistan'    => [ 'ازبکستان', '🇺🇿' ],
            'kyrgyzstan'    => [ 'قرقیزستان', '🇰🇬' ],
            'tajikistan'    => [ 'تاجیکستان', '🇹🇯' ],
            'belarus'       => [ 'بلاروس', '🇧🇾' ],
            'romania'       => [ 'رومانی', '🇷🇴' ],
            'portugal'      => [ 'پرتغال', '🇵🇹' ],
            'greece'        => [ 'یونان', '🇬🇷' ],
            'austria'       => [ 'اتریش', '🇦🇹' ],
            'switzerland'   => [ 'سوئیس', '🇨🇭' ],
            'belgium'       => [ 'بلژیک', '🇧🇪' ],
            'ireland'       => [ 'ایرلند', '🇮🇪' ],
            'czech'         => [ 'چک', '🇨🇿' ],
            'israel'        => [ 'اسرائیل', '🇮🇱' ],
        ];
        foreach ( $map as $kw => $info ) {
            if ( false !== mb_strpos( $n, $kw ) ) {
                return $info;
            }
        }
        return [ $eng, '🌐' ];
    }
}
