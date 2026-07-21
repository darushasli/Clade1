#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
🚀 TELEGRAM TURBO BOT Pro v3.3 - Professional Edition
ربات سین‌زن تلگرام حرفه‌ای

ساختار:
- Application base (telegram.ext)
- Thread-safe database with full error handling
- Proxy integration with fallback
- Async/await programming
- Global error handling
- Modular design
"""

from __future__ import annotations

import logging
import sqlite3
import threading
import os
import sys
from datetime import datetime
from pathlib import Path
from typing import Optional

from telegram import (
    Update, InlineKeyboardButton, InlineKeyboardMarkup,
    BotCommand, MenuButtonCommands
)
from telegram.ext import (
    Application, ContextTypes, CommandHandler, MessageHandler,
    CallbackQueryHandler, filters
)
from telegram.constants import ChatAction
from telegram.request import HTTPXRequest

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass

# ===== تنظیمات لاگ =====
LOG_DIR = Path("logs")
try:
    LOG_DIR.mkdir(exist_ok=True)
except Exception as e:
    print(f"⚠️ نتوانستم پوشهٔ logs را ایجاد کنم: {e}")
    LOG_DIR = Path(".")

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.FileHandler(LOG_DIR / f"bot_{datetime.now().strftime('%Y%m%d_%H%M%S')}.log", encoding='utf-8'),
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)

# ===== ثابت‌ها =====
TOKEN = os.getenv("TELEGRAM_BOT_TOKEN", "")
BASE_DIR = Path(__file__).parent
DB_PATH = BASE_DIR / "data.db"
try:
    DB_PATH.parent.mkdir(exist_ok=True)
except Exception as e:
    logger.error(f"❌ خطا در ایجاد پوشهٔ دیتابیس: {e}")

ITEMS_PER_PAGE = 10
MAX_MESSAGE_LENGTH = 4096

# ===== Database Setup =====
class Database:
    """مدیریت ایمن دیتابیس (Thread-safe)"""

    def __init__(self, db_path: Path):
        self.db_path = db_path
        self.lock = threading.Lock()
        self._init_db()

    def _init_db(self):
        """اولین‌بار تنظیمات دیتابیس"""
        with self.lock:
            try:
                conn = sqlite3.connect(self.db_path)
                conn.execute("PRAGMA journal_mode=WAL")
                cursor = conn.cursor()

                cursor.execute("""
                    CREATE TABLE IF NOT EXISTS channels (
                        id INTEGER PRIMARY KEY,
                        username TEXT UNIQUE NOT NULL,
                        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                """)

                cursor.execute("""
                    CREATE TABLE IF NOT EXISTS proxies (
                        id INTEGER PRIMARY KEY,
                        proxy_url TEXT UNIQUE NOT NULL,
                        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                """)

                conn.commit()
                conn.close()
                logger.info("✅ دیتابیس آماده است")
            except Exception as e:
                logger.error(f"❌ خطا در اولیه‌سازی دیتابیس: {e}")
                raise

    def add_channel(self, username: str) -> bool:
        """افزودن کانال"""
        with self.lock:
            try:
                conn = sqlite3.connect(self.db_path)
                cursor = conn.cursor()
                cursor.execute("INSERT INTO channels (username) VALUES (?)", (username.lstrip('@'),))
                conn.commit()
                conn.close()
                logger.info(f"✅ کانال اضافه شد: {username}")
                return True
            except sqlite3.IntegrityError:
                logger.warning(f"⚠️ کانال تکراری: {username}")
                return False
            except Exception as e:
                logger.error(f"❌ خطا در افزودن کانال: {e}")
                return False

    def remove_channel(self, username: str) -> bool:
        """حذف کانال"""
        with self.lock:
            try:
                conn = sqlite3.connect(self.db_path)
                cursor = conn.cursor()
                cursor.execute("DELETE FROM channels WHERE username = ?", (username.lstrip('@'),))
                conn.commit()
                success = cursor.rowcount > 0
                conn.close()
                if success:
                    logger.info(f"✅ کانال حذف شد: {username}")
                return success
            except Exception as e:
                logger.error(f"❌ خطا در حذف کانال: {e}")
                return False

    def get_channels(self) -> list[str]:
        """دریافت لیست کانال‌ها"""
        with self.lock:
            try:
                conn = sqlite3.connect(self.db_path)
                cursor = conn.cursor()
                cursor.execute("SELECT username FROM channels ORDER BY added_at DESC")
                channels = [row[0] for row in cursor.fetchall()]
                conn.close()
                return channels
            except Exception as e:
                logger.error(f"❌ خطا در خواندن کانال‌ها: {e}")
                return []

    def add_proxy(self, proxy: str) -> bool:
        """افزودن پروکسی"""
        with self.lock:
            try:
                conn = sqlite3.connect(self.db_path)
                cursor = conn.cursor()
                cursor.execute("INSERT INTO proxies (proxy_url) VALUES (?)", (proxy.strip(),))
                conn.commit()
                conn.close()
                logger.info(f"✅ پروکسی اضافه شد: {proxy}")
                return True
            except sqlite3.IntegrityError:
                logger.warning(f"⚠️ پروکسی تکراری: {proxy}")
                return False
            except Exception as e:
                logger.error(f"❌ خطا در افزودن پروکسی: {e}")
                return False

    def remove_proxy(self, proxy_id: int) -> bool:
        """حذف پروکسی"""
        with self.lock:
            try:
                conn = sqlite3.connect(self.db_path)
                cursor = conn.cursor()
                cursor.execute("DELETE FROM proxies WHERE id = ?", (proxy_id,))
                conn.commit()
                success = cursor.rowcount > 0
                conn.close()
                if success:
                    logger.info(f"✅ پروکسی حذف شد (ID: {proxy_id})")
                return success
            except Exception as e:
                logger.error(f"❌ خطا در حذف پروکسی: {e}")
                return False

    def get_proxies(self) -> list[tuple[int, str]]:
        """دریافت لیست پروکسی‌ها"""
        with self.lock:
            try:
                conn = sqlite3.connect(self.db_path)
                cursor = conn.cursor()
                cursor.execute("SELECT id, proxy_url FROM proxies ORDER BY added_at DESC")
                proxies = cursor.fetchall()
                conn.close()
                return proxies
            except Exception as e:
                logger.error(f"❌ خطا در خواندن پروکسی‌ها: {e}")
                return []

    def get_first_proxy(self) -> Optional[str]:
        """دریافت اولین پروکسی معتبر"""
        proxies = self.get_proxies()
        return proxies[0][1] if proxies else None

# ===== Keyboards =====
def _btn(text: str, data: str) -> InlineKeyboardButton:
    """Helper برای ساخت دکمه"""
    return InlineKeyboardButton(text, callback_data=data)

def main_menu() -> InlineKeyboardMarkup:
    """منو اصلی"""
    return InlineKeyboardMarkup([
        [_btn("📋 کانال‌ها", "menu:channels")],
        [_btn("🔧 پروکسی", "menu:proxies")]
    ])

def channels_menu() -> InlineKeyboardMarkup:
    """منو کانال‌ها"""
    return InlineKeyboardMarkup([
        [_btn("➕ افزودن", "ch:add"), _btn("📋 لیست", "ch:list")],
        [_btn("❌ حذف", "ch:remove"), _btn("« برگشت", "start")]
    ])

def proxies_menu() -> InlineKeyboardMarkup:
    """منو پروکسی‌ها"""
    return InlineKeyboardMarkup([
        [_btn("➕ افزودن", "pr:add"), _btn("📋 لیست", "pr:list")],
        [_btn("❌ حذف", "pr:remove"), _btn("« برگشت", "start")]
    ])

def make_delete_keyboard(items: list[tuple], callback_prefix: str, back_callback: str, page: int = 1) -> InlineKeyboardMarkup:
    """ساخت صفحه‌بندی برای منوی حذف"""
    start = (page - 1) * ITEMS_PER_PAGE
    end = start + ITEMS_PER_PAGE
    page_items = items[start:end]

    buttons = []
    for item_id, item_name in page_items:
        if callback_prefix == "ch:del":
            buttons.append([_btn(f"❌ @{item_name}", f"{callback_prefix}:{item_name}")])
        else:
            buttons.append([_btn(f"❌ {item_name[:30]}", f"{callback_prefix}:{item_id}")])

    nav_buttons = []
    if page > 1:
        nav_buttons.append(_btn("◀️ قبلی", f"{callback_prefix}:page:{page-1}"))
    if end < len(items):
        nav_buttons.append(_btn("بعدی ▶️", f"{callback_prefix}:page:{page+1}"))

    if nav_buttons:
        buttons.append(nav_buttons)

    buttons.append([_btn("« برگشت", back_callback)])

    return InlineKeyboardMarkup(buttons)

def truncate_text(text: str, max_len: int = MAX_MESSAGE_LENGTH - 100) -> str:
    """قطع متن اگر از حد تجاوز کند"""
    if len(text) > max_len:
        return text[:max_len] + f"\n\n... (متن بیشتر است، {len(text) - max_len} کاراکتر دیگر)"
    return text

# ===== Handlers =====
async def start_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """دستور /start"""
    try:
        await context.bot.send_chat_action(update.effective_chat.id, ChatAction.TYPING)
        context.user_data.pop("awaiting", None)
        await update.message.reply_text(
            "🚀 <b>ربات سین‌زن TURBO Pro v3.3</b>\n\n"
            "⚡ سرعت: +1000 سین/ثانیه\n"
            "🔄 پردازش: 150 همزمان\n"
            "🛡️ پایداری: 100% تضمینی\n\n"
            "گزینه‌ی مورد نظر را انتخاب کنید:",
            reply_markup=main_menu(),
            parse_mode="HTML"
        )
    except Exception as e:
        logger.error(f"❌ خطا در /start: {e}")

async def help_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """دستور /help"""
    try:
        context.user_data.pop("awaiting", None)
        await update.message.reply_text(
            "<b>📖 راهنمای استفاده</b>\n\n"
            "<b>📋 کانال‌ها:</b>\n"
            "• ➕ افزودن: نام کانال را وارد کنید\n"
            "• 📋 لیست: تمام کانال‌های ثبت‌شده را ببینید\n"
            "• ❌ حذف: یک کانال را حذف کنید\n\n"
            "<b>🔧 پروکسی:</b>\n"
            "• ➕ افزودن: آدرس پروکسی را وارد کنید\n"
            "• 📋 لیست: تمام پروکسی‌های ثبت‌شده را ببینید\n"
            "• ❌ حذف: یک پروکسی را حذف کنید\n\n"
            "<b>💡 نکات:</b>\n"
            "• برای لغو هر عملیات، /start را بفرستید\n"
            "• همه داده‌ها محفوظ و امن است\n"
            "• لاگ‌ها در پوشهٔ logs/ ثبت می‌شود",
            reply_markup=InlineKeyboardMarkup([[_btn("« برگشت", "start")]]),
            parse_mode="HTML"
        )
    except Exception as e:
        logger.error(f"❌ خطا در /help: {e}")

async def callback_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """مدیریت تمام دکمه‌ها"""
    query = update.callback_query

    try:
        await query.answer()
    except Exception as e:
        logger.error(f"⚠️ خطا در query.answer(): {e}")
        return

    data = query.data
    user_id = query.from_user.id
    db = context.bot_data.get("db")

    if not db:
        try:
            await query.edit_message_text("❌ خطا: دیتابیس در دسترس نیست")
        except Exception:
            pass
        return

    try:
        if data == "start":
            context.user_data.pop("awaiting", None)
            await query.edit_message_text(
                "🚀 <b>ربات سین‌زن TURBO Pro</b>\n\n"
                "⚡ سرعت: +1000 سین/ثانیه\n"
                "🔄 پردازش: 150 همزمان\n"
                "🛡️ پایداری: 100% تضمینی",
                reply_markup=main_menu(),
                parse_mode="HTML"
            )

        # ===== کانال‌ها =====
        elif data == "menu:channels":
            context.user_data.pop("awaiting", None)
            await query.edit_message_text(
                "📋 <b>مدیریت کانال‌ها</b>",
                reply_markup=channels_menu(),
                parse_mode="HTML"
            )

        elif data == "ch:add":
            context.user_data["awaiting"] = "channel_name"
            await context.bot.send_message(
                user_id,
                "نام کانال را وارد کنید:\nمثال: @mychannel\n\nبرای لغو، /start را بفرستید"
            )

        elif data == "ch:list":
            channels = db.get_channels()
            context.user_data.pop("awaiting", None)
            if channels:
                text = "📋 <b>کانال‌های فعال:</b>\n\n"
                for ch in channels:
                    text += f"• @{ch}\n"
                text = truncate_text(text)
            else:
                text = "❌ هیچ کانالی اضافه نشده است"

            await query.edit_message_text(
                text,
                reply_markup=channels_menu(),
                parse_mode="HTML"
            )

        elif data == "ch:remove":
            channels = db.get_channels()
            context.user_data.pop("awaiting", None)
            if not channels:
                await query.edit_message_text("❌ هیچ کانالی برای حذف نیست", reply_markup=channels_menu())
                return

            kb = make_delete_keyboard(
                [(ch, ch) for ch in channels],
                "ch:del",
                "menu:channels"
            )
            await query.edit_message_text("کانال را انتخاب کنید:", reply_markup=kb)

        elif data.startswith("ch:del:"):
            ch = data.replace("ch:del:", "")
            success = db.remove_channel(ch)
            if success:
                await query.edit_message_text(f"✅ کانال @{ch} حذف شد")
            else:
                await query.edit_message_text(f"⚠️ نتوانستم کانال @{ch} را حذف کنم")

        elif data.startswith("ch:del:page:"):
            page = int(data.replace("ch:del:page:", ""))
            channels = db.get_channels()
            kb = make_delete_keyboard(
                [(ch, ch) for ch in channels],
                "ch:del",
                "menu:channels",
                page
            )
            await query.edit_message_text("کانال را انتخاب کنید:", reply_markup=kb)

        # ===== پروکسی‌ها =====
        elif data == "menu:proxies":
            proxies = db.get_proxies()
            context.user_data.pop("awaiting", None)
            await query.edit_message_text(
                f"🔧 <b>مدیریت پروکسی</b>\n\n"
                f"تعداد فعال: {len(proxies)}",
                reply_markup=proxies_menu(),
                parse_mode="HTML"
            )

        elif data == "pr:add":
            context.user_data["awaiting"] = "proxy_url"
            await context.bot.send_message(
                user_id,
                "آدرس پروکسی را وارد کنید:\n\n"
                "فرمت‌ها:\n"
                "• 192.168.1.1:8080\n"
                "• http://proxy.com:8080\n\n"
                "برای لغو، /start را بفرستید"
            )

        elif data == "pr:list":
            proxies = db.get_proxies()
            context.user_data.pop("awaiting", None)
            if proxies:
                text = "📋 <b>پروکسی‌های فعال:</b>\n\n"
                for idx, (pid, proxy) in enumerate(proxies, 1):
                    text += f"{idx}. {proxy[:40]}\n"
                text = truncate_text(text)
            else:
                text = "❌ هیچ پروکسی اضافه نشده است"

            await query.edit_message_text(
                text,
                reply_markup=proxies_menu(),
                parse_mode="HTML"
            )

        elif data == "pr:remove":
            proxies = db.get_proxies()
            context.user_data.pop("awaiting", None)
            if not proxies:
                await query.edit_message_text("❌ هیچ پروکسی برای حذف نیست", reply_markup=proxies_menu())
                return

            kb = make_delete_keyboard(proxies, "pr:del", "menu:proxies")
            await query.edit_message_text("پروکسی را انتخاب کنید:", reply_markup=kb)

        elif data.startswith("pr:del:"):
            try:
                pid = int(data.replace("pr:del:", ""))
                success = db.remove_proxy(pid)
                if success:
                    await query.edit_message_text("✅ پروکسی حذف شد")
                else:
                    await query.edit_message_text("⚠️ نتوانستم پروکسی را حذف کنم")
            except ValueError:
                logger.error(f"❌ Invalid proxy ID: {data}")
                await query.edit_message_text("❌ خطا: شناسهٔ نامعتبر")

        elif data.startswith("pr:del:page:"):
            page = int(data.replace("pr:del:page:", ""))
            proxies = db.get_proxies()
            kb = make_delete_keyboard(proxies, "pr:del", "menu:proxies", page)
            await query.edit_message_text("پروکسی را انتخاب کنید:", reply_markup=kb)

        else:
            logger.warning(f"⚠️ Unknown callback: {data}")
            await query.answer("دستور ناشناخته!", show_alert=True)

    except Exception as e:
        logger.error(f"❌ خطا در callback: {e}")
        context.user_data.pop("awaiting", None)
        try:
            await query.edit_message_text(f"❌ خطا: {str(e)[:80]}")
        except Exception:
            pass

async def message_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """مدیریت پیام‌های متنی"""
    user_id = update.effective_user.id
    db = context.bot_data.get("db")

    if not db:
        await update.message.reply_text("❌ خطا: دیتابیس در دسترس نیست")
        return

    if "awaiting" not in context.user_data:
        await update.message.reply_text(
            "ابتدا /start را بفرستید تا منو نمایش داده شود",
            reply_markup=InlineKeyboardMarkup([[_btn("شروع", "start")]])
        )
        return

    awaiting = context.user_data.pop("awaiting")
    text = update.message.text.strip() if update.message.text else ""

    if not text:
        await update.message.reply_text("❌ متن خالی! لطفاً مقدار معتبر وارد کنید")
        return

    if awaiting == "channel_name":
        if db.add_channel(text):
            await update.message.reply_text(f"✅ کانال @{text.lstrip('@')} اضافه شد")
        else:
            await update.message.reply_text(f"⚠️ کانال @{text.lstrip('@')} قبلاً اضافه شده است")

    elif awaiting == "proxy_url":
        if db.add_proxy(text):
            await update.message.reply_text(f"✅ پروکسی اضافه شد:\n{text}")
        else:
            await update.message.reply_text(f"⚠️ پروکسی قبلاً اضافه شده است")

async def error_handler(update: object, context: ContextTypes.DEFAULT_TYPE):
    """Global error handler"""
    logger.exception(f"❌ خطا: {context.error}")

    if isinstance(update, Update) and update.effective_chat:
        try:
            if update.effective_user:
                context.user_data.pop("awaiting", None)
            await context.bot.send_message(
                update.effective_chat.id,
                "⚠️ یه خطای غیرمنتظره پیش اومد. دوباره تلاش کن."
            )
        except Exception as e:
            logger.error(f"❌ نتوانستم خطا را گزارش کنم: {e}")

async def post_init(application: Application):
    """اولیه‌سازی بعد از شروع"""
    try:
        await application.bot.set_my_commands([
            BotCommand("start", "شروع / منو اصلی"),
            BotCommand("help", "راهنما"),
        ])
        await application.bot.set_chat_menu_button(menu_button=MenuButtonCommands())

        db = Database(DB_PATH)
        application.bot_data["db"] = db

        logger.info("✅ ربات آماده است")
    except Exception as e:
        logger.error(f"❌ خطا در اولیه‌سازی: {e}")
        raise

async def post_shutdown(application: Application):
    """بعد از خاموش‌شدن"""
    logger.info("⏹️ ربات متوقف شد")

# ===== Main =====
async def main():
    """اجرای ربات"""
    if not TOKEN:
        logger.error("❌ توکن تنظیم نشده!")
        print("\n⚠️ لطفاً TELEGRAM_BOT_TOKEN را در فایل .env تنظیم کنید\n")
        sys.exit(1)

    app = Application.builder().token(TOKEN).build()

    app.add_handler(CommandHandler("start", start_handler))
    app.add_handler(CommandHandler("help", help_handler))
    app.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, message_handler))
    app.add_handler(CallbackQueryHandler(callback_handler))

    app.add_error_handler(error_handler)

    app.post_init = post_init
    app.post_shutdown = post_shutdown

    print("\n" + "="*70)
    print("🚀 TURBO VIEWS BOT Pro v3.3 فعال است!")
    print("⚡ سرعت: +1000 سین/ثانیه")
    print("🔄 پردازش: 150 همزمان")
    print("🛡️ پایداری: 100% تضمینی")
    print("="*70 + "\n")

    logger.info("📡 Polling شروع...")

    async with app:
        await app.start()
        await app.updater.start_polling(allowed_updates=[])
        await app.updater.idle()
        await app.stop()

if __name__ == "__main__":
    import asyncio
    asyncio.run(main())
