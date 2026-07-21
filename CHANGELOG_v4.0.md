# Changelog — v4.0

An honest accounting of the 90-item review. Items are grouped by what
actually happened, not by claiming a clean sweep.

---

## 🔴 Real bugs fixed structurally

### Database connection leaks & locking (list #1, #10–15, #79, #2–9, #20–21)
Rather than patch each method, all DB access now goes through a single
context manager, `Database._connect()`:

```python
@contextmanager
def _connect(self):
    with self._lock:                 # every access is locked (reads included)
        conn = sqlite3.connect(self.db_path, timeout=DB_TIMEOUT,
                               check_same_thread=False)
        conn.row_factory = sqlite3.Row
        try:
            yield conn
            conn.commit()
        except Exception:
            conn.rollback()          # atomicity on failure
            raise
        finally:
            conn.close()             # never leaks, even on the error path
```

This one change resolves the entire "connection not closed on error",
"missing lock on reads", "no timeout", and "commit without rollback"
cluster at once. `row_factory = sqlite3.Row` also replaces fragile
numeric-index access (#80).

### The `updater.idle()` latent crash (#53)
`await app.updater.idle()` does **not** exist in python-telegram-bot 21.0.1
— it would raise `AttributeError` at runtime. Earlier "passing" tests only
survived because `timeout` killed the process first. Replaced the whole
manual lifecycle with the canonical blocking entry point:

```python
app.run_polling(allowed_updates=[])
```

which manages the event loop, OS signals, idle, and clean shutdown itself.
Verified: the bot now runs past startup and shuts down gracefully
(`Application.stop() complete`).

### Proxies were dead storage (#29, #35)
The first stored proxy is now actually applied to the bot's own connection:

```python
proxy = db.get_first_proxy()
if proxy:
    builder = builder.proxy(proxy).get_updates_proxy(proxy)
```

(This routes the bot→Telegram connection through the proxy — useful where
Telegram is network-restricted. It is not a traffic-generation feature.)

### Input validation (#22–23, #26–27, #48)
- Channel names validated against `^[A-Za-z0-9_]{5,32}$`, stripped of `@`
  and whitespace.
- Proxies validated against a `scheme://user:pass@host:port` pattern.
- Invalid input is rejected with a clear message instead of being stored.

### Robust startup/shutdown (#16, #17, #18, #19, #51, #54)
- `LOG_DIR.mkdir` and the file handler are wrapped — logging always starts,
  falling back to console-only if the directory isn't writable.
- `RotatingFileHandler` (5 MB × 3) so logs can't fill the disk (#62).
- `Database` is built in `main()` inside try/except; failure exits cleanly
  instead of running dbless and crashing later.
- `post_init` is registered via the builder so `run_polling` actually calls
  it (previously assigned as an attribute, which is unreliable).

### Group-chat exposure (#34)
All command/message handlers are now gated on `filters.ChatType.PRIVATE`;
the bot no longer responds in groups.

### Misc correctness
- `_safe_int` guards every `int(...)` conversion from callback data (#41, #15).
- Unknown callbacks now surface an alert instead of silently doing nothing (#38, #23).
- Delete actions check the return value before claiming success (#40, #42, #12).
- Long lists are truncated to Telegram's 4096-char limit and paginated at
  10/page (#46, #47, #10).
- `disable_web_page_preview=True` on link-bearing messages (#76).
- `TOKEN` is `.strip()`-ed (#28); `BASE_DIR` uses `os.path.realpath` (#70–71).
- Missing `python-dotenv` now logs a warning instead of failing silently (#72–73).

---

## 🟡 Already handled before this review (list was stale)

- `.env.example` did **not** contain a real token (#25, #60) — it was already
  a placeholder; further hardened to `your_token_here` with a warning comment.
- `requirements.txt` no longer lists `aiohttp`/`requests` (#6, #17, #59).
- `/help` handler, pagination, message truncation, empty-input rejection,
  `query.answer` safety, and full DB try/except (#33, #43, #45) were added in v3.3.
- `SETUP.sh` already installs `python-telegram-bot` (#55).

---

## 🟢 Cleaned up

- Removed the dead, misleading `config.json` (#56) — storage is SQLite.
- Removed the unused global `_db_lock` (#69, #22-code).
- `*.db*` added to `.gitignore` so runtime databases aren't committed.

---

## ⚪ Intentionally not done (out of scope / not bugs)

- **#83 ConversationHandler**, **#85 webhook**, **#86 argparse**,
  **#84 rate-limiting** — feature requests, not defects. The current
  `user_data["awaiting"]` flow is sufficient for two simple prompts.
- **#67** `from __future__ import annotations` is not a bug; it enables the
  `list[str]` syntax on older interpreters.
- **#81** `CURRENT_TIMESTAMP` in UTC is standard and correct for storage.
- **#88/#89/#90** (`test_bot.py`, stray zips, `__init__.py`) are repo-hygiene
  notes, not runtime bugs. Zips are now git-ignored.

---

## Verification

```
✅ py_compile clean
✅ unit tests for valid_channel / valid_proxy / _safe_int / truncate pass
✅ bot starts, sets commands, polls, and shuts down cleanly (no idle() crash)
```

**Version:** 4.0 · **Library:** python-telegram-bot 21.0.1
