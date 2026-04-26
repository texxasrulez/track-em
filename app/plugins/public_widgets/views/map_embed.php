<?php
declare(strict_types=1);

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
header('Content-Type: text/html; charset=utf-8');
header('Referrer-Policy: no-referrer-when-downgrade');
header('X-Content-Type-Options: nosniff');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $h($ctx['title']) ?></title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="<?= $h($ctx['mapCssUrl']) ?>">
</head>
<body class="pw-map-shell">
  <div class="pw-map-wrap" style="height: <?= (int) $ctx['height'] ?>px">
    <div class="pw-map-header">
      <div>
        <h1><?= $h($ctx['title']) ?></h1>
      </div>
      <label class="pw-map-layer-picker">
        <span>View</span>
        <select id="pw-map-layer-select" aria-label="Map view">
          <?php foreach ($ctx['basemapOptions'] as $key => $label): ?>
            <option value="<?= $h($key) ?>" <?= $ctx['defaultTileLayer'] === $key
                ? 'selected'
                : '' ?>><?= $h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div id="pw-public-map" class="pw-map-canvas" aria-label="<?= $h(
        $ctx['title'],
    ) ?>"></div>
    <div class="pw-map-status" id="pw-map-status" hidden></div>
  </div>
  <script>
    window.PUBLIC_WIDGETS_MAP_DATA_URL = <?= json_encode(
        $ctx['mapDataUrl'],
    ) ?>;
    window.PUBLIC_WIDGETS_MAP_TITLE = <?= json_encode($ctx['title']) ?>;
    window.PUBLIC_WIDGETS_MAP_DEFAULT_LAYER = <?= json_encode(
        $ctx['defaultTileLayer'],
    ) ?>;
  </script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script defer src="<?= $h($ctx['mapJsUrl']) ?>"></script>
</body>
</html>
