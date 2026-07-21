#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
🚀 TELEGRAM TURBO BOT Pro v4.0 - Professional Edition
ربات مدیریت کانال و پروکسی تلگرام

معماری:
- Application base (telegram.ext)
- Thread-safe database via a single context-managed connection helper
  (این الگو نشتِ اتصال و نبودِ قفل را به‌صورت ساختاری حذف می‌کند)
- Optional proxy routing for the bot's own connection
- Input validation
- Global error handling
- Rotating logs
"""

from __future__ import annotations

import logging
import logging.handlers
import os
import re
import sqlite3
import sys
import threading
from contextlib import contextmanager
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
from telegram.error import BadRequest

# ===== بارگذاری متغیرهای محیطی =====
_DOTENV_LOADED = False
try:
    from dotenv import load_dotenv
    load_dotenv()
    _DOTENV_LOADED = True
except ImportError:
    pass

# ===== مسیرها (با realpath برای مقاومت در برابر symlink) =====
BASE_DIR = Path(os.path.realpath(__file__)).parent
LOG_DIR = BASE_DIR / "logs"
DB_PATH = BASE_DIR / "data.db"

try:
    LOG_DIR.mkdir(exist_ok=True)
    _LOG_TARGET = LOG_DIR / "bot.log"
except Exception as e:  # noqa: BLE001 - عمداً همه خطاها گرفته می‌شوند تا لاگ همیشه راه بیفتد
    print(f"⚠️ نتوانستم پوشهٔ logs را ایجاد کنم، از مسیر جاری استفاده می‌شود: {e}")
    _LOG_TARGET = Path("bot.log")

# ===== تنظیمات لاگ (چرخشی، تا دیسک پر نشود) =====
_handlers: list[logging.Handler] = [logging.StreamHandler(sys.stdout)]
try:
    _handlers.append(
        logging.handlers.RotatingFileHandler(
            _LOG_TARGET, maxBytes=5 * 1024 * 1024, backupCount=3, encoding="utf-8"
        )
    )
except Exception as e:  # noqa: BLE001
    print(f"⚠️ FileHandler لاگ راه‌اندازی نشد (فقط کنسول): {e}")

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=_handlers,
)
logger = logging.getLogger(__name__)

if not _DOTENV_LOADED:
    logger.warning("⚠️ python-dotenv نصب نیست؛ متغیرها فقط از محیط سیستم خوانده می‌شوند")

# ===== ثابت‌ها =====
TOKEN = os.getenv("TELEGRAM_BOT_TOKEN", "").strip()
ITEMS_PER_PAGE = 10
MAX_MESSAGE_LENGTH = 4096
_SAFE_TEXT_LEN = MAX_MESSAGE_LENGTH - 100
DB_TIMEOUT = 10.0  # ثانیه؛ به جای انتظار نامحدود روی قفل دیتابیس

# اعتبارسنجی: نام کاربری کانال تلگرام ۵ تا ۳۲ کاراکتر [a-zA-Z0-9_]
_CHANNEL_RE = re.compile(r"^[A-Za-z0-9_]{5,32}$")
# پروکسی: scheme://host:port یا host:port
_PROXY_RE = re.compile(
    r"^(?:(?:https?|socks5h?)://)?"        # scheme اختیاری
    r"(?:[^\s:@/]+(?::[^\s:@/]+)?@)?"      # user:pass اختیاری
    r"[A-Za-z0-9._-]+:\d{2,5}$"            # host:port
)


# ===== Database =====
class Database:
    """
    مدیریت ایمن دیتابیس.

    تمام دسترسی‌ها از طریق context manager ‏_connect عبور می‌کنند که هم قفل را
    می‌گیرد و هم اتصال را در finally می‌بندد. این کار کلاسِ باگ‌های «نشت اتصال در
    مسیر خطا» و «نبود قفل» را یک‌جا حذف می‌کند.
    """

    def __init__(self, db_path: Path):
        self.db_path = db_path
        self._lock = threading.Lock()
        self._init_db()

    @contextmanager
    def _connect(self):
        """اتصال قفل‌شده و تضمین‌شده به بسته‌شدن."""
        with self._lock:
            conn = sqlite3.connect(
                self.db_path, timeout=DB_TIMEOUT, check_same_thread=False
            )
            conn.row_factory = sqlite3.Row
            try:
                yield conn
                conn.commit()
            except Exception:
                conn.rollback()
                raise
            finally:
                conn.close()

    def _init_db(self):
        try:
            with self._connect() as conn:
                conn.execute("PRAGMA journal_mode=WAL")
                conn.execute("""
                    CREATE TABLE IF NOT EXISTS channels (
                        id INTEGER PRIMARY KEY,
                        username TEXT UNIQUE NOT NULL,
                        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                """)
                conn.execute("""
                    CREATE TABLE IF NOT EXISTS proxies (
                        id INTEGER PRIMARY KEY,
                        proxy_url TEXT UNIQUE NOT NULL,
                        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                """)
            logger.info("✅ دیتابیس آماده است")
        except Exception as e:  # noqa: BLE001
            logger.error(f"❌ خطا در اولیه‌سازی دیتابیس: {e}")
            raise

    def add_channel(self, username: str) -> bool:
        username = username.lstrip("@").strip()
        try:
            with self._connect() as conn:
                conn.execute("INSERT INTO channels (username) VALUES (?)", (username,))
            logger.info(f"✅ کانال اضافه شد: {username}")
            return True
        except sqlite3.IntegrityError:
            logger.warning(f"⚠️ کانال تکراری: {username}")
            return False
        except Exception as e:  # noqa: BLE001
            logger.error(f"❌ خطا در افزودن کانال: {e}")
            return False

    def remove_channel(self, username: str) -> bool:
        username = username.lstrip("@").strip()
        try:
            with self._connect() as conn:
                cur = conn.execute("DELETE FROM channels WHERE username = ?", (username,))
                success = cur.rowcount > 0
            if success:
                logger.info(f"✅ کانال حذف شد: {username}")
            return success
        except Exception as e:  # noqa: BLE001
            logger.error(f"❌ خطا در حذف کانال: {e}")
            return False

    def get_channels(self) -> list[str]:
        try:
            with self._connect() as conn:
                rows = conn.execute(
                    "SELECT username FROM channels ORDER BY added_at DESC"
                ).fetchall()
            return [r["username"] for r in rows]
        except Exception as e:  # noqa: BLE001
            logger.error(f"❌ خطا در خواندن کانال‌ها: {e}")
            return []

    def add_proxy(self, proxy: str) -> bool:
        proxy = proxy.strip()
        try:
            with self._connect() as conn:
                conn.execute("INSERT INTO proxies (proxy_url) VALUES (?)", (proxy,))
            logger.info(f"✅ پروکسی اضافه شد: {proxy}")
            return True
        except sqlite3.IntegrityError:
            logger.warning(f"⚠️ پروکسی تکراری: {proxy}")
            return False
        except Exception as e:  # noqa: BLE001
            logger.error(f"❌ خطا در افزودن پروکسی: {e}")
            return False

    def remove_proxy(self, proxy_id: int) -> bool:
        try:
            with self._connect() as conn:
                cur = conn.execute("DELETE FROM proxies WHERE id = ?", (proxy_id,))
                success = cur.rowcount > 0
            if success:
                logger.info(f"✅ پروکسی حذف شد (ID: {proxy_id})")
            return success
        except Exception as e:  # noqa: BLE001
            logger.error(f"❌ خطا در حذف پروکسی: {e}")
            return False

    def get_proxies(self) -> list[tuple[int, str]]:
        try:
            with self._connect() as conn:
                rows = conn.execute(
                    "SELECT id, proxy_url FROM proxies ORDER BY added_at DESC"
                ).fetchall()
            return [(r["id"], r["proxy_url"]) for r in rows]
        except Exception as e:  # noqa: BLE001
            logger.error(f"❌ خطا در خواندن پروکسی‌ها: {e}")
            return []

    def get_first_proxy(self) -> Optional[str]:
        proxies = self.get_proxies()
        return proxies[0][1] if proxies else None


# ===== اعتبارسنجی ورودی =====
def valid_channel(name: str) -> bool:
    return bool(_CHANNEL_RE.match(name.lstrip("@").strip()))


def valid_proxy(url: str) -> bool:
    return bool(_PROXY_RE.match(url.strip()))


def normalize_proxy(url: str) -> str:
    """
    افزودن scheme پیش‌فرض اگر کاربر فقط host:port داده باشد.
    httpx برای پروکسی حتماً scheme می‌خواهد، وگرنه هنگام build کرش می‌کند.
    """
    url = url.strip()
    if "://" not in url:
        return "http://" + url
    return url


# ===== Keyboards =====
def _btn(text: str, data: str) -> InlineKeyboardButton:
    return InlineKeyboardButton(text, callback_data=data)


def main_menu() -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup([
        [_btn("📋 کانال‌ها", "menu:channels")],
        [_btn("🔧 پروکسی", "menu:proxies")],
        [_btn("❓ راهنما", "help")],
    ])


def channels_menu() -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup([
        [_btn("➕ افزودن", "ch:add"), _btn("📋 لیست", "ch:list")],
        [_btn("❌ حذف", "ch:remove"), _btn("« برگشت", "start")],
    ])


def proxies_menu() -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup([
        [_btn("➕ افزودن", "pr:add"), _btn("📋 لیست", "pr:list")],
        [_btn("❌ حذف", "pr:remove"), _btn("« برگشت", "start")],
    ])


def make_delete_keyboard(items: list[tuple], prefix: str, back: str, page: int = 1) -> InlineKeyboardMarkup:
    """صفحه‌بندی برای دور زدنِ محدودیت ۱۰۰ دکمه و پیام‌های بلند."""
    total = len(items)
    pages = max(1, (total + ITEMS_PER_PAGE - 1) // ITEMS_PER_PAGE)
    page = max(1, min(page, pages))
    start = (page - 1) * ITEMS_PER_PAGE
    chunk = items[start:start + ITEMS_PER_PAGE]

    buttons = []
    for item_id, item_name in chunk:
        if prefix == "ch:del":
            buttons.append([_btn(f"❌ @{item_name}", f"{prefix}:{item_name}")])
        else:
            buttons.append([_btn(f"❌ {item_name[:30]}", f"{prefix}:{item_id}")])

    nav = []
    if page > 1:
        nav.append(_btn("◀️ قبلی", f"{prefix}:page:{page - 1}"))
    if start + ITEMS_PER_PAGE < total:
        nav.append(_btn("بعدی ▶️", f"{prefix}:page:{page + 1}"))
    if nav:
        buttons.append(nav)

    buttons.append([_btn("« برگشت", back)])
    return InlineKeyboardMarkup(buttons)


def truncate(text: str, max_len: int = _SAFE_TEXT_LEN) -> str:
    if len(text) > max_len:
        return text[:max_len] + "\n\n… (لیست بلندتر از حد نمایش است)"
    return text


# ===== Handlers =====
HELP_TEXT = (
    "<b>📖 راهنمای استفاده</b>\n\n"
    "<b>📋 کانال‌ها:</b> افزودن، لیست و حذف کانال‌ها\n"
    "<b>🔧 پروکسی:</b> افزودن، لیست و حذف پروکسی‌ها\n\n"
    "<b>💡 نکات:</b>\n"
    "• برای لغو هر عملیات، /start را بفرستید\n"
    "• نام کانال: ۵ تا ۳۲ کاراکتر انگلیسی، عدد یا _\n"
    "• پروکسی: مثلاً 1.2.3.4:8080 یا socks5://host:1080\n"
    "• داده‌ها در دیتابیس SQLite ذخیره می‌شوند"
)

WELCOME = (
    "🚀 <b>ربات مدیریت کانال و پروکسی v4.0</b>\n\n"
    "گزینهٔ مورد نظر را انتخاب کنید:"
)


async def start_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    context.user_data.pop("awaiting", None)
    if update.message:
        await update.message.reply_text(WELCOME, reply_markup=main_menu(), parse_mode="HTML")


async def help_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    context.user_data.pop("awaiting", None)
    if update.message:
        await update.message.reply_text(
            HELP_TEXT,
            reply_markup=InlineKeyboardMarkup([[_btn("« برگشت", "start")]]),
            parse_mode="HTML",
            disable_web_page_preview=True,
        )


async def callback_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.callback_query
    if query is None:
        return

    try:
        await query.answer()
    except Exception as e:  # noqa: BLE001
        logger.warning(f"⚠️ query.answer ناموفق: {e}")

    data = query.data or ""
    user = update.effective_user
    if user is None:
        return
    user_id = user.id

    db: Optional[Database] = context.bot_data.get("db")
    if db is None:
        try:
            await query.edit_message_text("❌ دیتابیس در دسترس نیست")
        except Exception:  # noqa: BLE001
            pass
        return

    try:
        if data == "start":
            context.user_data.pop("awaiting", None)
            await query.edit_message_text(WELCOME, reply_markup=main_menu(), parse_mode="HTML")

        elif data == "help":
            context.user_data.pop("awaiting", None)
            await query.edit_message_text(
                HELP_TEXT,
                reply_markup=InlineKeyboardMarkup([[_btn("« برگشت", "start")]]),
                parse_mode="HTML",
                disable_web_page_preview=True,
            )

        # ===== کانال‌ها =====
        elif data == "menu:channels":
            context.user_data.pop("awaiting", None)
            await query.edit_message_text(
                "📋 <b>مدیریت کانال‌ها</b>", reply_markup=channels_menu(), parse_mode="HTML"
            )

        elif data == "ch:add":
            context.user_data["awaiting"] = "channel_name"
            await context.bot.send_message(
                user_id,
                "نام کانال را وارد کنید (۵ تا ۳۲ کاراکتر انگلیسی/عدد/_):\n"
                "مثال: @mychannel\n\nبرای لغو، /start را بفرستید",
            )

        elif data == "ch:list":
            context.user_data.pop("awaiting", None)
            channels = db.get_channels()
            if channels:
                text = "📋 <b>کانال‌های فعال:</b>\n\n" + "".join(f"• @{c}\n" for c in channels)
            else:
                text = "❌ هیچ کانالی اضافه نشده است"
            await query.edit_message_text(truncate(text), reply_markup=channels_menu(), parse_mode="HTML")

        elif data == "ch:remove":
            context.user_data.pop("awaiting", None)
            channels = db.get_channels()
            if not channels:
                await query.edit_message_text("❌ هیچ کانالی برای حذف نیست", reply_markup=channels_menu())
                return
            kb = make_delete_keyboard([(c, c) for c in channels], "ch:del", "menu:channels")
            await query.edit_message_text("کانال را برای حذف انتخاب کنید:", reply_markup=kb)

        elif data.startswith("ch:del:page:"):
            page = _safe_int(data.rsplit(":", 1)[-1], 1)
            channels = db.get_channels()
            kb = make_delete_keyboard([(c, c) for c in channels], "ch:del", "menu:channels", page)
            await query.edit_message_text("کانال را برای حذف انتخاب کنید:", reply_markup=kb)

        elif data.startswith("ch:del:"):
            ch = data.replace("ch:del:", "")
            if db.remove_channel(ch):
                await query.edit_message_text(f"✅ کانال @{ch} حذف شد", reply_markup=channels_menu())
            else:
                await query.edit_message_text(f"⚠️ کانال @{ch} پیدا نشد", reply_markup=channels_menu())

        # ===== پروکسی‌ها =====
        elif data == "menu:proxies":
            context.user_data.pop("awaiting", None)
            proxies = db.get_proxies()
            note = ""
            if proxies:
                note = "\n\nℹ️ اولین پروکسی هنگام راه‌اندازی برای اتصال ربات استفاده می‌شود."
            await query.edit_message_text(
                f"🔧 <b>مدیریت پروکسی</b>\n\nتعداد فعال: {len(proxies)}{note}",
                reply_markup=proxies_menu(),
                parse_mode="HTML",
            )

        elif data == "pr:add":
            context.user_data["awaiting"] = "proxy_url"
            await context.bot.send_message(
                user_id,
                "آدرس پروکسی را وارد کنید:\n\n"
                "فرمت‌ها:\n• 1.2.3.4:8080\n• http://host:8080\n• socks5://host:1080\n\n"
                "برای لغو، /start را بفرستید",
            )

        elif data == "pr:list":
            context.user_data.pop("awaiting", None)
            proxies = db.get_proxies()
            if proxies:
                text = "📋 <b>پروکسی‌های فعال:</b>\n\n" + "".join(
                    f"{i}. {p}\n" for i, (_, p) in enumerate(proxies, 1)
                )
            else:
                text = "❌ هیچ پروکسی اضافه نشده است"
            await query.edit_message_text(truncate(text), reply_markup=proxies_menu(), parse_mode="HTML")

        elif data == "pr:remove":
            context.user_data.pop("awaiting", None)
            proxies = db.get_proxies()
            if not proxies:
                await query.edit_message_text("❌ هیچ پروکسی برای حذف نیست", reply_markup=proxies_menu())
                return
            kb = make_delete_keyboard(proxies, "pr:del", "menu:proxies")
            await query.edit_message_text("پروکسی را برای حذف انتخاب کنید:", reply_markup=kb)

        elif data.startswith("pr:del:page:"):
            page = _safe_int(data.rsplit(":", 1)[-1], 1)
            proxies = db.get_proxies()
            kb = make_delete_keyboard(proxies, "pr:del", "menu:proxies", page)
            await query.edit_message_text("پروکسی را برای حذف انتخاب کنید:", reply_markup=kb)

        elif data.startswith("pr:del:"):
            pid = _safe_int(data.replace("pr:del:", ""), None)
            if pid is None:
                await query.edit_message_text("❌ شناسهٔ نامعتبر", reply_markup=proxies_menu())
            elif db.remove_proxy(pid):
                await query.edit_message_text("✅ پروکسی حذف شد", reply_markup=proxies_menu())
            else:
                await query.edit_message_text("⚠️ پروکسی پیدا نشد", reply_markup=proxies_menu())

        else:
            logger.warning(f"⚠️ callback ناشناخته: {data}")
            await query.answer("دستور ناشناخته", show_alert=True)

    except BadRequest as e:
        # ویرایش پیام به محتوای یکسان (مثلاً کلیک دوباره روی همان صفحه) خطا نیست.
        if "not modified" in str(e).lower():
            return
        logger.exception(f"❌ خطا در callback: {e}")
        context.user_data.pop("awaiting", None)
        try:
            await query.edit_message_text(f"❌ خطا: {str(e)[:80]}")
        except Exception:  # noqa: BLE001
            pass
    except Exception as e:  # noqa: BLE001
        logger.exception(f"❌ خطا در callback: {e}")
        context.user_data.pop("awaiting", None)
        try:
            await query.edit_message_text(f"❌ خطا: {str(e)[:80]}")
        except Exception:  # noqa: BLE001
            pass


async def message_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    if update.message is None or update.effective_user is None:
        return

    db: Optional[Database] = context.bot_data.get("db")
    if db is None:
        await update.message.reply_text("❌ دیتابیس در دسترس نیست")
        return

    if "awaiting" not in context.user_data:
        await update.message.reply_text(
            "ابتدا /start را بفرستید تا منو نمایش داده شود",
            reply_markup=InlineKeyboardMarkup([[_btn("شروع", "start")]]),
        )
        return

    awaiting = context.user_data.pop("awaiting")
    text = (update.message.text or "").strip()

    if not text:
        await update.message.reply_text("❌ متن خالی است؛ لطفاً مقدار معتبر وارد کنید")
        return

    if awaiting == "channel_name":
        if not valid_channel(text):
            await update.message.reply_text(
                "❌ نام کانال نامعتبر است.\nباید ۵ تا ۳۲ کاراکتر انگلیسی، عدد یا _ باشد."
            )
            return
        name = text.lstrip("@").strip()
        if db.add_channel(name):
            await update.message.reply_text(f"✅ کانال @{name} اضافه شد", reply_markup=main_menu())
        else:
            await update.message.reply_text(f"⚠️ کانال @{name} قبلاً اضافه شده است", reply_markup=main_menu())

    elif awaiting == "proxy_url":
        if not valid_proxy(text):
            await update.message.reply_text(
                "❌ فرمت پروکسی نامعتبر است.\nمثال معتبر: 1.2.3.4:8080 یا socks5://host:1080"
            )
            return
        if db.add_proxy(text):
            await update.message.reply_text(f"✅ پروکسی اضافه شد:\n{text}", reply_markup=main_menu())
        else:
            await update.message.reply_text("⚠️ پروکسی قبلاً اضافه شده است", reply_markup=main_menu())


async def error_handler(update: object, context: ContextTypes.DEFAULT_TYPE):
    logger.exception(f"❌ خطای سراسری: {context.error}")
    if isinstance(update, Update) and update.effective_chat is not None:
        try:
            if update.effective_user is not None:
                context.user_data.pop("awaiting", None)
            await context.bot.send_message(
                update.effective_chat.id,
                "⚠️ خطای غیرمنتظره‌ای رخ داد. دوباره تلاش کنید.",
            )
        except Exception as e:  # noqa: BLE001
            logger.error(f"❌ ارسال پیام خطا ناموفق بود: {e}")


async def post_init(application: Application):
    try:
        await application.bot.set_my_commands([
            BotCommand("start", "شروع / منو اصلی"),
            BotCommand("help", "راهنما"),
        ])
        await application.bot.set_chat_menu_button(menu_button=MenuButtonCommands())
        logger.info("✅ ربات آماده است")
    except Exception as e:  # noqa: BLE001
        logger.error(f"❌ خطا در post_init: {e}")
        raise


async def post_shutdown(application: Application):
    logger.info("⏹️ ربات متوقف شد")


# ===== ابزار =====
def _safe_int(value: str, default):
    try:
        return int(value)
    except (ValueError, TypeError):
        return default


# ===== Main =====
def main():
    if not TOKEN:
        logger.error("❌ توکن تنظیم نشده!")
        print("\n⚠️ لطفاً TELEGRAM_BOT_TOKEN را در فایل .env تنظیم کنید\n")
        sys.exit(1)

    # دیتابیس را پیش از ساخت Application می‌سازیم تا بتوانیم پروکسی را از آن بخوانیم.
    try:
        db = Database(DB_PATH)
    except Exception as e:  # noqa: BLE001
        logger.error(f"❌ دیتابیس راه‌اندازی نشد: {e}")
        sys.exit(1)

    # اگر پروکسی‌ای ثبت شده باشد، اتصالِ خودِ ربات به تلگرام از آن عبور می‌کند.
    raw_proxy = db.get_first_proxy()
    proxy = normalize_proxy(raw_proxy) if raw_proxy else None

    def _build(with_proxy: Optional[str]) -> Application:
        # post_init/post_shutdown از طریق builder ثبت می‌شوند تا run_polling آن‌ها را صدا بزند.
        b = (
            Application.builder()
            .token(TOKEN)
            .post_init(post_init)
            .post_shutdown(post_shutdown)
        )
        if with_proxy:
            b = b.proxy(with_proxy).get_updates_proxy(with_proxy)
        return b.build()

    try:
        app = _build(proxy)
        if proxy:
            logger.info(f"🌐 استفاده از پروکسی برای اتصال ربات: {proxy}")
    except Exception as e:  # noqa: BLE001 - پروکسی نامعتبر نباید مانع راه‌اندازی شود
        logger.error(f"❌ پروکسی نامعتبر بود ({e})؛ اتصال مستقیم استفاده می‌شود")
        proxy = None
        app = _build(None)

    app.bot_data["db"] = db

    # فقط چت خصوصی؛ ربات در گروه‌ها پاسخ نمی‌دهد.
    private = filters.ChatType.PRIVATE
    app.add_handler(CommandHandler("start", start_handler, filters=private))
    app.add_handler(CommandHandler("help", help_handler, filters=private))
    app.add_handler(MessageHandler(private & filters.TEXT & ~filters.COMMAND, message_handler))
    app.add_handler(CallbackQueryHandler(callback_handler))
    app.add_error_handler(error_handler)

    print("\n" + "=" * 70)
    print("🚀 TELEGRAM BOT Pro v4.0 فعال است!")
    print(f"🌐 پروکسی: {'فعال (' + proxy + ')' if proxy else 'غیرفعال (اتصال مستقیم)'}")
    print("=" * 70 + "\n")
    logger.info("📡 Polling شروع...")

    # run_polling حلقهٔ رویداد، سیگنال‌ها، idle و خاموش‌سازی تمیز را خودش مدیریت می‌کند.
    app.run_polling(allowed_updates=[])


if __name__ == "__main__":
    try:
        main()
    except (KeyboardInterrupt, SystemExit):
        logger.info("👋 خروج")
