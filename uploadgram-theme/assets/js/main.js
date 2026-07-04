/* UploadGram Theme — Main JavaScript */
(function () {
  'use strict';

  /* ── Tabs ── */
  function initTabs() {
    document.querySelectorAll('.tab-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var group = btn.closest('.tab-row') && btn.closest('.tab-row').dataset.tabGroup;
        if (!group) {
          group = btn.dataset.tabGroup || btn.closest('[data-tab-group]') && btn.closest('[data-tab-group]').dataset.tabGroup;
        }
        var target = btn.dataset.tabTarget;

        /* Deactivate all buttons in same row */
        btn.closest('.tab-row').querySelectorAll('.tab-btn').forEach(function (b) {
          b.classList.remove('active');
        });
        btn.classList.add('active');

        /* Hide/show panels */
        document.querySelectorAll('.tab-panel[data-tab-panel-group="' + (group || 'platform') + '"]').forEach(function (p) {
          p.classList.remove('show');
          if (p.dataset.tabPanel === target) {
            p.classList.add('show');
          }
        });
      });
    });

    /* Member type cards jump to tab */
    document.querySelectorAll('[data-tab-jump]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        var target = el.dataset.tabJump;
        var tabBtn = document.querySelector('.tab-btn[data-tab-target="' + target + '"]');
        if (tabBtn) {
          tabBtn.click();
          var plans = document.getElementById('plans');
          if (plans) { plans.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        }
      });
    });
  }

  /* ── Filter tabs (accounts page) ── */
  function initFilterTabs() {
    document.querySelectorAll('.filter-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        btn.closest('.filter-tabs').querySelectorAll('.filter-tab').forEach(function (b) {
          b.classList.remove('active');
        });
        btn.classList.add('active');
        var filter = btn.dataset.filter;
        document.querySelectorAll('.acc-card').forEach(function (card) {
          if (!filter || filter === 'all' || card.dataset.cat === filter) {
            card.style.display = '';
          } else {
            card.style.display = 'none';
          }
        });
      });
    });
  }

  /* ── FAQ Accordion ── */
  function initFaq() {
    document.querySelectorAll('.faq-q').forEach(function (q) {
      q.addEventListener('click', function () {
        var item = q.closest('.faq-item');
        var isOpen = item.classList.contains('open');
        /* Close all */
        document.querySelectorAll('.faq-item').forEach(function (i) { i.classList.remove('open'); });
        if (!isOpen) { item.classList.add('open'); }
      });
    });
  }

  /* ── Cart badge counter (WooCommerce hook placeholder) ── */
  function initCart() {
    var badges = document.querySelectorAll('.cart-badge');
    if (!badges.length) return;
    /* WooCommerce updates fragment; we just show correct count */
    function updateBadge(count) {
      badges.forEach(function (b) { b.textContent = count; });
    }
    document.addEventListener('wc_fragments_refreshed', function () {
      var cartCount = document.querySelector('.woocommerce-cart-form__cart-item') ? document.querySelectorAll('.woocommerce-cart-form__cart-item').length : 0;
      updateBadge(cartCount);
    });
  }

  /* ── Country search (virtual number page) ── */
  function initCountrySearch() {
    var searchInput = document.getElementById('country-search');
    if (!searchInput) return;
    searchInput.addEventListener('input', function () {
      var q = searchInput.value.trim().toLowerCase();
      document.querySelectorAll('.price-table tbody tr').forEach(function (row) {
        var country = row.querySelector('.country-name');
        if (!country) return;
        row.style.display = country.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  }

  /* ── Header scroll shadow ── */
  function initHeaderScroll() {
    var header = document.querySelector('header');
    if (!header) return;
    window.addEventListener('scroll', function () {
      if (window.scrollY > 10) {
        header.style.boxShadow = '0 2px 20px rgba(0,0,0,0.4)';
      } else {
        header.style.boxShadow = '';
      }
    }, { passive: true });
  }

  /* ── Smooth scroll anchors ── */
  function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        var id = a.getAttribute('href').slice(1);
        var target = document.getElementById(id);
        if (target) {
          e.preventDefault();
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });
  }

  /* ── Mobile menu toggle ── */
  function initMobileMenu() {
    var toggle = document.getElementById('mobile-menu-toggle');
    var nav = document.querySelector('.main-nav');
    if (!toggle || !nav) return;
    toggle.addEventListener('click', function () {
      nav.classList.toggle('mobile-open');
    });
  }

  /* ── "Add to cart" button feedback ── */
  function initAddToCart() {
    document.querySelectorAll('.plan-btn, .mtc-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var orig = btn.textContent;
        btn.textContent = '✓ اضافه شد';
        btn.style.background = 'var(--mint)';
        btn.style.color = '#fff';
        setTimeout(function () {
          btn.textContent = orig;
          btn.style.background = '';
          btn.style.color = '';
        }, 1800);
      });
    });
  }

  /* ── Number counter animation ── */
  function animateCounters() {
    var counters = document.querySelectorAll('[data-count]');
    if (!counters.length) return;
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        var target = parseFloat(el.dataset.count);
        var prefix = el.dataset.prefix || '';
        var suffix = el.dataset.suffix || '';
        var duration = 1200;
        var start = performance.now();
        function update(now) {
          var progress = Math.min((now - start) / duration, 1);
          var eased = 1 - Math.pow(1 - progress, 3);
          el.textContent = prefix + Math.round(eased * target).toLocaleString('fa') + suffix;
          if (progress < 1) requestAnimationFrame(update);
        }
        requestAnimationFrame(update);
        observer.unobserve(el);
      });
    }, { threshold: 0.5 });
    counters.forEach(function (c) { observer.observe(c); });
  }

  /* ── Init all ── */
  document.addEventListener('DOMContentLoaded', function () {
    initTabs();
    initFilterTabs();
    initFaq();
    initCart();
    initCountrySearch();
    initHeaderScroll();
    initSmoothScroll();
    initMobileMenu();
    initAddToCart();
    animateCounters();
  });
})();

/* ══ Theme toggle (light/dark) + Language menu ══ */
(function () {
  'use strict';
  var root = document.documentElement;

  // Theme toggle
  var toggle = document.getElementById('ug-theme-toggle');
  if (toggle) {
    toggle.addEventListener('click', function () {
      var cur = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
      var next = cur === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('ug-theme', next); } catch (e) {}
      var meta = document.querySelector('meta[name="theme-color"]');
      if (meta) { meta.setAttribute('content', next === 'dark' ? '#0b0d17' : '#ffffff'); }
    });
  }

  // Language dropdown open/close
  var lang = document.getElementById('ug-lang-switch');
  if (lang) {
    var btn = lang.querySelector('.lang-current');
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      lang.classList.toggle('open');
    });
    document.addEventListener('click', function () { lang.classList.remove('open'); });
  }
})();
