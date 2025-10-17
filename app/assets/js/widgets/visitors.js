
// Visitors widget (omni-boot v3: api.geo first, visitors fallback)
(function (global) {
  'use strict';

  var LOG_PREFIX = '%c[VisitorsWidget]%c ';
  function log(){ try { var a=[LOG_PREFIX,'color:#4caf50;font-weight:700','color:inherit']; a.push.apply(a, arguments); console.log.apply(console,a);} catch(e){} }
  function warn(){ try { var a=[LOG_PREFIX,'color:#ff9800;font-weight:700','color:inherit']; a.push.apply(a, arguments); console.warn.apply(console,a);} catch(e){} }
  function err(){ try { var a=[LOG_PREFIX,'color:#e53935;font-weight:700','color:inherit']; a.push.apply(a, arguments); console.error.apply(console,a);} catch(e){} }

  var REMEMBER_FLAG_KEY = 'te_visitors_remember';
  var VIEW_STATE_KEY    = 'te_visitors_view';
  var MOUNTED_ATTR      = 'data-te-visitors-mounted';

  function rememberOn(){ try { return localStorage.getItem(REMEMBER_FLAG_KEY) === '1'; } catch(e){ return false; } }
  function viewLoad(){
    try {
      var raw = localStorage.getItem(VIEW_STATE_KEY);
      if (!raw) return null;
      var o = JSON.parse(raw);
      if (!o || typeof o.zoom !== 'number' || !Array.isArray(o.center) || o.center.length !== 2) return null;
      if (isNaN(o.center[0]) || isNaN(o.center[1])) return null;
      return o;
    } catch(e){ return null; }
  }
  function viewSave(center, zoom){ try { localStorage.setItem(VIEW_STATE_KEY, JSON.stringify({center:center, zoom:zoom})); } catch(e){} }

  function detectBase() {
    if (global.TE_BASE) return String(global.TE_BASE).replace(/\/+$/,'');
    var scripts = document.getElementsByTagName('script');
    for (var i=scripts.length-1; i>=0; i--) {
      var src = scripts[i].getAttribute('src') || '';
      var m = src.match(/^(.*)\/assets\/js\/(widget|visitors)\.js(?:\?.*)?$/);
      if (m && m[1]) return m[1];
    }
    return '/track-em';
  }

  function toPointsFromGeo(items){
    items = Array.isArray(items) ? items : [];
    return items.map(function(it){
      var lat = (typeof it.lat === 'number') ? it.lat : (typeof it.latitude === 'number' ? it.latitude : null);
      var lon = (typeof it.lon === 'number') ? it.lon : (typeof it.lng === 'number' ? it.lng : (typeof it.longitude === 'number' ? it.longitude : null));
      if (lat === null || lon === null) return null;
      return { lat: lat, lon: lon, title: (it.city || '') };
    }).filter(Boolean);
  }
  function toPointsFromVisitors(items){
    // same shape as geo in your API, but keep separate for clarity
    return toPointsFromGeo(items);
  }

  function fetchJSON(url){
    return fetch(url, { credentials: 'same-origin', cache: 'no-store' })
      .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); });
  }

  function fetchPoints(base, cb){
    var urlGeo = base + '/?p=api.geo&limit=500&_ts=' + Date.now();
    var urlVis = base + '/?p=api.visitors&limit=500&_ts=' + Date.now();

    fetchJSON(urlGeo).then(function(j){
      var pts = toPointsFromGeo((j && j.items) || []);
      log('fetched', pts.length, 'points from', urlGeo);
      if (pts.length > 0 || (j && j.ok)) return cb(null, pts);
      // if response ok but empty and we want to try visitors too, fall through
      return fetchJSON(urlVis).then(function(j2){
        var pts2 = toPointsFromVisitors((j2 && j2.items) || []);
        log('fallback fetched', pts2.length, 'points from', urlVis);
        cb(null, pts2);
      }).catch(function(e2){
        warn('fallback fetch failed', e2 && e2.message);
        cb(null, pts); // return geo pts (possibly empty)
      });
    }).catch(function(e){
      warn('geo fetch failed', e && e.message);
      fetchJSON(urlVis).then(function(j2){
        var pts2 = toPointsFromVisitors((j2 && j2.items) || []);
        log('fallback fetched', pts2.length, 'points from', urlVis);
        cb(null, pts2);
      }).catch(function(e2){
        warn('visitors fetch failed', e2 && e2.message);
        cb(e2, []);
      });
    });
  }

  function mount(el, options){
    if (!el || el.getAttribute(MOUNTED_ATTR)==='1') return null;
    el.setAttribute(MOUNTED_ATTR, '1');

    if (typeof L === 'undefined' || !L.map) { err('Leaflet is missing'); return null; }

    options = options || {};
    var remember = !!options.rememberView || rememberOn();
    var defaultZoom = (typeof options.defaultZoom === 'number') ? options.defaultZoom : 2;
    var initialCenter = Array.isArray(options.center) ? options.center : [20, 0];
    var capZoom = (typeof options.maxInitialZoom === 'number') ? options.maxInitialZoom : 3;

    var map = L.map(el, { scrollWheelZoom:true, worldCopyJump:true });
    var tiles = (options.tiles && typeof options.tiles.url === 'string')
      ? options.tiles
      : { url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', opts: { maxZoom: 19 } };
    L.tileLayer(tiles.url, tiles.opts || { maxZoom: 19 }).addTo(map);

    var restored = false;
    if (remember) {
      var saved = viewLoad();
      if (saved) { map.setView(saved.center, saved.zoom); restored = true; log('restored view', saved); }
    }
    if (!restored) { map.setView(initialCenter, defaultZoom); }

    var points = Array.isArray(options.points) ? options.points : [];
    var bounds = L.latLngBounds([]);
    var firstPt = null;

    points.forEach(function(p){
      if (!p || typeof p.lat !== 'number' || typeof p.lon !== 'number') return;
      var ll = [p.lat, p.lon];
      if (!firstPt) firstPt = ll;
      bounds.extend(ll);
      var m = L.marker(ll);
      if (p.title) m.bindPopup(p.title);
      m.addTo(map);
    });

    if (!restored) {
      if (points.length === 0) {
        map.fitWorld({ padding: [20,20] });
      } else if (points.length === 1 && firstPt) {
        map.setView(firstPt, capZoom);
      } else if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [20,20], maxZoom: capZoom });
      }
    }

    if (remember) {
      map.on('moveend', function () {
        var c = map.getCenter();
        viewSave([c.lat, c.lng], map.getZoom());
      });
    }

    setTimeout(function(){ try { map.invalidateSize({animate:false}); } catch(e){} }, 0);
    setTimeout(function(){ try { map.invalidateSize({animate:false}); } catch(e){} }, 250);

    global.TE_VISITORS_MAP = map;
    return map;
  }

  function findContainer() {
    var selectors = [
      '#te-visitors-map',
      '#widget-visitors',
      '.te-widget--visitors',
      '[data-widget="visitors"] .map',
      '[data-widget="visitors"]',
      '[data-key="visitors"] .map',
      '[data-key="visitors"]'
    ];
    for (var i=0; i<selectors.length; i++) {
      var el = document.querySelector(selectors[i]);
      if (el) return el;
    }
    return null;
  }

  function ensureRegistry(){
    global.TEWidgets = global.TEWidgets || {};
    if (!global.TEWidgets.visitors) {
      global.TEWidgets.visitors = function(el, opts, data){
        opts = opts || {};
        if (data && Array.isArray(data.items)) { opts.points = toPointsFromGeo(data.items); }
        return mount(el, opts);
      };
      log('registered TEWidgets.visitors');
    }
  }

  function createContainer(){
    var host = document.querySelector('.te-grid, #content, main, #main, body');
    if (!host) host = document.body;
    var wrapper = document.createElement('div');
    wrapper.id = 'widget-visitors';
    wrapper.className = 'te-widget te-widget--visitors';
    wrapper.style.minHeight = '360px';
    wrapper.style.position = 'relative';
    wrapper.style.border = '1px solid rgba(0,0,0,.08)';
    wrapper.style.borderRadius = '8px';
    wrapper.style.margin = '8px';
    wrapper.style.overflow = 'hidden';

    var header = document.createElement('div');
    header.textContent = 'Visitors';
    header.style.padding = '8px 12px';
    header.style.fontWeight = '600';
    header.style.borderBottom = '1px solid rgba(0,0,0,.08)';
    wrapper.appendChild(header);

    var mapEl = document.createElement('div');
    mapEl.id = 'te-visitors-map';
    mapEl.style.height = '320px';
    wrapper.appendChild(mapEl);

    host.appendChild(wrapper);
    log('injected fallback container into', host.tagName);
    return mapEl;
  }

  function autoInit(forceCreate) {
    ensureRegistry();
    var el = findContainer();
    if (!el && forceCreate) el = createContainer();
    if (!el) { log('no container yet; observing'); return; }

    var base = detectBase();
    fetchPoints(base, function(_err, pts){
      var target = el;
      if (target && target.id !== 'te-visitors-map') {
        var child = target.querySelector('#te-visitors-map, .map, .leaflet-container, div');
        if (child) target = child;
      }
      mount(target, { points: pts, rememberView: true, maxInitialZoom: 3 });
    });
  }

  log('omni-boot v3 loaded');
  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    setTimeout(function(){ autoInit(false); }, 0);
    setTimeout(function(){ autoInit(true); }, 1200);
  } else {
    document.addEventListener('DOMContentLoaded', function(){
      autoInit(false);
      setTimeout(function(){ autoInit(true); }, 1200);
    });
  }

  try {
    var mo = new MutationObserver(function(){
      var el = findContainer();
      if (el && el.getAttribute(MOUNTED_ATTR)!=='1') autoInit(false);
    });
    mo.observe(document.documentElement || document.body, { childList: true, subtree: true });
  } catch(e){}

  global.initVisitorsWidget = function(el, options){ log('manual init'); return mount(el, options || {}); };

})(window);
