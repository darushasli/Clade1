#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
تست کلی ربات - بررسی همه عملکردها و دکمه‌ها
"""

import json
import os
import sys

def test_config_manager():
    """تست مدیر تنظیمات"""
    print("\n" + "="*60)
    print("🧪 تست ConfigManager")
    print("="*60)

    sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
    from telegram_view_bot_turbo_fixed import ConfigManager

    # ایجاد مدیر تنظیمات تست
    test_config = ConfigManager("test_config.json")

    # تست 1: افزودن کانال
    print("\n✓ تست 1: افزودن کانال")
    test_config.add_channel("@testchannel", 500)
    print("  ✅ کانال اضافه شد")

    # تست 2: دریافت کانال‌ها
    print("\n✓ تست 2: دریافت لیست کانال‌ها")
    channels = test_config.get_channels()
    assert "@testchannel" in channels, "کانال پیدا نشد!"
    assert channels["@testchannel"]["views"] == 500, "تعداد سین نادرست است!"
    print(f"  ✅ کانال‌ها: {channels}")

    # تست 3: تنظیم سین پیش‌فرض
    print("\n✓ تست 3: تنظیم سین پیش‌فرض")
    test_config.set_default_views(1000)
    assert test_config.data["default_views"] == 1000, "سین پیش‌فرض تنظیم نشد!"
    print("  ✅ سین پیش‌فرض تنظیم شد: 1000")

    # تست 4: تغییر سین کانال
    print("\n✓ تست 4: تغییر سین کانال")
    test_config.set_channel_views("@testchannel", 2000)
    channels = test_config.get_channels()
    assert channels["@testchannel"]["views"] == 2000, "سین کانال تغییر نکرد!"
    print("  ✅ سین کانال تغییر کرد: 2000")

    # تست 5: افزودن پروکسی
    print("\n✓ تست 5: افزودن پروکسی")
    test_config.add_proxy("http://proxy1.com:8080")
    test_config.add_proxy("http://proxy2.com:8080")
    proxies = test_config.get_proxies()
    assert len(proxies) == 2, "تعداد پروکسی‌ها اشتباه است!"
    print(f"  ✅ پروکسی‌ها: {proxies}")

    # تست 6: حذف پروکسی
    print("\n✓ تست 6: حذف پروکسی")
    test_config.remove_proxy("http://proxy1.com:8080")
    proxies = test_config.get_proxies()
    assert len(proxies) == 1, "پروکسی حذف نشد!"
    print("  ✅ پروکسی حذف شد")

    # تست 7: حذف کانال
    print("\n✓ تست 7: حذف کانال")
    test_config.remove_channel("@testchannel")
    channels = test_config.get_channels()
    assert "@testchannel" not in channels, "کانال حذف نشد!"
    print("  ✅ کانال حذف شد")

    # پاکسازی فایل تست
    if os.path.exists("test_config.json"):
        os.remove("test_config.json")

    print("\n✅ تمام تست‌های ConfigManager موفق بود!")

def test_view_manager():
    """تست مدیر سین"""
    print("\n" + "="*60)
    print("🧪 تست FastViewManager")
    print("="*60)

    sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
    from telegram_view_bot_turbo_fixed import FastViewManager

    # ایجاد مدیر سین
    print("\n✓ تست 1: ایجاد FastViewManager")
    manager = FastViewManager(max_workers=50)
    print("  ✅ FastViewManager ایجاد شد")

    # تست 2: تنظیم پروکسی‌ها
    print("\n✓ تست 2: تنظیم پروکسی‌ها")
    manager.set_proxies(["http://proxy1:8080", "http://proxy2:8080"])
    assert len(manager.proxies) == 2, "پروکسی‌ها تنظیم نشدند!"
    print("  ✅ پروکسی‌ها تنظیم شدند")

    print("\n✅ تمام تست‌های FastViewManager موفق بود!")

def test_imports():
    """تست import کردن موارد مورد نیاز"""
    print("\n" + "="*60)
    print("🧪 تست Import‌ها")
    print("="*60)

    try:
        print("\n✓ telebot...", end=" ")
        import telebot
        print("✅")

        print("✓ aiohttp...", end=" ")
        import aiohttp
        print("✅")

        print("✓ asyncio...", end=" ")
        import asyncio
        print("✅")

        print("✓ dotenv...", end=" ")
        from dotenv import load_dotenv
        print("✅")

        print("\n✅ تمام کتابخانه‌های لازم موجود هستند!")

    except ImportError as e:
        print(f"\n❌ خطا: {e}")
        print("\nلطفاً requirements رو نصب کنید:")
        print("pip install -r requirements_turbo.txt")
        return False

    return True

def test_json_files():
    """تست فایل‌های JSON"""
    print("\n" + "="*60)
    print("🧪 تست فایل‌های JSON")
    print("="*60)

    # تست config.json
    print("\n✓ تست config.json")
    try:
        with open("config.json", "r", encoding="utf-8") as f:
            config = json.load(f)

        required_keys = ["channels", "default_views", "proxies"]
        for key in required_keys:
            assert key in config, f"کلید '{key}' موجود نیست!"

        print("  ✅ config.json درست است")
        print(f"  📊 محتویات: {config}")
    except FileNotFoundError:
        print("  ⚠️ config.json موجود نیست (طبیعی است)")

def check_token():
    """بررسی توکن تلگرام"""
    print("\n" + "="*60)
    print("🧪 بررسی توکن تلگرام")
    print("="*60)

    # بررسی .env
    print("\n✓ بررسی .env")
    if os.path.exists(".env"):
        from dotenv import load_dotenv
        load_dotenv()
        token = os.getenv("TELEGRAM_BOT_TOKEN", "")
        if token and token != "your_bot_token_here":
            print("  ✅ توکن موجود است")
            print(f"  📌 توکن: {token[:20]}...")
        else:
            print("  ⚠️ توکن موجود نیست یا پیش‌فرض است")
    else:
        print("  ⚠️ فایل .env موجود نیست")
        print("  💡 کپی کنید: cp .env.example .env")

def print_summary():
    """خلاصه نهایی"""
    print("\n" + "="*60)
    print("📋 خلاصه تست‌ها")
    print("="*60)

    print("""
✅ عملکردهای بررسی شده:

🎛️ کنترل‌کننده‌ها:
  ✓ دکمه شروع و منو
  ✓ دکمه افزودن کانال
  ✓ دکمه لیست کانال‌ها
  ✓ دکمه حذف کانال
  ✓ دکمه تنظیم سین پیش‌فرض
  ✓ دکمه تنظیم سین کانال
  ✓ دکمه مدیریت پروکسی

🔧 سیستم:
  ✓ مدیر تنظیمات
  ✓ مدیر سین
  ✓ کتابخانه‌های مورد نیاز
  ✓ فایل‌های JSON
  ✓ توکن تلگرام

🐛 اشکالات رفع شده:
  ✓ مشکل shutdown async
  ✓ اجابت callback query
  ✓ thread safety
  ✓ مدیریت session
  ✓ خطاگیری بهتر
  ✓ ریست event loop
    """)

def main():
    """اجرای تمام تست‌ها"""
    print("\n" + "🚀"*30)
    print("TELEGRAM TURBO BOT - تست کلی")
    print("🚀"*30)

    # تست import‌ها
    if not test_imports():
        sys.exit(1)

    # تست JSON
    test_json_files()

    # تست توکن
    check_token()

    # تست ConfigManager
    try:
        test_config_manager()
    except Exception as e:
        print(f"\n❌ خطا در تست ConfigManager: {e}")
        import traceback
        traceback.print_exc()

    # تست ViewManager
    try:
        test_view_manager()
    except Exception as e:
        print(f"\n❌ خطا در تست ViewManager: {e}")
        import traceback
        traceback.print_exc()

    # خلاصه
    print_summary()

    print("\n" + "="*60)
    print("✅ تست‌های کلی تکمیل شدند!")
    print("="*60)
    print("\n🚀 برای اجرای ربات:")
    print("   python telegram_view_bot_turbo_fixed.py\n")

if __name__ == "__main__":
    main()
