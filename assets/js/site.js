/* Small progressive enhancements. The page and the form work without this file. */
(function () {
  'use strict';

  // Mobile menu
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('site-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) {
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // Lead forms: show the dial code for the chosen country, lock the button while sending
  document.querySelectorAll('[data-lead-form]').forEach(function (form) {
    var country = form.querySelector('[data-country]');
    var out = form.querySelector('[data-dial-out]');
    function syncDial() {
      if (!country || !out) return;
      var dial = country.options[country.selectedIndex].getAttribute('data-dial');
      out.textContent = dial ? '+' + dial : '+';
    }
    if (country) {
      country.addEventListener('change', syncDial);
      syncDial();
    }
    form.addEventListener('submit', function (e) {
      if (!form.checkValidity()) {
        e.preventDefault();
        form.reportValidity();
        return;
      }
      var btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Sending…';
      }
    });
    var alert = form.querySelector('.lead-form__alert');
    if (alert) {
      alert.focus({ preventScroll: true });
    }
  });

  // "Register for this cohort" buttons pre-select the cohort in the main form without a page reload
  var register = document.getElementById('register');
  var cohortSelect = register ? register.querySelector('[data-cohort-select]') : null;
  document.querySelectorAll('a[data-cohort]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      if (!cohortSelect || !register) return;
      e.preventDefault();
      cohortSelect.value = link.getAttribute('data-cohort');
      register.scrollIntoView();
      var first = register.querySelector('input[name="name"]');
      if (first) first.focus({ preventScroll: true });
    });
  });

  // Sticky call to action on small screens: visible only while no form is on screen
  var sticky = document.querySelector('[data-sticky]');
  if (sticky && 'IntersectionObserver' in window) {
    sticky.hidden = false;
    var visible = new Set();
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) visible.add(en.target); else visible.delete(en.target);
      });
      sticky.classList.toggle('is-visible', visible.size === 0 && window.scrollY > 200);
    }, { threshold: 0.05 });
    document.querySelectorAll('.form-card').forEach(function (el) { io.observe(el); });
  }
})();
