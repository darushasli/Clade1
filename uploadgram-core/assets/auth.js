/* UploadGram — unified phone-first auth flow */
(function ($) {
  'use strict';

  var $card = $('#ug-auth');
  if ($card.length === 0) { return; }

  var phone = '';

  function post(action, data) {
    data = data || {};
    data.action = action;
    data.nonce = ugPanel.nonce;
    return $.post(ugPanel.ajaxUrl, data);
  }

  function showStep(step) {
    $card.find('[data-step]').hide();
    $card.find('[data-step="' + step + '"]').show();
    var subs = {
      'phone': 'ورود یا ثبت‌نام با شماره موبایل',
      'choose': 'خوش آمدید! روش ورود را انتخاب کنید',
      'login-otp': 'کد پیامک‌شده را وارد کنید',
      'login-password': 'رمز عبور خود را وارد کنید',
      'register': 'تکمیل ثبت‌نام — کد برایتان ارسال شد'
    };
    $('#ug-auth-sub').text(subs[step] || '');
    $card.find('.ug-auth-phone').text(phone);
    $card.find('input[name="login"]').val(phone);
  }

  function msg($form, text, type) {
    $form.find('.ug-form-msg').removeClass('is-error is-success').addClass(type).text(text).show();
  }
  function busy($btn, on) { $btn.prop('disabled', on).toggleClass('is-loading', on); }

  function redirect(res) {
    if (res && res.success) { window.location.href = res.data.redirect || ugPanel.panelUrl; return true; }
    return false;
  }
  function errText(x) { try { return JSON.parse(x.responseText).data.message; } catch (e) { return 'خطا'; } }

  function countdown($btn, seconds) {
    var s = seconds || 60;
    var label = $btn.data('label') || $btn.text();
    $btn.data('label', label);
    busy($btn, true);
    var iv = setInterval(function () {
      s--;
      if (s <= 0) { clearInterval(iv); busy($btn, false); $btn.text(label); }
      else { $btn.text('ارسال مجدد در ' + s + ' ثانیه'); }
    }, 1000);
  }

  function sendOtp(purpose, $btn) {
    if ($btn) { busy($btn, true); }
    return post('ug_send_otp', { phone: phone, purpose: purpose }).done(function (res) {
      if ($btn) { busy($btn, false); if (res && res.success) { countdown($btn, res.data.wait || 60); } }
    });
  }

  /* Step 1: phone → detect new/existing */
  $card.on('submit', '[data-step="phone"]', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $btn = $form.find('button[type="submit"]');
    phone = ($form.find('input[name="phone"]').val() || '').trim();
    if (!/^09\d{9}$/.test(phone)) { msg($form, 'شماره موبایل نامعتبر است.', 'is-error'); return; }

    busy($btn, true);
    post('ug_check_phone', { phone: phone }).done(function (res) {
      busy($btn, false);
      if (!res || !res.success) { msg($form, (res.data && res.data.message) || 'خطا', 'is-error'); return; }
      if (res.data.exists) {
        showStep('choose');
      } else {
        sendOtp('register');
        showStep('register');
      }
    }).fail(function (x) { busy($btn, false); msg($form, errText(x), 'is-error'); });
  });

  /* Step 2a: existing user picks a method */
  $card.on('click', '.ug-method', function () {
    var method = $(this).data('method');
    if (method === 'otp') {
      var $b = $card.find('[data-step="login-otp"] .ug-resend');
      sendOtp('login', $b);
      showStep('login-otp');
    } else {
      showStep('login-password');
    }
  });

  /* switch from password → OTP */
  $card.on('click', '.ug-switch-otp', function () {
    var $b = $card.find('[data-step="login-otp"] .ug-resend');
    sendOtp('login', $b);
    showStep('login-otp');
  });

  /* resend buttons */
  $card.on('click', '.ug-resend', function () {
    var purpose = $(this).data('purpose');
    sendOtp(purpose, $(this));
  });

  /* edit phone → back to step 1 */
  $card.on('click', '.ug-auth-edit', function () { showStep('phone'); });

  /* Step 2b: OTP login */
  $card.on('submit', '[data-step="login-otp"]', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $btn = $form.find('button[type="submit"]');
    busy($btn, true);
    post('ug_verify_otp_login', { phone: phone, code: $form.find('input[name="code"]').val() })
      .done(function (res) { if (!redirect(res)) { busy($btn, false); msg($form, res.data.message, 'is-error'); } })
      .fail(function (x) { busy($btn, false); msg($form, errText(x), 'is-error'); });
  });

  /* Step 2c: password login */
  $card.on('submit', '[data-step="login-password"]', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $btn = $form.find('button[type="submit"]');
    busy($btn, true);
    post('ug_login', { login: phone, password: $form.find('input[name="password"]').val() })
      .done(function (res) { if (!redirect(res)) { busy($btn, false); msg($form, res.data.message, 'is-error'); } })
      .fail(function (x) { busy($btn, false); msg($form, errText(x), 'is-error'); });
  });

  /* Step 3: register */
  $card.on('submit', '[data-step="register"]', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $btn = $form.find('button[type="submit"]');
    if (!$form.find('input[name="terms"]').is(':checked')) { msg($form, 'پذیرش قوانین الزامی است.', 'is-error'); return; }
    busy($btn, true);
    post('ug_verify_otp_register', {
      phone: phone,
      code: $form.find('input[name="code"]').val(),
      name: $form.find('input[name="name"]').val(),
      email: $form.find('input[name="email"]').val(),
      password: $form.find('input[name="password"]').val(),
      terms: 1
    }).done(function (res) {
      if (!redirect(res)) { busy($btn, false); msg($form, res.data.message, 'is-error'); }
    }).fail(function (x) { busy($btn, false); msg($form, errText(x), 'is-error'); });
  });

  /* Google Sign-In */
  window.ugGoogleCallback = function (response) {
    if (!response || !response.credential) { return; }
    post('ug_google_signin', { credential: response.credential }).done(function (res) {
      if (!redirect(res)) { alert((res.data && res.data.message) || 'ورود با گوگل ناموفق بود.'); }
    });
  };
  function initGoogle() {
    var $slot = $('#ug-google-btn');
    if (!$slot.length || !window.google || !window.google.accounts) { return; }
    var clientId = $slot.data('client-id') || ugPanel.googleClientId;
    if (!clientId) { return; }
    window.google.accounts.id.initialize({ client_id: clientId, callback: window.ugGoogleCallback, ux_mode: 'popup' });
    window.google.accounts.id.renderButton($slot[0], { theme: 'outline', size: 'large', shape: 'pill', text: 'signin_with', locale: 'fa', width: 300 });
  }
  var gi = setInterval(function () { if (window.google && window.google.accounts) { clearInterval(gi); initGoogle(); } }, 300);
  setTimeout(function () { clearInterval(gi); }, 10000);

})(jQuery);
