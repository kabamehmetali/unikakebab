/* =====================================================================
   Unika Kebab — admin panel behaviour
   ===================================================================== */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    sidebarToggle();
    dismissAlerts();
    secretToggles();
    imageDropzones();
    sortableRows();
    confirmDeletes();
    autoSlug();
    charCounters();
    submitGuard();
  });

  /* --- mobile sidebar ----------------------------------------------- */
  function sidebarToggle() {
    var side = document.getElementById('aSide');
    var burger = document.getElementById('aBurger');
    var backdrop = document.getElementById('aBackdrop');
    if (!side || !burger) return;

    var close = function () {
      side.classList.remove('is-open');
      if (backdrop) backdrop.classList.remove('is-on');
    };

    burger.addEventListener('click', function () {
      side.classList.toggle('is-open');
      if (backdrop) backdrop.classList.toggle('is-on', side.classList.contains('is-open'));
    });

    if (backdrop) backdrop.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
  }

  /* --- dismissable flash messages ----------------------------------- */
  function dismissAlerts() {
    document.querySelectorAll('.a-alert__close').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var alert = btn.closest('.a-alert');
        if (!alert) return;
        alert.style.transition = 'opacity .25s, transform .25s';
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-6px)';
        setTimeout(function () { alert.remove(); }, 250);
      });
    });
  }

  /* --- show / hide a stored API key --------------------------------- *
   * The input starts masked. Clicking "Show" swaps in the real value,
   * which the page holds in data-secret.                               */
  function secretToggles() {
    document.querySelectorAll('.a-secret').forEach(function (wrap) {
      var input = wrap.querySelector('.a-input');
      var btn = wrap.querySelector('.a-secret__toggle');
      if (!input || !btn) return;

      var real = input.getAttribute('data-secret') || '';
      var masked = input.value;
      var shown = false;

      btn.addEventListener('click', function () {
        // Once the admin types a new key, leave their text alone.
        if (input.dataset.dirty === '1') return;

        shown = !shown;
        input.value = shown ? real : masked;
        btn.innerHTML = shown
          ? '<i class="fa-solid fa-eye-slash"></i> Hide'
          : '<i class="fa-solid fa-eye"></i> Show';
      });

      input.addEventListener('input', function () {
        input.dataset.dirty = '1';
        btn.disabled = true;
        btn.style.opacity = '.4';
      });
    });
  }

  /* --- drop an image on the box, or click it to browse ---------------- *
   * Upgrades every .a-media picker in the admin: drag a file anywhere    *
   * over the box, or click it, and the preview updates straight away.    *
   * The markup stays a plain file input, so without JavaScript the form  *
   * still works exactly as before.                                       */
  var MAX_UPLOAD = 8 * 1024 * 1024;   // matches save_upload() in functions.php

  function imageDropzones() {
    document.querySelectorAll('.a-media input[type="file"]').forEach(function (input) {
      var zone = input.closest('.a-media');
      if (!zone) return;

      var img = input.getAttribute('data-preview')
        ? document.querySelector(input.getAttribute('data-preview'))
        : zone.querySelector('.a-media__preview');

      zone.classList.add('a-media--drop');

      // Affordances are added here rather than in the PHP, so they only ever
      // appear when the behaviour behind them is actually available.
      var veil = el('div', 'a-media__veil',
        '<i class="fa-solid fa-cloud-arrow-up"></i> Drop the image to use it');
      zone.appendChild(veil);

      var hint = el('p', 'a-media__hint',
        '<strong>Drop an image here</strong> or click to browse');
      input.parentNode.insertBefore(hint, input);

      var chosen = el('span', 'a-media__file', '');
      var problem = el('span', 'a-media__error', '');
      input.parentNode.insertBefore(chosen, input.nextSibling);
      input.parentNode.insertBefore(problem, chosen.nextSibling);

      /* Clicking the box opens the picker — but never when the click landed
         on something interactive that lives inside it. */
      zone.addEventListener('click', function (e) {
        if (e.target.closest('input, label, button, a, .a-media__file, .a-media__error')) return;
        input.click();
      });

      /* --- accept the file ------------------------------------------- */
      function accept(file) {
        problem.classList.remove('is-on');

        if (!file) return false;
        if (!/^image\//.test(file.type)) {
          fail('That file is not an image. Use a JPG, PNG, GIF or WebP.');
          return false;
        }
        if (file.size > MAX_UPLOAD) {
          fail('That image is ' + size(file.size) + ' — the limit is 8 MB.');
          return false;
        }

        if (img) img.src = URL.createObjectURL(file);
        chosen.innerHTML = '<i class="fa-solid fa-circle-check"></i>'
          + '<span>' + escapeHtml(file.name) + '</span>'
          + '<small>' + size(file.size) + '</small>';
        chosen.classList.add('is-on');
        return true;
      }

      function fail(message) {
        chosen.classList.remove('is-on');
        problem.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + escapeHtml(message);
        problem.classList.add('is-on');
      }

      /* --- picked through the normal file dialog --------------------- */
      input.addEventListener('change', function () {
        if (!accept(input.files && input.files[0])) input.value = '';
      });

      /* --- dragged in from the desktop ------------------------------- */
      var depth = 0;   // dragenter/leave also fire for children, so count them

      zone.addEventListener('dragenter', function (e) {
        if (!hasFile(e)) return;
        e.preventDefault();
        depth++;
        zone.classList.add('is-over');
      });

      zone.addEventListener('dragover', function (e) {
        if (!hasFile(e)) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
      });

      zone.addEventListener('dragleave', function () {
        depth = Math.max(0, depth - 1);
        if (depth === 0) zone.classList.remove('is-over');
      });

      zone.addEventListener('drop', function (e) {
        if (!hasFile(e)) return;
        e.preventDefault();
        depth = 0;
        zone.classList.remove('is-over');

        var file = e.dataTransfer.files && e.dataTransfer.files[0];
        if (!accept(file)) return;

        // Hand the dropped file to the input so a normal form submit carries it.
        var box = new DataTransfer();
        box.items.add(file);
        input.files = box.files;
      });
    });

    // Dropping a file anywhere else must not navigate away from the form.
    ['dragover', 'drop'].forEach(function (type) {
      window.addEventListener(type, function (e) {
        if (hasFile(e) && !e.target.closest('.a-media')) e.preventDefault();
      });
    });
  }

  function hasFile(e) {
    return !!(e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') > -1);
  }

  function size(bytes) {
    return bytes < 1024 * 1024
      ? Math.max(1, Math.round(bytes / 1024)) + ' KB'
      : (bytes / 1024 / 1024).toFixed(1) + ' MB';
  }

  function el(tag, className, html) {
    var node = document.createElement(tag);
    node.className = className;
    node.innerHTML = html;
    return node;
  }

  function escapeHtml(text) {
    var node = document.createElement('div');
    node.textContent = text;
    return node.innerHTML;
  }

  /* --- drag a menu item into a new position -------------------------- *
   * Each <tbody data-sortable> is one category. A row can only be moved  *
   * inside its own tbody, which is what keeps a drag from silently       *
   * changing an item's category. Pointer events cover mouse, pen and     *
   * touch; the arrow keys do the same job for the keyboard.              */
  function sortableRows() {
    var table = document.querySelector('.a-table--sortable[data-sort-url]');
    if (!table) return;

    var url = table.getAttribute('data-sort-url');
    var token = table.getAttribute('data-sort-token');
    var status = document.querySelector('[data-sort-status]');

    table.querySelectorAll('tbody[data-sortable]').forEach(function (group) {
      var dragging = null;
      var before = null;       // the order before the drag, to undo a failed save
      var handle = null;

      group.querySelectorAll('.a-drag').forEach(function (grip) {
        grip.addEventListener('pointerdown', start);
        grip.addEventListener('keydown', keyMove);
        // Prevent the browser's own drag-the-image behaviour inside the row.
        grip.addEventListener('dragstart', function (e) { e.preventDefault(); });
      });

      function rows() {
        return Array.prototype.slice.call(group.querySelectorAll('tr[data-id]'));
      }

      function ids() {
        return rows().map(function (row) { return row.getAttribute('data-id'); });
      }

      function renumber() {
        rows().forEach(function (row, i) {
          var num = row.querySelector('.a-sort__num');
          if (num) num.textContent = i + 1;
        });
      }

      /* --- pointer drag --------------------------------------------- */
      function start(e) {
        if (e.button > 0) return;               // right / middle click
        var row = e.target.closest('tr[data-id]');
        if (!row) return;

        e.preventDefault();
        dragging = row;
        handle = e.currentTarget;
        before = ids();

        row.classList.add('is-dragging');
        group.classList.add('is-sorting');
        document.body.classList.add('a-dragging');

        handle.setPointerCapture(e.pointerId);
        handle.addEventListener('pointermove', move);
        handle.addEventListener('pointerup', finish);
        handle.addEventListener('pointercancel', finish);
      }

      function move(e) {
        if (!dragging) return;

        edgeScroll(e.clientY);

        var over = rows().filter(function (row) {
          if (row === dragging) return false;
          var box = row.getBoundingClientRect();
          return e.clientY >= box.top && e.clientY <= box.bottom;
        })[0];
        if (!over) return;

        var box = over.getBoundingClientRect();
        var below = e.clientY > box.top + box.height / 2;
        group.insertBefore(dragging, below ? over.nextSibling : over);
        renumber();
      }

      function finish() {
        if (!dragging) return;

        var row = dragging;
        handle.removeEventListener('pointermove', move);
        handle.removeEventListener('pointerup', finish);
        handle.removeEventListener('pointercancel', finish);

        row.classList.remove('is-dragging');
        group.classList.remove('is-sorting');
        document.body.classList.remove('a-dragging');
        dragging = null;

        commit(row);
      }

      /* --- keyboard ------------------------------------------------- */
      function keyMove(e) {
        if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;

        var row = e.target.closest('tr[data-id]');
        if (!row) return;
        e.preventDefault();

        var all = rows();
        var at = all.indexOf(row);
        var to = e.key === 'ArrowUp' ? at - 1 : at + 1;
        if (to < 0 || to >= all.length) return;

        before = ids();
        if (e.key === 'ArrowUp') {
          group.insertBefore(row, all[to]);
        } else {
          group.insertBefore(row, all[to].nextSibling);
        }

        renumber();
        e.target.focus();          // moving the row in the DOM drops focus
        commit(row);
      }

      /* --- save ----------------------------------------------------- */
      function commit(row) {
        var now = ids();
        if (before && now.join() === before.join()) return;   // nothing moved

        var undo = before;
        renumber();
        say('saving', 'Saving order…');

        var body = new FormData();
        body.append('_csrf', token);
        body.append('action', 'reorder');
        body.append('category_id', group.getAttribute('data-category-id'));
        now.forEach(function (id) { body.append('order[]', id); });

        fetch(url, {
          method: 'POST',
          body: body,
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
          .then(function (res) {
            return res.text().then(function (text) {
              var data = null;
              try { data = JSON.parse(text); } catch (err) { /* not JSON */ }
              if (!res.ok || !data || !data.ok) {
                throw new Error((data && data.message) || 'The order could not be saved.');
              }
              return data;
            });
          })
          .then(function (data) {
            say('ok', data.message || 'Order saved');
            row.classList.add('is-settled');
            setTimeout(function () { row.classList.remove('is-settled'); }, 1200);
          })
          .catch(function (err) {
            // Put the rows back, so what is on screen is what is in the database.
            if (undo) {
              undo.forEach(function (id) {
                var back = group.querySelector('tr[data-id="' + id + '"]');
                if (back) group.appendChild(back);
              });
              renumber();
            }
            say('bad', err.message);
          });
      }
    });

    /* --- the little status line next to the hint --------------------- */
    var clear = null;
    function say(state, text) {
      if (!status) return;
      clearTimeout(clear);

      status.className = 'a-sortbar__status is-on is-' + state;
      status.innerHTML = (state === 'saving'
        ? '<i class="fa-solid fa-circle-notch fa-spin"></i> '
        : state === 'ok'
          ? '<i class="fa-solid fa-circle-check"></i> '
          : '<i class="fa-solid fa-circle-exclamation"></i> ') + escapeHtml(text);

      if (state !== 'saving') {
        clear = setTimeout(function () { status.classList.remove('is-on'); }, state === 'ok' ? 2200 : 6000);
      }
    }

    /* Keep scrolling when a drag reaches the top or bottom of the window. */
    function edgeScroll(y) {
      var margin = 90;
      if (y < margin) {
        window.scrollBy(0, -Math.ceil((margin - y) / 6));
      } else if (y > window.innerHeight - margin) {
        window.scrollBy(0, Math.ceil((y - (window.innerHeight - margin)) / 6));
      }
    }
  }

  /* --- confirm before deleting -------------------------------------- */
  function confirmDeletes() {
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (!window.confirm(form.getAttribute('data-confirm'))) {
          e.preventDefault();
        }
      });
    });
  }

  /* --- suggest a slug from the name field --------------------------- */
  function autoSlug() {
    var name = document.querySelector('[data-slug-source]');
    var slug = document.querySelector('[data-slug-target]');
    if (!name || !slug) return;

    var touched = slug.value.trim() !== '';
    slug.addEventListener('input', function () { touched = true; });

    name.addEventListener('input', function () {
      if (touched) return;
      slug.value = name.value
        .toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    });
  }

  /* --- live character counters for meta fields ---------------------- */
  function charCounters() {
    document.querySelectorAll('[data-counter]').forEach(function (field) {
      var target = document.querySelector(field.getAttribute('data-counter'));
      if (!target) return;

      var ideal = parseInt(field.getAttribute('data-counter-ideal') || '0', 10);
      var update = function () {
        var len = field.value.length;
        target.textContent = len + (ideal ? ' / ' + ideal : '') + ' characters';
        target.style.color = (ideal && len > ideal) ? '#c33601' : '';
      };
      field.addEventListener('input', update);
      update();
    });
  }

  /* --- stop accidental double submits ------------------------------- */
  function submitGuard() {
    document.querySelectorAll('form[data-guard]').forEach(function (form) {
      form.addEventListener('submit', function () {
        var btn = form.querySelector('button[type="submit"]');
        if (!btn || btn.disabled) return;
        var label = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving';
        setTimeout(function () { btn.disabled = false; btn.innerHTML = label; }, 8000);
      });
    });
  }
})();
