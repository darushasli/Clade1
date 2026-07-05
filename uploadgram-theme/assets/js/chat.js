/* UploadGram — support chat widget (auto-answers + escalation) */
(function () {
  'use strict';
  var root = document.getElementById('ug-chat');
  if (!root) { return; }

  var loggedIn = root.getAttribute('data-logged-in') === '1';
  var panelUrl = root.getAttribute('data-panel');
  var authUrl  = root.getAttribute('data-auth');
  var telegram = (root.getAttribute('data-telegram') || '').replace(/^@/, '');

  /* Edit these Q&A freely */
  var FAQS = [
    { q: 'چطور سفارش ثبت کنم؟', a: 'ابتدا وارد شوید و کیف پول را شارژ کنید، سپس در پنل کاربری بخش «خدمات مجازی» اپلیکیشن و سرویس را انتخاب و سفارش دهید.' },
    { q: 'چطور کیف پول را شارژ کنم؟', a: 'در پنل کاربری بخش «کیف پول» مبلغ را وارد کنید و از درگاه پرداخت، شارژ کنید.' },
    { q: 'سفارش ممبر چقدر طول می‌کشد؟', a: 'پس از پرداخت و افزودن ربات بررسی به‌عنوان ادمین کانال، ممبرگیری معمولاً از چند دقیقه تا چند ساعت انجام می‌شود.' },
    { q: 'شماره مجازی چطور کار می‌کند؟', a: 'شماره را می‌خرید، در سرویس موردنظر وارد می‌کنید و کد تأیید به‌صورت خودکار در پنل نمایش داده می‌شود.' },
    { q: 'اکانت‌ها اصل هستند؟', a: 'بله، تمام اکانت‌های پرمیوم اصل و با ضمانت هستند و در صورت مشکل جایگزین می‌شوند.' },
    { q: 'اگر سفارشم انجام نشد چه می‌شود؟', a: 'در صورت عدم انجام سفارش، مبلغ به‌صورت خودکار به کیف پول شما بازگردانده می‌شود.' }
  ];

  function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  // Bubble
  var bubble = el('button', 'ugc-bubble');
  bubble.setAttribute('aria-label', 'پشتیبانی آنلاین');
  bubble.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-8.5 8.5 8.5 8.5 0 0 1-3.6-.8L3 21l1.9-5.4A8.5 8.5 0 1 1 21 11.5z"/></svg>';

  // Panel
  var panel = el('div', 'ugc-panel');
  var head = el('div', 'ugc-head', '<span class="ugc-dot"></span><div>پشتیبانی آپلودگرام<small>معمولاً چند دقیقه‌ای پاسخ می‌دهیم</small></div>');
  var body = el('div', 'ugc-body');
  body.appendChild(el('div', 'ugc-msg bot', 'سلام 👋 خوش آمدید! یکی از سوال‌های پرتکرار را بزنید یا با پشتیبانی گفتگو کنید.'));

  // FAQ quick-reply chips live INSIDE the scrollable body so answers stay visible.
  var faqs = el('div', 'ugc-faqs');
  FAQS.forEach(function (f) {
    var b = el('button', 'ugc-faq', esc(f.q));
    b.addEventListener('click', function () {
      body.appendChild(el('div', 'ugc-msg user', esc(f.q)));
      var a = el('div', 'ugc-msg bot', esc(f.a));
      body.appendChild(a);
      a.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      body.scrollTop = body.scrollHeight;
    });
    faqs.appendChild(b);
  });
  body.appendChild(faqs);

  var foot = el('div', 'ugc-foot');
  var ticket = el('a', 'ugc-cta');
  ticket.textContent = 'ثبت تیکت پشتیبانی';
  ticket.href = loggedIn ? panelUrl : authUrl;
  foot.appendChild(ticket);

  if (telegram) {
    var tg = el('a', 'ugc-cta tg');
    tg.textContent = 'گفتگو در تلگرام';
    tg.href = 'https://t.me/' + telegram;
    tg.target = '_blank';
    tg.rel = 'noopener';
    foot.appendChild(tg);
  }

  panel.appendChild(head);
  panel.appendChild(body);
  panel.appendChild(foot);
  root.appendChild(panel);
  root.appendChild(bubble);

  bubble.addEventListener('click', function () { root.classList.toggle('is-open'); });
})();
