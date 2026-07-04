# آنالیز API فالوران (Followeran)

**Endpoint:** `https://my.followeran.ir/api/v2`
**Fallback (اگر IP ایران دسترسی نداشت):** `https://panel.smmflw.com/api/iran`
**Method:** POST (form-encoded)
**Auth:** پارامتر `key` در body
**Token:** `xtyV4MO-96fWnAQ9affnRXxLuHspaX7a`
**فرمت پاسخ:** JSON · واحد پول: **IRT (تومان)**

## اکشن‌ها

### services — لیست سرویس‌ها
POST: `key`, `action=services`
پاسخ: آرایه‌ای از:
```json
{ "service":"697", "name":"...", "type":"default", "rate":700000,
  "min":1000, "max":1000, "service_rate":"3.110", "desc":"", "template_link":"",
  "dripfeed":false, "refill":false, "cancel":false,
  "category":"فالوور ایرانی", "brand":"اینستاگرام" }
```
- `rate` = قیمت هر ۱۰۰۰ به تومان (هزینه‌ی ما / خرید). `min`/`max` = محدوده تعداد.

### add — ثبت سفارش
POST: `key`, `action=add`, `service`, `link`, `quantity`, `is_test`(اختیاری، 0/1)
پاسخ: `{ "status":"success", "order":151 }`

### status — وضعیت یک سفارش
POST: `key`, `action=status`, `order`
پاسخ:
```json
{ "order":8598432, "status":"Completed", "charge":"250",
  "start_count":null, "remains":0, "created_at":"1404-08-23 00:27:49",
  "service":637, "service_name":"..." }
```
مقادیر status: `Completed`, `Canceled`, `Pending`, `In progress`, `Partial`, `Processing`

### status چندتایی
POST: `key`, `action=status`, `orders=34,35,38`
پاسخ: آبجکت با کلید order id.

### balance — موجودی
POST: `key`, `action=balance`
پاسخ: `{ "status":"success", "balance":1526952, "currency":"IRT" }`

## تطبیق با کد فعلی
provider فعلی (`class-ug-provider-followeran.php`) دقیقاً همین فرمت را دارد.
تغییرات لازم هنگام نوشتن نهایی:
- افزودن endpoint جایگزین smmflw
- افزودن `is_test` (اختیاری)
- تأیید بررسی `status==success` روی add
- نگاشت وضعیت‌ها آماده است ✓
