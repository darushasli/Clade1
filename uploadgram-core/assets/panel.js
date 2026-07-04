/* UploadGram — panel & instant-order interactions */
(function ($) {
  'use strict';

  function toIRT(n) {
    try { return new Intl.NumberFormat('fa-IR').format(Math.round(n)); }
    catch (e) { return Math.round(n); }
  }

  /* ── Live price calc on order form ── */
  function recalc($form) {
    var fixed = $form.data('fixed') === 1 || $form.data('fixed') === '1';
    var rate  = parseFloat($form.data('rate')) || 0;
    var $out  = $form.find('.ug-calc-price');
    if (fixed || !rate) { return; }
    var qty = parseInt($form.find('input[name="quantity"]').val(), 10) || 0;
    var price = (qty / 1000) * rate;
    $out.text(toIRT(price) + ' تومان');
    $out.attr('data-price', price);
  }

  $(document).on('input change', '.ug-order-form input[name="quantity"]', function () {
    recalc($(this).closest('.ug-order-form'));
  });
  $('.ug-order-form').each(function () { recalc($(this)); });

  /* ── Submit instant order ── */
  $(document).on('submit', '.ug-order-form', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $btn  = $form.find('.ug-submit');
    var $msg  = $form.find('.ug-form-msg');
    var $otp  = $form.find('.ug-otp-box');

    $btn.prop('disabled', true).addClass('is-loading');
    $msg.hide().removeClass('is-error is-success');

    $.post(ugPanel.ajaxUrl, {
      action: 'ug_place_order',
      nonce: ugPanel.nonce,
      product_id: $form.data('product'),
      quantity: $form.find('input[name="quantity"]').val() || 0,
      target: $form.find('input[name="target"]').val() || ''
    }).done(function (res) {
      if (res && res.success) {
        $msg.addClass('is-success').text(res.data.message).show();
        if (res.data.balance) { $('#ug-wallet-balance').text(res.data.balance); }
        // Virtual number: begin polling for OTP.
        if (res.data.status === 'awaiting_otp') {
          $otp.show().html('<div class="ug-otp-wait">در انتظار دریافت کد…</div>');
          pollOtp($otp, res.data.order_id);
        } else if (res.data.data && res.data.data.number) {
          $otp.show().html('شماره: <code>' + res.data.data.number + '</code>');
        }
      } else {
        var m = (res && res.data && res.data.message) ? res.data.message : 'خطا در ثبت سفارش';
        $msg.addClass('is-error').text(m).show();
        if (res && res.data && res.data.recharge) {
          $msg.append(' <a href="#" class="ug-recharge-link">شارژ کیف پول</a>');
        }
      }
    }).fail(function (x) {
      var m = 'خطا';
      try { m = JSON.parse(x.responseText).data.message; } catch (e) {}
      $msg.addClass('is-error').text(m).show();
    }).always(function () {
      $btn.prop('disabled', false).removeClass('is-loading');
    });
  });

  /* ── Poll a single order for OTP ── */
  function pollOtp($box, orderId, tries) {
    tries = tries || 0;
    if (tries > 40) { $box.html('<div class="ug-otp-wait">کد دریافت نشد. لطفاً از پنل سفارش‌ها پیگیری کنید.</div>'); return; }
    $.post(ugPanel.ajaxUrl, {
      action: 'ug_order_status',
      nonce: ugPanel.nonce,
      order_id: orderId
    }).done(function (res) {
      if (res && res.success) {
        var d = res.data.data || {};
        var code = d.otp || d.code;
        if (code) {
          $box.html('کد دریافت شد: <code class="ug-otp">' + code + '</code>');
          return;
        }
      }
      setTimeout(function () { pollOtp($box, orderId, tries + 1); }, 5000);
    }).fail(function () {
      setTimeout(function () { pollOtp($box, orderId, tries + 1); }, 8000);
    });
  }

  /* ── Auto-poll awaiting orders in the "my orders" table ── */
  $('.ug-orders-table tr[data-status="awaiting_otp"]').each(function () {
    var $row = $(this);
    var id = $row.data('order');
    var $cell = $row.find('.ug-order-detail');
    (function loop(t) {
      t = t || 0;
      if (t > 40) { return; }
      $.post(ugPanel.ajaxUrl, { action: 'ug_order_status', nonce: ugPanel.nonce, order_id: id })
        .done(function (res) {
          if (res && res.success && res.data.data && (res.data.data.otp || res.data.data.code)) {
            var c = res.data.data.otp || res.data.data.code;
            $cell.append('<div>کد: <code class="ug-otp">' + c + '</code></div>');
            $row.find('.ug-status').text(res.data.status_label);
            return;
          }
          setTimeout(function () { loop(t + 1); }, 6000);
        });
    })();
  });

})(jQuery);

/* ═══ Top-up chips + submit ═══ */
(function ($) {
  $(document).on('click', '.ug-topup-chips .ug-chip', function () {
    var amt = $(this).data('amount');
    var $form = $(this).closest('form');
    $form.find('input[name="amount"]').val(amt);
    $form.find('.ug-chip').removeClass('active');
    $(this).addClass('active');
  });

  $(document).on('submit', '.ug-topup-form', function (e) {
    e.preventDefault();
    var $form = $(this);
    var amt = parseInt($form.find('input[name="amount"]').val(), 10) || 0;
    var $msg = $form.find('.ug-form-msg').hide().removeClass('is-error is-success');
    var $btn = $form.find('button[type="submit"]').prop('disabled', true).addClass('is-loading');

    $.post(ugPanel.ajaxUrl, {
      action: 'ug_topup',
      nonce: ugPanel.nonce,
      amount: amt
    }).done(function (res) {
      if (res && res.success && res.data.redirect) {
        window.location.href = res.data.redirect;
      } else {
        $msg.addClass('is-error').text((res.data && res.data.message) || 'خطا').show();
        $btn.prop('disabled', false).removeClass('is-loading');
      }
    }).fail(function (x) {
      var m = 'خطای شارژ'; try { m = JSON.parse(x.responseText).data.message; } catch(e){}
      $msg.addClass('is-error').text(m).show();
      $btn.prop('disabled', false).removeClass('is-loading');
    });
  });

  /* Profile form */
  $(document).on('submit', '.ug-profile-form', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $msg  = $form.find('.ug-form-msg').hide().removeClass('is-error is-success');
    var $btn  = $form.find('button[type="submit"]').prop('disabled', true).addClass('is-loading');

    $.post(ugPanel.ajaxUrl, {
      action: 'ug_update_profile',
      nonce: ugPanel.nonce,
      name: $form.find('input[name="name"]').val(),
      email: $form.find('input[name="email"]').val(),
      current_password: $form.find('input[name="current_password"]').val(),
      new_password: $form.find('input[name="new_password"]').val()
    }).done(function (res) {
      if (res && res.success) {
        $msg.addClass('is-success').text(res.data.message).show();
        $form.find('input[name="current_password"], input[name="new_password"]').val('');
      } else {
        $msg.addClass('is-error').text((res.data && res.data.message) || 'خطا').show();
      }
      $btn.prop('disabled', false).removeClass('is-loading');
    }).fail(function (x) {
      var m = 'خطا'; try { m = JSON.parse(x.responseText).data.message; } catch(e){}
      $msg.addClass('is-error').text(m).show();
      $btn.prop('disabled', false).removeClass('is-loading');
    });
  });
})(jQuery);
