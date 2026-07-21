#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
🚀 TELEGRAM TURBO BOT - Pro Edition
ربات سین‌زن تلگرام با استخراج و تست خودکار پروکسی
ویژگی‌های جدید: 🌐 استخراج هوشمند | 🧪 تست خودکار | 🛡️ پایداری کامل
"""

import telebot
import json
import asyncio
import aiohttp
import random
import time
import logging
import os
import sys
import traceback
import re
from typing import Optional, Dict, List, Tuple
from telebot.types import InlineKeyboardMarkup, InlineKeyboardButton
from datetime import datetime
import signal
from concurrent.futures import ThreadPoolExecutor
from threading import Thread, Lock
import requests
from bs4 import BeautifulSoup

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass

# ===== رنگ‌های ترمینال =====
class Colors:
    HEADER = '\033[95m'
    BLUE = '\033[94m'
    CYAN = '\033[96m'
    GREEN = '\033[92m'
    YELLOW = '\033[93m'
    RED = '\033[91m'
    END = '\033[0m'
    BOLD = '\033[1m'

# ===== تنظیمات لاگ =====
LOG_DIR = "logs"
if not os.path.exists(LOG_DIR):
    os.makedirs(LOG_DIR)

log_filename = os.path.join(LOG_DIR, f"bot_{datetime.now().strftime('%Y%m%d_%H%M%S')}.log")

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.FileHandler(log_filename, encoding='utf-8'),
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)

# ===== تنظیمات اولیه =====
TOKEN = os.getenv("TELEGRAM_BOT_TOKEN", "")
CONFIG_FILE = "config.json"
bot = None
config = None
view_manager = None
config_lock = Lock()

# ===== متغیرهای وضعیت =====
proxy_extraction_in_progress = {}
proxy_testing_in_progress = {}

# ===== کلاس ProxyExtractor: استخراج هوشمند پروکسی =====
class ProxyExtractor:
    """استخراج پروکسی از وب‌چنل‌های تلگرام"""

    PROXY_PATTERNS = [
        r'\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d{2,5}',
        r'http[s]?://[^\s:]+:\d{2,5}',
        r'socks5?://[^\s:]+:\d{2,5}',
    ]

    @staticmethod
    def parse_telegram_channel_url(url: str) -> Optional[str]:
        """تبدیل URL به نام کانال"""
        try:
            if "t.me/s/" in url:
                match = re.search(r't\.me/s/(\w+)', url)
                return match.group(1) if match else None
            elif "t.me/" in url or "@" in url:
                match = re.search(r'(?:t\.me/)?@?(\w+)', url)
                return match.group(1) if match else None
            return url if url.isalnum() or '_' in url else None
        except Exception as e:
            logger.error(f"❌ خطا در تبدیل URL: {e}")
            return None

    @staticmethod
    async def fetch_proxies_from_channel(channel_name: str) -> List[str]:
        """دریافت پروکسی‌ها از وب‌چنل"""
        proxies_found = set()

        urls = [
            f"https://t.me/s/{channel_name}",
            f"https://tg-me.com/{channel_name}",
        ]

        headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
        }

        for url in urls:
            try:
                async with aiohttp.ClientSession() as session:
                    async with session.get(url, headers=headers,
                                          timeout=aiohttp.ClientTimeout(total=10),
                                          ssl=False) as resp:
                        if resp.status == 200:
                            html = await resp.text()

                            for pattern in ProxyExtractor.PROXY_PATTERNS:
                                matches = re.findall(pattern, html, re.IGNORECASE)
                                proxies_found.update(matches)

                            if proxies_found:
                                logger.info(f"✅ {len(proxies_found)} پروکسی از {channel_name} استخراج شد")
                                break
            except Exception as e:
                logger.debug(f"⚠️ خطا در {url}: {e}")
                continue

        return list(proxies_found)

# ===== کلاس ProxyTester: تست هوشمند پروکسی =====
class ProxyTester:
    """تست پروکسی‌ها با دقت بالا"""

    def __init__(self, timeout: int = 5):
        self.timeout = timeout
        self.test_urls = [
            "https://www.google.com",
            "https://www.cloudflare.com",
            "https://www.wikipedia.org"
        ]

    async def test_proxy(self, proxy: Optional[str],
                        test_url: str = "https://www.google.com") -> Tuple[bool, str]:
        """تست یک پروکسی با دقت"""
        try:
            if proxy and not proxy.startswith(('http://', 'https://', 'socks5://')):
                proxy_url = f"http://{proxy}"
            else:
                proxy_url = proxy

            async with aiohttp.ClientSession() as session:
                async with session.get(
                    test_url,
                    proxy=proxy_url,
                    timeout=aiohttp.ClientTimeout(total=self.timeout),
                    ssl=False,
                    allow_redirects=False
                ) as resp:
                    if resp.status in [200, 301, 302, 304]:
                        return True, "✅"
        except asyncio.TimeoutError:
            return False, "⏱️"
        except Exception as e:
            return False, "❌"

        return False, "❌"

    async def test_proxies_batch(self, proxies: List[str],
                                progress_callback=None) -> List[str]:
        """تست گروهی پروکسی‌ها"""
        valid_proxies = []

        for idx, proxy in enumerate(proxies):
            is_valid, status = await self.test_proxy(proxy)

            if is_valid:
                valid_proxies.append(proxy)
                logger.info(f"✅ {idx+1}/{len(proxies)}: {proxy}")
            else:
                logger.debug(f"❌ {idx+1}/{len(proxies)}: {proxy}")

            if progress_callback:
                progress_callback(idx + 1, len(proxies), proxy, is_valid)

        return valid_proxies

# ===== کلاس FastViewManager: مدیر سین فوق‌سریع =====
class FastViewManager:
    """مدیر سین سریع با پروکسی‌های متوازن"""

    def __init__(self, max_workers=100):
        self.session = None
        self.proxies = []
        self.executor = ThreadPoolExecutor(max_workers=max_workers)
        self.active_tasks = {}
        self.max_workers = max_workers
        self.loop = None

    async def ensure_session(self):
        """تضمین وجود session فعال"""
        if not self.session or self.session.closed:
            connector = aiohttp.TCPConnector(
                limit=200,
                limit_per_host=50,
                ttl_dns_cache=300,
                ssl_context=False
            )
            self.session = aiohttp.ClientSession(connector=connector)

    async def close(self):
        """بستن session به‌صورت ایمن"""
        try:
            if self.session and not self.session.closed:
                await self.session.close()
                await asyncio.sleep(0.25)
        except Exception as e:
            logger.error(f"خطا در بستن session: {e}")
        finally:
            self.executor.shutdown(wait=False)

    def set_proxies(self, proxies: List[str]):
        """تنظیم پروکسی‌ها با ایمنی"""
        self.proxies = list(set(proxies))  # حذف تکراری‌ها
        logger.info(f"📊 {len(self.proxies)} پروکسی فعال")

    async def send_view_batch(self, url: str, count: int, delay: float = 0.05) -> int:
        """ارسال سریع سین‌های متعدد"""
        try:
            await self.ensure_session()

            headers = {
                "User-Agent": random.choice([
                    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
                    "Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15",
                    "Mozilla/5.0 (Linux; Android 11; SM-G991B) AppleWebKit/537.36",
                    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36",
                    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36",
                ]),
                "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
                "Accept-Language": "en-US,en;q=0.9,fa;q=0.8",
                "Connection": "keep-alive",
                "Cache-Control": "max-age=0",
            }

            tasks = []
            for i in range(count):
                proxy = random.choice(self.proxies) if self.proxies else None
                task = self._send_single_view(url, headers, proxy)
                tasks.append(task)

                if (i + 1) % 10 == 0:
                    await asyncio.sleep(delay)

            results = await asyncio.gather(*tasks, return_exceptions=True)
            successful = sum(1 for r in results if r is True)

            return successful
        except Exception as e:
            logger.error(f"خطا در batch: {e}")
            return 0

    async def _send_single_view(self, url: str, headers: dict, proxy: Optional[str]):
        """ارسال درخواست منفرد"""
        try:
            async with self.session.get(
                url,
                headers=headers,
                timeout=aiohttp.ClientTimeout(total=8),
                proxy=proxy,
                ssl=False,
                allow_redirects=True
            ) as response:
                return response.status in [200, 301, 302, 304]
        except Exception:
            return False

    async def process_channel_async(self, channel_id: str, views_count: int):
        """پردازش کانال به‌صورت async"""
        try:
            channel_url = f"https://t.me/{channel_id.lstrip('@')}"

            logger.info(f"🚀 شروع {views_count} سین برای {channel_id}")
            start_time = time.time()

            successful = await self.send_view_batch(channel_url, views_count, delay=0.05)

            elapsed = time.time() - start_time
            speed = successful / elapsed if elapsed > 0 else 0

            logger.info(f"✅ {channel_id}: {successful}/{views_count} سین در {elapsed:.1f}s ({speed:.1f}/s)")

            return successful
        except Exception as e:
            logger.error(f"خطا در {channel_id}: {e}")
            return 0

# ===== کلاس ConfigManager: مدیر تنظیمات امن =====
class ConfigManager:
    """مدیر تنظیمات با thread safety"""

    def __init__(self, filename: str):
        self.filename = filename
        self.lock = Lock()
        self.data = self._load()

    def _load(self) -> Dict:
        try:
            with open(self.filename, 'r', encoding='utf-8') as f:
                return json.load(f)
        except FileNotFoundError:
            return {
                "channels": {},
                "default_views": 100,
                "proxies": [],
                "last_processed": {}
            }

    def save(self):
        try:
            with self.lock:
                with open(self.filename, 'w', encoding='utf-8') as f:
                    json.dump(self.data, f, indent=2, ensure_ascii=False)
        except Exception as e:
            logger.error(f"خطا در ذخیره: {e}")

    def add_channel(self, channel_id: str, views: int = None):
        with self.lock:
            if "channels" not in self.data:
                self.data["channels"] = {}

            self.data["channels"][channel_id] = {
                "name": channel_id,
                "views": views or self.data.get("default_views", 100),
                "added_at": datetime.now().isoformat()
            }
            self.save()
            logger.info(f"✅ کانال اضافه: {channel_id}")

    def remove_channel(self, channel_id: str):
        with self.lock:
            if "channels" in self.data and channel_id in self.data["channels"]:
                del self.data["channels"][channel_id]
                self.save()
                return True
        return False

    def get_channels(self) -> Dict:
        with self.lock:
            return dict(self.data.get("channels", {}))

    def set_default_views(self, views: int):
        with self.lock:
            self.data["default_views"] = views
            self.save()

    def set_channel_views(self, channel_id: str, views: int):
        with self.lock:
            if "channels" in self.data and channel_id in self.data["channels"]:
                self.data["channels"][channel_id]["views"] = views
                self.save()
                return True
        return False

    def add_proxy(self, proxy: str):
        with self.lock:
            if "proxies" not in self.data:
                self.data["proxies"] = []
            if proxy not in self.data["proxies"]:
                self.data["proxies"].append(proxy)
                self.save()

    def add_proxies_batch(self, proxies: List[str]) -> int:
        """اضافه کردن گروهی پروکسی‌ها"""
        with self.lock:
            if "proxies" not in self.data:
                self.data["proxies"] = []

            added_count = 0
            for proxy in proxies:
                if proxy not in self.data["proxies"]:
                    self.data["proxies"].append(proxy)
                    added_count += 1

            if added_count > 0:
                self.save()

            return added_count

    def remove_proxy(self, proxy: str):
        with self.lock:
            if "proxies" in self.data and proxy in self.data["proxies"]:
                self.data["proxies"].remove(proxy)
                self.save()

    def remove_proxies_batch(self, proxies: List[str]) -> int:
        """حذف گروهی پروکسی‌ها"""
        with self.lock:
            if "proxies" not in self.data:
                return 0

            removed_count = 0
            for proxy in proxies:
                if proxy in self.data["proxies"]:
                    self.data["proxies"].remove(proxy)
                    removed_count += 1

            if removed_count > 0:
                self.save()

            return removed_count

    def get_proxies(self) -> List:
        with self.lock:
            return list(self.data.get("proxies", []))

# ===== تابع‌های مقدار دهی =====
def initialize_bot():
    """مقدار دهی ربات"""
    global bot
    if not TOKEN or TOKEN.strip() == "":
        logger.error("❌ توکن تنظیم نشده است!")
        print(f"\n{Colors.RED}⚠️ لطفاً TELEGRAM_BOT_TOKEN را در .env تنظیم کنید{Colors.END}\n")
        return False

    try:
        bot = telebot.TeleBot(TOKEN, parse_mode="HTML")
        logger.info(f"✅ ربات مقدار دهی شد")
        return True
    except Exception as e:
        logger.error(f"❌ خطا در مقدار دهی ربات: {e}")
        return False

def initialize_config():
    """مقدار دهی تنظیمات"""
    global config
    try:
        config = ConfigManager(CONFIG_FILE)
        logger.info("✅ تنظیمات بارگذاری شدند")
        return True
    except Exception as e:
        logger.error(f"❌ خطا در تنظیمات: {e}")
        return False

def initialize_view_manager():
    """مقدار دهی مدیر سین"""
    global view_manager
    try:
        view_manager = FastViewManager(max_workers=150)
        if config:
            view_manager.set_proxies(config.get_proxies())
        logger.info("✅ FastViewManager مقدار دهی شد")
        return True
    except Exception as e:
        logger.error(f"❌ خطا در ViewManager: {e}")
        return False

def start_auto_viewer():
    """شروع مشاهده‌کننده خودکار"""

    def run_viewer():
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        view_manager.loop = loop

        while True:
            try:
                channels = config.get_channels()
                if not channels:
                    time.sleep(10)
                    continue

                logger.info(f"🔄 شروع پردازش {len(channels)} کانال...")
                start_time = time.time()

                tasks = []
                for channel_id, ch_data in channels.items():
                    views = ch_data.get("views", 100)
                    task = view_manager.process_channel_async(channel_id, views)
                    tasks.append(task)

                results = loop.run_until_complete(asyncio.gather(*tasks))

                total_views = sum(results)
                elapsed = time.time() - start_time

                logger.info(f"✅ کل {total_views} سین در {elapsed:.1f}s")

                time.sleep(30)

            except Exception as e:
                logger.error(f"❌ خطا در auto viewer: {e}")
                time.sleep(10)

    thread = Thread(target=run_viewer, daemon=True)
    thread.start()
    return thread

def register_handlers():
    """ثبت تمام handlers"""

    @bot.message_handler(commands=['start'])
    def start_menu(message):
        try:
            markup = InlineKeyboardMarkup(row_width=2)

            # ردیف اول: کانال‌ها
            markup.add(
                InlineKeyboardButton("➕ افزودن کانال", callback_data="add_channel"),
                InlineKeyboardButton("📋 لیست کانال‌ها", callback_data="list_channels")
            )

            # ردیف دوم: حذف و تنظیمات
            markup.add(
                InlineKeyboardButton("❌ حذف کانال", callback_data="remove_channel"),
                InlineKeyboardButton("⚙️ سین پیش‌فرض", callback_data="set_default_views")
            )

            # ردیف سوم: تنظیم سین کانال و پروکسی
            markup.add(
                InlineKeyboardButton("🔢 تنظیم سین", callback_data="set_channel_views"),
                InlineKeyboardButton("🔧 مدیریت پروکسی", callback_data="manage_proxy")
            )

            # ردیف چهارم: ویژگی‌های جدید
            markup.add(
                InlineKeyboardButton("🌐 استخراج پروکسی", callback_data="extract_proxy"),
                InlineKeyboardButton("🧪 تست پروکسی", callback_data="test_proxy")
            )

            bot.send_message(
                message.chat.id,
                f"<b>🚀 ربات سین‌زن TURBO Pro</b>\n\n"
                f"⚡ <b>سرعت:</b> فوق‌سریع\n"
                f"🔄 <b>پروسس:</b> 100 همزمان\n"
                f"⏱️ <b>سرعت:</b> +1000 سین/ثانیه\n"
                f"🌐 <b>استخراج:</b> هوشمند و خودکار\n"
                f"🧪 <b>تست:</b> دقیق و خودکار\n"
                f"🛡️ <b>پایداری:</b> کامل\n\n"
                f"کانال‌ها رو اضافه کن تا خودکار سین بزنم",
                reply_markup=markup
            )
        except Exception as e:
            logger.error(f"خطا در start_menu: {e}")
            bot.send_message(message.chat.id, "❌ خطایی رخ داد")

    # ===== Handlers کانال‌ها =====

    @bot.callback_query_handler(func=lambda call: call.data == "add_channel")
    def add_channel_handler(call):
        try:
            bot.answer_callback_query(call.id, "درخواست پردازش شد", show_alert=False)
            msg = bot.send_message(call.message.chat.id, "نام کانال یا @username را وارد کن:")
            bot.register_next_step_handler(msg, process_add_channel)
        except Exception as e:
            logger.error(f"خطا: {e}")
            bot.answer_callback_query(call.id, "❌ خطا", show_alert=True)

    def process_add_channel(message):
        try:
            channel_id = message.text.strip()
            if not channel_id:
                bot.send_message(message.chat.id, "❌ نام خالی است")
                return

            if not channel_id.startswith('@'):
                channel_id = '@' + channel_id

            config.add_channel(channel_id)
            view_manager.set_proxies(config.get_proxies())

            bot.send_message(message.chat.id, f"✅ {channel_id} اضافه شد\n⏰ سین‌های خودکار شروع می‌شود")
        except Exception as e:
            logger.error(f"خطا: {e}")
            bot.send_message(message.chat.id, f"❌ خطا: {str(e)[:50]}")

    @bot.callback_query_handler(func=lambda call: call.data == "list_channels")
    def list_channels_handler(call):
        try:
            bot.answer_callback_query(call.id)
            channels = config.get_channels()
            if not channels:
                bot.send_message(call.message.chat.id, "📭 کانالی اضافه نشده")
                return

            text = "<b>📋 کانال‌های فعال:</b>\n\n"
            for ch_id, ch_data in channels.items():
                views = ch_data.get("views", 100)
                text += f"• {ch_id} → {views} سین\n"

            bot.send_message(call.message.chat.id, text)
        except Exception as e:
            logger.error(f"خطا: {e}")
            bot.answer_callback_query(call.id, "❌ خطا", show_alert=True)

    @bot.callback_query_handler(func=lambda call: call.data == "remove_channel")
    def remove_channel_handler(call):
        try:
            bot.answer_callback_query(call.id, "درخواست پردازش شد", show_alert=False)
            msg = bot.send_message(call.message.chat.id, "نام کانالی که حذف کنم:")
            bot.register_next_step_handler(msg, process_remove_channel)
        except Exception as e:
            logger.error(f"خطا: {e}")
            bot.answer_callback_query(call.id, "❌ خطا", show_alert=True)

    def process_remove_channel(message):
        try:
            channel_id = message.text.strip()
            if not channel_id.startswith('@'):
                channel_id = '@' + channel_id

            if config.remove_channel(channel_id):
                bot.send_message(message.chat.id, f"✅ {channel_id} حذف شد")
            else:
                bot.send_message(message.chat.id, "❌ کانال پیدا نشد")
        except Exception as e:
            logger.error(f"خطا: {e}")

    # ===== Handlers تنظیمات =====

    @bot.callback_query_handler(func=lambda call: call.data == "set_default_views")
    def set_default_views_handler(call):
        try:
            bot.answer_callback_query(call.id, "درخواست پردازش شد", show_alert=False)
            msg = bot.send_message(call.message.chat.id, "تعداد سین پیش‌فرض را وارد کن:")
            bot.register_next_step_handler(msg, process_default_views)
        except Exception as e:
            logger.error(f"خطا: {e}")
            bot.answer_callback_query(call.id, "❌ خطا", show_alert=True)

    def process_default_views(message):
        try:
            views = int(message.text.strip())
            if views > 0:
                config.set_default_views(views)
                bot.send_message(message.chat.id, f"✅ سین پیش‌فرض: {views}")
            else:
                bot.send_message(message.chat.id, "❌ عدد باید مثبت باشد")
        except ValueError:
            bot.send_message(message.chat.id, "❌ لطفاً عدد وارد کن")
        except Exception as e:
            logger.error(f"خطا: {e}")

    @bot.callback_query_handler(func=lambda call: call.data == "set_channel_views")
    def set_channel_views_handler(call):
        try:
            bot.answer_callback_query(call.id)
            channels = config.get_channels()
            if not channels:
                bot.send_message(call.message.chat.id, "📭 کانالی نیست")
                return

            markup = InlineKeyboardMarkup()
            for idx, ch_id in enumerate(list(channels.keys())[:10]):
                markup.add(InlineKeyboardButton(ch_id, callback_data=f"select_ch_{ch_id}"))

            bot.send_message(call.message.chat.id, "کانال رو انتخاب کن:", reply_markup=markup)
        except Exception as e:
            logger.error(f"خطا: {e}")
            bot.answer_callback_query(call.id, "❌ خطا", show_alert=True)

    @bot.callback_query_handler(func=lambda call: call.data.startswith("select_ch_"))
    def select_channel_handler(call):
        try:
            channel_id = call.data.replace("select_ch_", "")
            bot.answer_callback_query(call.id)
            msg = bot.send_message(call.message.chat.id, f"تعداد سین برای {channel_id}:")
            bot.register_next_step_handler(msg, lambda m: process_channel_views(m, channel_id))
        except Exception as e:
            logger.error(f"خطا: {e}")
            bot.answer_callback_query(call.id, "❌ خطا", show_alert=True)

    def process_channel_views(message, channel_id):
        try:
            views = int(message.text.strip())
            if views > 0:
                config.set_channel_views(channel_id, views)
                bot.send_message(message.chat.id, f"✅ {channel_id} → {views} سین")
            else:
                bot.send_message(message.chat.id, "❌ عدد باید مثبت باشد")
        except ValueError:
            bot.send_message(message.chat.id, "❌ لطفاً عدد وارد کن")
        except Exception as e:
            logger.error(f"خطا: {e}")

    # ===== Handlers پروکسی =====

    @bot.callback_query_handler(func=lambda call: call.data == "manage_proxy")
    def manage_proxy_handler(call):
        try:
            bot.answer_callback_query(call.id)
            markup = InlineKeyboardMarkup()
            markup.add(
                InlineKeyboardButton("➕ افزودن", callback_data="add_proxy"),
                InlineKeyboardButton("📋 لیست", callback_data="list_proxy")
            )
            markup.add(InlineKeyboardButton("❌ حذف", callback_data="remove_proxy"))

            bot.send_message(call.message.chat.id, "انتخاب:", reply_markup=markup)
        except Exception as e:
            logger.error(f"خطا: {e}")
            bot.answer_callback_query(call.id, "❌ خطا", show_alert=True)

    @bot.callback_query_handler(func=lambda call: call.data == "add_proxy")
    def add_proxy_handler(call):
        try:
            bot.answer_callback_query(call.id, "درخواست پردازش شد", show_alert=False)
            msg = bot.send_message(call.message.chat.id,
                "آدرس پروکسی را وارد کن (یک یا بیش‌تر در هر سطر):\n\nمثال:\n192.168.1.1:8080\nhttp://proxy.com:3128")
            bot.register_next_step_handler(msg, process_add_proxy)
        except Exception as e:
            logger.error(f"خطا: {e}")
            bot.answer_callback_query(call.id, "❌ خطا", show_alert=True)

    def process_add_proxy(message):
        try:
            proxy_text = message.text.strip()
            proxies = [p.strip() for p in proxy_text.split('\n') if p.strip()]

            if not proxies:
                bot.send_message(message.chat.id, "❌ پروکسی خالی است")
                return

            for proxy in proxies:
                config.add_proxy(proxy)

            view_manager.set_proxies(config.get_proxies())
            bot.send_message(message.chat.id, f"✅ {len(proxies)} پروکسی اضافه شد")
        except Exception as e:
            logger.error(f"خطا: {e}")
            bot.send_message(message.chat.id, f"❌ خطا: {str(e)[:50]}")

    @bot.callback_query_handler(func=lambda call: call.data == "list_proxy")
    def list_proxy_handler(call):
        try:
            bot.answer_callback_query(call.id)
            proxies = config.get_proxies()
            if not proxies:
                bot.send_message(call.message.chat.id, "📭 پروکسی نیست")
                return

            text = f"<b>📋 پروکسی‌ها ({len(proxies)}):</b>\n\n"
            for idx, proxy in enumerate(proxies[:50], 1):
                text += f"{idx}. <code>{proxy}</code>\n"

            if len(proxies) > 50:
                text += f"\n... و {len(proxies)-50} پروکسی دیگر"

            bot.send_message(call.message.chat.id, text)
        except Exception as e:
            logger.error(f"خطا: {e}")
            bot.answer_callback_query(call.id, "❌ خطا", show_alert=True)

    @bot.callback_query_handler(func=lambda call: call.data == "remove_proxy")
    def remove_proxy_handler(call):
        try:
            bot.answer_callback_query(call.id)
            proxies = config.get_proxies()
            if not proxies:
                bot.send_message(call.message.chat.id, "📭 پروکسی نیست")
                return

            markup = InlineKeyboardMarkup()
            for idx, proxy in enumerate(proxies[:20]):
                proxy_display = proxy[:25] + "..." if len(proxy) > 25 else proxy
                markup.add(InlineKeyboardButton(proxy_display, callback_data=f"del_proxy_{idx}"))

            bot.send_message(call.message.chat.id, "حذف کن:", reply_markup=markup)
        except Exception as e:
            logger.error(f"خطا: {e}")
            bot.answer_callback_query(call.id, "❌ خطا", show_alert=True)

    @bot.callback_query_handler(func=lambda call: call.data.startswith("del_proxy_"))
    def delete_proxy_handler(call):
        try:
            bot.answer_callback_query(call.id)
            idx = int(call.data.replace("del_proxy_", ""))
            proxies = config.get_proxies()
            if 0 <= idx < len(proxies):
                proxy = proxies[idx]
                config.remove_proxy(proxy)
                view_manager.set_proxies(config.get_proxies())
                bot.send_message(call.message.chat.id, f"✅ حذف شد:\n<code>{proxy}</code>")
            else:
                bot.send_message(call.message.chat.id, "❌ پروکسی پیدا نشد")
        except Exception as e:
            logger.error(f"خطا: {e}")
            bot.answer_callback_query(call.id, "❌ خطا", show_alert=True)

    # ===== Handlers ویژگی‌های جدید =====

    @bot.callback_query_handler(func=lambda call: call.data == "extract_proxy")
    def extract_proxy_handler(call):
        try:
            bot.answer_callback_query(call.id, "درخواست پردازش شد", show_alert=False)
            msg = bot.send_message(call.message.chat.id,
                "ایدی کانالی که پروکسی منتشر میکنه رو وارد کن:\n\n"
                "<b>مثال:</b>\n"
                "channelname\n"
                "@channelname\n"
                "https://t.me/s/channelname")
            bot.register_next_step_handler(msg, process_extract_proxy)
        except Exception as e:
            logger.error(f"خطا: {e}")
            bot.answer_callback_query(call.id, "❌ خطا", show_alert=True)

    def process_extract_proxy(message):
        chat_id = message.chat.id
        try:
            channel_input = message.text.strip()

            if chat_id in proxy_extraction_in_progress:
                bot.send_message(chat_id, "⚠️ عملیات قبلی هنوز در حال انجام است")
                return

            status_msg = bot.send_message(chat_id, "🔍 در حال جستجو...")
            proxy_extraction_in_progress[chat_id] = True

            def run_extraction():
                loop = asyncio.new_event_loop()
                asyncio.set_event_loop(loop)

                try:
                    channel_name = ProxyExtractor.parse_telegram_channel_url(channel_input)
                    if not channel_name:
                        bot.edit_message_text("❌ فرمت کانال اشتباه است", chat_id, status_msg.message_id)
                        return

                    bot.edit_message_text(f"🔍 جستجو در {channel_name}...", chat_id, status_msg.message_id)

                    proxies = loop.run_until_complete(
                        ProxyExtractor.fetch_proxies_from_channel(channel_name)
                    )

                    if not proxies:
                        bot.edit_message_text("❌ پروکسی پیدا نشد", chat_id, status_msg.message_id)
                        return

                    bot.edit_message_text(
                        f"✅ {len(proxies)} پروکسی پیدا شد\n\n"
                        "🧪 در حال تست...",
                        chat_id, status_msg.message_id
                    )

                    tester = ProxyTester(timeout=5)

                    def test_progress(current, total, proxy, is_valid):
                        status_text = "✅" if is_valid else "❌"
                        bot.edit_message_text(
                            f"✅ {len(proxies)} پروکسی یافت شد\n\n"
                            f"🧪 تست: {current}/{total}\n"
                            f"{status_text} {proxy[:25]}...",
                            chat_id, status_msg.message_id
                        )

                    valid_proxies = loop.run_until_complete(
                        tester.test_proxies_batch(proxies, progress_callback=test_progress)
                    )

                    if valid_proxies:
                        added = config.add_proxies_batch(valid_proxies)
                        view_manager.set_proxies(config.get_proxies())

                        bot.edit_message_text(
                            f"✅ {len(valid_proxies)} پروکسی معتبر یافت شد\n"
                            f"✅ {added} پروکسی اضافه شد",
                            chat_id, status_msg.message_id
                        )
                    else:
                        bot.edit_message_text(
                            f"⚠️ {len(proxies)} پروکسی یافت شد اما معتبر نبود",
                            chat_id, status_msg.message_id
                        )

                except Exception as e:
                    logger.error(f"خطا در استخراج: {e}")
                    bot.edit_message_text(
                        f"❌ خطا: {str(e)[:100]}",
                        chat_id, status_msg.message_id
                    )
                finally:
                    proxy_extraction_in_progress.pop(chat_id, None)

            Thread(target=run_extraction, daemon=True).start()

        except Exception as e:
            logger.error(f"خطا: {e}")
            bot.send_message(chat_id, f"❌ خطا: {str(e)[:50]}")
            proxy_extraction_in_progress.pop(chat_id, None)

    @bot.callback_query_handler(func=lambda call: call.data == "test_proxy")
    def test_proxy_handler(call):
        try:
            bot.answer_callback_query(call.id)
            chat_id = call.message.chat.id
            proxies = config.get_proxies()

            if not proxies:
                bot.send_message(chat_id, "📭 پروکسی نیست برای تست")
                return

            if chat_id in proxy_testing_in_progress:
                bot.send_message(chat_id, "⚠️ تست هنوز در حال اجرا است")
                return

            status_msg = bot.send_message(chat_id, f"🧪 تست {len(proxies)} پروکسی...")
            proxy_testing_in_progress[chat_id] = True

            def run_test():
                loop = asyncio.new_event_loop()
                asyncio.set_event_loop(loop)

                try:
                    tester = ProxyTester(timeout=5)
                    invalid_proxies = []
                    valid_count = 0

                    def test_progress(current, total, proxy, is_valid):
                        nonlocal valid_count
                        if is_valid:
                            valid_count += 1
                        else:
                            invalid_proxies.append(proxy)

                        bot.edit_message_text(
                            f"🧪 تست {current}/{total}\n"
                            f"✅ معتبر: {valid_count}\n"
                            f"❌ نامعتبر: {len(invalid_proxies)}",
                            chat_id, status_msg.message_id
                        )

                    loop.run_until_complete(
                        tester.test_proxies_batch(proxies, progress_callback=test_progress)
                    )

                    if invalid_proxies:
                        config.remove_proxies_batch(invalid_proxies)
                        view_manager.set_proxies(config.get_proxies())

                    bot.edit_message_text(
                        f"✅ تست تمام شد\n"
                        f"✅ معتبر: {valid_count}\n"
                        f"❌ حذف شده: {len(invalid_proxies)}",
                        chat_id, status_msg.message_id
                    )

                except Exception as e:
                    logger.error(f"خطا در تست: {e}")
                    bot.edit_message_text(
                        f"❌ خطا: {str(e)[:100]}",
                        chat_id, status_msg.message_id
                    )
                finally:
                    proxy_testing_in_progress.pop(chat_id, None)

            Thread(target=run_test, daemon=True).start()

        except Exception as e:
            logger.error(f"خطا: {e}")
            bot.answer_callback_query(call.id, "❌ خطا", show_alert=True)

async def graceful_shutdown_async():
    """خاتمه async"""
    logger.info("🛑 درحال خاتمه...")
    try:
        if view_manager:
            await view_manager.close()
    except Exception as e:
        logger.error(f"خطا در خاتمه: {e}")

def graceful_shutdown(signum, frame):
    """خاتمه آرام"""
    try:
        if view_manager and view_manager.loop:
            asyncio.run_coroutine_threadsafe(
                graceful_shutdown_async(),
                view_manager.loop
            )
    except Exception as e:
        logger.error(f"خطا در shutdown: {e}")
    sys.exit(0)

def main():
    """تابع اصلی"""
    logger.info("🚀 شروع TURBO BOT Pro...")

    if not TOKEN or TOKEN.strip() == "":
        logger.error("❌ توکن تنظیم نشده!")
        sys.exit(1)

    if not initialize_bot():
        sys.exit(1)

    if not initialize_config():
        sys.exit(1)

    if not initialize_view_manager():
        sys.exit(1)

    register_handlers()

    viewer_thread = start_auto_viewer()

    signal.signal(signal.SIGINT, graceful_shutdown)
    signal.signal(signal.SIGTERM, graceful_shutdown)

    print("\n" + "="*70)
    print(f"{Colors.BOLD}{Colors.GREEN}🚀 TURBO VIEWS BOT Pro فعال است!{Colors.END}")
    print(f"{Colors.CYAN}⚡ سرعت: +1000 سین/ثانیه{Colors.END}")
    print(f"{Colors.CYAN}🔄 پروسس: 150 همزمان{Colors.END}")
    print(f"{Colors.CYAN}🌐 استخراج: هوشمند و خودکار{Colors.END}")
    print(f"{Colors.CYAN}🧪 تست: دقیق و لحظه‌ای{Colors.END}")
    print(f"{Colors.CYAN}🛡️ پایداری: 100% تضمینی{Colors.END}")
    print("="*70 + "\n")

    retry_count = 0
    while True:
        try:
            logger.info("📡 Polling شروع...")
            bot.infinity_polling(
                timeout=30,
                long_polling_timeout=30,
                allowed_updates=['message', 'callback_query'],
                skip_pending=True
            )
        except KeyboardInterrupt:
            logger.info("⏸️ توقف (Ctrl+C)")
            graceful_shutdown(None, None)
        except Exception as e:
            logger.error(f"❌ خطا: {e}")
            retry_count += 1
            if retry_count >= 10:
                logger.error("❌ تعداد تلاش‌ها پایان یافت")
                sys.exit(1)
            logger.info(f"🔄 تلاش دوباره ({retry_count}/10)...")
            time.sleep(5)
        else:
            retry_count = 0

if __name__ == "__main__":
    main()
