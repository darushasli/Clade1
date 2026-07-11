<?php
/**
 * Outbound proxy helper — lets any provider route its HTTP calls through a
 * proxy (useful when the upstream API is filtered on an Iran host).
 *
 * Natively supported by cURL/PHP: http, https, socks4, socks4a, socks5, socks5h.
 *
 * VLESS/VMess/Trojan CANNOT be dialed directly from PHP (no PHP client speaks
 * the VLESS/XTLS/Reality handshake). The working path is to run a local Xray /
 * sing-box client that exposes a local SOCKS5 port and point the plugin at
 * `socks5://127.0.0.1:PORT`. `vless_to_xray()` turns a vless:// link into a
 * ready Xray config so that setup is trivial.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UG_Proxy {

    /** Run $callback with $proxy applied to WP's cURL transport (scoped). */
    public static function with( string $proxy, callable $callback ) {
        $proxy = trim( $proxy );
        if ( '' === $proxy || ! self::is_dialable( $proxy ) ) {
            return $callback(); // nothing cURL can use → run direct.
        }
        $cb = static function ( $handle ) use ( $proxy ) {
            self::configure_curl( $handle, $proxy );
        };
        add_action( 'http_api_curl', $cb, 10, 1 );
        try {
            return $callback();
        } finally {
            remove_action( 'http_api_curl', $cb, 10 );
        }
    }

    /** True when cURL can dial this proxy (http/https/socks, or bare host:port). */
    public static function is_dialable( string $proxy ): bool {
        $proxy = trim( $proxy );
        if ( '' === $proxy ) {
            return true; // "no proxy" is fine (direct).
        }
        if ( self::is_special( $proxy ) ) {
            return false;
        }
        // Accept http(s)://, socks…://, or a bare host:port.
        return (bool) preg_match( '#^(https?|socks4a?|socks5h?)://#i', $proxy )
            || (bool) preg_match( '#^[a-z0-9.\-]+:\d+$#i', $proxy )
            || (bool) preg_match( '#^\S+:\S+@[a-z0-9.\-]+:\d+$#i', $proxy );
    }

    /** True for proxy schemes PHP/cURL cannot dial (need a local client). */
    public static function is_special( string $proxy ): bool {
        return (bool) preg_match( '#^(vless|vmess|trojan|ss|ssr|hysteria2?|tuic)://#i', trim( $proxy ) );
    }

    /** A Persian warning if the configured proxy can't be dialed directly. */
    public static function warning( string $proxy ): string {
        $proxy = trim( $proxy );
        if ( '' === $proxy || self::is_dialable( $proxy ) ) {
            return '';
        }
        if ( self::is_special( $proxy ) ) {
            return 'این نوع پروکسی (مثل VLESS) مستقیماً توسط PHP قابل استفاده نیست. یک کلاینت Xray/sing-app محلی اجرا کنید که یک درگاه SOCKS5 محلی باز کند و این‌جا آدرس آن را بگذارید (مثل socks5://127.0.0.1:10808). از ابزار «تبدیل VLESS به کانفیگ Xray» در تب ابزار و تست استفاده کنید.';
        }
        return 'قالب پروکسی نامعتبر است. نمونه‌ها: http://user:pass@host:port یا socks5://host:port';
    }

    private static function configure_curl( $handle, string $proxy ): void {
        if ( preg_match( '#^socks5h?://#i', $proxy ) ) {
            curl_setopt( $handle, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME );
        } elseif ( preg_match( '#^socks4a?://#i', $proxy ) ) {
            curl_setopt( $handle, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4 );
        }
        // cURL accepts the full URL (scheme + optional user:pass) in CURLOPT_PROXY.
        curl_setopt( $handle, CURLOPT_PROXY, $proxy );
        curl_setopt( $handle, CURLOPT_HTTPPROXYTUNNEL, true );
    }

    /* ══════════════ VLESS → Xray config ══════════════ */

    /**
     * Parse a vless:// share link into an Xray client config (array) that
     * exposes a local SOCKS5 inbound on 127.0.0.1:$local_port. Returns null on
     * a malformed link.
     *
     * Link shape:
     *   vless://<uuid>@<host>:<port>?encryption=none&security=<tls|reality|none>
     *     &sni=<sni>&fp=<fp>&pbk=<publicKey>&sid=<shortId>&type=<tcp|ws|grpc>
     *     &flow=<flow>&path=<path>&host=<host>&serviceName=<name>#<remark>
     */
    public static function vless_to_xray( string $link, int $local_port = 10808 ): ?array {
        $link = trim( $link );
        if ( ! preg_match( '#^vless://#i', $link ) ) {
            return null;
        }
        $rest = substr( $link, strlen( 'vless://' ) );
        $frag = '';
        if ( false !== ( $h = strpos( $rest, '#' ) ) ) {
            $frag = rawurldecode( substr( $rest, $h + 1 ) );
            $rest = substr( $rest, 0, $h );
        }
        $query = [];
        if ( false !== ( $q = strpos( $rest, '?' ) ) ) {
            parse_str( substr( $rest, $q + 1 ), $query );
            $rest = substr( $rest, 0, $q );
        }
        // rest = uuid@host:port
        if ( ! preg_match( '#^([^@]+)@(.+):(\d+)$#', $rest, $m ) ) {
            return null;
        }
        $uuid = $m[1];
        $host = $m[2];
        $port = (int) $m[3];

        $security = strtolower( (string) ( $query['security'] ?? 'none' ) );
        $type     = strtolower( (string) ( $query['type'] ?? 'tcp' ) );
        $flow     = (string) ( $query['flow'] ?? '' );
        $sni      = (string) ( $query['sni'] ?? ( $query['host'] ?? $host ) );

        $stream = [ 'network' => $type ];

        if ( 'reality' === $security ) {
            $stream['security']        = 'reality';
            $stream['realitySettings'] = [
                'serverName'  => $sni,
                'fingerprint' => (string) ( $query['fp'] ?? 'chrome' ),
                'publicKey'   => (string) ( $query['pbk'] ?? '' ),
                'shortId'     => (string) ( $query['sid'] ?? '' ),
                'spiderX'     => (string) ( $query['spx'] ?? '' ),
            ];
        } elseif ( 'tls' === $security ) {
            $stream['security']    = 'tls';
            $stream['tlsSettings'] = [
                'serverName'  => $sni,
                'fingerprint' => (string) ( $query['fp'] ?? 'chrome' ),
                'allowInsecure' => false,
            ];
        }

        if ( 'ws' === $type ) {
            $stream['wsSettings'] = [
                'path'    => (string) ( $query['path'] ?? '/' ),
                'headers' => [ 'Host' => (string) ( $query['host'] ?? $sni ) ],
            ];
        } elseif ( 'grpc' === $type ) {
            $stream['grpcSettings'] = [ 'serviceName' => (string) ( $query['serviceName'] ?? '' ) ];
        }

        $user = [ 'id' => $uuid, 'encryption' => (string) ( $query['encryption'] ?? 'none' ) ];
        if ( '' !== $flow ) {
            $user['flow'] = $flow;
        }

        return [
            'log'       => [ 'loglevel' => 'warning' ],
            'inbounds'  => [
                [
                    'tag'      => 'socks-in',
                    'port'     => $local_port,
                    'listen'   => '127.0.0.1',
                    'protocol' => 'socks',
                    'settings' => [ 'udp' => true, 'auth' => 'noauth' ],
                ],
            ],
            'outbounds' => [
                [
                    'tag'            => 'proxy',
                    'protocol'       => 'vless',
                    'settings'       => [
                        'vnext' => [
                            [
                                'address' => $host,
                                'port'    => $port,
                                'users'   => [ $user ],
                            ],
                        ],
                    ],
                    'streamSettings' => $stream,
                    '_remark'        => $frag,
                ],
            ],
        ];
    }
}
