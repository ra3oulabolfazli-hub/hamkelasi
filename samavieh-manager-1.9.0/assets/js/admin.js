(function () {
  'use strict';
  document.querySelectorAll('form[data-smv-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-smv-confirm'))) event.preventDefault();
    });
  });
}());
