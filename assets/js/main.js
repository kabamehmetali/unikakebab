/* =====================================================================
   Unika Kebab — front-end behaviour
   Plain JS, no dependencies beyond the Bootstrap bundle.
   ===================================================================== */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  document.addEventListener('DOMContentLoaded', function () {
    stickyNav();
    revealOnScroll();
    backToTop();
    menuCategoryNav();
    menuViewToggle();
    dishModal();
    activeYear();
    newsletterGuard();
  });

  /* --- menu: open a dish in a popup -------------------------------- */
  function dishModal() {
    var modal = document.getElementById('dishModal');
    var rows = document.querySelectorAll('[data-dish]');
    if (!modal || !rows.length) return;

    var panel = modal.querySelector('.dish-modal__panel');
    var elImage = document.getElementById('dishModalImage');
    var elCat = document.getElementById('dishModalCat');
    var elTitle = document.getElementById('dishModalTitle');
    var elPrice = document.getElementById('dishModalPrice');
    var elText = document.getElementById('dishModalText');
    var elChips = document.getElementById('dishModalChips');
    var closeBtn = modal.querySelector('.dish-modal__close');
    var lastTrigger = null;

    var chip = function (cls, icon, label) {
      return '<span class="chip ' + cls + '">' +
             (icon ? '<i class="' + icon + '" aria-hidden="true"></i> ' : '') +
             label + '</span>';
    };

    // Text from data-* is already escaped by PHP on the way in; assigning it
    // through textContent keeps it inert here too.
    var open = function (row) {
      lastTrigger = row;

      var name = row.getAttribute('data-dish-name') || '';
      elImage.src = row.getAttribute('data-dish-image') || '';
      elImage.alt = name;

      elTitle.textContent = name;
      elCat.textContent = row.getAttribute('data-dish-category') || '';
      elPrice.textContent = row.getAttribute('data-dish-price') || '';
      elText.textContent = row.getAttribute('data-dish-text') || '';

      var badge = row.getAttribute('data-dish-badge') || '';
      var chips = '';
      if (badge) chips += chip('chip--gold', '', escapeHtml(badge));
      if (row.getAttribute('data-dish-spicy') === '1') chips += chip('chip--spicy', 'fa-solid fa-pepper-hot', 'Spicy');
      if (row.getAttribute('data-dish-veg') === '1') chips += chip('chip--veg', 'fa-solid fa-leaf', 'Vegetarian');
      elChips.innerHTML = chips;

      lockScroll(true);
      modal.hidden = false;
      // let the browser paint the hidden state before transitioning in
      requestAnimationFrame(function () { modal.classList.add('is-open'); });
      if (closeBtn) closeBtn.focus();
    };

    var close = function () {
      modal.classList.remove('is-open');
      lockScroll(false);

      var done = function () {
        modal.hidden = true;
        if (lastTrigger) { lastTrigger.focus(); lastTrigger = null; }
      };
      if (reduceMotion) { done(); return; }
      window.setTimeout(done, 300);
    };

    rows.forEach(function (row) {
      row.addEventListener('click', function () { open(row); });
      row.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
          e.preventDefault();
          open(row);
        }
      });
    });

    modal.querySelectorAll('[data-dish-close]').forEach(function (el) {
      el.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
      if (modal.hidden) return;
      if (e.key === 'Escape') { close(); return; }
      if (e.key === 'Tab') trapFocus(e, panel);
    });
  }

  /* Keep Tab inside the open dialog. */
  function trapFocus(e, container) {
    if (!container) return;
    var items = container.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])');
    if (!items.length) return;

    var first = items[0];
    var last = items[items.length - 1];

    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }

  /* Freeze the page behind an overlay without the width jumping. */
  function lockScroll(on) {
    if (on) {
      var gap = window.innerWidth - document.documentElement.clientWidth;
      if (gap > 0) document.body.style.paddingRight = gap + 'px';
      document.body.classList.add('has-modal');
    } else {
      document.body.classList.remove('has-modal');
      document.body.style.paddingRight = '';
    }
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* --- menu: switch between photo cards and the plain list ---------- */
  function menuViewToggle() {
    var scope = document.querySelector('[data-menu-view]');
    if (!scope) return;

    var buttons = scope.querySelectorAll('[data-view-btn]');
    if (!buttons.length) return;

    var STORE_KEY = 'unika:menu-view-v2';

    var apply = function (view, remember) {
      if (view !== 'grid' && view !== 'list') view = 'grid';
      scope.setAttribute('data-view', view);

      buttons.forEach(function (btn) {
        var on = btn.getAttribute('data-view-btn') === view;
        btn.classList.toggle('is-active', on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      });

      if (remember) {
        try { window.localStorage.setItem(STORE_KEY, view); } catch (e) { /* private mode */ }
      }
    };

    // Bring back whichever layout they chose last time.
    var saved = null;
    try { saved = window.localStorage.getItem(STORE_KEY); } catch (e) { /* private mode */ }
    if (saved) apply(saved, false);

    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        apply(btn.getAttribute('data-view-btn'), true);
      });
    });
  }

  /* --- navbar gets a shadow once the page scrolls ------------------- */
  function stickyNav() {
    var nav = document.querySelector('.u-nav');
    if (!nav) return;

    var apply = function () {
      nav.classList.toggle('is-stuck', window.scrollY > 20);
    };
    apply();
    window.addEventListener('scroll', apply, { passive: true });
  }

  /* --- fade sections in as they enter the viewport ------------------ */
  function revealOnScroll() {
    var items = document.querySelectorAll('[data-reveal]');
    if (!items.length) return;

    if (reduceMotion || !('IntersectionObserver' in window)) {
      items.forEach(function (el) { el.classList.add('is-visible'); });
      return;
    }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });

    items.forEach(function (el) { io.observe(el); });
  }

  /* --- floating back-to-top button ---------------------------------- */
  function backToTop() {
    var btn = document.querySelector('.to-top');
    if (!btn) return;

    window.addEventListener('scroll', function () {
      btn.classList.toggle('is-on', window.scrollY > 500);
    }, { passive: true });

    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    });
  }

  /* --- menu page: highlight the category you are looking at --------- */
  function menuCategoryNav() {
    var pills = document.querySelectorAll('.menu-pill');
    var blocks = document.querySelectorAll('.menu-block');
    if (!pills.length || !blocks.length) return;

    var strip = document.querySelector('.menu-nav__scroll');

    // Slide the active pill into view — on a phone the later categories sit off-screen.
    var scrollPillIntoView = function (pill) {
      if (!strip || !pill) return;
      var target = pill.offsetLeft - (strip.clientWidth - pill.offsetWidth) / 2;
      strip.scrollTo({
        left: Math.max(0, target),
        behavior: reduceMotion ? 'auto' : 'smooth'
      });
    };

    var setActive = function (id) {
      pills.forEach(function (pill) {
        var on = pill.getAttribute('href') === '#' + id;
        pill.classList.toggle('is-active', on);
        if (on) scrollPillIntoView(pill);
      });
    };

    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) setActive(entry.target.id);
        });
      }, { rootMargin: '-45% 0px -50% 0px', threshold: 0 });

      blocks.forEach(function (block) { io.observe(block); });
    }

    // Keep the active pill in view on narrow screens.
    pills.forEach(function (pill) {
      pill.addEventListener('click', function () {
        setActive(pill.getAttribute('href').replace('#', ''));
      });
    });

    /* The strip hides its scrollbar, so on a narrow screen the last category
       just looks cut in half. Fade whichever edge still has pills behind it,
       so it reads as "there is more this way" rather than as clipped text. */
    if (strip) {
      var edges = function () {
        var over = strip.scrollWidth - strip.clientWidth;
        strip.classList.toggle('has-more-start', over > 1 && strip.scrollLeft > 2);
        strip.classList.toggle('has-more-end', over > 1 && strip.scrollLeft < over - 2);
      };

      edges();
      strip.addEventListener('scroll', edges, { passive: true });
      window.addEventListener('resize', edges);
      if ('ResizeObserver' in window) new ResizeObserver(edges).observe(strip);
    }
  }

  /* --- footer year -------------------------------------------------- */
  function activeYear() {
    document.querySelectorAll('[data-year]').forEach(function (el) {
      el.textContent = new Date().getFullYear();
    });
  }

  /* --- stop double submits on the newsletter / contact forms -------- */
  function newsletterGuard() {
    document.querySelectorAll('form[data-guard]').forEach(function (form) {
      form.addEventListener('submit', function () {
        var btn = form.querySelector('button[type="submit"]');
        if (!btn || btn.disabled) return;
        btn.disabled = true;
        btn.dataset.label = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Sending';
        // Re-enable if the browser restores the page from cache.
        setTimeout(function () {
          btn.disabled = false;
          btn.innerHTML = btn.dataset.label;
        }, 8000);
      });
    });
  }
})();
