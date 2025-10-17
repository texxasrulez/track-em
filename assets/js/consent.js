(function () {
  try {
    var script = document.currentScript || (function() {
      var arr = document.getElementsByTagName('script');
      return arr[arr.length - 1];
    })();
    var src = script && script.getAttribute('src') || '';
    var BASE = (window.TE_BASE || '').replace(/\/+$/, '');
    if (!BASE) {
      var m = src.match(/^(.*)\/assets\/js\/consent\.js(?:\?.*)?$/);
      BASE = (m && m[1]) ? m[1] : '/track-em';
    }

    function getCookie(name) {
      var pattern = new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\\/\+^])/g, '\\$1') + '=([^;]*)');
      var m = document.cookie.match(pattern);
      return m ? decodeURIComponent(m[1]) : null;
    }
    function setCookie(name, value, days) {
      var d = new Date();
      d.setTime(d.getTime() + (days || 365) * 24 * 60 * 60 * 1000);
      document.cookie = name + '=' + encodeURIComponent(value) +
        '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
    }

    if (!window.TE_CONSENT_FORCE) {
      var existing = getCookie('te_consent');
      if (existing === 'allow' || existing === 'deny') return;
    }

    function fetchJSON(url) {
      return fetch(url, {credentials: 'same-origin', cache: 'no-store'})
        .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); });
    }

    var apiUrl = BASE + '/?p=api.plugins&_ts=' + Date.now();
    fetchJSON(apiUrl).then(function (data) {
      var enabled = false, cfg = {};

      if (data && Array.isArray(data.items)) {
        for (var i=0;i<data.items.length;i++){
          var it = data.items[i];
          if (!it || !it.key) continue;
          if (it.key === 'consent_banner' || it.key === 'consent') {
            enabled = it.enabled !== false;
            cfg = it.config || {};
            break;
          }
        }
      }
      if (!enabled && data && data.configs) {
        var c = data.configs['consent_banner'] || data.configs['consent'];
        if (c && (typeof c.enabled === 'undefined' || c.enabled === true)) {
          enabled = true;
          cfg = c.config || c || {};
        }
      }

      if (!enabled) return;
      var requireConsent = (typeof cfg.require_consent === 'undefined') ? true : !!cfg.require_consent;
      if (!requireConsent && !window.TE_CONSENT_FORCE) return;
      renderBanner(cfg || {});
    }).catch(function(){ if(window.TE_CONSENT_FORCE) renderBanner({}); });

    function renderBanner(cfg) {
      if (document.getElementById('te-consent-banner')) return;
      var text = cfg.message || 'We use cookies and similar tech to analyze traffic. Choose allow or deny.';
      var pos = (cfg.position || 'bottom').toLowerCase();
      var align = cfg.align || 'center';
      var allowTxt = cfg.allow_text || 'Allow';
      var denyTxt = cfg.deny_text || 'Deny';

      var bar = document.createElement('div');
      bar.id = 'te-consent-banner';
      bar.style.position = 'fixed';
      bar.style.left = '0'; bar.style.right = '0';
      bar.style[pos === 'top' ? 'top' : 'bottom'] = '0';
      bar.style.zIndex = '2147483000';
      bar.style.background = cfg.background || 'rgba(20,20,20,0.98)';
      bar.style.color = cfg.color || '#fff';
      bar.style.padding = '12px 16px';
      bar.style.fontFamily = 'system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif';
      bar.style.display = 'flex'; bar.style.gap = '12px';
      bar.style.justifyContent = (align==='left')?'flex-start':(align==='right')?'flex-end':'center';
      bar.style.alignItems = 'center'; bar.style.flexWrap = 'wrap';
      bar.style.boxShadow = '0 2px 12px rgba(0,0,0,.25)';

      var msg = document.createElement('div');
      msg.textContent = text; msg.style.maxWidth = '900px';

      function mkBtn(label,bg,color){
        var b=document.createElement('button');
        b.textContent=label;
        b.style.border='0'; b.style.padding='8px 12px';
        b.style.cursor='pointer'; b.style.borderRadius='8px';
        b.style.fontWeight='600'; b.style.boxShadow='0 1px 2px rgba(0,0,0,.2)';
        b.style.background=bg; b.style.color=color;
        return b;
      }
      var allow = mkBtn(allowTxt,cfg.allow_bg||'#4caf50',cfg.allow_color||'#fff');
      var deny  = mkBtn(denyTxt,cfg.deny_bg||'#e53935',cfg.deny_color||'#fff');

      function decide(val){
        setCookie('te_consent', val, 365);
        new Image().src = BASE+'/track.php?event=consent&value='+encodeURIComponent(val)+'&_ts='+Date.now();
        bar.remove();
      }
      allow.onclick=function(){decide('allow');};
      deny.onclick=function(){decide('deny');};

      bar.append(msg, allow, deny);
      document.body.appendChild(bar);
    }
  } catch(e){}
})();
