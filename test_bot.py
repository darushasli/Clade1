#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
تست جامع ربات v4.0 — دیتابیس، اعتبارسنجی، صفحه‌بندی و تمام هندلرها.
اجرا:  TELEGRAM_BOT_TOKEN=x python3 test_bot.py
"""
import asyncio
import os
import tempfile
from pathlib import Path
from unittest.mock import AsyncMock, MagicMock

os.environ.setdefault("TELEGRAM_BOT_TOKEN", "test:token")

import telegram_turbo_pro_final as m  # noqa: E402

_passed = 0
_failed = 0


def check(name, cond):
    global _passed, _failed
    if cond:
        _passed += 1
        print(f"  ✅ {name}")
    else:
        _failed += 1
        print(f"  ❌ {name}")


# ===================== Database =====================
def test_database():
    print("\n== Database CRUD ==")
    tmp = Path(tempfile.mkdtemp()) / "t.db"
    db = m.Database(tmp)

    check("افزودن کانال", db.add_channel("mychannel") is True)
    check("افزودن کانال با @ نرمال می‌شود", db.add_channel("@second") is True)
    check("کانال تکراری رد می‌شود", db.add_channel("mychannel") is False)
    check("لیست ۲ کانال", len(db.get_channels()) == 2)
    check("حذف کانال موجود", db.remove_channel("mychannel") is True)
    check("حذف کانال ناموجود", db.remove_channel("ghost") is False)
    check("لیست بعد از حذف = ۱", len(db.get_channels()) == 1)

    check("افزودن پروکسی", db.add_proxy("1.2.3.4:8080") is True)
    check("پروکسی تکراری رد می‌شود", db.add_proxy("1.2.3.4:8080") is False)
    proxies = db.get_proxies()
    check("لیست ۱ پروکسی", len(proxies) == 1)
    check("get_first_proxy درست است", db.get_first_proxy() == "1.2.3.4:8080")
    pid = proxies[0][0]
    check("حذف پروکسی با id", db.remove_proxy(pid) is True)
    check("حذف پروکسی ناموجود", db.remove_proxy(99999) is False)
    check("get_first_proxy وقتی خالی است None", db.get_first_proxy() is None)

    # پایداری بین اتصال‌ها (WAL / دیتابیس واقعی)
    db2 = m.Database(tmp)
    check("داده‌ها پس از باز کردن مجدد باقی می‌مانند", len(db2.get_channels()) == 1)


# ===================== Validators =====================
def test_validators():
    print("\n== Validators ==")
    check("کانال معتبر", m.valid_channel("@good_name"))
    check("کانال کوتاه رد", not m.valid_channel("abc"))
    check("کانال با فاصله رد", not m.valid_channel("bad name"))
    check("کانال با - رد", not m.valid_channel("bad-name"))
    check("پروکسی host:port", m.valid_proxy("1.2.3.4:8080"))
    check("پروکسی http", m.valid_proxy("http://h:8080"))
    check("پروکسی socks5 با auth", m.valid_proxy("socks5://u:p@h:1080"))
    check("پروکسی بدون پورت رد", not m.valid_proxy("1.2.3.4"))
    check("پروکسی چرت رد", not m.valid_proxy("hello"))


def test_normalize_proxy():
    print("\n== normalize_proxy ==")
    check("host:port -> http://", m.normalize_proxy("1.2.3.4:8080") == "http://1.2.3.4:8080")
    check("scheme حفظ می‌شود", m.normalize_proxy("socks5://h:1080") == "socks5://h:1080")
    check("فاصله حذف می‌شود", m.normalize_proxy("  h:80 ") == "http://h:80")


# ===================== Pagination =====================
def test_pagination():
    print("\n== make_delete_keyboard pagination ==")
    # ۲۵۰ آیتم -> نباید از محدودیت ۱۰۰ دکمه عبور کند
    items = [(i, f"item{i}") for i in range(250)]
    kb = m.make_delete_keyboard(items, "pr:del", "menu:proxies", 1)
    rows = kb.inline_keyboard
    check("صفحه ۱: ۱۰ آیتم + nav + برگشت", len(rows) == 12)
    check("صفحه ۱ دکمهٔ «بعدی» دارد", any("بعدی" in b.text for r in rows for b in r))
    check("صفحه ۱ دکمهٔ «قبلی» ندارد", not any("قبلی" in b.text for r in rows for b in r))

    kb2 = m.make_delete_keyboard(items, "pr:del", "menu:proxies", 5)
    rows2 = kb2.inline_keyboard
    check("صفحه میانی هر دو nav را دارد",
          any("قبلی" in b.text for r in rows2 for b in r) and
          any("بعدی" in b.text for r in rows2 for b in r))

    # صفحهٔ خارج از محدوده clamp می‌شود
    kb3 = m.make_delete_keyboard(items, "pr:del", "menu:proxies", 9999)
    last_page_no_next = not any("بعدی" in b.text for r in kb3.inline_keyboard for b in r)
    check("صفحهٔ بیش از حد به آخرین صفحه clamp می‌شود", last_page_no_next)

    # لیست خالی
    kb4 = m.make_delete_keyboard([], "pr:del", "menu:proxies", 1)
    check("لیست خالی فقط دکمهٔ برگشت", len(kb4.inline_keyboard) == 1)


def test_truncate():
    print("\n== truncate ==")
    check("متن کوتاه دست‌نخورده", m.truncate("hi") == "hi")
    long = "x" * 5000
    out = m.truncate(long)
    check("متن بلند کوتاه می‌شود", len(out) < 4096)
    check("متن بلند نشانگر دارد", "حد نمایش" in out)


def test_safe_int():
    print("\n== _safe_int ==")
    check("عدد درست", m._safe_int("42", 1) == 42)
    check("غیرعدد -> default", m._safe_int("abc", 7) == 7)
    check("None -> default", m._safe_int(None, None) is None)


# ===================== Handlers (async, mocked) =====================
def _ctx(db):
    ctx = MagicMock()
    ctx.user_data = {}
    ctx.bot_data = {"db": db}
    ctx.bot = MagicMock()
    ctx.bot.send_message = AsyncMock()
    return ctx


def _update_message(text="hello", uid=1):
    upd = MagicMock()
    upd.message = MagicMock()
    upd.message.text = text
    upd.message.reply_text = AsyncMock()
    upd.effective_user = MagicMock()
    upd.effective_user.id = uid
    return upd


def _update_callback(data, uid=1):
    upd = MagicMock()
    upd.callback_query = MagicMock()
    upd.callback_query.data = data
    upd.callback_query.answer = AsyncMock()
    upd.callback_query.edit_message_text = AsyncMock()
    upd.callback_query.from_user = MagicMock()
    upd.callback_query.from_user.id = uid
    upd.effective_user = MagicMock()
    upd.effective_user.id = uid
    return upd


async def _run_handlers():
    print("\n== Handlers (mocked) ==")
    tmp = Path(tempfile.mkdtemp()) / "h.db"
    db = m.Database(tmp)

    # /start
    ctx = _ctx(db)
    upd = _update_message()
    await m.start_handler(upd, ctx)
    check("/start پاسخ می‌دهد", upd.message.reply_text.called)

    # /help
    upd = _update_message()
    await m.help_handler(upd, ctx)
    check("/help پاسخ می‌دهد", upd.message.reply_text.called)

    # message بدون awaiting -> راهنمای شروع
    ctx = _ctx(db)
    upd = _update_message("random")
    await m.message_handler(upd, ctx)
    args = upd.message.reply_text.call_args
    check("پیام بدون awaiting پاسخِ راهنما می‌دهد", "start" in str(args).lower() or "شروع" in str(args))

    # افزودن کانال معتبر
    ctx = _ctx(db)
    ctx.user_data["awaiting"] = "channel_name"
    upd = _update_message("validchannel")
    await m.message_handler(upd, ctx)
    check("کانال معتبر ذخیره شد", "validchannel" in db.get_channels())
    check("awaiting پس از پردازش پاک شد", "awaiting" not in ctx.user_data)

    # افزودن کانال نامعتبر -> رد
    ctx = _ctx(db)
    ctx.user_data["awaiting"] = "channel_name"
    upd = _update_message("no")  # کوتاه
    await m.message_handler(upd, ctx)
    check("کانال نامعتبر ذخیره نشد", "no" not in db.get_channels())

    # ورودی خالی
    ctx = _ctx(db)
    ctx.user_data["awaiting"] = "channel_name"
    upd = _update_message("   ")
    await m.message_handler(upd, ctx)
    check("ورودی خالی رد شد", True)

    # افزودن پروکسی نامعتبر
    ctx = _ctx(db)
    ctx.user_data["awaiting"] = "proxy_url"
    upd = _update_message("garbage")
    await m.message_handler(upd, ctx)
    check("پروکسی نامعتبر ذخیره نشد", len(db.get_proxies()) == 0)

    # افزودن پروکسی معتبر
    ctx = _ctx(db)
    ctx.user_data["awaiting"] = "proxy_url"
    upd = _update_message("5.6.7.8:3128")
    await m.message_handler(upd, ctx)
    check("پروکسی معتبر ذخیره شد", len(db.get_proxies()) == 1)

    # callback: menu navigation
    ctx = _ctx(db)
    upd = _update_callback("menu:channels")
    await m.callback_handler(upd, ctx)
    check("callback menu:channels ویرایش می‌کند", upd.callback_query.edit_message_text.called)

    # callback: ch:list
    upd = _update_callback("ch:list")
    await m.callback_handler(upd, ctx)
    check("callback ch:list ویرایش می‌کند", upd.callback_query.edit_message_text.called)

    # callback: ch:add -> awaiting تنظیم می‌شود
    ctx = _ctx(db)
    upd = _update_callback("ch:add")
    await m.callback_handler(upd, ctx)
    check("ch:add پیام راهنما می‌فرستد", ctx.bot.send_message.called)
    check("ch:add وضعیت awaiting می‌گذارد", ctx.user_data.get("awaiting") == "channel_name")

    # callback: حذف پروکسی با id نامعتبر
    ctx = _ctx(db)
    upd = _update_callback("pr:del:notanint")
    await m.callback_handler(upd, ctx)
    txt = str(upd.callback_query.edit_message_text.call_args)
    check("pr:del با id نامعتبر پیام خطا می‌دهد", "نامعتبر" in txt)

    # callback: ناشناخته
    ctx = _ctx(db)
    upd = _update_callback("totally:unknown")
    await m.callback_handler(upd, ctx)
    check("callback ناشناخته alert می‌دهد", upd.callback_query.answer.call_count >= 1)

    # callback بدون db
    ctx = _ctx(db)
    ctx.bot_data = {}
    upd = _update_callback("start")
    await m.callback_handler(upd, ctx)
    check("callback بدون db کرش نمی‌کند", True)

    # پاک‌شدن awaiting هنگام کلیک روی منوی دیگر (باگ #36 قدیمی)
    ctx = _ctx(db)
    ctx.user_data["awaiting"] = "channel_name"
    upd = _update_callback("menu:proxies")
    await m.callback_handler(upd, ctx)
    check("کلیک روی منوی دیگر awaiting را پاک می‌کند", "awaiting" not in ctx.user_data)


def main():
    test_database()
    test_validators()
    test_normalize_proxy()
    test_pagination()
    test_truncate()
    test_safe_int()
    asyncio.run(_run_handlers())

    print("\n" + "=" * 50)
    print(f"نتیجه: {_passed} پاس ✅   |   {_failed} ناموفق ❌")
    print("=" * 50)
    return 0 if _failed == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
