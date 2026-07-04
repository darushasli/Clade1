/* UploadGram — auth card interactions (SMS OTP, email/pass, Google) */
(function ($) {
  'use strict';

  var $card = $('.ug-auth-card');
  if ($card.length === 0) { return; }

  /* ── main tabs (Login / Register) ── */
  $card.on('click', '.ug-auth-tab', function () {
    var tab = $(this).data('tab');
    $card.find('.ug-auth-tab').removeClass('active');
    $(this).addClass('active');
    $card.find('.ug-auth-body').hide();
    $card.find('.ug-auth-body[data-body="' + tab + '"]').show();
  });

  /* ── sub-tabs (login phone / email) ── */
  $card.on('click', '.ug-auth-subtab', function () {
    var s = $(this).data('subtab');
    $card.find('.ug-auth-subtab').removeClass('active');
    $(this).addClass('active');
    if (s === 'phone') {
      $card.find('[data-form="login-phone"]').show();
      $card.find('[data-form="login-email"]').hide();
    } else {
      $card.find('[data-form="login-phone"]').hide();
      $card.find('[data-form="login-email"]').show();
    }
  });

  /* ── send OTP ── */
  $card.on('click', '.ug-send-otp', function () {
    var $btn  = $(this);
    var $form = $btn.closest('form');
    var $phone = $form.find('input[name="phone"]');
    var phone  = ($phone.val() || '').trim();
    var purpose = $btn.data('purpose') || 'login';
    var $msg = $form.find('.ug-form-msg').hide().removeClass('is-error is-success');

    if (!/^09\d{9}$/.test(phone)) {
      $msg.addClass('is-error').text('شماره موبایل نامعتبر است.').show();
      return;
    }

    $btn.prop('disabled', true).addClass('is-loading').text('در حال ارسال…');

    $.post(ugPanel.ajaxUrl, {
      action: 'ug_send_otp',
      nonce: ugPanel.nonce,
      phone: phone,
      purpose: purpose
    }).done(function (res) {
      if (res && res.success) {
        $msg.addClass('is-success').text('کد به شماره ' + phone + ' ارسال شد.').show();
        $form.find('.ug-code-field').show();
        $form.find('button[type="submit"]').show();
        startCountdown($btn, res.data.wait || 60);
      } else {
        var m = (res && res.data && res.data.message) ? res.data.message : 'خطا در ارسال';
        $msg.addClass('is-error').text(m).show();
        $btn.prop('disabled', false).removeClass('is-loading').text('ارسال کد تایید');
      }
    }).fail(function (x) {
      var m = 'خطا در ارسال کد.';
      try { m = JSON.parse(x.responseText).data.message; } catch (e) {}
      $msg.addClass('is-error').text(m).show();
      $btn.prop('disabled', false).removeClass('is-loading').text('ارسال کد تایید');
    });
  });

  function startCountdown($btn, seconds) {
    var s = parseInt(seconds, 10) || 60;
    var label = $btn.data('label') || $btn.text();
    $btn.data('label', label);
    var iv = setInterval(function () {
      s -= 1;
      if (s <= 0) {
        clearInterval(iv);
        $btn.prop('disabled', false).removeClass('is-loading').text('ارسال مجدد کد');
      } else {
        $btn.text('ارسال مجدد در ' + s + ' ثانیه');
      }
    }, 1000);
  }

  /* ── login by phone (OTP verify) ── */
  $card.on('submit', '[data-form="login-phone"]', function (e) {
    e.preventDefault();
    var $form = $(this);
    var phone = $form.find('input[name="phone"]').val();
    var code  = $form.find('input[name="code"]').val();
    var $msg  = $form.find('.ug-form-msg').hide().removeClass('is-error is-success');
    var $btn  = $form.find('button[type="submit"]').prop('disabled', true).addClass('is-loading');

    $.post(ugPanel.ajaxUrl, {
      action: 'ug_verify_otp_login',
      nonce: ugPanel.nonce,
      phone: phone,
      code: code
    }).done(function (res) {
      if (res && res.success) {
        $msg.addClass('is-success').text(res.data.message).show();
        setTimeout(function () { window.location.href = res.data.redirect || ugPanel.panelUrl; }, 500);
      } else {
        $msg.addClass('is-error').text(res.data.message || 'خطا').show();
        $btn.prop('disabled', false).removeClass('is-loading');
      }
    }).fail(function (x) {
      var m = 'خطا در ورود'; try { m = JSON.parse(x.responseText).data.message; } catch(e){}
      $msg.addClass('is-error').text(m).show();
      $btn.prop('disabled', false).removeClass('is-loading');
    });
  });

  /* ── login by email/pass ── */
  $card.on('submit', '[data-form="login-email"]', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $msg  = $form.find('.ug-form-msg').hide().removeClass('is-error is-success');
    var $btn  = $form.find('button[type="submit"]').prop('disabled', true).addClass('is-loading');

    $.post(ugPanel.ajaxUrl, {
      action: 'ug_login',
      nonce: ugPanel.nonce,
      login: $form.find('input[name="login"]').val(),
      password: $form.find('input[name="password"]').val()
    }).done(function (res) {
      if (res && res.success) {
        window.location.href = res.data.redirect || ugPanel.panelUrl;
      } else {
        $msg.addClass('is-error').text(res.data.message || 'خطا').show();
        $btn.prop('disabled', false).removeClass('is-loading');
      }
    }).fail(function (x) {
      var m = 'خطا در ورود'; try { m = JSON.parse(x.responseText).data.message; } catch(e){}
      $msg.addClass('is-error').text(m).show();
      $btn.prop('disabled', false).removeClass('is-loading');
    });
  });

  /* ── register (phone + code + full profile) ── */
  $card.on('submit', '[data-form="register"]', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $msg  = $form.find('.ug-form-msg').hide().removeClass('is-error is-success');
    var $btn  = $form.find('button[type="submit"]').prop('disabled', true).addClass('is-loading');

    if (!$form.find('input[name="terms"]').is(':checked')) {
      $msg.addClass('is-error').text('پذیرش قوانین الزامی است.').show();
      $btn.prop('disabled', false).removeClass('is-loading');
      return;
    }

    $.post(ugPanel.ajaxUrl, {
      action:   'ug_verify_otp_register',
      nonce:    ugPanel.nonce,
      name:     $form.find('input[name="name"]').val(),
      email:    $form.find('input[name="email"]').val(),
      password: $form.find('input[name="password"]').val(),
      phone:    $form.find('input[name="phone"]').val(),
      code:     $form.find('input[name="code"]').val(),
      terms:    $form.find('input[name="terms"]').is(':checked') ? 1 : 0
    }).done(function (res) {
      if (res && res.success) {
        $msg.addClass('is-success').text(res.data.message).show();
        setTimeout(function () { window.location.href = res.data.redirect || ugPanel.panelUrl; }, 600);
      } else {
        $msg.addClass('is-error').text(res.data.message || 'خطا').show();
        $btn.prop('disabled', false).removeClass('is-loading');
      }
    }).fail(function (x) {
      var m = 'خطا در ثبت‌نام'; try { m = JSON.parse(x.responseText).data.message; } catch(e){}
      $msg.addClass('is-error').text(m).show();
      $btn.prop('disabled', false).removeClass('is-loading');
    });
  });

  /* ── Google Sign-In ── */
  window.ugGoogleCallback = function (response) {
    if (!response || !response.credential) { return; }
    $.post(ugPanel.ajaxUrl, {
      action: 'ug_google_signin',
      nonce: ugPanel.nonce,
      credential: response.credential
    }).done(function (res) {
      if (res && res.success) {
        window.location.href = res.data.redirect || ugPanel.panelUrl;
      } else {
        alert((res && res.data && res.data.message) || 'ورود با گوگل ناموفق بود.');
      }
    });
  };

  function initGoogle() {
    var $slot = $('#ug-google-btn');
    if ($slot.length === 0) { return; }
    var clientId = $slot.data('client-id') || ugPanel.googleClientId;
    if (!clientId || !window.google || !window.google.accounts) { return; }
    window.google.accounts.id.initialize({
      client_id: clientId,
      callback:  window.ugGoogleCallback,
      ux_mode:   'popup',
      auto_select: false
    });
    window.google.accounts.id.renderButton($slot[0], {
      theme: 'filled_black',
      size:  'large',
      shape: 'pill',
      text:  'signin_with',
      locale: 'fa',
      width: 300
    });
  }

  var giTimer = setInterval(function () {
    if (window.google && window.google.accounts) {
      clearInterval(giTimer);
      initGoogle();
    }
  }, 300);
  setTimeout(function () { clearInterval(giTimer); }, 10000);

})(jQuery);
