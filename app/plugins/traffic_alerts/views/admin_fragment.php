<?php
declare(strict_types=1);

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
?>
<style>
  .ta-admin {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    background: color-mix(in srgb, var(--panel, var(--card, #fff)) 92%, transparent);
  }
  .ta-admin .ta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 10px 12px;
    margin: 10px 0 12px;
  }
  .ta-admin .ta-grid label {
    display: block;
    font-size: 13px;
  }
  .ta-admin input[type="text"],
  .ta-admin input[type="number"] {
    width: 100%;
    box-sizing: border-box;
  }
  .ta-admin .ta-section + .ta-section {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
  }
  .ta-admin .ta-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    margin-top: 12px;
  }
  .ta-admin .ta-actions .button,
  .ta-admin .ta-actions button {
    width: auto;
    flex: 0 0 auto;
  }
  .ta-admin .ta-note,
  .ta-admin .ta-flash {
    font-size: 13px;
  }
  .ta-admin .ta-flash {
    min-height: 18px;
    margin-top: 10px;
  }
  .ta-admin table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
  }
  .ta-admin th,
  .ta-admin td {
    text-align: left;
    padding: 6px 8px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    vertical-align: top;
  }
</style>
<div class="ta-admin" data-traffic-alerts-admin>
  <form data-traffic-alerts-form action="<?= $h(
      $this->service->routeUrl('traffic_alerts.save')
  ) ?>" data-reset-url="<?= $h(
    $this->service->routeUrl('traffic_alerts.reset')
) ?>" data-rebuild-url="<?= $h(
    $this->service->routeUrl('traffic_alerts.rebuild')
) ?>">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="ta-note">
      Alerts are checked opportunistically from admin and dashboard activity. There is no always-running daemon.
    </div>

    <section class="ta-section">
      <h5>Channels</h5>
      <div class="ta-grid">
        <label><input type="checkbox" name="enabled" value="1" <?= !empty(
            $config['enabled']
        )
            ? 'checked'
            : '' ?>> Enable traffic alerts</label>
        <label><input type="checkbox" name="dashboard_notice" value="1" <?= !empty(
            $config['dashboard_notice']
        )
            ? 'checked'
            : '' ?>> Dashboard notice</label>
        <label><input type="checkbox" name="email_enabled" value="1" <?= !empty(
            $config['email_enabled']
        )
            ? 'checked'
            : '' ?>> Email alerts</label>
        <label>Email recipient
          <input type="text" name="email_recipient" value="<?= $h(
              $config['email_recipient'] ?? ''
          ) ?>" placeholder="admin@example.com">
        </label>
        <label><input type="checkbox" name="webhook_enabled" value="1" <?= !empty(
            $config['webhook_enabled']
        )
            ? 'checked'
            : '' ?>> Webhook alerts</label>
        <label>Webhook URL
          <input type="text" name="webhook_url" value="<?= $h(
              $config['webhook_url'] ?? ''
          ) ?>" placeholder="https://example.com/webhook">
        </label>
        <label><input type="checkbox" name="webhook_include_detail" value="1" <?= !empty(
            $config['webhook_include_detail']
        )
            ? 'checked'
            : '' ?>> Include more detail in webhook</label>
      </div>
      <div class="ta-note">
        Channel availability:
        Dashboard <?= $channels['dashboard'] ? 'enabled' : 'off' ?>,
        Email <?= $channels['email'] ? 'ready' : 'unavailable/off' ?>,
        Webhook <?= $channels['webhook'] ? 'ready' : 'unavailable/off' ?>.
      </div>
    </section>

    <section class="ta-section">
      <h5>Thresholds</h5>
      <div class="ta-grid">
        <label>Traffic spike threshold %
          <input type="number" name="spike_threshold_percent" min="110" max="1000" value="<?= $h(
              $config['spike_threshold_percent'] ?? 200
          ) ?>">
        </label>
        <label>Traffic drop threshold %
          <input type="number" name="drop_threshold_percent" min="10" max="95" value="<?= $h(
              $config['drop_threshold_percent'] ?? 60
          ) ?>">
        </label>
        <label><input type="checkbox" name="new_country_alert" value="1" <?= !empty(
            $config['new_country_alert']
        )
            ? 'checked'
            : '' ?>> New country alert</label>
        <label>Same source threshold
          <input type="number" name="same_source_threshold" min="5" max="10000" value="<?= $h(
              $config['same_source_threshold'] ?? 40
          ) ?>">
        </label>
        <label>Bot-like spike threshold
          <input type="number" name="bot_like_threshold" min="10" max="10000" value="<?= $h(
              $config['bot_like_threshold'] ?? 60
          ) ?>">
        </label>
        <label>Path probing threshold
          <input type="number" name="probing_threshold" min="5" max="10000" value="<?= $h(
              $config['probing_threshold'] ?? 25
          ) ?>">
        </label>
        <label>Cooldown minutes
          <input type="number" name="cooldown_minutes" min="1" max="10080" value="<?= $h(
              $config['cooldown_minutes'] ?? 60
          ) ?>">
        </label>
        <label>Check interval minutes
          <input type="number" name="check_interval_minutes" min="1" max="1440" value="<?= $h(
              $config['check_interval_minutes'] ?? 5
          ) ?>">
        </label>
      </div>
    </section>

    <section class="ta-section">
      <h5>Quiet Hours</h5>
      <div class="ta-grid">
        <label><input type="checkbox" name="quiet_hours_enabled" value="1" <?= !empty(
            $config['quiet_hours_enabled']
        )
            ? 'checked'
            : '' ?>> Enable quiet hours</label>
        <label>Quiet start
          <input type="text" name="quiet_hours_start" value="<?= $h(
              $config['quiet_hours_start'] ?? '23:00'
          ) ?>" placeholder="23:00">
        </label>
        <label>Quiet end
          <input type="text" name="quiet_hours_end" value="<?= $h(
              $config['quiet_hours_end'] ?? '07:00'
          ) ?>" placeholder="07:00">
        </label>
      </div>
    </section>

    <section class="ta-section">
      <h5>Latest Check</h5>
      <div class="ta-note">
        Last check:
        <?= !empty($state['last_check_ts'])
            ? $h(date('Y-m-d H:i:s', (int) $state['last_check_ts']))
            : 'Never' ?>.
        Last run result: <?= $h($result['reason'] ?? 'unknown') ?>.
        <?php if (!empty($state['last_delivery_error'])): ?>
          Last delivery error: <?= $h((string) $state['last_delivery_error']) ?>.
        <?php endif; ?>
      </div>
      <table>
        <thead><tr><th>When</th><th>Type</th><th>Severity</th><th>Summary</th></tr></thead>
        <tbody>
          <?php if ($alerts): ?>
            <?php foreach ($alerts as $alert): ?>
              <tr>
                <td><?= $h(date('Y-m-d H:i:s', (int) ($alert['ts'] ?? 0))) ?></td>
                <td><?= $h((string) ($alert['type'] ?? '')) ?></td>
                <td><?= $h((string) ($alert['severity'] ?? '')) ?></td>
                <td><?= $h((string) ($alert['summary'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="4">No alerts logged yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <div class="ta-actions">
      <button type="submit" class="button btn disable">Save Traffic Alerts Settings</button>
      <button type="button" class="button" data-traffic-alerts-rebuild>Run Checks Now</button>
      <button type="button" class="button" data-traffic-alerts-reset>Reset To Defaults</button>
    </div>
    <div class="ta-flash" data-traffic-alerts-flash></div>
  </form>
</div>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.hasAttribute('data-traffic-alerts-admin')) {
    root = document.querySelector('[data-traffic-alerts-admin]:last-of-type');
  }
  if (!root) return;

  var form = root.querySelector('[data-traffic-alerts-form]');
  var flash = root.querySelector('[data-traffic-alerts-flash]');
  var resetBtn = root.querySelector('[data-traffic-alerts-reset]');
  var rebuildBtn = root.querySelector('[data-traffic-alerts-rebuild]');

  function setFlash(msg, isError){
    if (!flash) return;
    flash.textContent = msg || '';
    flash.style.color = isError ? '#c2410c' : 'var(--text)';
  }

  function stopDragPropagation(el){
    if (!el) return;
    ['mousedown', 'pointerdown', 'dragstart', 'touchstart'].forEach(function(type){
      el.addEventListener(type, function(e){ e.stopPropagation(); });
    });
  }

  function encodeFormData(formEl){
    var pairs = [];
    var els = formEl.querySelectorAll('[name]');
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      if (!el.name) continue;
      if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) continue;
      pairs.push(encodeURIComponent(el.name) + '=' + encodeURIComponent(el.type === 'checkbox' ? '1' : el.value));
    }
    return pairs.join('&');
  }

  if (form) {
    stopDragPropagation(form);
    form.addEventListener('submit', function(e){
      e.preventDefault();
      setFlash('Saving...', false);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.getAttribute('action') || '', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function(){
        if (xhr.readyState !== 4) return;
        var data = null;
        try { data = JSON.parse(xhr.responseText || '{}'); } catch (err) {}
        if (xhr.status !== 200 || !data || data.ok !== true) {
          setFlash((data && data.error) ? data.error : 'Save failed.', true);
          return;
        }
        setFlash('Settings saved.', false);
      };
      xhr.send(encodeFormData(form));
    });
  }

  if (resetBtn && form) {
    stopDragPropagation(resetBtn);
    resetBtn.addEventListener('click', function(){
      if (!confirm('Reset Traffic Alerts to defaults?')) return;
      setFlash('Resetting...', false);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.getAttribute('data-reset-url') || '', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function(){
        if (xhr.readyState !== 4) return;
        var data = null;
        try { data = JSON.parse(xhr.responseText || '{}'); } catch (err) {}
        if (xhr.status !== 200 || !data || data.ok !== true) {
          setFlash('Reset failed.', true);
          return;
        }
        window.location.reload();
      };
      xhr.send('csrf=' + encodeURIComponent(form.querySelector('[name="csrf"]').value || ''));
    });
  }

  if (rebuildBtn && form) {
    stopDragPropagation(rebuildBtn);
    rebuildBtn.addEventListener('click', function(){
      setFlash('Running checks...', false);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.getAttribute('data-rebuild-url') || '', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function(){
        if (xhr.readyState !== 4) return;
        var data = null;
        try { data = JSON.parse(xhr.responseText || '{}'); } catch (err) {}
        if (xhr.status !== 200 || !data || data.ok !== true) {
          setFlash('Run failed.', true);
          return;
        }
        window.location.reload();
      };
      xhr.send('csrf=' + encodeURIComponent(form.querySelector('[name="csrf"]').value || ''));
    });
  }
})();
</script>
