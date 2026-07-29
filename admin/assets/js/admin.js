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
    mirrorFields();
    tokenChips();
    emailPreview();
    campaignSender();
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
        before = ids();

        row.classList.add('is-dragging');
        group.classList.add('is-sorting');
        document.body.classList.add('a-dragging');

        // Deliberately not setPointerCapture on the handle: dragging moves the
        // row — and the handle inside it — out of the DOM and back, and a
        // capturing element that leaves the document loses the capture. The
        // document sees every move regardless of what sits under the cursor.
        document.addEventListener('pointermove', move);
        document.addEventListener('pointerup', finish);
        document.addEventListener('pointercancel', finish);
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
        document.removeEventListener('pointermove', move);
        document.removeEventListener('pointerup', finish);
        document.removeEventListener('pointercancel', finish);

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

  /* --- keep a hidden field in step with a visible one ----------------- *
   * The "send a test copy" form sits outside the composer, so it carries  *
   * hidden copies of the subject and body. They are filled in server-side *
   * as well, which is what keeps the button working without JavaScript.   */
  function mirrorFields() {
    document.querySelectorAll('[data-mirror]').forEach(function (hidden) {
      var source = document.querySelector(hidden.getAttribute('data-mirror'));
      if (!source) return;

      var copy = function () { hidden.value = source.value; };
      source.addEventListener('input', copy);
      source.addEventListener('change', copy);
      copy();
    });
  }

  /* --- placeholder chips --------------------------------------------- *
   * Clicking {{first_name}} drops it wherever the cursor was last, which  *
   * is nearly always inside the email body.                               */
  function tokenChips() {
    var chips = document.querySelectorAll('.a-token[data-token]');
    if (!chips.length) return;

    var fields = Array.prototype.slice.call(document.querySelectorAll('[data-tpl-field]'));
    if (!fields.length) return;

    // Default to the body — the biggest field, and the one people mean.
    var target = fields.filter(function (f) { return f.tagName === 'TEXTAREA'; })[0] || fields[0];

    fields.forEach(function (field) {
      field.addEventListener('focus', function () { target = field; });
    });

    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        var token = chip.getAttribute('data-token');
        var at = target.selectionStart;
        var to = target.selectionEnd;

        if (typeof at === 'number' && typeof to === 'number') {
          target.value = target.value.slice(0, at) + token + target.value.slice(to);
          target.selectionStart = target.selectionEnd = at + token.length;
        } else {
          target.value += token;
        }

        target.focus();
        target.dispatchEvent(new Event('input', { bubbles: true }));

        chip.classList.add('is-copied');
        setTimeout(function () { chip.classList.remove('is-copied'); }, 700);
      });
    });
  }

  /* --- live inbox preview --------------------------------------------- *
   * The server owns the layout, so the preview asks the server rather than *
   * trying to rebuild the email in the browser and getting it slightly     *
   * wrong. The reply goes into a sandboxed iframe: nothing inside a        *
   * preview can run, whatever HTML was pasted into the editor.             */
  function emailPreview() {
    var wrap = document.querySelector('.a-preview[data-preview-url]');
    if (!wrap) return;

    var frame = wrap.querySelector('.a-preview__frame');
    var line = wrap.querySelector('.a-preview__subject');
    var form = document.querySelector(wrap.getAttribute('data-preview-form') || '#tplForm, #campForm');
    if (!frame || !form) return;

    var url = wrap.getAttribute('data-preview-url');
    var token = wrap.getAttribute('data-preview-token');
    var wait = null;

    function field(name) {
      var input = form.querySelector('[name="' + name + '"]');
      return input ? input.value : '';
    }

    function render() {
      wrap.classList.add('is-loading');
      if (line) line.textContent = field('subject') || 'Subject line';

      var body = new FormData();
      body.append('_csrf', token);
      body.append('action', 'preview_html');
      body.append('name', field('name'));
      body.append('subject', field('subject'));
      body.append('preheader', field('preheader'));
      body.append('body_html', field('body_html'));

      fetch(url, {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (res) { return res.text(); })
        .then(function (html) { frame.srcdoc = html; })
        .catch(function () {
          frame.srcdoc = '<p style="font:15px system-ui;padding:2rem;color:#9a2b02">'
            + 'The preview could not be loaded. Save the page and try again.</p>';
        })
        .then(function () { wrap.classList.remove('is-loading'); });
    }

    // Typing redraws the preview, but only once the typing stops.
    form.querySelectorAll('[data-tpl-field]').forEach(function (input) {
      input.addEventListener('input', function () {
        clearTimeout(wait);
        wait = setTimeout(render, 900);
      });
    });

    document.querySelectorAll('#tplRefresh, #campRefresh').forEach(function (btn) {
      btn.addEventListener('click', render);
    });

    // Picking a template pours its wording into the composer.
    var picker = form.querySelector('[data-template-picker]');
    if (picker) {
      picker.addEventListener('change', function () {
        var option = picker.options[picker.selectedIndex];
        if (!option || !option.value || option.value === '0') return;

        ['subject', 'preheader', 'body_html'].forEach(function (name) {
          var input = form.querySelector('[name="' + name + '"]');
          var value = option.getAttribute('data-' + (name === 'body_html' ? 'body' : name));
          if (input && value !== null) {
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });

        render();
      });
    }

    /* phone / desktop */
    document.querySelectorAll('[data-preview-width]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        wrap.classList.toggle('is-phone', btn.getAttribute('data-preview-width') !== '0');
      });
    });

    render();
  }

  /* --- work through a campaign, one batch at a time -------------------- *
   * The page holds the loop rather than the server: a batch is one short   *
   * request, so no single request can hit a time limit, and the numbers on *
   * screen are the numbers in the database after every round.              */
  function campaignSender() {
    var box = document.getElementById('campProgress');
    if (!box) return;

    var url = box.getAttribute('data-send-url');
    var id = box.getAttribute('data-campaign-id');
    var token = box.getAttribute('data-token');

    var fill = box.querySelector('.a-progress__fill');
    var say = box.querySelector('[data-progress-message]');
    var done = box.querySelector('[data-progress-done]');
    var total = box.querySelector('[data-progress-total]');
    var sent = box.querySelector('[data-progress-sent]');
    var failed = box.querySelector('[data-progress-failed]');

    // The plain "send the next batch" button is the no-JavaScript path.
    // With JavaScript running it would only get in the way.
    var manual = document.querySelector('[data-batch-fallback]');
    var manualForm = manual ? manual.closest('form') : null;

    if (box.getAttribute('data-autostart') !== '1') return;
    if (manualForm) manualForm.style.display = 'none';

    function paint(data) {
      if (typeof data.total === 'number' && data.total > 0) {
        var pct = Math.round(((data.sent + data.failed) / data.total) * 100);
        if (fill) fill.style.width = pct + '%';
        if (total) total.textContent = data.total;
        if (done) done.textContent = data.sent + data.failed;
      }
      if (sent && typeof data.sent === 'number') sent.textContent = data.sent;
      if (failed && typeof data.failed === 'number') failed.textContent = data.failed;
    }

    function status(text, state) {
      if (!say) return;
      say.className = 'a-progress__status' + (state ? ' is-' + state : '');
      say.innerHTML = (state === 'bad'
        ? '<i class="fa-solid fa-circle-exclamation"></i> '
        : state === 'done'
          ? '<i class="fa-solid fa-circle-check"></i> '
          : '<i class="fa-solid fa-circle-notch fa-spin"></i> ') + escapeHtml(text);
    }

    function stop(message) {
      status(message, 'bad');
      if (manualForm) manualForm.style.display = '';   // let them retry by hand
    }

    function step() {
      status('Sending…');

      var body = new FormData();
      body.append('_csrf', token);
      body.append('action', 'send_batch');
      body.append('id', id);

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
            if (!data) throw new Error('The server sent back something unexpected. Reload the page to see where the send got to.');
            return data;
          });
        })
        .then(function (data) {
          paint(data);

          if (!data.ok) {
            stop(data.message || 'The send stopped. Nobody was skipped — press the button to carry on.');
            return;
          }
          if (data.done) {
            status(data.message || 'Finished.', 'done');
            // Reload once so the recipient table shows the final result.
            setTimeout(function () { window.location.reload(); }, 1600);
            return;
          }

          status(data.message || 'Sending…');
          setTimeout(step, 400);
        })
        .catch(function (err) {
          stop(err.message);
        });
    }

    step();
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
