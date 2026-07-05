/* UploadGram — app-first service/number selector */
(function ($) {
  'use strict';

  function money(n) { return (n || 0).toLocaleString('fa-IR') + ' تومان'; }

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
        $msg.addClass('is-success').text(res.data.message || 'سفارش ثبت شد ✅').show();
        if (res.data.data && res.data.data.number) {
          $msg.append(' — شماره: ' + res.data.data.number);
        }
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

  function serviceCard(p) {
    var $card = $('<div class="ug-svc-card"></div>');
    $card.append('<div class="ug-svc-name">' + $('<i>').text(p.name).html() + '</div>');

    var countBased = (p.input !== 'none' && !p.fixed);
    var $form = $('<div class="ug-svc-form"></div>');
    var $priceLine = $('<div class="ug-svc-price"></div>');

    if (countBased) {
      var $target = $('<input type="text" class="ug-svc-target" placeholder="لینک یا یوزرنیم">');
      var $qty = $('<input type="number" class="ug-svc-qty" placeholder="تعداد">');
      if (p.min) { $qty.attr('min', p.min).val(p.min); }
      if (p.max) { $qty.attr('max', p.max); }
      function recalc() {
        var q = parseInt($qty.val(), 10) || 0;
        var price = p.rate > 0 ? Math.round(q / 1000 * p.rate) : 0;
        $priceLine.text(price ? money(price) : (p.min ? 'حداقل ' + p.min : ''));
      }
      $qty.on('input', recalc); recalc();
      $form.append($target).append($qty);
      if (p.min || p.max) {
        $card.append('<div class="ug-svc-limits">محدوده: ' + (p.min || 0).toLocaleString('fa-IR') + ' تا ' + (p.max || 0).toLocaleString('fa-IR') + '</div>');
      }
    } else {
      $priceLine.text(p.price);
    }

    var $btn = $('<button class="ug-svc-buy">خرید</button>');
    var $msg = $('<div class="ug-svc-msg"></div>').hide();
    $btn.on('click', function () {
      var q = countBased ? (parseInt($card.find('.ug-svc-qty').val(), 10) || 0) : 0;
      var t = countBased ? ($card.find('.ug-svc-target').val() || '') : '';
      if (countBased && !t) { $msg.addClass('is-error').text('لینک/یوزرنیم را وارد کنید').show(); return; }
      buy(p.id, q, t, $msg, $btn);
    });

    $card.append($priceLine).append($form).append($btn).append($msg);
    return $card;
  }

  function numberCard(p) {
    var $card = $('<div class="ug-num-card"></div>');
    $card.append('<div class="ug-num-name">' + $('<i>').text(p.name).html() + '</div>');
    $card.append('<div class="ug-num-price">' + $('<i>').text(p.price).html() + '</div>');
    var $btn = $('<button class="ug-svc-buy">خرید شماره</button>');
    var $msg = $('<div class="ug-svc-msg"></div>').hide();
    $btn.on('click', function () { buy(p.id, 0, '', $msg, $btn); });
    $card.append($btn).append($msg);
    return $card;
  }

  function initServices($app, data) {
    var $kinds = $app.find('.ug-app-kinds');
    var $products = $app.find('.ug-app-products');

    function showKind(platform, kindIdx) {
      var groups = data[platform] || [];
      $kinds.empty();
      groups.forEach(function (g, i) {
        var $b = $('<button class="ug-kind-chip">' + $('<i>').text(g.label).html() + '</button>');
        if (i === kindIdx) { $b.addClass('active'); }
        $b.on('click', function () { showKind(platform, i); });
        $kinds.append($b);
      });
      $products.empty();
      var group = groups[kindIdx];
      if (!group) { return; }
      group.products.forEach(function (p) { $products.append(serviceCard(p)); });
    }

    $app.find('.ug-app-chip').on('click', function () {
      $app.find('.ug-app-chip').removeClass('active');
      $(this).addClass('active');
      showKind($(this).data('platform'), 0);
    });
    var $firstApp = $app.find('.ug-app-chip.active').first();
    if ($firstApp.length) { showKind($firstApp.data('platform'), 0); }
  }

  function initNumbers($app, data) {
    var $products = $app.find('.ug-app-products');
    function show(platform) {
      $products.empty();
      (data[platform] || []).forEach(function (p) { $products.append(numberCard(p)); });
    }
    $app.find('.ug-app-chip').on('click', function () {
      $app.find('.ug-app-chip').removeClass('active');
      $(this).addClass('active');
      show($(this).data('platform'));
    });
    var $first = $app.find('.ug-app-chip.active').first();
    if ($first.length) { show($first.data('platform')); }
  }

  $(function () {
    $('.ug-app').each(function () {
      var $app = $(this);
      var raw = $app.find('.ug-app-data').text();
      var data;
      try { data = JSON.parse(raw); } catch (e) { return; }
      if ($app.data('mode') === 'numbers') { initNumbers($app, data); }
      else { initServices($app, data); }
    });
  });
})(jQuery);
