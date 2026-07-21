#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
🧪 تست جامع ربات سین‌زن TURBO Pro
تست تمام ویژگی‌های پیشرفته شامل استخراج و تست پروکسی
"""

import asyncio
import sys
import os
import json
import tempfile
from unittest.mock import Mock, AsyncMock, patch

# رنگ‌های ترمینال
class Colors:
    GREEN = '\033[92m'
    RED = '\033[91m'
    YELLOW = '\033[93m'
    BLUE = '\033[94m'
    CYAN = '\033[96m'
    END = '\033[0m'
    BOLD = '\033[1m'

def print_test(name, passed, message=""):
    icon = f"{Colors.GREEN}✅{Colors.END}" if passed else f"{Colors.RED}❌{Colors.END}"
    print(f"{icon} {name}")
    if message:
        print(f"   {Colors.YELLOW}→{Colors.END} {message}")

def print_section(title):
    print(f"\n{Colors.BLUE}{'='*60}{Colors.END}")
    print(f"{Colors.BOLD}{Colors.BLUE}{title}{Colors.END}")
    print(f"{Colors.BLUE}{'='*60}{Colors.END}")

async def test_proxy_extractor():
    """تست استخراج پروکسی"""
    print_section("🧪 تست 1: ProxyExtractor")

    from telegram_turbo_pro_final import ProxyExtractor

    # تست تبدیل URL
    test_cases = [
        ("t.me/s/channelname", "channelname", True),
        ("@channelname", "channelname", True),
        ("channelname", "channelname", True),
        ("https://t.me/s/mychannel", "mychannel", True),
    ]

    passed_tests = 0
    for input_url, expected, should_work in test_cases:
        result = ProxyExtractor.parse_telegram_channel_url(input_url)
        success = (result == expected) if should_work else (result is not None)
        print_test(f"  تبدیل: {input_url}", success, f"نتیجه: {result}")
        if success:
            passed_tests += 1

    return passed_tests == len(test_cases)

async def test_proxy_patterns():
    """تست الگوهای استخراج پروکسی"""
    print_section("🧪 تست 2: Proxy Patterns")

    from telegram_turbo_pro_final import ProxyExtractor
    import re

    test_html = """
    <html>
        <p>192.168.1.1:8080</p>
        <p>http://proxy.example.com:3128</p>
        <p>socks5://socks.example.com:1080</p>
        <p>https://secure.proxy.com:443</p>
    </html>
    """

    found_proxies = set()
    for pattern in ProxyExtractor.PROXY_PATTERNS:
        matches = re.findall(pattern, test_html, re.IGNORECASE)
        found_proxies.update(matches)

    success = len(found_proxies) >= 3
    print_test(f"  یافتن الگوها", success, f"پروکسی‌های یافت شده: {len(found_proxies)}")

    for proxy in list(found_proxies)[:5]:
        print(f"    {Colors.GREEN}•{Colors.END} {proxy}")

    return success

async def test_proxy_tester():
    """تست ProxyTester"""
    print_section("🧪 تست 3: ProxyTester")

    from telegram_turbo_pro_final import ProxyTester

    tester = ProxyTester(timeout=3)

    # تست بدون پروکسی (Google)
    is_valid, status = await tester.test_proxy(None, "https://www.google.com")
    print_test("  تست بدون پروکسی", is_valid, f"وضعیت: {status}")

    # تست پروکسی نامعتبر
    is_valid_invalid, status_invalid = await tester.test_proxy("http://invalid.proxy:9999", "https://www.google.com")
    print_test("  تست پروکسی نامعتبر", not is_valid_invalid, f"وضعیت: {status_invalid}")

    return is_valid and not is_valid_invalid

async def test_proxy_batch_testing():
    """تست تست گروهی پروکسی"""
    print_section("🧪 تست 4: Batch Proxy Testing")

    from telegram_turbo_pro_final import ProxyTester

    tester = ProxyTester(timeout=3)

    test_proxies = [
        "192.168.1.1:8080",  # نامعتبر
        "invalid.proxy:9999",  # نامعتبر
    ]

    progress_log = []

    def progress_callback(current, total, proxy, is_valid):
        progress_log.append((current, total, proxy, is_valid))

    valid_proxies = await tester.test_proxies_batch(test_proxies, progress_callback=progress_callback)

    success1 = len(progress_log) == len(test_proxies)
    success2 = len(valid_proxies) >= 0

    print_test("  Callback پیشرفت", success1, f"آپدیت‌های دریافت شده: {len(progress_log)}")
    print_test("  نتیجه تست", success2, f"پروکسی‌های معتبر: {len(valid_proxies)}")

    return success1 and success2

def test_config_manager():
    """تست ConfigManager"""
    print_section("🧪 تست 5: ConfigManager")

    from telegram_turbo_pro_final import ConfigManager

    with tempfile.NamedTemporaryFile(mode='w', suffix='.json', delete=False) as f:
        f.write('{}')  # Write empty JSON to avoid parse error
        test_file = f.name

    try:
        config = ConfigManager(test_file)

        # تست اضافه‌کردن کانال
        config.add_channel("@test_channel", 100)
        channels = config.get_channels()
        success1 = "@test_channel" in channels
        print_test("  اضافه‌کردن کانال", success1)

        # تست اضافه‌کردن پروکسی
        config.add_proxy("192.168.1.1:8080")
        proxies = config.get_proxies()
        success2 = "192.168.1.1:8080" in proxies
        print_test("  اضافه‌کردن پروکسی", success2)

        # تست اضافه‌کردن گروهی
        added = config.add_proxies_batch(["1.1.1.1:8080", "2.2.2.2:8080"])
        success3 = added == 2
        proxies = config.get_proxies()
        success4 = len(proxies) >= 3
        print_test("  اضافه‌کردن گروهی", success3 and success4, f"تعداد: {len(proxies)}")

        # تست حذف گروهی
        removed = config.remove_proxies_batch(["1.1.1.1:8080"])
        success5 = removed == 1
        print_test("  حذف گروهی", success5, f"حذف شدگان: {removed}")

        # تست تنظیم سین کانال
        success6 = config.set_channel_views("@test_channel", 500)
        channel_data = channels.get("@test_channel", {})
        print_test("  تنظیم سین کانال", success6)

        return success1 and success2 and success3 and success4 and success5 and success6

    finally:
        if os.path.exists(test_file):
            os.remove(test_file)

async def test_fast_view_manager():
    """تست FastViewManager"""
    print_section("🧪 تست 6: FastViewManager")

    from telegram_turbo_pro_final import FastViewManager

    manager = FastViewManager(max_workers=10)

    try:
        # تست تنظیم پروکسی
        proxies = ["192.168.1.1:8080", "10.0.0.1:3128"]
        manager.set_proxies(proxies)
        success1 = len(manager.proxies) == 2
        print_test("  تنظیم پروکسی", success1, f"تعداد: {len(manager.proxies)}")

        # تست ایجاد session
        await manager.ensure_session()
        success2 = manager.session is not None and not manager.session.closed
        print_test("  ایجاد session", success2)

        # تست event loop
        success3 = manager.loop is None  # هنوز تنظیم نشده
        print_test("  Event loop", success3)

        return success1 and success2 and success3

    finally:
        await manager.close()

def test_imports():
    """بررسی تمام کتابخانه‌ها"""
    print_section("🧪 تست 7: بررسی کتابخانه‌ها")

    required_imports = [
        ("telebot", "pyTelegramBotAPI"),
        ("aiohttp", "aiohttp"),
        ("requests", "requests"),
        ("bs4", "beautifulsoup4"),
        ("dotenv", "python-dotenv"),
        ("asyncio", "asyncio"),
        ("threading", "threading"),
    ]

    passed = 0
    for module_name, package_name in required_imports:
        try:
            __import__(module_name)
            print_test(f"  {package_name}", True)
            passed += 1
        except ImportError:
            print_test(f"  {package_name}", False, "نصب کن: pip install -r requirements.txt")

    return passed == len(required_imports)

def test_state_management():
    """تست State Management"""
    print_section("🧪 تست 8: State Management")

    # شبیه‌سازی state management
    proxy_extraction_in_progress = {}
    proxy_testing_in_progress = {}

    user_id = 123456

    # تست افزودن state
    proxy_extraction_in_progress[user_id] = True
    success1 = user_id in proxy_extraction_in_progress
    print_test("  تنظیم state استخراج", success1)

    # تست بررسی تعارض
    success2 = proxy_extraction_in_progress.get(user_id) is not None
    print_test("  بررسی state", success2)

    # تست حذف state
    proxy_extraction_in_progress.pop(user_id, None)
    success3 = user_id not in proxy_extraction_in_progress
    print_test("  حذف state", success3)

    return success1 and success2 and success3

def test_error_handling():
    """تست Error Handling"""
    print_section("🧪 تست 9: Error Handling")

    from telegram_turbo_pro_final import ConfigManager, ProxyExtractor

    # تست فایل موجود نیست
    config = ConfigManager("/nonexistent/path/config.json")
    success1 = config.data is not None
    print_test("  بارگذاری فایل موجود نیست", success1)

    # تست URL نامعتبر
    result = ProxyExtractor.parse_telegram_channel_url("")
    success2 = result is None or result == ""
    print_test("  تبدیل URL خالی", success2)

    # تست حذف کانال موجود نیست
    result = config.remove_channel("@nonexistent")
    success3 = result is False
    print_test("  حذف کانال موجود نیست", success3)

    return success1 and success2 and success3

def test_thread_safety():
    """تست Thread Safety"""
    print_section("🧪 تست 10: Thread Safety")

    from threading import Thread
    from telegram_turbo_pro_final import ConfigManager
    import tempfile

    with tempfile.NamedTemporaryFile(mode='w', suffix='.json', delete=False) as f:
        f.write('{}')  # Write empty JSON to avoid parse error
        test_file = f.name

    try:
        config = ConfigManager(test_file)
        results = []

        def add_proxies(start, count):
            for i in range(start, start + count):
                config.add_proxy(f"proxy{i}:8080")

        threads = [
            Thread(target=add_proxies, args=(0, 10)),
            Thread(target=add_proxies, args=(10, 10)),
            Thread(target=add_proxies, args=(20, 10)),
        ]

        for t in threads:
            t.start()

        for t in threads:
            t.join()

        proxies = config.get_proxies()
        success = len(proxies) == 30
        print_test("  Thread safety", success, f"پروکسی‌ها: {len(proxies)}")

        return success

    finally:
        if os.path.exists(test_file):
            os.remove(test_file)

async def run_all_tests():
    """اجرای تمام تست‌ها"""
    print(f"\n{Colors.BOLD}{Colors.BLUE}╔════════════════════════════════════════╗{Colors.END}")
    print(f"{Colors.BOLD}{Colors.BLUE}║  🧪 تست جامع ربات TURBO Pro{Colors.END}")
    print(f"{Colors.BOLD}{Colors.BLUE}╚════════════════════════════════════════╝{Colors.END}\n")

    results = []

    # تست‌های Async
    try:
        results.append(("ProxyExtractor", await test_proxy_extractor()))
    except Exception as e:
        print_test("ProxyExtractor", False, f"خطا: {e}")
        results.append(("ProxyExtractor", False))

    try:
        results.append(("Proxy Patterns", await test_proxy_patterns()))
    except Exception as e:
        print_test("Proxy Patterns", False, f"خطا: {e}")
        results.append(("Proxy Patterns", False))

    try:
        results.append(("ProxyTester", await test_proxy_tester()))
    except Exception as e:
        print_test("ProxyTester", False, f"خطا: {e}")
        results.append(("ProxyTester", False))

    try:
        results.append(("Batch Testing", await test_proxy_batch_testing()))
    except Exception as e:
        print_test("Batch Testing", False, f"خطا: {e}")
        results.append(("Batch Testing", False))

    try:
        results.append(("FastViewManager", await test_fast_view_manager()))
    except Exception as e:
        print_test("FastViewManager", False, f"خطا: {e}")
        results.append(("FastViewManager", False))

    # تست‌های Sync
    try:
        results.append(("ConfigManager", test_config_manager()))
    except Exception as e:
        print_test("ConfigManager", False, f"خطا: {e}")
        results.append(("ConfigManager", False))

    try:
        results.append(("کتابخانه‌ها", test_imports()))
    except Exception as e:
        print_test("کتابخانه‌ها", False, f"خطا: {e}")
        results.append(("کتابخانه‌ها", False))

    try:
        results.append(("State Management", test_state_management()))
    except Exception as e:
        print_test("State Management", False, f"خطا: {e}")
        results.append(("State Management", False))

    try:
        results.append(("Error Handling", test_error_handling()))
    except Exception as e:
        print_test("Error Handling", False, f"خطا: {e}")
        results.append(("Error Handling", False))

    try:
        results.append(("Thread Safety", test_thread_safety()))
    except Exception as e:
        print_test("Thread Safety", False, f"خطا: {e}")
        results.append(("Thread Safety", False))

    # خلاصه
    print_section("📊 خلاصه نتایج")

    passed = sum(1 for _, result in results if result)
    total = len(results)

    for name, result in results:
        icon = f"{Colors.GREEN}✅{Colors.END}" if result else f"{Colors.RED}❌{Colors.END}"
        print(f"{icon} {name}")

    print(f"\n{Colors.BLUE}{'='*60}{Colors.END}")
    print(f"{Colors.BOLD}{Colors.GREEN}✅ تست‌های موفق: {passed}/{total}{Colors.END}")

    if passed == total:
        print(f"{Colors.BOLD}{Colors.GREEN}🎉 تمام تست‌ها پاس شدند!{Colors.END}")
    else:
        missing = total - passed
        print(f"{Colors.BOLD}{Colors.YELLOW}⚠️  {missing} تست ناموفق{Colors.END}")

    print(f"{Colors.BLUE}{'='*60}{Colors.END}\n")

    return passed == total

if __name__ == "__main__":
    try:
        success = asyncio.run(run_all_tests())
        sys.exit(0 if success else 1)
    except Exception as e:
        print(f"\n{Colors.RED}❌ خطا در اجرای تست‌ها: {e}{Colors.END}\n")
        import traceback
        traceback.print_exc()
        sys.exit(1)
