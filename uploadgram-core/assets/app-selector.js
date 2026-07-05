/* UploadGram — app-first service/number selector */
(function ($) {
  'use strict';

  function esc(s) { return $('<i>').text(s == null ? '' : s).html(); }

  function buy(productId, quantity, target, $msg, $btn) {
    if (!ugApp.loggedIn) { window.location.href = ugApp.authUrl; return; }
    $btn.prop('disabled', true).addClass('is-loading');
    $msg.removeClass('is-error is-success').hide();
    $.post(ugApp.ajaxUrl, {
      action: 'ug_place_order',
      nonce: ugApp.nonce,
      product_id: productId,
      quantity: quantity || 0,
      target: target || ''
    }).done(function (res) {
      if (res && res.success) {
        $msg.addClass('is-success').text(res.data.message || 'سفارش ثبت شد ✔').show();
        if (res.data.data && res.data.data.number) { $msg.append(' — شماره: ' + res.data.data.number); }
      } else {
        var m = (res && res.data && res.data.message) ? res.data.message : 'خطا در ثبت سفارش';
        $msg.addClass('is-error').text(m).show();
      }
    }).fail(function (x) {
      var m = 'خطا';
      try { m = JSON.parse(x.responseText).data.message; } catch (e) {}
      $msg.addClass('is-error').text(m).show();
    }).always(function () {
      $btn.prop('disabled', false).removeClass('is-loading');
    });
  }

  /* ── Single order box for one product (buy mode) ── */
  function orderBox(p) {
    var $box = $('<div class="ug-svc-order"></div>');
    var countBased = (p.input !== 'none' && !p.fixed);
    var $price = $('<div class="ug-svc-price"></div>');
    var $target, $qty;

    if (countBased) {
      $target = $('<input type="text" class="ug-svc-target" placeholder="لینک یا یوزرنیم">');
      $qty = $('<input type="number" class="ug-svc-qty" placeholder="تعداد">');
      if (p.min) { $qty.attr('min', p.min).val(p.min); }
      if (p.max) { $qty.attr('max', p.max); }
      var recalc = function () {
        var q = parseInt($qty.val(), 10) || 0;
        var price = p.rate > 0 ? Math.round(q / 1000 * p.rate) : 0;
        $price.text(price ? price.toLocaleString('fa-IR') + ' تومان' : (p.min ? 'حداقل ' + p.min.toLocaleString('fa-IR') : ''));
      };
      $qty.on('input', recalc);
      $box.append($('<div class="ug-svc-fields"></div>').append($target).append($qty));
      if (p.min || p.max) {
        $box.append('<div class="ug-svc-limits">محدوده: ' + (p.min || 0).toLocaleString('fa-IR') + ' تا ' + (p.max || 0).toLocaleString('fa-IR') + '</div>');
      }
      recalc();
    } else {
      $price.text(p.price);
    }

    var $btn = $('<button class="ug-svc-buy">خرید</button>');
    var $msg = $('<div class="ug-svc-msg"></div>').hide();
    $btn.on('click', function () {
      var q = countBased ? (parseInt($qty.val(), 10) || 0) : 0;
      var t = countBased ? ($target.val() || '') : '';
      if (countBased && !t) { $msg.addClass('is-error').text('لینک/یوزرنیم را وارد کنید').show(); return; }
      buy(p.id, q, t, $msg, $btn);
    });

    $box.append($price).append($btn).append($msg);
    return $box;
  }

  /* ── Services in BUY mode: platform → kind chips → product dropdown → order ── */
  function initServicesBuy($app, data) {
    var $kinds = $app.find('.ug-app-kinds');
    var $out = $app.find('.ug-app-products');

    function showKind(platform, kindIdx) {
      var groups = data[platform] || [];
      $kinds.empty();
      groups.forEach(function (g, i) {
        var $b = $('<button class="ug-kind-chip">' + esc(g.label) + '</button>');
        if (i === kindIdx) { $b.addClass('active'); }
        $b.on('click', function () { showKind(platform, i); });
        $kinds.append($b);
      });
      $out.empty();
      var group = groups[kindIdx];
      if (!group || !group.products.length) { $out.html('<div class="ug-app-empty">موردی نیست.</div>'); return; }

      var $sel = $('<select class="ug-svc-select"></select>');
      group.products.forEach(function (p, i) {
        $sel.append('<option value="' + i + '">' + esc(p.name) + '</option>');
      });
      var $holder = $('<div class="ug-svc-picked"></div>');
      function pick() { $holder.empty().append(orderBox(group.products[parseInt($sel.val(), 10) || 0])); }
      $sel.on('change', pick);
      $out.append($('<div class="ug-field"><span>انتخاب سرویس</span></div>').append($sel)).append($holder);
      pick();
    }

    $app.find('.ug-app-chip').on('click', function () {
      $app.find('.ug-app-chip').removeClass('active');
      $(this).addClass('active');
      showKind($(this).data('platform'), 0);
    });
    var $f = $app.find('.ug-app-chip.active').first();
    if ($f.length) { showKind($f.data('platform'), 0); }
  }

  /* ── Services in SHOWCASE mode: one tariff per kind, no buying ── */
  function initServicesShowcase($app, data) {
    var $kinds = $app.find('.ug-app-kinds').hide();
    var $out = $app.find('.ug-app-products');
    var cta = ugApp.loggedIn ? ugApp.panelUrl + '?section=services' : ugApp.authUrl;

    function show(platform) {
      var groups = data[platform] || [];
      $out.empty();
      groups.forEach(function (g) {
        var $c = $('<div class="ug-tariff"></div>');
        $c.append('<div class="ug-tariff-kind">' + esc(g.label) + '</div>');
        $c.append('<div class="ug-tariff-from">' + (g.from ? 'شروع از ' + esc(g.from) : '') + '</div>');
        $c.append('<a class="ug-tariff-cta" href="' + cta + '">مشاهده و خرید</a>');
        $out.append($c);
      });
    }
    $app.find('.ug-app-chip').on('click', function () {
      $app.find('.ug-app-chip').removeClass('active');
      $(this).addClass('active');
      show($(this).data('platform'));
    });
    var $f = $app.find('.ug-app-chip.active').first();
    if ($f.length) { show($f.data('platform')); }
  }

  /* ── Numbers ── */
  function numberCard(p) {
    var $card = $('<div class="ug-num-card"></div>');
    $card.append('<div class="ug-num-name">' + esc(p.name) + '</div>');
    $card.append('<div class="ug-num-price">' + esc(p.price) + '</div>');
    var $btn = $('<button class="ug-svc-buy">خرید شماره</button>');
    var $msg = $('<div class="ug-svc-msg"></div>').hide();
    $btn.on('click', function () { buy(p.id, 0, '', $msg, $btn); });
    $card.append($btn).append($msg);
    return $card;
  }
  function initNumbers($app, data) {
    var $out = $app.find('.ug-app-products');
    function show(platform) {
      $out.empty();
      (data[platform] || []).forEach(function (p) { $out.append(numberCard(p)); });
    }
    $app.find('.ug-app-chip').on('click', function () {
      $app.find('.ug-app-chip').removeClass('active');
      $(this).addClass('active');
      show($(this).data('platform'));
    });
    var $f = $app.find('.ug-app-chip.active').first();
    if ($f.length) { show($f.data('platform')); }
  }

  $(function () {
    $('.ug-app').each(function () {
      var $app = $(this);
      var data;
      try { data = JSON.parse($app.find('.ug-app-data').text()); } catch (e) { return; }
      if ($app.data('mode') === 'numbers') { initNumbers($app, data); }
      else if ($app.data('view') === 'showcase') { initServicesShowcase($app, data); }
      else { initServicesBuy($app, data); }
    });
  });
})(jQuery);
