(function () {
  'use strict';

  /* Menu mobile (drawer) */
  var toggle = document.querySelector('.menu-toggle');
  var drawer = document.getElementById('mobile-nav');
  var overlay = document.getElementById('mobile-nav-overlay');

  function closeDrawer() {
    drawer.classList.remove('is-open');
    overlay.classList.remove('is-open');
    toggle.setAttribute('aria-expanded', 'false');
    setTimeout(function () {
      drawer.hidden = true;
      overlay.hidden = true;
    }, 300);
  }

  function openDrawer() {
    drawer.hidden = false;
    overlay.hidden = false;
    requestAnimationFrame(function () {
      drawer.classList.add('is-open');
      overlay.classList.add('is-open');
    });
    toggle.setAttribute('aria-expanded', 'true');
  }

  if (toggle && drawer && overlay) {
    toggle.addEventListener('click', function () {
      var isOpen = drawer.classList.contains('is-open');
      isOpen ? closeDrawer() : openDrawer();
    });
    overlay.addEventListener('click', closeDrawer);
    drawer.querySelectorAll('a:not(.menu-disabled)').forEach(function (link) {
      link.addEventListener('click', closeDrawer);
    });
  }

  /* FAQ accordion */
  document.querySelectorAll('.faq-question').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var answer = document.getElementById(btn.getAttribute('aria-controls'));
      var isOpen = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', String(!isOpen));
      if (answer) answer.hidden = isOpen;
    });
  });
})();
