<?php
use TrackEm\Core\I18n;
use TrackEm\Core\Security;
use TrackEm\Core\Theme;

$themes = Theme::list();
$active = Theme::activeId();
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
?>
<style>
  .form input[type="text"],
  .form input[type="password"],
  .form select {
    background-color: var(--muted);
    color: var(--text);
    width: 180px;         /* Adjust this to your liking */
    display: inline-block;
    margin-right: 6px;
  }

  .form-inline input[type="text"],
  .form-inline input[type="password"],
  .form-inline select {
    background-color: var(--muted);
    color: var(--text);
    width: 140px;
  }

  .form button {
    padding: 4px 10px;
  }

  table {
    width: auto;
  }

  table td, table th {
    padding: 6px 8px;
  }
  .card table {
  width: 100%;
}
.card td form {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
</style>
<div class="card">
  <h3><?= I18n::t('plugins','Plugins') ?></h3>
  <p class="note"><?= I18n::t('plugins_note','Manage plugins. Upload zips, enable/disable, or remove. Actions apply immediately.') ?></p>

  <form id="plg-upload" class="form form-row" enctype="multipart/form-data" method="post" action="?p=api.plugins.install" style="margin-bottom:12px">
    <input style="width:10vw" type="file" name="plugin_zip" accept=".zip" required class="ms-popup"/>
    <button type="submit" class="button btn"><?= I18n::t('upload_install','Upload &amp; Install') ?></button>
  </form>

  <div id="plg-error" class="alert" style="display:none"></div>
  <div id="plg-grid" class="grid grid-3"></div>
</div>

<script>
(function(){
  var CSRF = "<?= Security::csrfToken() ?>";
  function API(ep){ return '?p='+encodeURIComponent(ep); }
  var grid = document.getElementById('plg-grid');
  var err  = document.getElementById('plg-error');

  function showErr(m){ if(err){ err.style.display='block'; err.textContent=String(m||''); } }
  function hideErr(){ if(err){ err.style.display='none'; err.textContent=''; } }
  function esc(s){ return (s==null?'':String(s)).replace(/[&<>"]/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]); }); }

  function serializeForm(form){
    var out = {}; if(!form) return out;
    var els = form.querySelectorAll('[name]');
    for (var i=0;i<els.length;i++){ var el=els[i]; out[el.name]=el.value; }
    return out;
  }

  function card(p, cfg){
    cfg = (cfg && typeof cfg === 'object' && !Array.isArray(cfg)) ? cfg : {};
    var k=p.key, m=p.meta||{}; var n=m.name||k, v=m.version||'-', d=m.description||'';
    var enabled = !!p.enabled;
    var badge = enabled ? '<span class="badge">Enabled</span>' : '';
    var cfgHtml = '';

    if (k==='consent_banner'){
      var msg = (cfg.message!=null ? String(cfg.message) : 'This site uses privacy-friendly analytics.');
      var pos = (cfg.position==='top' ? 'top' : 'bottom');
      cfgHtml =
        '<div class="form" style="margin-top:8px">' +
        '<input type="hidden" name="csrf" value="'+CSRF+'"/>' +
        '  <label>Banner message<br/><input type="text" name="message" value="'+esc(msg)+'" style="width:95%"/></label>' +
        '  <label style="display:block;margin-top:6px">Position<br/>' +
        '    <select name="position">' +
        '      <option value="bottom"'+(pos==='bottom'?' selected':'')+'>Bottom</option>' +
        '      <option value="top"'+(pos==='top'?' selected':'')+'>Top</option>' +
        '    </select>' +
        '  </label>' +
        '  <button class="button btn" data-save="'+k+'" style="margin-top:8px">Save</button>' +
        '</div>';
    }

    var actions = '<div class="actions actions-right">' +
      (enabled
        ? '<button class="button btn" data-act="disable" data-key="'+k+'">Disable</button>'
        : '<button class="button btn" data-act="enable" data-key="'+k+'">Enable</button>') +
      ' <button class="button danger" data-act="remove" data-key="'+k+'">Remove</button>' +
      '</div>';

    var html = '' +
      '<div class="card" data-plugin="'+k+'">' +
      '  <h4>'+esc(n)+' '+badge+'</h4>' +
      '  <p class="muted">v'+esc(v)+'</p>' +
      '  <p>'+esc(d)+'</p>' +
      cfgHtml +
      actions +
      '</div>';
    return html;
  }

  function refresh(){
    hideErr();
    var xhr=new XMLHttpRequest(); xhr.open('GET', API('api.plugins.list'), true);
    xhr.onreadystatechange=function(){
      if(xhr.readyState===4){
        if(xhr.status!==200){ showErr('Failed to load plugin list'); return; }
        var data;
        try { data = JSON.parse(xhr.responseText || '{}'); }
        catch(e){ showErr('Bad JSON'); return; }
        var items = (data && data.items) || [];
        var html='';
        for (var i=0;i<items.length;i++){
          var p = items[i];
          var current = (p.config && typeof p.config === 'object' && !Array.isArray(p.config)) ? p.config : {};
          html += card(p, current);
        }
        if (grid) grid.innerHTML = html || '<div class="note">No plugins installed yet.</div>';
      }
    };
    var _data = null; try { if (typeof _data === 'string' && _data.indexOf('csrf=') === -1) { _data += (_data ? '&' : '') + 'csrf=' + encodeURIComponent(CSRF); } } catch(e) {}
    xhr.send(_data);
  }

  if (grid){
    grid.addEventListener('click', function(e){
      var t=e.target; if(!t) return;
      if (t.matches('button[data-save]')){
        e.preventDefault();
        var key = t.getAttribute('data-save');
        var form = t.closest('.card').querySelector('.form');
        var payload = serializeForm(form);
        var xhr=new XMLHttpRequest(); xhr.open('POST', API('api.plugins.config.set'), true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        var body = 'key='+encodeURIComponent(key)+'&config='+encodeURIComponent(JSON.stringify(payload));
        xhr.onreadystatechange=function(){ if(xhr.readyState===4){ refresh(); } };
        var _data = body; try { if (typeof _data === 'string' && _data.indexOf('csrf=') === -1) { _data += (_data ? '&' : '') + 'csrf=' + encodeURIComponent(CSRF); } } catch(e) {}
    xhr.send(_data);
        return;
      }
      if (!t.hasAttribute('data-act')) return;
      var act=t.getAttribute('data-act'), key=t.getAttribute('data-key');
      if (act==='enable' || act==='disable'){
        var on = (act==='enable') ? 1 : 0;
        var xhr=new XMLHttpRequest(); xhr.open('POST', API('api.plugins.toggle')+'&key='+encodeURIComponent(key)+'&enabled='+on, true);
        xhr.onreadystatechange=function(){ if(xhr.readyState===4) refresh(); };
        var _data = null; try { if (typeof _data === 'string' && _data.indexOf('csrf=') === -1) { _data += (_data ? '&' : '') + 'csrf=' + encodeURIComponent(CSRF); } } catch(e) {}
    xhr.send(_data);
      } else if (act==='remove'){
        if(!confirm('Remove plugin '+key+'?')) return;
        var xhr=new XMLHttpRequest(); xhr.open('POST', API('api.plugins.remove')+'&key='+encodeURIComponent(key), true);
        xhr.onreadystatechange=function(){ if(xhr.readyState===4) refresh(); };
        var _data = null; try { if (typeof _data === 'string' && _data.indexOf('csrf=') === -1) { _data += (_data ? '&' : '') + 'csrf=' + encodeURIComponent(CSRF); } } catch(e) {}
    xhr.send(_data);
      }
    });
  }

  refresh();
})();
</script>
