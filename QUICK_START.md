# 🚀 راهنمای شروع سریع - Telegram Turbo Bot

## 📋 فهرست
1. [نیازمندی‌ها](#نیازمندیها)
2. [نصب](#نصب)
3. [تنظیم](#تنظیم)
4. [اجرا](#اجرا)
5. [استفاده](#استفاده)
6. [عیب یابی](#عیب-یابی)

---

## نیازمندی‌ها

- **Python**: 3.8+
- **pip**: مدیر پکیج Python
- **توکن BotFather**: از `@BotFather` در تلگرام

---

## نصب

### مرحله 1: کپی کردن فایل‌ها
```bash
cd /مسیر/پروژه
ls -la
```

فایل‌های مورد نیاز:
- ✅ `telegram_view_bot_turbo_fixed.py` (ربات اصلاح شده)
- ✅ `requirements_turbo.txt` (وابستگی‌ها)
- ✅ `.env.example` (نمونه محیط)

### مرحله 2: نصب وابستگی‌ها
```bash
pip install -r requirements_turbo.txt
```

خروجی مورد انتظار:
```
Successfully installed pyTelegramBotAPI-4.14.0
Successfully installed aiohttp-3.9.1
Successfully installed python-dotenv-1.0.0
Successfully installed requests-2.31.0
```

---

## تنظیم

### مرحله 1: ایجاد فایل .env
```bash
cp .env.example .env
```

### مرحله 2: دریافت توکن
1. تلگرام رو باز کنید
2. جستجو کنید: `@BotFather`
3. فرمان: `/newbot`
4. نام ربات رو وارد کنید
5. Username ربات رو وارد کنید
6. **کپی** کنید: توکن 👇

### مرحله 3: تنظیم توکن
```bash
nano .env
```

یا اگر از ویندوز استفاده می‌کنید:
```bash
notepad .env
```

تنظیم کنید:
```env
TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
```

**نکات:**
- ⚠️ توکن رو public شیئر نکنید
- ⚠️ هیچ space نباشد
- ✅ کل‌ید قبل "=" و بعد "=" باشد

---

## اجرا

### گزینه 1: اجرا ساده
```bash
python telegram_view_bot_turbo_fixed.py
```

خروجی مورد انتظار:
```
🚀 TURBO VIEWS BOT فعال است!
⚡ سرعت: بسیار سریع | 🔄 همزمان | ✅ فوری
```

### گزینه 2: اجرا با Logging
```bash
python telegram_view_bot_turbo_fixed.py 2>&1 | tee bot.log
```

### گزینه 3: اجرا در Background (Linux/Mac)
```bash
nohup python telegram_view_bot_turbo_fixed.py > bot.log 2>&1 &
```

### گزینه 4: اجرا در Background (Windows)
```bash
pythonw telegram_view_bot_turbo_fixed.py
```

### 🛑 توقف ربات
```bash
# Ctrl+C رو فشار دهید
```

یا اگر در background است:
```bash
ps aux | grep telegram_view_bot_turbo_fixed.py
kill -9 <PID>
```

---

## استفاده

### فلوچارت دستورات

```
┌──────────────────────────┐
│   /start در تلگرام      │
└────────────┬─────────────┘
             │
    ┌────────┴────────┐
    │  منو اصلی       │
    └────────┬────────┘
             │
    ┌────────┴──────────────────────────────────┐
    │                                            │
    ▼                                            ▼
┌────────────────────┐              ┌─────────────────────┐
│ مدیریت کانال‌ها     │              │ مدیریت پروکسی       │
└─────────┬──────────┘              └──────────┬──────────┘
          │                                    │
    ┌─────┴───────────────┬──────────────┐    │
    │                     │              │    │
    ▼                     ▼              ▼    ▼
┌─────────┐          ┌────────┐      ┌────────────────┐
│ افزودن  │          │ حذف    │      │ لیست/حذف/اضافه│
│ کانال   │          │ کانال  │      │ پروکسی         │
└─────────┘          └────────┘      └────────────────┘
```

### مثال عملی

#### 1️⃣ شروع ربات
```
👤: /start
🤖: 🚀 ربات سین‌زن TURBO
    ⚡ سرعت بالا | 🔄 همزمان | ⏱️ فوری
    
    کانال‌ها رو اضافه کن تا خودکار سین بزنم
    
    [➕ افزودن کانال] [📋 لیست کانال‌ها]
    [❌ حذف کانال]     [⚙️ سین پیش‌فرض]
    [🔢 تنظیم سین]    [🔧 مدیریت پروکسی]
```

#### 2️⃣ افزودن کانال
```
👤: ➕ افزودن کانال
🤖: نام کانال یا @username را وارد کن:

👤: @MyChannel
🤖: ✅ @MyChannel اضافه شد
    ⏰ سین‌های خودکار شروع می‌شود
```

#### 3️⃣ تنظیم سین
```
👤: 🔢 تنظیم سین کانال
🤖: کانال رو انتخاب کن:
    [@MyChannel]

👤: @MyChannel
🤖: تعداد سین برای @MyChannel:

👤: 500
🤖: ✅ @MyChannel → 500 سین
```

#### 4️⃣ مشاهده لیست
```
👤: 📋 لیست کانال‌ها
🤖: 📋 کانال‌های فعال:
    • @MyChannel → 500 سین
    • @Channel2 → 1000 سین
```

#### 5️⃣ مدیریت پروکسی
```
👤: 🔧 مدیریت پروکسی
🤖: انتخاب:
    [➕ افزودن] [📋 لیست]
    [❌ حذف]

👤: ➕ افزودن
🤖: آدرس پروکسی را وارد کن (مثال: http://ip:port):

👤: http://proxy.com:8080
🤖: ✅ پروکسی اضافه شد
```

---

## عیب یابی

### ❌ خطا: "توکن تنظیم نشده"

**علت**: `TELEGRAM_BOT_TOKEN` در `.env` موجود نیست

**حل**:
```bash
# بررسی فایل .env
cat .env

# اگر خالی بود:
echo "TELEGRAM_BOT_TOKEN=YOUR_TOKEN_HERE" > .env
```

### ❌ خطا: "توکن نامعتبر است"

**علت**: توکن اشتباه است

**حل**:
1. `@BotFather` رو دوباره ملاقات کنید
2. `/mybots` فرمان دهید
3. ربات رو انتخاب کنید
4. توکن جدید تولید کنید

### ❌ خطا: "ModuleNotFoundError"

**علت**: کتابخانه‌ها نصب نشده‌اند

**حل**:
```bash
pip install -r requirements_turbo.txt
```

### ❌ ربات Offline است

**علت**: ربات توقف شده است

**حل**:
```bash
# بررسی اجرا
ps aux | grep telegram_view_bot_turbo_fixed.py

# دوباره شروع کنید
python telegram_view_bot_turbo_fixed.py
```

### ❌ دکمه‌ها کار نمی‌کنند

**علت**: احتمالاً network issue

**حل**:
```bash
# proxy رو تست کنید
curl -I https://t.me

# ربات رو restart کنید
# Ctrl+C - سپس دوباره شروع
```

### 📋 لاگ‌ها کجا هستند؟

```bash
# فایل‌های log:
ls -la logs/

# دیدن لاگ‌ها:
tail -f logs/bot_*.log

# یا:
cat logs/bot_20260721_143022.log | less
```

---

## 📊 Monitoring

### بررسی وضعیت

```bash
# Process رو چک کنید
ps aux | grep telegram_view_bot

# Log آخر رو بخش کنید
tail -50 logs/bot_*.log

# Statistics
grep "✅" logs/bot_*.log | wc -l
```

### بهینه‌سازی

- **سین کم تر**: کم جریمه برای throttle
- **پروکسی اضافه کنید**: سرعت بیشتر
- **تاخیر کم تر**: سین بیشتر در ثانیه

---

## 🆘 کمک و پشتیبانی

### مشکلات رایج

| خطا | حل |
|-----|-----|
| `Connection refused` | بررسی internet connection |
| `Timeout` | سرور تلگرام مشغول است، دوباره تلاش |
| `Invalid token` | توکن جدید دریافت کنید از BotFather |
| `Port error` | پورت 443 رو بازکنید یا VPN استفاده کنید |

### دستورات مفید

```bash
# نصب دوباره
pip install --upgrade -r requirements_turbo.txt

# بررسی Python version
python --version

# بررسی توابع
python -c "import telebot; print(telebot.__version__)"
```

---

## ✅ چک لیست شروع

- [ ] Python 3.8+ نصب شده
- [ ] requirements نصب شده
- [ ] توکن BotFather دریافت شده
- [ ] .env تنظیم شده
- [ ] `telegram_view_bot_turbo_fixed.py` آماده است
- [ ] `/start` کار می‌کند
- [ ] دکمه‌ها کار می‌کنند
- [ ] لاگ‌ها تولید می‌شوند

---

## 🎉 تبریک!

شما ربات Turbo رو با موفقیت راه‌اندازی کردید!

```
🚀 TURBO VIEWS BOT
⚡ سرعت: بسیار سریع
🔄 همزمان: 100 کانال
✅ وضعیت: آنلاین
```

---

## 📞 نکات بیشتر

- 📖 ببینید: `DEBUG_REPORT.md` برای تمام اصلاح‌ها
- 🧪 تست: `python test_bot.py` برای بررسی کامل
- 📝 لاگ: `logs/` فایل برای debugging

**موفق باشید! 🎊**
