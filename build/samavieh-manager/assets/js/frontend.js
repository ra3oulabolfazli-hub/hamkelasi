(function () {
  'use strict';

  function copyText(text, button) {
    var done = function () {
      if (!button) return;
      var old = button.innerHTML;
      button.textContent = (window.smvFrontend && smvFrontend.copied) || 'کد پیگیری کپی شد.';
      window.setTimeout(function () { button.innerHTML = old; }, 2200);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done).catch(function () { fallback(text, done); });
    } else {
      fallback(text, done);
    }
  }

  function fallback(text, done) {
    var input = document.createElement('textarea');
    input.value = text;
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.appendChild(input);
    input.select();
    try { document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(input);
    done();
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-smv-copy]');
    if (button) copyText(button.getAttribute('data-smv-copy'), button);
  });

  document.querySelectorAll('[data-smv-uppercase]').forEach(function (input) {
    input.addEventListener('input', function () {
      input.value = input.value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
    });
  });

  document.querySelectorAll('[data-smv-file]').forEach(function (input) {
    input.addEventListener('change', function () {
      var form = input.closest('.smv-field');
      var label = form ? form.querySelector('[data-smv-file-label]') : null;
      if (!input.files || !input.files[0]) return;
      var file = input.files[0];
      var max = window.smvFrontend ? parseInt(smvFrontend.maxSize, 10) : 5242880;
      if (file.size > max) {
        window.alert((window.smvFrontend && smvFrontend.sizeError) || 'حجم فایل بیشتر از حد مجاز است.');
        input.value = '';
        if (label) label.textContent = 'تصویر یا PDF فیش را انتخاب کنید';
        return;
      }
      if (label) label.textContent = file.name;
    });
  });

  document.querySelectorAll('[data-smv-form]').forEach(function (form) {
    form.addEventListener('submit', function () {
      var button = form.querySelector('[data-smv-submit]');
      if (!button) return;
      button.disabled = true;
      button.style.opacity = '.72';
      button.textContent = (window.smvFrontend && smvFrontend.sending) || 'در حال ارسال…';
    });
  });

  window.addEventListener('pageshow', function () {
    document.querySelectorAll('[data-smv-submit]').forEach(function (button) {
      button.disabled = false;
      button.style.opacity = '';
    });
  });
}());
