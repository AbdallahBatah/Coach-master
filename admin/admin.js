/* Admin panel behaviour: repeatable rows, character counters, search preview, local times. */
(function () {
  'use strict';

  // Sidebar on small screens
  var toggle = document.querySelector('.side__toggle');
  var nav = document.getElementById('side-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Repeatable lists: add, remove, reorder. Row order in the page is the order that gets saved.
  var seq = 0;
  document.querySelectorAll('[data-list]').forEach(function (list) {
    var rows = list.querySelector('[data-rows]');
    var tpl = list.querySelector('template');
    list.addEventListener('click', function (e) {
      var btn = e.target.closest('button');
      if (!btn || !list.contains(btn)) return;
      var row = btn.closest('[data-row]');
      if (btn.hasAttribute('data-add')) {
        var html = tpl.innerHTML.replace(/__ID__/g, 'n' + Date.now() + (seq++));
        rows.insertAdjacentHTML('beforeend', html);
        var added = rows.lastElementChild;
        var first = added.querySelector('input,textarea,select');
        if (first) first.focus();
        markDirty();
      } else if (btn.hasAttribute('data-remove') && row) {
        row.remove();
        markDirty();
      } else if (btn.hasAttribute('data-move') && row) {
        var dir = parseInt(btn.getAttribute('data-move'), 10);
        var other = dir < 0 ? row.previousElementSibling : row.nextElementSibling;
        if (other) {
          if (dir < 0) rows.insertBefore(row, other); else rows.insertBefore(other, row);
          btn.focus();
          markDirty();
        }
      }
    });
  });

  // Unsaved-changes hint
  var editor = document.querySelector('[data-editor]');
  var dirty = document.querySelector('[data-dirty]');
  function markDirty() { if (dirty) dirty.hidden = false; }
  if (editor) editor.addEventListener('input', markDirty);

  // Character counters (meta title / description)
  document.querySelectorAll('[data-count]').forEach(function (el) {
    var max = parseInt(el.getAttribute('data-count'), 10);
    var out = document.createElement('p');
    out.className = 'counter';
    el.insertAdjacentElement('afterend', out);
    function update() {
      var n = el.value.length;
      out.textContent = n + ' of ' + max + ' characters' + (n > max ? ' — may be cut off in search results' : '');
      out.classList.toggle('is-over', n > max);
    }
    el.addEventListener('input', update);
    update();
  });

  // Google result preview on the SEO screen
  var sTitle = document.querySelector('[data-serp-title]');
  if (sTitle) {
    var sDesc = document.querySelector('[data-serp-desc]');
    var sUrl = document.querySelector('[data-serp-url]');
    var inTitle = document.getElementById('f-title');
    var inDesc = document.getElementById('f-description');
    var inUrl = document.getElementById('f-site_url');
    var autoUrl = sUrl.textContent;
    var cut = function (s, n) { return s.length > n ? s.slice(0, n - 1).trimEnd() + '…' : s; };
    var sync = function () {
      sTitle.textContent = cut(inTitle.value, 60);
      sDesc.textContent = cut(inDesc.value, 158);
      sUrl.textContent = (inUrl.value || autoUrl).replace(/\/$/, '');
    };
    [inTitle, inDesc, inUrl].forEach(function (el) { el.addEventListener('input', sync); });
    sync();
  }

  // Show lead times in the viewer's own time zone
  document.querySelectorAll('time[data-local]').forEach(function (t) {
    var d = new Date(t.getAttribute('datetime'));
    if (!isNaN(d)) {
      t.textContent = d.toLocaleString(undefined, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }
  });

  // Show / hide the password on the sign-in screen
  document.querySelectorAll('[data-show-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('aria-controls'));
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Hide' : 'Show';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    });
  });

  document.querySelectorAll('[data-select]').forEach(function (el) {
    el.addEventListener('focus', function () { el.select(); });
  });
})();
