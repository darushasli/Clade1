# تحلیل ربات آپلودر تلگرام (ثبت سفارش ممبر)

> تحلیل از روی سورس کامل ربات (`webhook.php`, `processor.php`,
> `admin_functions.php`, `miniapp.php`, `config.php`) و دیتابیس `chqbfmwg_bot6`.
> هیچ توکن/سکرت واقعی در این فایل نوشته نشده است.

## معماری اتصال (تصمیم‌گرفته‌شده)

وردپرس و ربات روی **دو هاست جدا** هستند → یک فایل PHP کوچک به‌نام
**`ug-bridge.php`** کنار سورس ربات می‌گذاریم که به دیتابیس ربات وصل است و
`config.php` + `admin_functions.php` ربات را `require` می‌کند تا از **همان
توابع خود ربات** (`assign_bot`, قیمت‌ها, `check_channel_membership`) استفاده کند —
این تضمین می‌کند فرمت ثبت دقیقاً مثل خود ربات باشد. افزونهٔ وردپرس فقط با یک
**secret مشترک** به این پل POST می‌زند.

## چرخهٔ عمر سفارش در ربات (مهم)

جدول `orders` در ربات دو نقش دارد: هم **ویزارد گفتگو** (مرحله‌به‌مرحله) و هم
**سفارش نهایی**. مسیر واقعی ثبت:

```
pending  (ویزارد: current_step + temp_data کم‌کم پر می‌شود)
   │  کاربر تأیید می‌کند →
   │  ۱) check_channel_membership  (ربات بررسی، ادمینِ کانال هست؟)
   │  ۲) deduct_wallet + INSERT transactions
   │  ۳) UPDATE orders SET status='processing', assigned_bot_id, target_chat
   ▼
processing  ── processor.php (کرون) برمی‌دارد ──▶
   │  fj_add_channel (ثبت کانال در جوین‌اجباری ربات آپلودر)
   │  INSERT active_orders + UPDATE status='monitoring'
   ▼
monitoring ── monitor.php رشد اعضا را با baseline مقایسه می‌کند ──▶
   ▼
completed   (وقتی هدف رسید)   |   failed (خطا/لغو)
```

**نکتهٔ کلیدی:** پردازشگر فقط ردیف‌های `status = 'processing'` را برمی‌دارد.
پس پل ما باید سفارش را **مستقیم با `status='processing'`** درج کند.

## فرمت دقیق ثبت سفارش (چیزی که باید درست باشد)

برای اینکه `processor.php` سفارش را بگیرد و اجرا کند، ردیف `orders` باید این‌طور باشد:

| ستون | مقدار | توضیح |
|------|-------|-------|
| `user_id` | varchar | آی‌دی کاربر. برای سفارش‌های سایت یک آی‌دی «وب» می‌گذاریم |
| `assigned_bot_id` | int ۱–۵ | خروجی `assign_bot($pdo,$type)`: ethical→[1,2]، unethical→[3,4,5]، کم‌بارترین |
| `target_chat` | **یوزرنیم کانال بدون @** | مثل `StableGate_liq` (پردازشگر `extract_username` هم دارد ولی ربات یوزرنیم خام می‌نویسد) |
| `order_type` | `'ethical'` \| `'unethical'` | |
| `price` | int (تومان) | `ceil(count/1000) * price_ethical|price_unethical` از جدول `settings` |
| `temp_data` | JSON | حداقل `count`؛ ربات این‌ها را می‌گذارد: `count, price, order_type, assigned_bot, channel_link, channel_username` |
| `status` | `'processing'` | همین باعث برداشته‌شدن توسط پردازشگر می‌شود |
| `result` | `'در حال پردازش...'` | |

نمونهٔ واقعی `temp_data` از دیتابیس:
```json
{"count": 1000, "price": 250, "order_type": "ethical", "assigned_bot": 1,
 "channel_link": "https://t.me/StableGate_liq", "channel_username": "StableGate_liq"}
```

قیمت‌ها از `settings`: `price_ethical=250`، `price_unethical=200` (تومان به‌ازای هر ۱۰۰۰).
محدودهٔ سفارش: `MIN_ORDER=1000` تا `MAX_ORDER=100000`.

## ⚠️ حیاتی‌ترین نکته — بررسی عضویت ربات

قبل از ثبت، ربات چک می‌کند که **ربات بررسی عضویت** (CHECK_BOTS[assigned_bot]،
مثلاً `@activeupinfo_bot` برای ربات ۱) واقعاً **ادمین کانال مشتری** شده باشد
(`check_channel_membership` با دسترسی `restrict_members`). اگر این نباشد، پول
کسر می‌شود ولی سرویس کار **نمی‌کند** (خود کد ربات چند بار این را به‌عنوان باگ
اصلاح کرده). پس در سایت هم این مرحله اجباری است:

> کاربر بعد از انتخاب کانال، باید ربات بررسیِ متناظر را به‌عنوان ادمین به کانالش
> اضافه کند، بعد «تأیید» بزند؛ پل قبل از درج سفارش، `check_channel_membership`
> را صدا می‌زند.

## اندپوینت‌های پل `ug-bridge.php` (طراحی)

همه POST، با `secret` مشترک، خروجی JSON:

| action | ورودی | کار |
|--------|-------|-----|
| `quote` | `order_type`, `count` | قیمت را حساب و `assign_bot` را انتخاب می‌کند؛ یوزرنیم ربات بررسی (که کاربر باید ادمین کند) را برمی‌گرداند |
| `check` | `assigned_bot`, `channel` | آیا ربات بررسی ادمین کانال شده؟ (`check_channel_membership`) |
| `create` | `channel`, `count`, `order_type`, `assigned_bot` | ردیف `orders` را با `status='processing'` و فرمت بالا درج می‌کند، `order_id` را برمی‌گرداند. **بدون** کسر کیف پول ربات (پرداخت در ووکامرس/کیف پول سایت انجام شده) |
| `status` | `order_id` | `orders.status` + `result` + از `active_orders`: `current_count`/`target_count` |
| `cancel` | `order_id` | لغو سفارش (اگر هنوز شروع نشده) |

## نگاشت وضعیت به پنل سایت

`pending/processing` → «در حال ثبت» · `monitoring` → «در حال ممبرگیری (x/y)» ·
`completed` → «تکمیل شده» · `failed` → «ناموفق» (+ متن `result`).

## سمت افزونه

`class-ug-provider-telegram.php` بازنویسی می‌شود تا به `ug-bridge.php` وصل شود
(آدرس پل + secret در تنظیمات پلاگین). پرداخت از **کیف پول داخلی سایت** انجام
می‌شود؛ سفارش فقط وقتی به پل فرستاده می‌شود که پرداخت موفق بوده و عضویت ربات
تأیید شده باشد.

## نکتهٔ جانبی

پوشهٔ `site/` داخل زیپ ربات یک فروشگاه وب نیمه‌کارهٔ جدا (با دیتابیس و اسکیمای
`orders` متفاوت: uid/product_id/service_id...) است. آن ربطی به دیتابیس ربات
ندارد و همان چیزی است که سایت وردپرسی ما جایگزینش می‌شود — در اتصال ربات از آن
استفاده نمی‌کنیم.
