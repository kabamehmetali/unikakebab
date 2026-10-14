/* =====================================================================
   Locations — the Toronto zone locator.
   Loaded only by locations.php (see $pageJs in includes/footer.php).

   Every zone is already in the HTML with its distance and its directions
   link, so the page works with no JavaScript at all: the tabs are open,
   the list is readable and the Directions button goes to Google Maps.
   This file only adds the map swap and the tab filtering on top.
   ===================================================================== */
(function () {
  'use strict';

  var locator = document.querySelector('.zone-locator');
  if (!locator) { return; }

  var map        = document.getElementById('zoneMap');
  var nameOut    = document.getElementById('zoneName');
  var kmOut      = document.getElementById('zoneKm');
  var bandOut    = document.getElementById('zoneBand');
  var dirOut     = document.getElementById('zoneDirections');
  var pageOut    = document.getElementById('zonePage');

  // ---------------------------------------------------------------
  //  District tabs
  // ---------------------------------------------------------------
  var tabs   = Array.prototype.slice.call(locator.querySelectorAll('[data-zone-tab]'));
  var panels = Array.prototype.slice.call(locator.querySelectorAll('[data-zone-panel]'));

  function showDistrict(key) {
    tabs.forEach(function (tab) {
      var on = tab.getAttribute('data-zone-tab') === key;
      tab.classList.toggle('is-active', on);
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    panels.forEach(function (panel) {
      var on = panel.getAttribute('data-zone-panel') === key;
      panel.classList.toggle('is-active', on);
      panel.hidden = !on;
    });
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      showDistrict(tab.getAttribute('data-zone-tab'));
    });

    // Left/right arrows move between districts, which is what a person
    // expects from a tab strip and what a screen reader announces.
    tab.addEventListener('keydown', function (event) {
      var step = event.key === 'ArrowRight' ? 1 : (event.key === 'ArrowLeft' ? -1 : 0);
      if (!step) { return; }
      event.preventDefault();
      var next = tabs[(tabs.indexOf(tab) + step + tabs.length) % tabs.length];
      showDistrict(next.getAttribute('data-zone-tab'));
      next.focus();
    });
  });

  // ---------------------------------------------------------------
  //  Picking a zone redraws the route
  // ---------------------------------------------------------------
  var template = locator.getAttribute('data-zone-embed-template') || '';
  var rows = Array.prototype.slice.call(locator.querySelectorAll('[data-zone-origin]'));

  function embedFor(origin) {
    return template ? template.replace('{o}', encodeURIComponent(origin)) : '';
  }

  function selectZone(row) {
    rows.forEach(function (other) { other.classList.remove('is-active'); });
    row.classList.add('is-active');

    var embed = embedFor(row.getAttribute('data-zone-origin') || '');
    if (map && embed) { map.src = embed; }

    var km   = row.getAttribute('data-zone-km');
    var page = row.getAttribute('data-zone-page');

    if (nameOut) { nameOut.textContent = row.getAttribute('data-zone-name') || ''; }
    if (kmOut)   { kmOut.textContent   = km ? km + ' km' : ''; }
    if (bandOut) { bandOut.textContent = row.getAttribute('data-zone-band') || ''; }

    // The row's own href is already the directions URL for that zone.
    if (dirOut) { dirOut.href = row.getAttribute('href') || '#'; }

    // The "read the route" button only exists for the handful of
    // neighbourhoods that have actually been written up.
    if (pageOut) {
      if (page) {
        pageOut.href = page;
        pageOut.hidden = false;
      } else {
        pageOut.hidden = true;
        pageOut.removeAttribute('href');
      }
    }
  }

  rows.forEach(function (row) {
    row.addEventListener('click', function (event) {
      // Let a deliberate new-tab click through to Maps untouched.
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) { return; }
      event.preventDefault();
      selectZone(row);
    });
  });

  if (rows.length) { selectZone(rows[0]); }
}());
