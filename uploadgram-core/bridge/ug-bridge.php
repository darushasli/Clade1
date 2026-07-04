<?php
/**
 * ug-bridge.php — پل اتصال سایت آپلودگرام (وردپرس) به ربات آپلودر تلگرام
 * ---------------------------------------------------------------------------
 * این فایل را کنار سورس ربات (همان‌جا که config.php هست) آپلود کنید.
 * سایت وردپرسی با یک secret مشترک به این فایل POST می‌زند و سفارش ممبر را
 * دقیقاً با همان فرمتِ خود ربات در جدول `orders` ثبت می‌کند (status='processing')
 * تا processor.php آن را بردارد و اجرا کند.
 *
 * پرداخت روی سایت (کیف پول وردپرس) انجام می‌شود؛ این پل کیف‌پولِ ربات را دور
 * می‌زند و فقط سفارشِ از-پیش-پرداخت‌شده را وارد صف پردازش می‌کند.
 *
 * نصب:
 *   1) این فایل را در پوشهٔ ربات (کنار config.php) بگذارید.
 *   2) مقدار UG_BRIDGE_SECRET زیر را به یک رشتهٔ تصادفی و قوی تغییر دهید و
 *      همان مقدار را در وردپرس › آپلودگرام › ربات تلگرام › «توکن امنیتی» بگذارید.
 *   3) در همان صفحه، «آدرس وب‌هوک ربات» را آدرس همین فایل بگذارید، مثلاً:
 *      https://activemember.shop/6/ug-bridge.php
 *
 * نکتهٔ امنیتی: بعد از لو رفتن، توکن ربات‌ها و FJ_API_SECRET را حتماً عوض کنید.
 *
 * @version 1.0.0
 */

// ⬇⬇⬇ این را عوض کنید (باید با «توکن امنیتی» در تنظیمات وردپرس یکی باشد) ⬇⬇⬇
define( 'UG_BRIDGE_SECRET', 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET' );

// اگر config.php ربات جای دیگری است مسیرش را اصلاح کنید.
require_once __DIR__ . '/config.php';

header( 'Content-Type: application/json; charset=utf-8' );

/* ── ابزار پاسخ ─────────────────────────────── */
function ug_out( array $data ) {
    echo json_encode( $data, JSON_UNESCAPED_UNICODE );
    exit;
}
function ug_err( string $msg, int $http = 200 ) {
    http_response_code( $http );
    ug_out( [ 'ok' => false, 'error' => $msg ] );
}

/* ── احراز هویت ─────────────────────────────── */
$raw   = file_get_contents( 'php://input' );
$input = json_decode( $raw, true );
if ( ! is_array( $input ) ) {
    $input = $_POST; // پشتیبانی از فرم‌انکد هم
}

if ( UG_BRIDGE_SECRET === 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET' ) {
    ug_err( 'پل هنوز پیکربندی نشده است (UG_BRIDGE_SECRET را تنظیم کنید).', 500 );
}
$secret = (string) ( $input['secret'] ?? '' );
if ( ! hash_equals( UG_BRIDGE_SECRET, $secret ) ) {
    ug_err( 'unauthorized', 401 );
}

$action = (string) ( $input['action'] ?? '' );
$pdo    = get_db_connection();
if ( ! $pdo ) {
    ug_err( 'اتصال به دیتابیس ربات ناموفق بود', 500 );
}

/* ── توابع کمکی ─────────────────────────────── */

/** استخراج یوزرنیم کانال از t.me/@/خام (هماهنگ با processor.php ربات). */
function ug_extract_username( $input ) {
    $input = trim( (string) $input );
    if ( preg_match( '/t\.me\/([a-zA-Z0-9_]{5,})/', $input, $m ) ) return $m[1];
    if ( preg_match( '/^@([a-zA-Z0-9_]{5,})$/', $input, $m ) )     return $m[1];
    if ( preg_match( '/^[a-zA-Z0-9_]{5,}$/', $input ) )            return $input;
    return null;
}

/** قیمت هر ۱۰۰۰ بر اساس نوع سفارش (از جدول settings ربات). */
function ug_price_per_1000( $pdo, $type ) {
    return ( $type === 'ethical' )
        ? (int) ( get_setting( $pdo, 'price_ethical' )   ?: 250 )
        : (int) ( get_setting( $pdo, 'price_unethical' ) ?: 200 );
}

/** محاسبهٔ قیمت کل (تومان). */
function ug_calc_price( $pdo, $type, $count ) {
    return (int) ( ceil( $count / 1000 ) * ug_price_per_1000( $pdo, $type ) );
}

/**
 * بررسی اینکه ربات بررسی عضویتِ متناظر واقعاً ادمین کانال است.
 * (بازتولید check_channel_membership ربات، بدون include کردن admin_functions.php)
 */
function ug_check_membership( $bot_id, $channel ) {
    if ( ! defined( 'FJ_API_URLS' ) ) return false;
    $urls = FJ_API_URLS;
    $url  = $urls[ $bot_id ] ?? null;
    if ( ! $url || ! function_exists( 'fj_api_request' ) ) return false;

    $resp = fj_api_request( $url, [
        'secret'  => FJ_API_SECRET,
        'action'  => 'check_membership',
        'channel' => $channel,
    ] );
    return ! empty( $resp['ok'] ) && ! empty( $resp['is_admin'] );
}

/** انتخاب ربات (از assign_bot خود ربات، در صورت وجود). */
function ug_pick_bot( $pdo, $type ) {
    if ( function_exists( 'assign_bot' ) ) {
        return assign_bot( $pdo, $type );
    }
    // پشتیبان: نگاشت پیش‌فرض
    $map = defined( 'BOT_ASSIGNMENT' ) ? BOT_ASSIGNMENT : [ 'ethical' => [ 1, 2 ], 'unethical' => [ 3, 4, 5 ] ];
    $list = $map[ $type ] ?? [ 1 ];
    return $list[0] ?? 1;
}

$min = defined( 'MIN_ORDER' ) ? MIN_ORDER : 1000;
$max = defined( 'MAX_ORDER' ) ? MAX_ORDER : 100000;

/* ── مسیرها ─────────────────────────────────── */
try {
    switch ( $action ) {

        /* اتصال/تست */
        case 'ping':
        case 'test':
            ug_out( [
                'ok'              => true,
                'bot_username'    => get_setting( $pdo, 'bot_username' ) ?: null,
                'price_ethical'   => ug_price_per_1000( $pdo, 'ethical' ),
                'price_unethical' => ug_price_per_1000( $pdo, 'unethical' ),
                'min'             => $min,
                'max'             => $max,
            ] );
            break;

        /* قیمت‌گیری + انتخاب ربات + ربات بررسی که باید ادمین شود */
        case 'quote': {
            $type  = ( ( $input['order_type'] ?? 'ethical' ) === 'unethical' ) ? 'unethical' : 'ethical';
            $count = (int) ( $input['count'] ?? 0 );
            if ( $count < $min || $count > $max ) {
                ug_err( "تعداد باید بین $min تا $max باشد" );
            }
            $bot   = ug_pick_bot( $pdo, $type );
            if ( ! $bot ) {
                ug_err( 'در حال حاضر رباتی برای این نوع سفارش در دسترس نیست' );
            }
            $checks = defined( 'CHECK_BOTS' ) ? CHECK_BOTS : [];
            ug_out( [
                'ok'             => true,
                'order_type'     => $type,
                'count'          => $count,
                'price_per_1000' => ug_price_per_1000( $pdo, $type ),
                'price'          => ug_calc_price( $pdo, $type, $count ),
                'assigned_bot'   => (int) $bot,
                'check_bot'      => $checks[ $bot ] ?? null,
                'admin_rights'   => defined( 'CHECK_BOT_ADMIN_RIGHTS' ) ? CHECK_BOT_ADMIN_RIGHTS : 'restrict_members',
            ] );
            break;
        }

        /* آیا ربات بررسی ادمین کانال شده؟ */
        case 'check': {
            $bot     = (int) ( $input['assigned_bot'] ?? 0 );
            $channel = ug_extract_username( $input['channel'] ?? '' );
            if ( ! $bot || ! $channel ) {
                ug_err( 'ربات یا کانال نامعتبر است' );
            }
            ug_out( [ 'ok' => true, 'is_admin' => ug_check_membership( $bot, $channel ) ] );
            break;
        }

        /* ثبت سفارشِ از-پیش-پرداخت‌شده */
        case 'create': {
            $type    = ( ( $input['order_type'] ?? 'ethical' ) === 'unethical' ) ? 'unethical' : 'ethical';
            $count   = (int) ( $input['count'] ?? 0 );
            $channel = ug_extract_username( $input['channel'] ?? '' );
            $webUser = trim( (string) ( $input['web_user_id'] ?? '' ) );

            if ( ! $channel ) {
                ug_err( 'لینک/یوزرنیم کانال نامعتبر است' );
            }
            if ( $count < $min || $count > $max ) {
                ug_err( "تعداد باید بین $min تا $max باشد" );
            }

            $bot = (int) ( $input['assigned_bot'] ?? 0 );
            if ( $bot < 1 || $bot > 5 ) {
                $bot = (int) ug_pick_bot( $pdo, $type );
            }
            if ( $bot < 1 ) {
                ug_err( 'رباتی برای این سفارش در دسترس نیست' );
            }

            // دفاع در عمق: قبل از ثبت مطمئن شو ربات بررسی ادمین کانال است،
            // وگرنه سرویس کار نمی‌کند (همان باگی که خود ربات چند بار اصلاح کرده).
            if ( ! ug_check_membership( $bot, $channel ) ) {
                $checks = defined( 'CHECK_BOTS' ) ? CHECK_BOTS : [];
                ug_out( [
                    'ok'        => false,
                    'error'     => 'membership_not_verified',
                    'check_bot' => $checks[ $bot ] ?? null,
                ] );
            }

            $price = ug_calc_price( $pdo, $type, $count ); // همیشه سمت سرور محاسبه می‌شود
            $uid   = $webUser !== '' ? ( 'web:' . preg_replace( '/[^a-zA-Z0-9_\-]/', '', $webUser ) ) : 'website';

            $temp = [
                'count'            => $count,
                'price'            => $price,
                'order_type'      => $type,
                'assigned_bot'     => $bot,
                'channel_link'     => 'https://t.me/' . $channel,
                'channel_username' => $channel,
                'source'           => 'uploadgram-site',
            ];

            $stmt = $pdo->prepare(
                "INSERT INTO orders
                 (user_id, bot_id, assigned_bot_id, target_chat, order_type, price, status, result, current_step, temp_data, is_force_join_active, created_at)
                 VALUES (?, 0, ?, ?, ?, ?, 'processing', 'در حال پردازش...', 'count', ?, 1, NOW())"
            );
            $stmt->execute( [ $uid, $bot, $channel, $type, $price, json_encode( $temp, JSON_UNESCAPED_UNICODE ) ] );
            $order_id = (int) $pdo->lastInsertId();

            // اطلاع به ادمین (اختیاری، مثل finalize_order خود ربات)
            if ( function_exists( 'send_telegram_message' ) && defined( 'BOT6_TOKEN' ) && defined( 'SUPER_ADMIN_ID' ) ) {
                @send_telegram_message( BOT6_TOKEN, SUPER_ADMIN_ID,
                    "🆕 سفارش جدید از سایت\nکد: #$order_id\nکانال: @$channel\nتعداد: " . number_format( $count ) . "\nمبلغ: " . number_format( $price ) . " تومان\nربات: $bot" );
            }

            ug_out( [
                'ok'           => true,
                'order_id'     => $order_id,
                'status'       => 'processing',
                'price'        => $price,
                'assigned_bot' => $bot,
                'channel'      => $channel,
            ] );
            break;
        }

        /* وضعیت سفارش */
        case 'status': {
            $order_id = (int) ( $input['order_id'] ?? 0 );
            if ( ! $order_id ) {
                ug_err( 'شناسه سفارش نامعتبر است' );
            }
            $stmt = $pdo->prepare(
                "SELECT o.status, o.result, a.current_count, a.target_count
                 FROM orders o
                 LEFT JOIN active_orders a ON a.order_id = o.id
                 WHERE o.id = ? LIMIT 1"
            );
            $stmt->execute( [ $order_id ] );
            $row = $stmt->fetch( PDO::FETCH_ASSOC );
            if ( ! $row ) {
                ug_err( 'سفارش یافت نشد' );
            }
            ug_out( [
                'ok'            => true,
                'status'        => $row['status'],
                'result'        => $row['result'],
                'current_count' => isset( $row['current_count'] ) ? (int) $row['current_count'] : null,
                'target_count'  => isset( $row['target_count'] ) ? (int) $row['target_count'] : null,
            ] );
            break;
        }

        /* لغو سفارش (فقط قبل از شروع ممبرگیری) */
        case 'cancel': {
            $order_id = (int) ( $input['order_id'] ?? 0 );
            if ( ! $order_id ) {
                ug_err( 'شناسه سفارش نامعتبر است' );
            }
            $stmt = $pdo->prepare( "SELECT status FROM orders WHERE id = ? LIMIT 1" );
            $stmt->execute( [ $order_id ] );
            $st = $stmt->fetchColumn();
            if ( $st === false ) {
                ug_err( 'سفارش یافت نشد' );
            }
            if ( ! in_array( $st, [ 'pending', 'processing' ], true ) ) {
                ug_out( [ 'ok' => false, 'error' => 'این سفارش دیگر قابل لغو نیست', 'status' => $st ] );
            }
            $pdo->prepare( "UPDATE orders SET status='failed', result='لغو توسط سایت' WHERE id = ?" )
                ->execute( [ $order_id ] );
            ug_out( [ 'ok' => true, 'status' => 'failed' ] );
            break;
        }

        default:
            ug_err( 'action نامعتبر است: ' . $action );
    }
} catch ( Throwable $e ) {
    if ( function_exists( 'log_to_file' ) && defined( 'ERROR_LOG' ) ) {
        @log_to_file( ERROR_LOG, 'ug-bridge error: ' . $e->getMessage(), 'ERROR' );
    }
    ug_err( 'خطای داخلی پل: ' . $e->getMessage(), 500 );
}
