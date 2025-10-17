(function(){
  try {
    var grid = document.getElementById('dash-grid');
    if (!grid || document.querySelector('.card[data-id="visitors"]')) return;

    var BASE = (typeof window.BASE === 'string') ? window.BASE : (function(){
      var p = (location.pathname||'').replace(/\/index\.php.*$/,'');
      return p.replace(/\/+$/,'');
    })();
    var API = (typeof window.API === 'function') ? window.API : function(ep){ return BASE + '/index.php?p=' + ep; };
    var rowLimit = (window.DASH && window.DASH.row_limit) ? window.DASH.row_limit : 200;

    var card = document.createElement('div');
    card.className = 'card';
    card.setAttribute('data-id','visitors');
    card.innerHTML = "<div class='card-handle' style='cursor:grab;margin:-4px 0 8px;font-weight:bold'>Visitors</div><div id='visitors-body'><div>Loading…</div></div>";
    grid.appendChild(card);

    fetch(API('api.geo') + '&limit=' + encodeURIComponent(String(rowLimit)), {credentials:'same-origin', cache:'no-store'})
      .then(function(r){ return r.text(); })
      .then(function(txt){
        var data=null; try{ data=JSON.parse(txt); }catch(e){ console.warn('[visitors] bad json'); return; }
        var rows = Array.isArray(data) ? data : (Array.isArray(data.items) ? data.items : []);
        var seen = Object.create(null);
        var byCountry = Object.create(null);
        for (var i=0;i<rows.length;i++){
          var row = rows[i]||{};
          var ip = row.ip||''; var ts = row.ts||0; var c = row.country||'Unknown';
          if (!(ip in seen) || ts > seen[ip]) seen[ip] = ts;
          byCountry[c] = (byCountry[c]||0)+1;
        }
        var unique = Object.keys(seen).length;
        var cutoff = Math.floor(Date.now()/1000) - 86400;
        var dayCount = 0;
        for (var k in seen){ if (seen[k] >= cutoff) dayCount++; }
        var topCountries = Object.keys(byCountry).map(function(k){ return [k, byCountry[k]]; })
                            .sort(function(a,b){ return b[1]-a[1]; }).slice(0,5);

        var el = document.getElementById('visitors-body');
        if (!el) return;
        var html = "<div style='display:flex;gap:16px;flex-wrap:wrap'>"
                 +   "<div style='min-width:120px'><div style='font-size:12px;opacity:.7'>Unique IPs</div><div style='font-size:24px;font-weight:700'>"+unique+"</div></div>"
                 +   "<div style='min-width:120px'><div style='font-size:12px;opacity:.7'>Last 24h</div><div style='font-size:24px;font-weight:700'>"+dayCount+"</div></div>"
                 +   "<div style='min-width:200px'><div style='font-size:12px;opacity:.7;margin-bottom:6px'>Top Countries</div>";
        if (topCountries.length===0) {
          html += "<div>—</div>";
        } else {
          for (var j=0;j<topCountries.length;j++){
            var c = topCountries[j];
            html += "<div style='display:flex;justify-content:space-between;border-bottom:1px dashed var(--border,#2a3340);padding:2px 0'><span>"+c[0]+"</span><span>"+c[1]+"</span></div>";
          }
        }
        html +=   "</div></div>";
        el.innerHTML = html;
      })
      .catch(function(e){ console.warn('[visitors] fetch error', e); });
  } catch(e) {
    console.warn('[visitors] widget exception', e);
  }
})();