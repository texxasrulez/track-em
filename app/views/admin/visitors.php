<?php
$__cfg = \TrackEm\Core\Config::instance()->all();
$__dash = (isset($__cfg['dashboard']) && is_array($__cfg['dashboard'])) ? $__cfg['dashboard'] : array();
$__show_icons = !empty($__dash['show_icons']);
$__base = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
if ($__base === '/') $__base = '';
?>
<?php $__base = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/'); if ($__base === '/') $__base = ''; ?>
<?php /* Recent Visits with Browser + OS (logo + label) */ ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
  <h3 style="margin:0">Recent Visits</h3>
  <div style="display:flex;gap:8px;align-items:center">
    <input id="q" placeholder="Search..." style="padding:6px 10px;border-radius:8px;border:1px solid var(--border,#2a3340);background:transparent;color:inherit;min-width:260px">
    <select id="limit" style="padding:6px;border-radius:8px;border:1px solid var(--border,#2a3340);background:transparent;color:inherit">
      <option value="50" selected>50</option>
      <option value="100">100</option>
      <option value="200">200</option>
      <option value="500">500</option>
    </select>
    <button id="refresh" style="padding:6px 10px;border-radius:8px;border:1px solid var(--border,#2a3340);background:transparent;color:inherit;cursor:pointer">Refresh</button>
  </div>
</div>

<div class="table wrap">
  <table id="vis-table" style="width:100%">
    <thead><tr>
      <th style="text-align:left">IP</th>
      <th style="text-align:left">Path</th>
      <th style="text-align:left">Time</th>
      <th style="text-align:left">Browser</th>
      <th style="text-align:left">OS</th>
      <th style="text-align:left">City</th>
      <th style="text-align:left">Country</th>
    </tr></thead>
    <tbody id="vis-tbody"></tbody>
  </table>
</div>

<script>
(function(){
  var limit = document.getElementById('limit');
  var q = document.getElementById('q');
  var tbody = document.getElementById('vis-tbody');

  function fmtTime(ts){ try { return new Date(ts*1000).toLocaleString(); } catch(e){ return ts; } }
  function parseUA(ua){
    ua = (ua||'')+'';
    var b='Other', o='Other', bk='other', ok='other';
    if(/Edg\//i.test(ua)){b='Edge';bk='edge';}
    else if(/OPR\//i.test(ua)){b='Opera';bk='opera';}
    else if(/Brave\\/|Brave/i.test(ua)){b='Brave';bk='brave';}
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
    else if(/openSUSE/i.test(ua)){o='openSUSE';ok='opensuse';}
    else if(/RHEL|Red Hat/i.test(ua)){o='RHEL';ok='rhel';}
    else if(/Alpine/i.test(ua)){o='Alpine';ok='alpine';}
    else if(/Mint/i.test(ua)){o='Mint';ok='mint';}
    else if(/Kali/i.test(ua)){o='Kali';ok='kali';}
    else if(/Linux/i.test(ua)){o='Linux';ok='linux';}
    return {browser:b, os:o, bkey:bk, okey:ok};
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
    linux:'<svg width="14" height="14" viewBox="0 0 24 24"><path fill="#FCC624" d="M7 20c-2-2-2-5 0-7 0-4 2-7 5-7s5 3 5 7c2 2 2 5 0 7H7z"/><circle cx="10" cy="10" r="1" fill="#000"/><circle cx="14" cy="10" r="1" fill="#000"/></svg>',
    other:'<svg width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#888"/></svg>'
  };
  function iconHTML(k,l){
  var label=(l||'');
  <?php if ($__show_icons): ?>
  var src=(SVG[k]||SVG.other);
  return '<img class="ua-icon" src="'+src+'" width="14" height="14" alt="'+k+'"/> '+label;
  <?php else: ?>
  return label;
  <?php endif; ?>
} return '<img class="ua-icon" src="'+src+'" width="14" height="14" alt="'+k+'"/> '+l; }

  function refresh(){
    var url = 'index.php?p=api.geo&limit=' + encodeURIComponent(limit.value);
    fetch(url).then(r=>r.json()).then(data=>{
      var rows = Array.isArray(data) ? data : (Array.isArray(data.items) ? data.items : []);
      var qv = (q.value||'').toLowerCase();
      var out = '';
      for (var i=0;i<rows.length;i++){
        var r = rows[i]||{};
        var ua = parseUA(r.ua||'');
        var line = ((r.ip||'')+' '+(r.path||'')+' '+(r.country||'')+' '+(r.city||'')+' '+ua.browser+' '+ua.os).toLowerCase();
        if (qv && line.indexOf(qv)===-1) continue;
        out += '<tr>'+
         out += '<tr>'+
               '<td>'+(<?php if($__show_icons): ?>'<img class=\'ua-icon\' src=\''+BASE+\'\' width=\'14\' height=\'14\' alt=\'ip\'> '+<?php endif; ?>+"<span class=\'has-tip\' data-tip=\'"+(r.ip||'')+(r.city?(' • '+r.city):'')+(r.country?(', '+r.country):'')+"\'>"+(r.ip||'')+"</span>")+'</td>'+
               '<td style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+(r.path||'')+'">'+(r.path||'')+'</td>'+
               '<td>'+fmtTime(r.ts||0)+'</td>'+
               '<td>'+iconHTML(ua.bkey,ua.browser)+'</td>'+
               '<td>'+iconHTML(ua.okey,ua.os)+'</td>'+
               '<td>'+(r.city||'-')+'</td>'+
               '<td>'+(r.country||'-')+'</td>'+
               '</tr>';
      }
      tbody.innerHTML = out || '<tr><td colspan="7" style="opacity:.7">No results</td></tr>';
    }).catch(e=>{ tbody.innerHTML = '<tr><td colspan="7" style="opacity:.7">Load failed</td></tr>'; });
  }

  document.getElementById('refresh').onclick = refresh;
  limit.onchange = refresh;
  q.addEventListener('keydown', function(e){ if(e.key==='Enter') refresh(); });
  refresh();
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
