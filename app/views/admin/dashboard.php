
<?php /* Ensure analytics widgets render for admins even if consent cookie is missing */ ?>
<script>
  (function(){
    try {
      document.cookie = 'te_consent=allow; path=/; SameSite=Lax';
    } catch(e) {}
  })();
</script>


<style>
.visitor-ip img { vertical-align: middle; margin-right: 0.75rem; }
</style>
<?php $__base = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/'); if ($__base === '/') $__base = ''; ?>
<?php
$__cfg = \TrackEm\Core\Config::instance()->all();
$__dash = isset($__cfg['dashboard']) && is_array($__cfg['dashboard']) ? $__cfg['dashboard'] : array();
$__row_limit = (int)(isset($__dash['row_limit']) ? $__dash['row_limit'] : 200);
$__show_icons = !empty($__dash['show_icons']);
$__ip_tooltips = !empty($__dash['ip_tooltips']);
$__map = isset($__dash['map']) && is_array($__dash['map']) ? $__dash['map'] : array();
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;gap:12px;flex-wrap:wrap">
  <h3 style="margin:0">Dashboard</h3>
  <div style="display:flex;gap:12px;align-items:center;">
    <label>Grid columns:
      <select id="grid-cols">
        <option value="1">1</option>
        <option value="2" selected>2</option>
        <option value="3">3</option>
      </select>
    </label>
    <label>Timeframe:
      <select id="timeframe">
        <option value="day">Today</option>
        <option value="week">This Week</option>
        <option value="month">This Month</option>
        <option value="year">This Year</option>
        <option value="all" selected>All time</option>
      </select>
    </label>
  </div>
</div>

<style>
:root{--pin-color: var(--theme-pin-color, #4CC9F0)}
.icon{width:14px;height:14px;vertical-align:middle;margin-right:6px;opacity:.9;fill:currentColor;stroke:none}
.badge{display:inline-flex;align-items:center;gap:6px;padding:2px 6px;border-radius:999px;border:1px solid var(--border,#2a3340);background:var(--muted,#0f1318);color:var(--text,#e8e8e8);font-size:11px}
.error{margin-top:8px;font-size:12px;color:#ffb4b4;white-space:pre-wrap}
.hint{margin:6px 0;font-size:12px;opacity:.8}
#realtime .item{display:flex;gap:8px;align-items:center;padding:4px 0;border-bottom:1px dashed #2a334055}
#realtime .id{font-weight:600;opacity:.85}
#realtime .time{opacity:.75;font-size:12px}
#realtime .ip{opacity:.85}
#realtime .path{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.card{user-select:none; overflow:visible}
.card.dragging{opacity:.6}
.card-placeholder{border:2px dashed #6b7280;border-radius:12px;height:48px;margin:6px 0;min-height:48px}
.te-pin{width:12px;height:12px;border-radius:50%;background:var(--pin-color);border:2px solid rgba(0,0,0,.55);box-shadow:0 0 0 2px rgba(0,0,0,.15)}
#map, #map * { -webkit-user-drag: none; user-select: none; }
.map-gear{border:1px solid var(--border,#2a3340);background:transparent;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:inherit;border-radius:8px;padding:2px 6px}
.sheet{
  position:fixed; left:0; top:0;
  width:360px; max-width:92vw; max-height:80vh;
  overflow:auto;background:var(--muted,#0f1318);color:var(--text,#e8e8e8);
  border:1px solid var(--border,#2a3340);box-shadow:0 20px 40px rgba(0,0,0,.35);
  border-radius:12px;padding:12px;display:none;z-index:99998
}
.sheet h4{margin:0 0 8px 0}
.sheet .row{display:flex;gap:8px;align-items:center;margin:6px 0}
.sheet label{font-size:13px;min-width:140px}
.sheet input[type="text"], .sheet select, .sheet input[type="number"]{width:100%;padding:6px;border-radius:8px;border:1px solid var(--border,#2a3340);background:transparent;color:inherit}
.sheet .grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.sheet .btns{display:flex;gap:8px;justify-content:flex-end;margin-top:10px}
.sheet button{padding:6px 10px;border-radius:8px;border:1px solid var(--border,#2a3340);background:transparent;color:inherit;cursor:pointer}
.sheet .subtle{opacity:.8}
.sheet select{appearance:none;background-color:var(--muted,#0f1318);color:var(--text,#e8e8e8);border:1px solid var(--border,#2a3340)}
.sheet select:focus{outline:none;box-shadow:0 0 0 2px rgba(76,201,240,.25);border-color:var(--accent,#4CC9F0)}
.sheet select option{background-color:var(--muted,#0f1318);color:var(--text,#e8e8e8)}
.sheet select option:checked{background-color:#1e293b;color:#e6f6ff}
.card-handle{cursor:grab;margin:-4px 0 8px;font-weight:bold;display:flex;align-items:center;gap:8px}
.card-handle:active{cursor:grabbing}
</style>

<div class="grid" id="dash-grid" style="display:grid;grid-template-columns:repeat(2, 1fr);gap:12px">
  <div class="card" data-id="recent" draggable="false">
    <div class="card-handle">Recent Visits</div>
    <div class="hint">API: <code id="api-url"></code></div>
    <div id="recent-error" class="error" style="display:none"></div>
    <table>
      <thead><tr><th>ID</th><th>IP</th><th>Path</th><th>Browser</th><th>OS</th><th>When</th></tr></thead>
      <tbody id="recent-body"></tbody>
    </table>
  </div>

  <div class="card" data-id="map" draggable="false" id="map-card">
    <div class="card-handle">
      Map <button type="button" id="open-map-settings" class="map-gear" title="Map settings" aria-haspopup="dialog" aria-expanded="false">⚙</button>
    </div>
    <div id="map" style="height:300px"></div>
    <div id="map-error" class="error" style="display:none"></div>
  </div>

  <div class="card" data-id="realtime" draggable="false">
    <div class="card-handle">Realtime</div>
    <div id="realtime-error" class="error" style="display:none"></div>
    <div id="realtime"></div>
  </div>
</div>

<div id="map-settings-sheet" class="sheet" aria-hidden="true" role="dialog">
  <h4>Map settings</h4>
  <div class="row"><label>Basemap</label>
    <select id="ms-basemap">
      <option value="osm">OpenStreetMap</option>
      <option value="carto-positron">Carto Positron</option>
      <option value="carto-darkmatter">Carto DarkMatter</option>
      <option value="esri-satellite">Esri Satellite</option>
    </select>
  </div>
  <div class="grid">
    <div class="row"><label>Max zoom</label><input type="number" id="ms-maxzoom" min="0" max="22"></div>
    <div class="row"><label>Refresh (s)</label><input type="number" id="ms-refresh" min="5" max="3600"></div>
  </div>
  <div class="row"><label><input type="checkbox" id="ms-remember"> Remember last view</label></div>
  <div class="row"><label><input type="checkbox" id="ms-autofit"> Auto-fit on load</label></div>
  <div class="row"><label><input type="checkbox" id="ms-scroll"> Scroll wheel zoom</label></div>
  <div class="row"><label><input type="checkbox" id="ms-dragging"> Dragging</label></div>
  <div class="row"><label><input type="checkbox" id="ms-locate"> Show locate button</label></div>
  <hr style="border:none;border-top:1px solid var(--border,#2a3340);margin:8px 0">
  <div class="row"><label><input type="checkbox" id="ms-cluster" disabled title="Clustering depends on server plugin"> Enable clustering</label></div>
  <div class="grid">
    <div class="row"><label>Cluster radius</label><input type="number" id="ms-cluster-radius" min="10" max="120"></div>
    <div class="row"><label>Disable @ zoom</label><input type="number" id="ms-cluster-disable" min="0" max="22"></div>
  </div>
  <div class="row"><label><input type="checkbox" id="ms-spiderfy"> Spiderfy on max zoom</label></div>
  <hr style="border:none;border-top:1px solid var(--border,#2a3340);margin:8px 0">
  <div class="row"><label>Marker style</label>
    <select id="ms-marker-style"><option value="dot">Dot</option><option value="marker">Marker</option></select>
  </div>
  <div class="row"><label>Dot size (px)</label><input type="number" id="ms-marker-size" min="6" max="24"></div>
  <div class="row subtle">Popup fields:</div>
  <div class="grid">
    <label><input type="checkbox" class="ms-popup" value="ip"> IP</label>
    <label><input type="checkbox" class="ms-popup" value="path"> Path</label>
    <label><input type="checkbox" class="ms-popup" value="ts"> Time</label>
    <label><input type="checkbox" class="ms-popup" value="city"> City</label>
    <label><input type="checkbox" class="ms-popup" value="country"> Country</label>
    <label><input type="checkbox" class="ms-popup" value="coords"> Coords</label>
  </div>
  <div class="row"><label><input type="checkbox" id="ms-mask-ip"> Mask IP (a.b.c.0)</label></div>
  <div class="btns">
    <button id="ms-reset" type="button">Reset</button>
    <button id="ms-close" type="button">Close</button>
    <button id="ms-save" type="button"><strong>Save</strong></button>
  </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<script>
// ---- BASE + API with automatic fallback ----
(function(){
  var raw = (location.pathname||'').replace(/\/index\.php.*$/,'').replace(/\/+$/,'');
  window.__TE_BASE = raw || '';
  window.__teApi = function(ep){
    var root = window.__TE_BASE;
    var a = root + '/?p=' + encodeURIComponent(ep);
    var b = root + '/index.php?p=' + encodeURIComponent(ep);
    return { primary:a, fallback:b };
  };
})();

function teFetch(ep, opts){
  var pair = window.__teApi(ep);
  return fetch(pair.primary, opts || {credentials:'same-origin', cache:'no-store'})
    .then(function(r){ if(r.ok) return r; return fetch(pair.fallback, opts || {credentials:'same-origin', cache:'no-store'}); });
}

// raw-query helper (no encoding of '&')
function teFetchQ(ep, qs, opts){
  var pair = window.__teApi(ep);
  var url1 = pair.primary + (qs ? '&' + qs : '');
  var url2 = pair.fallback + (qs ? '&' + qs : '');
  var o = opts || {credentials:'same-origin', cache:'no-store'};
  return fetch(url1, o).then(function(r){ if (r.ok) return r; return fetch(url2, o); });
}
</script>

<script>
// ---------- Layout drag + persist (handle-only) ----------
(function(){
  function qClosest(el, sel) { if (!el) return null; if (typeof el.closest !== 'function') return null; return el.closest(sel); }

  var grid = document.getElementById('dash-grid');
  var colsSel = document.getElementById('grid-cols');
  var ORDER_KEY = 'te.layout.order';
  var COLS_KEY  = 'te.layout.cols';
  var LAST_ORDER = null;
  var dragging = false;
  var dragCard = null;

  function ensureColsStyle(n) {
    var tag = document.getElementById('dash-grid-columns');
    if (!tag) { tag = document.createElement('style'); tag.id = 'dash-grid-columns'; document.head.appendChild(tag); }
    var nn = parseInt(n,10); if (!nn || nn < 1) nn = 2;
    tag.textContent = '#dash-grid{grid-template-columns:repeat(' + nn + ', 1fr) !important;}';
  }

  function applyCols(n) {
    var nn = parseInt(n,10); if (!nn || nn < 1) nn = 2;
    grid.style.gridTemplateColumns = 'repeat(' + nn + ', 1fr)';
    ensureColsStyle(nn);
  }

  function cards() { var list = grid.querySelectorAll('.card[data-id]'); var arr = []; for (var i=0;i<list.length;i++) { arr.push(list[i]); } return arr; }

  function applyOrder(ids) {
    if (!ids || !ids.length) return;
    var map = {}; var cs = cards();
    for (var i=0;i<cs.length;i++) { var id = cs[i].getAttribute('data-id'); if (id) map[id] = cs[i]; }
    for (var j=0;j<ids.length;j++) { var el = map[ids[j]]; if (el && el.parentNode === grid) grid.appendChild(el); }
  }

  function snapshotOrder() { var cs = cards(); var out = []; for (var i=0;i<cs.length;i++) { var id = cs[i].getAttribute('data-id'); if (id) out.push(id); } return out; }

  function loadLocal() {
    var ids = null, cols = null;
    try { ids = JSON.parse(localStorage.getItem(ORDER_KEY) || '[]'); } catch(e){}
    try { cols = localStorage.getItem(COLS_KEY); } catch(e){}
    if (cols) colsSel.value = cols;
    applyCols(colsSel.value);
    applyOrder(ids);
    if (ids && ids.length) LAST_ORDER = ids.slice(0);
  }

  function saveLocal() {
    try { localStorage.setItem(ORDER_KEY, JSON.stringify(snapshotOrder())); } catch(e){}
    try { localStorage.setItem(COLS_KEY, colsSel.value); } catch(e){}
  }

  function loadServerThenFallback() {
    var usedLocal = false;
    var localIds = null, localCols = null;
    try { localIds = JSON.parse(localStorage.getItem(ORDER_KEY) || '[]'); } catch(_){}
    try { localCols = localStorage.getItem(COLS_KEY); } catch(_){}
    if (localCols) colsSel.value = String(localCols);
    applyCols(colsSel.value);
    if (Array.isArray(localIds) && localIds.length > 0) { applyOrder(localIds); LAST_ORDER = localIds.slice(0); usedLocal = true; }

    teFetch('api.layout.get')
      .then(function (r) { return r.text(); })
      .then(function (txt) {
        var data = null; try { data = JSON.parse(txt); } catch (_){}
        var applied = false;
        if (data && Array.isArray(data.order) && data.order.length > 0) { applyOrder(data.order); LAST_ORDER = data.order.slice(0); applied = true; }
        if (data && (data.cols === 0 || data.cols)) {
          var n = parseInt(data.cols, 10); if (n && n > 0) { colsSel.value = String(n); applyCols(colsSel.value); applied = true; }
        }
        if (!applied && !usedLocal) loadLocal();
      })
      .catch(function(){ if (!usedLocal) loadLocal(); });

    var ticks = 0, maxTicks = 25;
    var iv = setInterval(function(){ ticks += 1; if (LAST_ORDER && LAST_ORDER.length) applyOrder(LAST_ORDER); if (ticks >= maxTicks) clearInterval(iv); }, 200);
  }

  function nearestCard(x, y) {
    var cs = cards(), best = null, bestDist = Infinity;
    for (var i=0;i<cs.length;i++) {
      var el = cs[i]; if (el === dragCard) continue;
      var r = el.getBoundingClientRect(); var cx = r.left + r.width/2, cy = r.top + r.height/2;
      var dx = cx - x, dy = cy - y; var d = dx*dx + dy*dy;
      if (d < bestDist) { bestDist = d; best = el; }
    }
    return best;
  }

  function moveBefore(target, node){ if (!target||!node||target===node) return; grid.insertBefore(node, target); }
  function moveAfter(target, node){ if (!target||!node||target===node) return; if (target.nextSibling) grid.insertBefore(node, target.nextSibling); else grid.appendChild(node); }

  grid.addEventListener('mousedown', function(e){
    var gear = qClosest(e.target, '#open-map-settings'); if (gear) return;
    var h = qClosest(e.target, '.card-handle'); if (!h) return;
    dragCard = qClosest(h, '.card'); if (!dragCard) return;
    dragging = true; document.body.style.userSelect = 'none'; e.preventDefault();
  });

  document.addEventListener('mousemove', function(e){
    if (!dragging || !dragCard) return;
    e.preventDefault();
    var tgt = nearestCard(e.clientX, e.clientY); if (!tgt) return;
    var r = tgt.getBoundingClientRect(); var before = (e.clientY - r.top) < (r.height/2);
    if (before) moveBefore(tgt, dragCard); else moveAfter(tgt, dragCard);
  });

  document.addEventListener('mouseup', function(){
    if (!dragging) return;
    dragging = false; document.body.style.userSelect = '';
    LAST_ORDER = snapshotOrder().slice(0);
    saveLocal(); saveServer();
  });

  function saveServer() {
    var payload = { order: snapshotOrder(), cols: parseInt(colsSel.value, 10) || 2 };
    teFetch('api.layout.save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      credentials: 'same-origin',
      cache: 'no-store'
    })
    .then(function (r) { return r.text(); })
    .then(function (txt) {
      var data = null; try { data = JSON.parse(txt); } catch (_) {}
      if (data && data.ok === true) console.log('[layout.save] ok', data.saved || payload);
      else console.warn('[layout.save] failed', data || txt);
    })
    .catch(function (err) { console.warn('[layout.save] network', String(err)); });
  }

  loadServerThenFallback();

  colsSel.addEventListener('change', function(){
    applyCols(colsSel.value);
    saveLocal();
    saveServer();
  });
})();
</script>

<script>
// ---------- Data + Map + Settings (gear) + Visitors loader + Timeframe ----------
(function(){
  var tbody = document.getElementById('recent-body');
  var recentErr = document.getElementById('recent-error');
  var rt = document.getElementById('realtime');
  var mapErr = document.getElementById('map-error');
  var apiCode = document.getElementById('api-url');
  var tfSel = document.getElementById('timeframe');

  if (apiCode){
    var pair = window.__teApi('api.geo');
    apiCode.textContent = pair.primary;
  }

  function rerr(msg){ recentErr.style.display='block'; recentErr.textContent = msg; }
  function clearErr(){ recentErr.style.display='none'; mapErr.style.display='none'; }

  // ---- timeframe helpers
  var TF_KEY = 'te.timeframe';
  (function seedTF(){
    try { var saved = localStorage.getItem(TF_KEY); if (saved) tfSel.value = saved; } catch(e){}
  })();

  function tfRange(value){
    var now = Math.floor(Date.now()/1000);
    switch (value) {
      case 'day':   return { since: now - 1*86400,  until: now, limit: 1000 };
      case 'week':  return { since: now - 7*86400,  until: now, limit: 5000 };
      case 'month': return { since: now - 30*86400, until: now, limit: 20000 };
      case 'year':  return { since: now - 365*86400,until: now, limit: 100000 };
      case 'all':
      default:      return { since: null,            until: now, limit: 200000 };
    }
  }

  function buildGeoQS(){
    var v = tfSel.value || 'all';
    var r = tfRange(v);
    var qs = [];
    if (r.since != null) qs.push('since='+encodeURIComponent(r.since));
    if (r.until != null) qs.push('until='+encodeURIComponent(r.until));
    if (r.limit != null) qs.push('limit='+encodeURIComponent(r.limit));
    qs.push('_ts='+Date.now());
    return qs.join('&');
  }

  var lastRows = []; // used by IP tooltip portal

  var map=null, layer=null, cluster=null;
  var DEFAULTS = {
    basemap:'osm', max_zoom:19, refresh_seconds:15,
    remember_view:true, auto_fit:true, scroll_wheel:true, dragging:true,
    locate_button:true, marker_style:'dot', marker_size:12, popup_fields:['ip','path','ts','city','country','coords'], mask_ip:false
  };
  var serverCfg = <?php echo json_encode($__map ? $__map : new stdClass()); ?> || {};
  var cfg = (function(){
    try{
      var local = JSON.parse(localStorage.getItem('te.map.cfg') || 'null') || {};
      var out = {}; for (var k in DEFAULTS) out[k]=DEFAULTS[k];
      for (var k2 in serverCfg) out[k2]=serverCfg[k2];
      for (var k3 in local) out[k3]=local[k3];
      return out;
    }catch(e){ return DEFAULTS; }
  })();
  function saveCfg(){ try{ localStorage.setItem('te.map.cfg', JSON.stringify(cfg)); }catch(e){} }

  // settings sheet wiring
  var sheet = document.getElementById('map-settings-sheet');
  var openBtn = document.getElementById('open-map-settings');
  var closeBtn = document.getElementById('ms-close');
  var resetBtn = document.getElementById('ms-reset');
  var saveBtn = document.getElementById('ms-save');

  function seedForm(){
    document.getElementById('ms-basemap').value = cfg.basemap;
    document.getElementById('ms-maxzoom').value = cfg.max_zoom;
    document.getElementById('ms-refresh').value = cfg.refresh_seconds;
    document.getElementById('ms-remember').checked = !!cfg.remember_view;
    document.getElementById('ms-autofit').checked = !!cfg.auto_fit;
    document.getElementById('ms-scroll').checked = !!cfg.scroll_wheel;
    document.getElementById('ms-dragging').checked = !!cfg.dragging;
    document.getElementById('ms-locate').checked = !!cfg.locate_button;
    document.getElementById('ms-marker-style').value = cfg.marker_style;
    document.getElementById('ms-marker-size').value = cfg.marker_size;
    var boxes = document.querySelectorAll('.ms-popup');
    for (var i=0;i<boxes.length;i++){ boxes[i].checked = (cfg.popup_fields||[]).indexOf(boxes[i].value)>=0; }
    document.getElementById('ms-mask-ip').checked = !!cfg.mask_ip;
  }
  function readForm(){
    cfg.basemap = document.getElementById('ms-basemap').value;
    cfg.max_zoom = parseInt(document.getElementById('ms-maxzoom').value||'19',10);
    cfg.refresh_seconds = Math.max(5, parseInt(document.getElementById('ms-refresh').value||'15',10));
    cfg.remember_view = document.getElementById('ms-remember').checked;
    cfg.auto_fit = document.getElementById('ms-autofit').checked;
    cfg.scroll_wheel = document.getElementById('ms-scroll').checked;
    cfg.dragging = document.getElementById('ms-dragging').checked;
    cfg.locate_button = document.getElementById('ms-locate').checked;
    cfg.marker_style = document.getElementById('ms-marker-style').value;
    cfg.marker_size = parseInt(document.getElementById('ms-marker-size').value||'12',10);
    var sels = document.querySelectorAll('.ms-popup:checked'); var arr=[];
    for (var i=0;i<sels.length;i++) arr.push(sels[i].value);
    cfg.popup_fields = arr;
    cfg.mask_ip = document.getElementById('ms-mask-ip').checked;
  }

  function anchorSheet(){
    var r = openBtn.getBoundingClientRect();
    var s = sheet.style;
    var w = Math.min(360, Math.max(260, sheet.offsetWidth || 360));
    var left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8));
    var top  = Math.max(60, Math.min(r.bottom + 8, window.innerHeight - sheet.offsetHeight - 8));
    s.left = left + 'px'; s.top  = top + 'px';
  }

  openBtn.addEventListener('click', function(ev){
    ev.stopPropagation(); seedForm(); sheet.style.display='block'; sheet.setAttribute('aria-hidden','false'); anchorSheet();
  });
  closeBtn.addEventListener('click', function(){ sheet.style.display='none'; sheet.setAttribute('aria-hidden','true'); });
  resetBtn.addEventListener('click', function(){
    try{ localStorage.removeItem('te.map.cfg'); }catch(e){}
    for (var k in DEFAULTS) cfg[k]=DEFAULTS[k];
    seedForm(); applyInteraction(); applyBase(); anchorSheet();
  });
  saveBtn.addEventListener('click', function(){
    readForm(); saveCfg(); applyInteraction(); applyBase(); sheet.style.display='none'; sheet.setAttribute('aria-hidden','true');
    if (map) { map.invalidateSize(); }
  });
  document.addEventListener('click', function(e){
    if (!sheet.contains(e.target) && e.target !== openBtn){ sheet.style.display='none'; sheet.setAttribute('aria-hidden','true'); }
  });

  // Map helpers
  function ensureMap(){
    if (map) return map;
    map = L.map('map', {zoomControl:true});
    applyBase(); applyInteraction();
    cluster = L.layerGroup(); map.addLayer(cluster);
    if (cfg.remember_view){
      try{
        var saved = JSON.parse(localStorage.getItem('te.map.view')||'null');
        if (saved && saved.center && typeof saved.zoom === 'number'){ map.setView(saved.center, saved.zoom); }
        else map.setView([20,0], 2);
      }catch(e){ map.setView([20,0], 2); }
      map.on('moveend', function(){
        var c = map.getCenter();
        try{ localStorage.setItem('te.map.view', JSON.stringify({center:[c.lat,c.lng], zoom: map.getZoom()})); }catch(e){}
      });
    } else {
      map.setView([20,0], 2);
    }
    return map;
  }
  function applyBase(){
    if (!map) return;
    if (layer){ map.removeLayer(layer); layer=null; }
    var meta = {
      'osm': { url:'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', opts:{maxZoom:cfg.max_zoom, attribution:'&copy; OpenStreetMap'} },
      'carto-positron': { url:'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png', opts:{maxZoom:cfg.max_zoom, attribution:'&copy; OSM & CARTO'} },
      'carto-darkmatter': { url:'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png', opts:{maxZoom:cfg.max_zoom, attribution:'&copy; OSM & CARTO'} },
      'esri-satellite': { url:'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', opts:{maxZoom:19, attribution:'Tiles &copy; Esri'} }
    }[cfg.basemap] || null;
    if (!meta){ meta = { url:'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', opts:{maxZoom:cfg.max_zoom, attribution:'&copy; OpenStreetMap'} }; }
    layer = L.tileLayer(meta.url, meta.opts); layer.addTo(map);
  }
  function applyInteraction(){
    if (!map) return;
    if (cfg.scroll_wheel) map.scrollWheelZoom.enable(); else map.scrollWheelZoom.disable();
    if (cfg.dragging) map.dragging.enable(); else map.dragging.disable();
    var btn = document.getElementById('btn-my-loc'); if (btn) btn.style.display = cfg.locate_button ? 'inline-block' : 'none';
  }

  function popupHtml(row){
    function mask(ip){ var p=String(ip||'').split('.'); if(p.length===4){ p[3]='0'; return p.join('.'); } return ip||''; }
    var buf=[];
    if ((cfg.popup_fields||[]).indexOf('ip')>=0) buf.push('<div><strong>IP:</strong> '+(cfg.mask_ip?mask(row.ip):row.ip)+'</div>');
    if ((cfg.popup_fields||[]).indexOf('path')>=0) buf.push('<div><strong>Path:</strong> '+(row.path||'')+'</div>');
    if ((cfg.popup_fields||[]).indexOf('ts')>=0) buf.push('<div><strong>When:</strong> '+new Date((row.ts||0)*1000).toLocaleString()+'</div>');
    if ((cfg.popup_fields||[]).indexOf('city')>=0 || (cfg.popup_fields||[]).indexOf('country')>=0){
      var loc=[row.city||'',row.country||''].filter(Boolean).join(', '); if(loc) buf.push('<div><strong>Loc:</strong> '+loc+'</div>');
    }
    if ((cfg.popup_fields||[]).indexOf('coords')>=0 && row.lat!=null && row.lon!=null) buf.push('<div><strong>Coords:</strong> '+row.lat+', '+row.lon+'</div>');
    return '<div>'+buf.join('')+'</div>';
  }

  
// === UA Helpers with SVGs ===
function __te_parseUA(ua){
  ua=(ua||'')+'';var b='Other',o='Other',bk='other',ok='other';
  if(/Edg\//i.test(ua)){b='Edge';bk='edge';}
  else if(/OPR\//i.test(ua)){b='Opera';bk='opera';}
  else if(/Brave\/|Brave/i.test(ua)){b='Brave';bk='brave';}
  else if(/Vivaldi\//i.test(ua)){b='Vivaldi';bk='vivaldi';}
  else if(/YaBrowser\//i.test(ua)){b='Yandex';bk='yandex';}
  else if(/Maxthon\//i.test(ua)){b='Maxthon';bk='maxthon';}
  else if(/Waterfox\//i.test(ua)){b='Waterfox';bk='waterfox';}
  else if(/PaleMoon\//i.test(ua)){b='Pale Moon';bk='palemoon';}
  else if(/TorBrowser\//i.test(ua)){b='Tor';bk='tor';}
  else if(/Chromium\//i.test(ua)){b='Chromium';bk='chromium';}
  else if(/Chrome\//i.test(ua)){b='Chrome';bk='chrome';}
  else if(/Firefox\//i.test(ua)){b='Firefox';bk='firefox';}
  else if(/Whale\//i.test(ua)){b='Whale';bk='whale';}
  else if(/DuckDuckGo\//i.test(ua)){b='DDG';bk='ddg';}
  else if(/Safari\//i.test(ua)){b='Safari';bk='safari';}
  if(/Windows NT/i.test(ua)){o='Windows';ok='windows';}
  else if(/Mac OS X|Macintosh/i.test(ua)){o='macOS';ok='mac';}
  else if(/Android/i.test(ua)){o='Android';ok='android';}
  else if(/iPhone|iPad|iOS/i.test(ua)){o='iOS';ok='ios';}
  else if(/Debian/i.test(ua)){o='Debian';ok='debian';}
  else if(/Ubuntu/i.test(ua)){o='Ubuntu';ok='ubuntu';}
  else if(/Pop!_?OS|PopOS/i.test(ua)){o='Pop!_OS';ok='popos';}
  else if(/Fedora/i.test(ua)){o='Fedora';ok='fedora';}
  else if(/CentOS/i.test(ua)){o='CentOS';ok='centos';}
  else if(/Red Hat|RHEL/i.test(ua)){o='Red Hat';ok='redhat';}
  else if(/Arch/i.test(ua)){o='Arch';ok='arch';}
  else if(/Manjaro/i.test(ua)){o='Manjaro';ok='manjaro';}
  else if(/Gentoo/i.test(ua)){o='Gentoo';ok='gentoo';}
  else if(/openSUSE|SUSE/i.test(ua)){o='openSUSE';ok='opensuse';}
  else if(/Manjaro/i.test(ua)){o='Manjaro';ok='manjaro';}
  else if(/openSUSE/i.test(ua)){o='openSUSE';ok='opensuse';}
  else if(/RHEL|Red Hat/i.test(ua)){o='RHEL';ok='rhel';}
  else if(/Alpine/i.test(ua)){o='Alpine';ok='alpine';}
  else if(/Linux\s*Mint/i.test(ua)){o='Linux Mint';ok='mint';}
  else if(/Mint/i.test(ua)){o='Linux Mint';ok='mint';}
  else if(/Kali/i.test(ua)){o='Kali';ok='kali';}
  else if(/FreeBSD/i.test(ua)){o='FreeBSD';ok='freebsd';}
  else if(/OpenBSD/i.test(ua)){o='OpenBSD';ok='openbsd';}
  else if(/Linux/i.test(ua)){o='Linux';ok='linux';}
  return {browser:b,os:o,bkey:bk,okey:ok};
}
var __te_SVG={'other':'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#607D8B"/><text x="12" y="16" font-size="12" text-anchor="middle" fill="#fff" font-family="system-ui,Segoe UI,Arial" font-weight="700">?</text></svg>','chrome':'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#DB4437"/><path d="M12 2a10 10 0 019.5 6h-9.5a4 4 0 00-3.464 2L5.2 6.6A9.99 9.99 0 0112 2z" fill="#FFC107"/><path d="M4.5 7.8A10 10 0 112 12h7a4 4 0 013.464 2l-3.5 6.06A10 10 0 014.5 7.8z" fill="#0F9D58"/><circle cx="12" cy="12" r="4" fill="#4285F4"/></svg>','firefox':'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M12 2c6 0 10 4 10 10s-4 10-10 10S2 18 2 12C2 7 6 3 10 3c-1 2 0 3 2 3 4 0 6 3 6 6 0 4-3 7-7 7-3 0-6-2-6-5 0-2 1-4 3-5 1-2 2-3 2-4 1 0 2 1 3 2 2 1 3 3 3 5 0 3-2 5-5 5-2 0-4-1-4-3 0-3 3-4 4-5" fill="#FF7139"/></svg>','edge':'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M12 2c5.5 0 10 4.5 10 10 0 5-4 9-9 9-3 0-5-2-5-4 0-3 3-4 5-4h7c-1-5-5-8-10-8-4 0-7 3-7 7 0 5 4 9 9 9" fill="#0078D7"/></svg>','opera':'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="#FF1B2D"/><circle cx="12" cy="12" r="5" fill="#fff"/></svg>','safari':'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#00A1F1"/><path d="M12 6l3 6-6 3 3-9z" fill="#fff"/></svg>','brave':'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M7 3h10l3 4-1 8-7 6-7-6L4 7 7 3z" fill="#FB542B"/></svg>','vivaldi':'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#EF3939"/><path d="M8 10c0-2 3-2 3 0v1c0 2-3 2-3 0v-1zm5 0c0-2 3-2 3 0v1c0 2-3 2-3 0v-1z" fill="#fff"/></svg>','yandex':'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#CC0000"/><path d="M11 6h2v12h-2z" fill="#fff"/></svg>','maxthon':'<svg width="14" height="14" viewBox="0 0 24 24"><rect x="4" y="6" width="16" height="12" rx="2" fill="#2E91FF"/><path d="M7 12h10" stroke="#fff" stroke-width="2"/></svg>','waterfox':'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M12 3c6 0 9 6 6 10-2 3-5 6-6 8-1-2-4-5-6-8-3-4 0-10 6-10z" fill="#145DA0"/></svg>','palemoon':'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="#6C8CD5"/><circle cx="10" cy="12" r="5" fill="#fff"/></svg>','tor':'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#7E57C2"/><path d="M12 6c2 0 3 2 3 4s-1 4-3 4-3-2-3-4 1-4 3-4z" fill="#fff"/></svg>','ddg':'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#DE5833"/><path d="M10 8h4v8h-4z" fill="#fff"/></svg>','whale':'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M2 13c2 5 8 7 12 7 5 0 8-3 8-6-2 1-4 0-6-2-3 1-6 1-9 1H2z" fill="#1EC8E1"/></svg>','chromium':'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#3F51B5"/><circle cx="12" cy="12" r="4" fill="#fff"/></svg>','windows':'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M3 5l9-2v9H3V5zm0 11h9v5l-9-2v-3zm11-13l7-1v10h-7V3zm0 11h7v9l-7-1v-8z" fill="#00A4EF"/></svg>','mac':'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M16 13c0 4 3 5 3 5s-2 3-5 3-4-2-6-2-4 2-4 2-3-2-3-6c0-4 3-7 6-7 2 0 3 1 4 1s2-1 4-1c1 0 5 1 5 5z" fill="#555"/></svg>','android':'<svg width="14" height="14" viewBox="0 0 24 24"><rect x="5" y="8" width="14" height="10" rx="3" fill="#3DDC84"/><rect x="7" y="4" width="2" height="3" fill="#3DDC84"/><rect x="15" y="4" width="2" height="3" fill="#3DDC84"/></svg>','ios':'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M16 13c0 4 3 5 3 5s-2 3-5 3-4-2-6-2-4 2-4 2-3-2-3-6c0-4 3-7 6-7 2 0 3 1 4 1s2-1 4-1c1 0 5 1 5 5z" fill="#333"/></svg>','linux':'<svg width="14" height="14" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3c3 0 5 2 5 5 0 2-1 3-1 4 0 3 3 3 3 5 0 2-2 4-4 4-1 0-2-1-3-1s-2 1-3 1c-2 0-4-2-4-4 0-2 3-2 3-5 0-1-1-2-1-4 0-3 2-5 5-5z" fill="#000"/><ellipse cx="12" cy="12" rx="3.2" ry="4.3" fill="#fff"/><circle cx="10.2" cy="9.2" r="0.9" fill="#fff"/><circle cx="13.8" cy="9.2" r="0.9" fill="#fff"/><circle cx="10.2" cy="9.2" r="0.35" fill="#000"/><circle cx="13.8" cy="9.2" r="0.35" fill="#000"/><path d="M11.1 11.1L12 11.7l.9-.6-.9-.6z" fill="#FFC107"/><path d="M8.8 19.4c1 .9 2.2 1 3.2 1s2.2-.1 3.2-1l1 .8c-1.1 1.1-2.4 1.4-4.2 1.4s-3.1-.3-4.2-1.4z" fill="#FFC107"/></svg>','ubuntu':'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#E95420"/><circle cx="12" cy="12" r="4" fill="#fff"/></svg>','debian':'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M12 2c4 0 8 3 8 8s-4 8-8 8-8-4-8-8 4-8 8-8zm0 3c-3 0-5 2-5 5s2 5 5 5 5-2 5-5-2-5-5-5z" fill="#D70751"/></svg>','fedora':'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#294172"/><path d="M9 8h6v6H9z" fill="#fff"/></svg>','centos':'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M4 9l8-7 8 7-8 7-8-7zM4 15l8 7 8-7" fill="#9A1D6A"/></svg>','redhat':'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M3 14c3-4 10-6 18-1-3 5-7 7-11 7S3 17 3 14z" fill="#EE0000"/></svg>','arch':'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M12 3l7 18-7-4-7 4 7-18z" fill="#1793D1"/></svg>','manjaro':'<svg width="14" height="14" viewBox="0 0 24 24"><rect x="5" y="5" width="6" height="14" fill="#34BE5B"/><rect x="13" y="5" width="6" height="6" fill="#34BE5B"/><rect x="13" y="13" width="6" height="6" fill="#34BE5B"/></svg>','gentoo':'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M5 8c1-3 6-5 10-3 4 1 5 6 1 8-3 3-6 5-8 5s-4-3-3-6z" fill="#54487A"/></svg>','opensuse':'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M4 14c0-5 5-9 12-9 3 0 4 2 4 4-8-2-14 5-16 5z" fill="#73BA25"/></svg>','mint':'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M4 7h12a4 4 0 014 4v6H4V7z" fill="#87CF3E"/></svg>','kali':'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M4 12c3-5 9-8 16-8-3 3-4 5-4 7 1 1 3 1 4 1-3 2-6 4-9 7-2-2-5-5-7-7z" fill="#268BD2"/></svg>','freebsd':'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="10" cy="12" r="8" fill="#AB2B28"/><circle cx="16" cy="6" r="3" fill="#AB2B28"/></svg>','openbsd':'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#F2C200"/><path d="M8 10h8v4H8z" fill="#fff"/></svg>','popos':'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#48B9C7"/><path d="M10 8h6v2h-6zM8 12h10v2H8z" fill="#fff"/></svg>', 'rhel':'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M3 14c3-4 10-6 18-1-3 5-7 7-11 7S3 17 3 14z" fill="#A30000"/></svg>', 'alpine':'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M3 17l9-10 9 10h-6l-3-3-3 3H3z" fill="#0D597F"/></svg>'};
function __te_iconHTML(k,l){
  var label=(l||'');
  <?php if (!empty($__dash['show_icons'])): ?>
  if (window.__te_iconHTML_ext) return window.__te_iconHTML_ext(k,label);
  return label;
  <?php else: ?>
  return label;
  <?php endif; ?>
}
function render(rows){
    // table
    tbody.innerHTML='';
    rows.forEach(function(row){
      var tr=document.createElement('tr');
      tr.innerHTML = <?php if ($__show_icons): ?>
        "<td><span class='badge'>#"+row.id+"</span></td>"
      <?php else: ?>
        "<td>"+row.id+"</td>"
      <?php endif; ?>
        <?php if ($__show_icons || $__ip_tooltips): ?>
        + "<td class=\"ip-cell\">"
        <?php if ($__show_icons): ?>
        + "<img class=\"ua-icon\" src=\"<?php echo $__base; ?>/assets/icons/ip.svg\" width=\"14\" height=\"14\" alt=\"ip\">"
        <?php endif; ?>
        + "<span class=\"ip-text\">"+(row.ip||"")+"</span>"
        + "</td>"
      <?php else: ?>
        + "<td>"+(row.ip||"")+"</td>"
      <?php endif; ?>
        + "<td>"+(row.path||"")+"</td>"
        + (function(){ var __u = __te_parseUA(row.ua||""); return "<td>"+__te_iconHTML(__u.bkey, __u.browser)+"</td><td>"+__te_iconHTML(__u.okey, __u.os)+"</td>"; })()
        + "<td>"+new Date((row.ts||0)*1000).toISOString()+"</td>";
      tbody.appendChild(tr);
    });

    // ----- IP tooltip portal (restored) -----
    <?php if ($__ip_tooltips): ?>
    (function(){
      var portal = document.getElementById('ip-portal');
      if (!portal){
        portal = document.createElement('div');
        portal.id = 'ip-portal';
        var s = portal.style;
        s.position='fixed'; s.zIndex='99999'; s.minWidth='220px'; s.maxWidth='360px';
        s.padding='8px 10px'; s.borderRadius='8px'; s.background='rgba(13,22,35,0.98)'; s.color='#eaf2ff';
        s.border='1px solid #1e2a3c'; s.boxShadow='0 8px 24px rgba(0,0,0,.35)';
        s.fontSize='12px'; s.lineHeight='1.3'; s.display='none'; s.pointerEvents='none';
        document.body.appendChild(portal);
      }
      var tbodyEl = document.getElementById('recent-body');
      tbodyEl.onmouseover = function(e){
        var cell = e.target.closest ? e.target.closest('.ip-cell') : null;
        if (!cell){ portal.style.display='none'; return; }
        var tr = cell.closest ? cell.closest('tr') : null; if (!tr) return;
        var idx = Array.prototype.indexOf.call(tr.parentNode.children, tr);
        if (idx < 0 || !lastRows[idx]) return;
        portal.innerHTML = popupHtml(lastRows[idx]);
        var r = cell.getBoundingClientRect(), m = 8;
        var left = Math.max(m, Math.min(r.left, window.innerWidth - 360 - m));
        var top = r.bottom + m;
        if (top + 160 > window.innerHeight) top = r.top - m - 160;
        portal.style.left = left + 'px'; portal.style.top = top + 'px'; portal.style.display='block';
      };
      tbodyEl.onmouseout = function(e){
        var cell = e.target.closest ? e.target.closest('.ip-cell') : null;
        if (cell && !(cell.contains ? cell.contains(e.relatedTarget) : false)){ portal.style.display='none'; }
      };
      window.addEventListener('scroll', function(){ portal.style.display='none'; }, true);
    })();
    <?php endif; ?>

    // realtime
    rt.innerHTML='';
    rows.slice(0,50).forEach(function(row){
      var el=document.createElement('div'); el.className='item';
      el.innerHTML = "<span class='id'>#"+row.id+"</span>"
        + "<span class='ip'>"+(row.ip||"")+"</span>"
        + "<span class='path'>"+(row.path||"")+"</span>"
        + "<span class='time'>"+new Date((row.ts||0)*1000).toLocaleString()+"</span>";
      rt.appendChild(el);
    });

    // map
    ensureMap();
    cluster.clearLayers();
    var size = Math.max(6, Math.min(24, parseInt((cfg.marker_size||12),10)));
    var count=0;
    rows.forEach(function(row){
      var la=parseFloat(row.lat), lo=parseFloat(row.lon);
      if(!isFinite(la)||!isFinite(lo)) return;
      var icon=L.divIcon({className:'te-pin', iconSize:[size,size]});
      L.marker([la,lo],{icon:icon}).bindPopup(popupHtml(row)).addTo(cluster);
      count++;
    });
    if(count && cfg.auto_fit){
      var hasSaved = false;
      try { hasSaved = !!(cfg.remember_view && localStorage.getItem('te.map.view')); } catch(_){}
      var bounds=[]; rows.forEach(function(r){ var la=parseFloat(r.lat), lo=parseFloat(r.lon); if(isFinite(la)&&isFinite(lo)) bounds.push([la,lo]); });
      // If "Remember last view" is enabled and a saved view exists, do not override it.
      if (!hasSaved) {
        if (bounds.length === 1) {
          // For a single point, don't zoom all the way in.
          var cap = Math.min( (cfg.max_zoom||19), 6 );
          map.setView(bounds[0], cap);
        } else if (bounds.length) {
          // Cap the zoom when fitting to many points to avoid extreme zoom-in.
          var capFit = Math.min( (cfg.max_zoom||19), 8 );
          map.fitBounds(bounds, {padding:[20,20], maxZoom: capFit});
        }
      }
    }
  }

  function fetchGeo(){
    clearErr();
    var qs = buildGeoQS();
    teFetchQ('api.geo', qs)
      .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
      .then(function(data){
        var rows = Array.isArray(data)?data : (data.items||data.rows||data.data||[]);
        rows = rows.slice(0, <?php echo (int)$__row_limit; ?>);
        lastRows = rows;            // <<< restore data for tooltips
        render(rows);
      })
      .catch(function(err){ rerr('Fetch failed: ' + (err && err.message ? err.message : String(err))); });
  }

  // initial load
  fetchGeo();

  // periodic refresh uses current picker value each time
  var poll = setInterval(fetchGeo, Math.max(5, parseInt(cfg.refresh_seconds||15,10))*1000);

  // picker change: persist + immediate refresh
  tfSel.addEventListener('change', function(e){
    try { localStorage.setItem('te.timeframe', e.target.value || 'all'); } catch(_){}
    fetchGeo();
  });

  // Visitors plugin widgets
  teFetch('api.plugins.list')
    .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
    .then(function(data){
      var base = window.__TE_BASE || '';
      var items = (data.items||[]);
      items.forEach(function(p){
        if(!p || !p.enabled) return;
        var url = base + '/app/plugins/' + p.key + '/assets/widget.js';
        // probe first to avoid MIME-type errors (HTML 404s, etc.)
        fetch(url, {method:'GET', cache:'no-store'}).then(function(r){
          var ct = (r.headers.get('content-type')||'').toLowerCase();
          if(!r.ok) return;
          if(ct.indexOf('javascript') === -1 && ct.indexOf('ecmascript') === -1) return;
          return r.text().then(function(code){
            var blob = new Blob([code], {type:'application/javascript'});
            var s = document.createElement('script');
            s.defer = true;
            s.src = URL.createObjectURL(blob);
            s.onload = function(){ URL.revokeObjectURL(s.src); };
            document.body.appendChild(s);
          });
        }).catch(function(){});
      });
    })
    .catch(function(){});
})();
</script>

<script>
/* EXT_ICON_HELPER */
(function(){
  var BASE = "<?php echo $__base; ?>/assets/icons/";
  var MAP = {
    chrome: BASE+'chrome.svg', firefox: BASE+'firefox.svg', edge: BASE+'edge.svg', opera: BASE+'opera.svg', safari: BASE+'safari.svg',
    windows: BASE+'windows.svg', mac: BASE+'mac.svg', android: BASE+'android.svg', ios: BASE+'ios.svg', linux: BASE+'linux.svg',
    ubuntu: BASE+'ubuntu.svg', debian: BASE+'debian.svg', fedora: BASE+'fedora.svg', centos: BASE+'centos.svg', arch: BASE+'arch.svg',
    manjaro: BASE+'manjaro.svg', opensuse: BASE+'opensuse.svg', rhel: BASE+'rhel.svg', alpine: BASE+'alpine.svg', mint: BASE+'mint.svg', 
    kali: BASE+'kali.svg', other: BASE+'other.svg', ip: BASE+'ip.svg'
  };
  window.__te_iconHTML_ext = function(k,label){
    var src = MAP[k] || MAP.other;
    return '<img class="ua-icon" src="'+src+'" width="14" height="14" alt="'+k+'"/> '+(label||'');
  };
})();
</script>
