<?php
$__cfg = \TrackEm\Core\Config::instance()->all();
$__dash =
    isset($__cfg["dashboard"]) && is_array($__cfg["dashboard"])
        ? $__cfg["dashboard"]
        : [];
$__show_icons = !empty($__dash["show_icons"]);
$__base = rtrim(
    str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")),
    "/",
);
if ($__base === "/") {
    $__base = "";
}
?>
<?php
$__base = rtrim(
    str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")),
    "/",
);
if ($__base === "/") {
    $__base = "";
}
?>
<?php
/* Recent Visits with Browser + OS (logo + label) */
?>
<style>
.repeat-badge {
  display:inline-flex;
  align-items:center;
  margin-left:6px;
  padding:2px 6px;
  border-radius:999px;
  font-size:11px;
  border:1px solid var(--border,#2a3340);
  background:rgba(255,255,255,0.06);
  text-transform:uppercase;
}
#auto-progress {
  font-size:12px;
  opacity:0.75;
}
</style>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
  <h3 style="margin:0"><?= I18n::t("recent_visits", "Recent Visits") ?></h3>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input id="q" placeholder="<?= I18n::t(
        "search_placeholder",
        "Search...",
    ) ?>" style="padding:6px 10px;border-radius:8px;border:1px solid var(--border,#2a3340);background:transparent;color:inherit;min-width:260px">
    <select id="limit" style="padding:6px;border-radius:8px;border:1px solid var(--border,#2a3340);background:transparent;color:inherit">
      <option value="50" selected><?= I18n::t("50", "50") ?></option>
      <option value="100"><?= I18n::t("100", "100") ?></option>
      <option value="200"><?= I18n::t("200", "200") ?></option>
      <option value="500"><?= I18n::t("500", "500") ?></option>
    </select>
    <select id="order" style="padding:6px;border-radius:8px;border:1px solid var(--border,#2a3340);background:transparent;color:inherit">
      <option value="desc" selected><?= I18n::t(
          "newest_first",
          "Newest first",
      ) ?></option>
      <option value="asc"><?= I18n::t(
          "oldest_first",
          "Oldest first",
      ) ?></option>
    </select>
    <details id="column-picker" style="position:relative">
      <summary style="list-style:none;padding:6px 10px;border-radius:8px;border:1px solid var(--border,#2a3340);cursor:pointer;user-select:none;min-width:120px;"><?= I18n::t(
          "columns",
          "Columns",
      ) ?> ▾</summary>
      <div style="position:absolute;top:calc(100% + 4px);left:0;background:var(--muted,#0f1318);border:1px solid var(--border,#2a3340);border-radius:10px;padding:10px;display:flex;flex-direction:column;gap:4px;z-index:20;min-width:160px;box-shadow:0 10px 20px rgba(0,0,0,0.25);">
        <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" data-col="ip" checked disabled> <?= I18n::t(
            "ip",
            "IP",
        ) ?></label>
        <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" data-col="path" checked disabled> <?= I18n::t(
            "path",
            "Path",
        ) ?></label>
        <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" data-col="time" checked disabled> <?= I18n::t(
            "time",
            "Time",
        ) ?></label>
        <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" data-col="browser" checked> <?= I18n::t(
            "browser",
            "Browser",
        ) ?></label>
        <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" data-col="os" checked> <?= I18n::t(
            "os",
            "OS",
        ) ?></label>
        <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" data-col="city" checked> <?= I18n::t(
            "city",
            "City",
        ) ?></label>
        <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" data-col="country" checked> <?= I18n::t(
            "country",
            "Country",
        ) ?></label>
      </div>
    </details>
    <details id="export-menu" style="position:relative">
      <summary style="list-style:none;padding:6px 10px;border-radius:8px;border:1px solid var(--border,#2a3340);cursor:pointer;user-select:none;min-width:110px;"><?= I18n::t(
          "export",
          "Export",
      ) ?> ▾</summary>
      <div style="position:absolute;top:calc(100% + 4px);left:0;background:var(--muted,#0f1318);border:1px solid var(--border,#2a3340);border-radius:10px;padding:10px;display:flex;flex-direction:column;gap:6px;z-index:20;min-width:160px;box-shadow:0 10px 20px rgba(0,0,0,0.25);">
        <button type="button" data-export="csv" style="padding:6px 8px;border-radius:6px;border:1px solid var(--border,#2a3340);background:transparent;color:inherit;cursor:pointer"><?= I18n::t(
            "csv",
            "CSV",
        ) ?></button>
        <button type="button" data-export="json" style="padding:6px 8px;border-radius:6px;border:1px solid var(--border,#2a3340);background:transparent;color:inherit;cursor:pointer"><?= I18n::t(
            "json",
            "JSON",
        ) ?></button>
      </div>
    </details>
    <button id="refresh" style="padding:6px 10px;border-radius:8px;border:1px solid var(--border,#2a3340);background:transparent;color:inherit;cursor:pointer"><?= I18n::t(
        "refresh",
        "Refresh",
    ) ?></button>
    <label style="display:flex;align-items:center;gap:6px;font-size:13px">
      <input type="checkbox" id="auto-refresh">
      <?= I18n::t("auto_refresh", "Auto 30s") ?>
    </label>
    <span id="auto-progress"></span>
  </div>
</div>

<div class="table wrap">
  <table id="vis-table" style="width:100%">
    <thead><tr id="vis-head-row"></tr></thead>
    <tbody id="vis-tbody"></tbody>
  </table>
</div>
<div id="vis-pager" style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;gap:10px;flex-wrap:wrap">
  <div id="vis-page-info" style="opacity:.7;font-size:13px"> </div>
  <div style="display:flex;gap:6px;align-items:center">
    <button id="vis-prev" type="button" style="padding:6px 10px;border-radius:8px;border:1px solid var(--border,#2a3340);background:transparent;color:inherit;cursor:pointer">&lsaquo; <?= I18n::t(
        "prev",
        "Prev",
    ) ?></button>
    <button id="vis-next" type="button" style="padding:6px 10px;border-radius:8px;border:1px solid var(--border,#2a3340);background:transparent;color:inherit;cursor:pointer"><?= I18n::t(
        "next",
        "Next",
    ) ?> &rsaquo;</button>
  </div>
</div>

<script>
(function(){
  var limit = document.getElementById('limit');
  var q = document.getElementById('q');
  var tbody = document.getElementById('vis-tbody');
  var refreshBtn = document.getElementById('refresh');
  var prevBtn = document.getElementById('vis-prev');
  var nextBtn = document.getElementById('vis-next');
  var pageInfo = document.getElementById('vis-page-info');
  var orderSel = document.getElementById('order');
  var headRow = document.getElementById('vis-head-row');
  var columnPicker = document.getElementById('column-picker');
  var columnChecks = columnPicker ? columnPicker.querySelectorAll('input[data-col]') : [];
  var exportMenu = document.getElementById('export-menu');
  var exportButtons = exportMenu ? exportMenu.querySelectorAll('button[data-export]') : [];
  var autoToggle = document.getElementById('auto-refresh');
  var autoIndicator = document.getElementById('auto-progress');
  var SHOW_ICONS = <?= $__show_icons ? "true" : "false" ?>;
  var IP_ICON = "<?= $__base ?>/assets/icons/ip.svg";
  var state = {
    limit: parseInt(limit.value, 10) || 50,
    page: 1,
    pages: 1,
    total: 0,
    q: '',
    order: (orderSel && orderSel.value === 'asc') ? 'asc' : 'desc'
  };
  var lastRows = [];
  var COLUMN_PREF_KEY = 'vis.columns';
  var LABEL_REPEAT = <?= json_encode(I18n::t("repeat", "Repeat")) ?>;
  var LABEL_SINCE_LAST = <?= json_encode(
      I18n::t("since_last", "since last"),
  ) ?>;
  var LABEL_AUTO_IN = <?= json_encode(
      I18n::t("auto_in_s", "Auto in {seconds}s"),
  ) ?>;
  var LABEL_NO_RESULTS = <?= json_encode(
      I18n::t("no_results", "No results"),
  ) ?>;
  var LABEL_LOADING = <?= json_encode(I18n::t("loading", "Loading...")) ?>;
  var LABEL_LOAD_FAILED = <?= json_encode(
      I18n::t("load_failed", "Load failed"),
  ) ?>;
  var LABEL_UNABLE_LOAD_VISITORS = <?= json_encode(
      I18n::t("unable_load_visitors", "Unable to load visitors"),
  ) ?>;
  var LABEL_NO_DATA_EXPORT = <?= json_encode(
      I18n::t("no_data_export", "No data to export"),
  ) ?>;
  var LABEL_NO_MATCHING_VISITORS = <?= json_encode(
      I18n::t("no_matching_visitors", "No matching visitors"),
  ) ?>;
  var LABEL_SHOWING_RANGE = <?= json_encode(
      I18n::t(
          "showing_range_of_total_visitors",
          "Showing {start}-{end} of {total} visitors",
      ),
  ) ?>;
  var AUTO_INTERVAL = 30;
  var autoTimer = null;
  var autoRemaining = AUTO_INTERVAL;
  var isLoading = false;

  function esc(str){
    return (str === null || str === undefined ? '' : String(str)).replace(/[&<>"']/g, function(ch){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch] || ch;
    });
  }
  function fmtTime(ts){ try { return new Date(ts*1000).toLocaleString(); } catch(e){ return ts; } }
  function parseUA(ua){
    ua = (ua||'')+'';
    var b='Other', o='Other', bk='other', ok='other';
    if(/Edg\//i.test(ua)){b='Edge';bk='edge';}
    else if(/OPR\//i.test(ua)){b='Opera';bk='opera';}
    else if(/Brave\\/|Brave/i.test(ua)){b='Brave';bk='brave';}
    else if(/Whale\//i.test(ua)){b='Whale';bk='whale';}
    else if(/DuckDuckGo\//i.test(ua)){b='DDG';bk='ddg';}
    else if(/Chrome\//i.test(ua)){b='Chrome';bk='chrome';}
    else if(/Firefox\//i.test(ua)){b='Firefox';bk='firefox';}
    else if(/Safari\//i.test(ua)){b='Safari';bk='safari';}
    if(/Windows NT/i.test(ua)){o='Windows';ok='windows';}
    else if(/Mac OS X|Macintosh/i.test(ua)){o='macOS';ok='mac';}
    else if(/Android/i.test(ua)){o='Android';ok='android';}
    else if(/iPhone|iPad|iOS/i.test(ua)){o='iOS';ok='ios';}
    else if(/Debian/i.test(ua)){o='Debian';ok='debian';}
    else if(/Ubuntu/i.test(ua)){o='Ubuntu';ok='ubuntu';}
    else if(/Fedora/i.test(ua)){o='Fedora';ok='fedora';}
    else if(/CentOS/i.test(ua)){o='CentOS';ok='centos';}
    else if(/Arch/i.test(ua)){o='Arch';ok='arch';}
    else if(/Manjaro/i.test(ua)){o='Manjaro';ok='manjaro';}
    else if(/openSUSE|SUSE/i.test(ua)){o='openSUSE';ok='opensuse';}
    else if(/RHEL|Red Hat/i.test(ua)){o='RHEL';ok='rhel';}
    else if(/Alpine/i.test(ua)){o='Alpine';ok='alpine';}
    else if(/Mint/i.test(ua)){o='Mint';ok='mint';}
    else if(/Kali/i.test(ua)){o='Kali';ok='kali';}
    else if(/Linux/i.test(ua)){o='Linux';ok='linux';}
    return {browser:b, os:o, bkey:bk, okey:ok};
  }
  function parseUACached(row){
    if (!row) return parseUA('');
    if (!Object.prototype.hasOwnProperty.call(row, '__uaParsed')) {
      Object.defineProperty(row, '__uaParsed', {
        value: parseUA(row.ua || ''),
        enumerable: false,
        configurable: true,
        writable: false
      });
    }
    return row.__uaParsed;
  }
  var SVG = {
    chrome:'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#DB4437"/><path d="M12 2a10 10 0 018.66 5H12a5 5 0 00-4.33 2.5L7 9 3 2h9z" fill="#0F9D58"/><circle cx="12" cy="12" r="4" fill="#4285F4"/></svg>',
    firefox:'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M12 2c6 0 10 5 10 10s-4 10-10 10S2 18 2 12c0-4 2-7 5-9-1 2-1 3 0 5 2-2 5-2 7 0 2 3-1 7-5 7" fill="#FF7139"/></svg>',
    edge:'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M12 2c5 0 10 4 10 9-3-3-8-3-12 0-4 3-4 8 0 11-5 0-10-5-10-10S7 2 12 2z" fill="#0078D7"/></svg>',
    opera:'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#FF1B2D"/><circle cx="12" cy="12" r="5" fill="#fff"/></svg>',
    safari:'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#00A1F1"/><path d="M12 6l3 6-6 3 3-9z" fill="#fff"/></svg>',
    windows:'<svg width="14" height="14" viewBox="0 0 24 24"><rect width="24" height="24" fill="#00A4EF"/><path d="M1 3l10-1v9H1V3zm0 10h10v9L1 21v-8zm12-11l10-2v12H13V2zm0 13h10v9l-10-2v-7z" fill="#fff"/></svg>',
    mac:'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="11" fill="#000"/><path d="M16 12c0-2-2-3-4-3-3 0-5 2-5 5 0 2 2 5 5 5 2 0 4-1 4-3-1 0-2-1-2-2s1-2 2-2z" fill="#fff"/></svg>',
    android:'<svg width="14" height="14" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="10" rx="2" fill="#3DDC84"/><circle cx="9" cy="11" r="1.5" fill="#fff"/><circle cx="15" cy="11" r="1.5" fill="#fff"/></svg>',
    ios:'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M17 7c-1 1-2 2-3 2-2 0-3-1-4-1-2 0-5 2-5 6 0 3 2 7 5 7 1 0 2-1 4-1 2 0 3 1 4 1 2 0 4-3 4-6 0-4-2-6-5-8z" fill="#000"/></svg>',
    ddg:'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#DE5833"/><path d="M11 7h2v10h-2z" fill="#fff"/></svg>',
    debian:'<svg width="14" height="14" viewBox="0 0 24 24"><path fill="#A80030" d="M12 2c5.5 0 10 4.5 10 10s-4.5 10-10 10S2 17.5 2 12 6.5 2 12 2z"/></svg>',
    ubuntu:'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#E95420"/><circle cx="12" cy="12" r="4" fill="#fff"/></svg>',
    fedora:'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#294172"/><path d="M9 6h2a3 3 0 010 6H9v4H7V8a2 2 0 012-2z" fill="#fff"/></svg>',
    centos:'<svg width="14" height="14" viewBox="0 0 24 24"><rect width="24" height="24" rx="3" fill="#932279"/><path d="M4 4h16v16H4z" fill="#fff"/></svg>',
    arch:'<svg width="14" height="14" viewBox="0 0 24 24"><path fill="#1793D1" d="M12 2l2 6h-4l2-6zm-2 8h4l-2 6-2-6z"/></svg>',
    manjaro:'<svg width="14" height="14" viewBox="0 0 24 24"><rect width="24" height="24" rx="3" fill="#35BF5C"/><path d="M4 4h6v16H4zM14 4h6v16h-6zM10 4h4v10h-4z" fill="#fff"/></svg>',
    opensuse:'<svg width="14" height="14" viewBox="0 0 24 24"><path fill="#73BA25" d="M12 2a10 10 0 1010 10A10 10 0 0012 2z"/><path fill="#fff" d="M16 10a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
    rhel:'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#EE0000"/><path fill="#fff" d="M12 7l5 5-5 5-5-5z"/></svg>',
    alpine:'<svg width="14" height="14" viewBox="0 0 24 24"><path fill="#0D597F" d="M2 18l10-14 10 14H2z"/><path fill="#fff" d="M8 14l4-6 4 6H8z"/></svg>',
    mint:'<svg width="14" height="14" viewBox="0 0 24 24"><rect width="24" height="24" rx="3" fill="#87CF3E"/><path fill="#fff" d="M6 6h6a6 6 0 016 6v6h-4v-6a2 2 0 00-2-2H6V6z"/></svg>',
    kali:'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#268BD2"/><path fill="#fff" d="M7 12l10-5-5 10-2-5z"/></svg>',
    whale:'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M2 13c2 5 8 7 12 7 5 0 8-3 8-6-2 1-4 0-6-2-3 1-6 1-9 1H2z" fill="#1EC8E1"/></svg>',
    linux:'<svg width="14" height="14" viewBox="0 0 24 24"><path fill="#FCC624" d="M7 20c-2-2-2-5 0-7 0-4 2-7 5-7s5 3 5 7c2 2 2 5 0 7H7z"/><circle cx="10" cy="10" r="1" fill="#000"/><circle cx="14" cy="10" r="1" fill="#000"/></svg>',
    other:'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#888"/></svg>'
  };
  function iconHTML(k,label){
    label = label || '';
    if (!SHOW_ICONS) return label;
    var svg = SVG[k] || SVG.other;
    return '<span class="ua-icon-inline">'+svg+'</span> '+label;
  }
  function buildIpCell(row){
    var ip = row.ip || '';
    var tip = ip;
    if (row.city) tip += (tip ? ' • ' : '') + row.city;
    if (row.country) tip += (row.city ? ', ' : (tip ? ', ' : '')) + row.country;
    var html = '';
    if (SHOW_ICONS) html += '<img class="ua-icon" src="'+IP_ICON+'" width="14" height="14" alt="ip"> ';
    html += '<span class="has-tip" data-tip="'+esc(tip)+'">'+(ip ? esc(ip) : '-')+'</span>';
    if ((row.__repeatTotal || 0) > 1) {
      var deltaText = '';
      if (row.__prevTs) {
        var diff = Math.max(0, (row.__prevTs || 0) - (row.ts || 0));
        deltaText = ' • ' + formatDelta(diff) + ' ' + LABEL_SINCE_LAST;
      }
      html += '<span class="repeat-badge" title="Seen '+row.__repeatTotal+' times">'+LABEL_REPEAT+' ×'+row.__repeatTotal + deltaText + '</span>';
    }
    return html;
  }
  var columnDefs = [
    {id:'ip', label:<?= json_encode(
        I18n::t("ip", "IP"),
    ) ?>, required:true, active:true,
      render:function(row){ return '<td>'+buildIpCell(row)+'</td>'; },
      csv:function(row){ return row.ip || ''; }},
    {id:'path', label:<?= json_encode(
        I18n::t("path", "Path"),
    ) ?>, required:true, active:true,
      render:function(row){ var path=row.path||''; return '<td style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+esc(path)+'">'+esc(path)+'</td>'; },
      csv:function(row){ return row.path || ''; }},
    {id:'time', label:<?= json_encode(
        I18n::t("time", "Time"),
    ) ?>, required:true, active:true,
      render:function(row){ return '<td>'+esc(fmtTime(row.ts||0))+'</td>'; },
      csv:function(row){ try { return new Date((row.ts||0)*1000).toISOString(); } catch(e){ return row.ts || ''; } }},
    {id:'browser', label:<?= json_encode(
        I18n::t("browser", "Browser"),
    ) ?>, required:false, active:true,
      render:function(row){ var ua=parseUACached(row); return '<td>'+iconHTML(ua.bkey, ua.browser)+'</td>'; },
      csv:function(row){ return parseUACached(row).browser; }},
    {id:'os', label:<?= json_encode(
        I18n::t("os", "OS"),
    ) ?>, required:false, active:true,
      render:function(row){ var ua=parseUACached(row); return '<td>'+iconHTML(ua.okey, ua.os)+'</td>'; },
      csv:function(row){ return parseUACached(row).os; }},
    {id:'city', label:<?= json_encode(
        I18n::t("city", "City"),
    ) ?>, required:false, active:true,
      render:function(row){ return '<td>'+esc(row.city||'-')+'</td>'; },
      csv:function(row){ return row.city || ''; }},
    {id:'country', label:<?= json_encode(
        I18n::t("country", "Country"),
    ) ?>, required:false, active:true,
      render:function(row){ return '<td>'+esc(row.country||'-')+'</td>'; },
      csv:function(row){ return row.country || ''; }},
  ];

  (function seedColumnPrefs(){
    var saved=null;
    try { saved = JSON.parse(localStorage.getItem(COLUMN_PREF_KEY)||'null'); } catch(e){}
    if (saved && typeof saved === 'object'){
      columnDefs.forEach(function(def){
        if (def.required) { def.active = true; return; }
        if (Object.prototype.hasOwnProperty.call(saved, def.id)) def.active = !!saved[def.id];
      });
    }
  })();

  function saveColumnPrefs(){
    var out={};
    columnDefs.forEach(function(def){ out[def.id] = !!def.active; });
    try { localStorage.setItem(COLUMN_PREF_KEY, JSON.stringify(out)); } catch(e){}
  }

  function getActiveColumns(){
    return columnDefs.filter(function(def){ return def.active || def.required; });
  }

  function updateHead(){
    var active = getActiveColumns();
    headRow.innerHTML = active.map(function(def){ return '<th style="text-align:left">'+def.label+'</th>'; }).join('');
  }

  function columnColspan(){
    var len = getActiveColumns().length;
    return len > 0 ? len : 1;
  }

  function formatDelta(seconds){
    seconds = Math.max(0, Math.floor(seconds));
    var units = [
      {label:'d', value:86400},
      {label:'h', value:3600},
      {label:'m', value:60},
      {label:'s', value:1},
    ];
    for (var i=0;i<units.length;i++){
      var u = units[i];
      if (seconds >= u.value) {
        var count = Math.floor(seconds / u.value);
        return count + u.label;
      }
    }
    return '0s';
  }

  function updateAutoIndicator(){
    if (autoIndicator) {
      if (autoToggle && autoToggle.checked) autoIndicator.textContent = LABEL_AUTO_IN.replace('{seconds}', String(autoRemaining));
      else autoIndicator.textContent = '';
    }
  }
  function startAutoRefresh(){
    stopAutoRefresh();
    if (!autoToggle || !autoToggle.checked) return;
    autoRemaining = AUTO_INTERVAL;
    updateAutoIndicator();
    autoTimer = setInterval(function(){
      if (isLoading) return;
      autoRemaining--;
      if (autoRemaining <= 0) {
        refresh();
        autoRemaining = AUTO_INTERVAL;
      }
      updateAutoIndicator();
    }, 1000);
  }
  function stopAutoRefresh(){
    if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
    updateAutoIndicator();
  }

  function fieldsForActiveColumns(){
    var needed = {ip:true, path:true, ts:true};
    getActiveColumns().forEach(function(def){
      if (def.id === 'browser' || def.id === 'os') needed.ua = true;
      if (def.id === 'city') needed.city = true;
      if (def.id === 'country') needed.country = true;
    });
    return Object.keys(needed);
  }

  function syncColumnUI(){
    columnChecks.forEach(function(chk){
      var id = chk.getAttribute('data-col');
      var def = columnDefs.find(function(d){ return d.id === id; });
      if (def) chk.checked = !!def.active;
    });
  }

  function renderRows(rows){
    rows = Array.isArray(rows) ? rows : [];
    lastRows = rows.slice();
    var active = getActiveColumns();
    var ipCounts = {};
    rows.forEach(function(r){
      var ip = r.ip || '';
      if (!ip) return;
      ipCounts[ip] = (ipCounts[ip] || 0) + 1;
    });
    var lastSeenMap = {};
    rows.forEach(function(r){
      var ip = r.ip || '';
      if (!ip) return;
      r.__repeatTotal = ipCounts[ip] || 0;
      r.__prevTs = lastSeenMap[ip];
      if (!lastSeenMap[ip]) lastSeenMap[ip] = r.ts || 0;
    });
    if (!rows.length){
      tbody.innerHTML = '<tr><td colspan="'+columnColspan()+'" style="opacity:.7;text-align:center">'+esc(LABEL_NO_RESULTS)+'</td></tr>';
      return;
    }
    var out = rows.map(function(r){
      var cells = active.map(function(def){ return def.render(r); }).join('');
      return '<tr>'+cells+'</tr>';
    }).join('');
    tbody.innerHTML = out;
  }
  function csvEscape(val){
    var str = (val === null || val === undefined) ? '' : String(val);
    if (/[",\n\r]/.test(str)) return '"' + str.replace(/"/g, '""') + '"';
    return str;
  }
  function rowsToCSV(rows){
    var active = getActiveColumns();
    if (!active.length) return '';
    var header = active.map(function(def){ return def.label; });
    var lines = [header.join(',')];
    rows.forEach(function(row){
      var line = active.map(function(def){
        var val = def.csv ? def.csv(row) : '';
        return csvEscape(val);
      }).join(',');
      lines.push(line);
    });
    return lines.join('\r\n');
  }
  function downloadBlob(content, mime, filename){
    var blob = new Blob([content], {type:mime});
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function(){ URL.revokeObjectURL(url); }, 0);
  }
  function exportData(format){
    if (!lastRows.length) {
      pageInfo.textContent = LABEL_NO_DATA_EXPORT;
      return;
    }
    var stamp = new Date();
    var base = 'visitors-page-' + state.page + '-' + stamp.toISOString().replace(/[:.]/g,'-');
    if (format === 'json') {
      downloadBlob(JSON.stringify(lastRows, null, 2), 'application/json', base + '.json');
    } else {
      var csv = rowsToCSV(lastRows);
      downloadBlob(csv, 'text/csv;charset=utf-8', base + '.csv');
    }
    if (exportMenu) exportMenu.open = false;
  }
  function updatePager(meta, rowsCount){
    var total = parseInt(meta.total, 10);
    if (!isFinite(total)) total = rowsCount || 0;
    var page = parseInt(meta.page, 10);
    if (!isFinite(page) || page < 1) page = 1;
    var pages = parseInt(meta.pages, 10);
    if (!isFinite(pages) || pages < 1) pages = 1;
    state.page = page;
    state.pages = pages;
    state.total = total;
    var start = rowsCount ? ((page - 1) * state.limit) + 1 : 0;
    var end = rowsCount ? start + rowsCount - 1 : 0;
    if (!rowsCount) {
      pageInfo.textContent = LABEL_NO_MATCHING_VISITORS;
    } else {
      pageInfo.textContent = LABEL_SHOWING_RANGE
        .replace('{start}', String(start))
        .replace('{end}', String(end))
        .replace('{total}', String(total));
    }
    prevBtn.disabled = page <= 1;
    nextBtn.disabled = page >= pages;
  }
  function buildParams(fieldsOverride){
    var params = [
      'limit=' + encodeURIComponent(state.limit),
      'page=' + encodeURIComponent(state.page)
    ];
    if (state.q) params.push('q=' + encodeURIComponent(state.q));
    params.push('order=' + encodeURIComponent(state.order || 'desc'));
    var fields = fieldsOverride && fieldsOverride.length ? fieldsOverride : fieldsForActiveColumns();
    if (fields && fields.length) params.push('fields=' + encodeURIComponent(fields.join(',')));
    return params;
  }

  function setLoading(){
    isLoading = true;
    tbody.innerHTML = '<tr><td colspan="'+columnColspan()+'" style="opacity:.7;text-align:center">'+esc(LABEL_LOADING)+'</td></tr>';
    pageInfo.textContent = LABEL_LOADING;
  }
  function refresh(pageOverride){
    var desiredPage = typeof pageOverride === 'number' ? pageOverride : state.page;
    var newLimit = parseInt(limit.value, 10) || 50;
    var newQ = q.value.trim();
    var limitChanged = newLimit !== state.limit;
    var qChanged = newQ !== state.q;
    state.limit = newLimit;
    state.q = newQ;
    if (limitChanged || qChanged) desiredPage = 1;
    state.page = Math.max(1, desiredPage);

    state.order = (orderSel && orderSel.value === 'asc') ? 'asc' : 'desc';
    var url = 'index.php?p=api.geo&' + buildParams();

    setLoading();
    fetch(url, {credentials:'same-origin'})
      .then(function(resp){
        if (!resp.ok) throw new Error('Request failed (' + resp.status + ')');
        return resp.json();
      })
      .then(function(data){
        if (data && data.ok === false) throw new Error(data.err || 'Request error');
        var rows = Array.isArray(data) ? data : (data && Array.isArray(data.items) ? data.items : []);
        renderRows(rows);
        var meta = (data && !Array.isArray(data)) ? data : {total: rows.length, page: state.page, pages: Math.max(1, Math.ceil(Math.max(rows.length, 1) / state.limit))};
        updatePager(meta, rows.length);
        isLoading = false;
        if (autoToggle && autoToggle.checked) {
          autoRemaining = AUTO_INTERVAL;
          updateAutoIndicator();
        }
      })
      .catch(function(){
        tbody.innerHTML = '<tr><td colspan="'+columnColspan()+'" style="opacity:.7;text-align:center">'+esc(LABEL_LOAD_FAILED)+'</td></tr>';
        pageInfo.textContent = LABEL_UNABLE_LOAD_VISITORS;
        isLoading = false;
        if (autoToggle && autoToggle.checked) {
          autoRemaining = AUTO_INTERVAL;
          updateAutoIndicator();
        }
      });
  }

  updateHead();
  syncColumnUI();

  refreshBtn.addEventListener('click', function(){ refresh(); });
  limit.addEventListener('change', function(){ refresh(1); });
  q.addEventListener('keydown', function(e){ if (e.key === 'Enter') refresh(1); });
  if (orderSel) {
    orderSel.addEventListener('change', function(){
      state.order = (orderSel.value === 'asc') ? 'asc' : 'desc';
      refresh(1);
    });
  }
  exportButtons.forEach(function(btn){
    btn.addEventListener('click', function(){
      var format = this.getAttribute('data-export') || 'csv';
      exportData(format);
    });
  });
  if (autoToggle) {
    autoToggle.addEventListener('change', function(){
      if (autoToggle.checked) {
        startAutoRefresh();
      } else {
        stopAutoRefresh();
      }
    });
  }
  columnChecks.forEach(function(chk){
    chk.addEventListener('change', function(){
      var id = this.getAttribute('data-col');
      var def = columnDefs.find(function(d){ return d.id === id; });
      if (!def) return;
      if (def.required) {
        def.active = true;
        this.checked = true;
        return;
      }
      def.active = this.checked;
      saveColumnPrefs();
      updateHead();
      renderRows(lastRows);
      refresh(state.page);
    });
  });
  prevBtn.addEventListener('click', function(){ if (state.page > 1) refresh(state.page - 1); });
  nextBtn.addEventListener('click', function(){ if (state.page < state.pages) refresh(state.page + 1); });
  refresh();
  if (autoToggle && autoToggle.checked) startAutoRefresh();
})();
</script>

<script>
// /* IP_ICON_SWAPPER */ - force external IP icon even if inline SVG slips in
(function(){
  var BASE = "<?php echo $__base; ?>/assets/icons/ip.svg";
  function swapIcons(rootEl){
    try {
      var cells = (rootEl ? rootEl.querySelectorAll('td:first-child svg') : document.querySelectorAll('#vis-table td:first-child svg'));
      cells.forEach(function(svg){
        var img = document.createElement('img');
        img.className = 'ua-icon';
        img.width = 14; img.height = 14;
        img.alt = 'ip';
        img.src = BASE;
        svg.replaceWith(img);
      });
    } catch(e){}
  }
  setTimeout(function(){ swapIcons(); }, 0);
  try {
    var target = document.querySelector('#vis-table tbody') || document.querySelector('#vis-table');
    if (target && window.MutationObserver){
      var mo = new MutationObserver(function(muts){ muts.forEach(function(m){ if (m.addedNodes) m.addedNodes.forEach(function(n){ if (n.nodeType===1) swapIcons(n); }); }); });
      mo.observe(target, {childList:true, subtree:true});
    }
  } catch(e){}
})();
</script>
<script>
(function(){
  var tipEl = null;
  function showTip(e){
    var t = e.currentTarget.getAttribute('data-tip') || '';
    if(!t) return;
    hideTip();
    tipEl = document.createElement('div');
    tipEl.className = 'tooltip';
    tipEl.textContent = t;
    document.body.appendChild(tipEl);
    position(e);
  }
  function position(e){
    if(!tipEl) return;
    var x = (e.clientX || 0) + 12;
    var y = (e.clientY || 0) + 12;
    var maxX = window.innerWidth - tipEl.offsetWidth - 8;
    var maxY = window.innerHeight - tipEl.offsetHeight - 8;
    if (x > maxX) x = maxX;
    if (y > maxY) y = maxY;
    tipEl.style.left = x + 'px';
    tipEl.style.top = y + 'px';
  }
  function hideTip(){
    if(tipEl && tipEl.parentNode){ tipEl.parentNode.removeChild(tipEl); }
    tipEl = null;
  }
  function bind(container){
    container = container || document;
    var els = container.querySelectorAll('[data-tip]');
    els.forEach(function(el){
      el.addEventListener('mouseenter', showTip);
      el.addEventListener('mousemove', position);
      el.addEventListener('mouseleave', hideTip);
      if (el.hasAttribute('title')) el.setAttribute('data-tip', el.getAttribute('title')), el.removeAttribute('title');
    });
  }
  bind();
  if (window.MutationObserver){
    var target = document.querySelector('#vis-table tbody') || document.body;
    var mo = new MutationObserver(function(muts){
      muts.forEach(function(m){
        (m.addedNodes||[]).forEach(function(n){ if(n.nodeType===1) bind(n); });
      });
    });
    mo.observe(target, {childList:true, subtree:true});
  }
  window.addEventListener('scroll', function(){ if(tipEl) hideTip(); }, {passive:true});
})();
</script>
