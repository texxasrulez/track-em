<?php
declare(strict_types=1);

namespace TrackEm\Controllers\_Addons;

/**
 * CLEAN trait with a single closing brace, no duplicate methods.
 * Includes: api_plugins_list, api_plugins_install (stub), api_plugins_toggle,
 * api_plugins_remove, api_plugins_config_set, api_plugins_asset, api_plugins_configs.
 * Also includes helpers for enabled state and config merging.
 */
trait ApiPluginsAddon
{
    // -------------- Public API endpoints --------------

    // GET ?p=api.plugins.list
    public function api_plugins_list(): void
    {
        $dir = $this->__pluginsDir();
        $items = [];
        foreach ((glob($dir . '/*/plugin.json') ?: []) as $mf) {
            $key  = basename(dirname($mf));
            $meta = json_decode((string)@file_get_contents($mf), true) ?: [];
            $saved = $this->__getPluginConfig($key);
            $self  = $this->__getPluginSelfConfig($key); // plugin-local config.json (consent_banner etc.)
            if (is_array($self) && $self) { $saved = array_merge($saved, $self); }

            $items[] = [
                'key'     => $key,
                'meta'    => $meta,
                'enabled' => $this->__enabled($key),
                'config'  => $saved,
            ];
        }
        $this->__respond(200, ['items' => $items]);
    }

    // POST ?p=api.plugins.install (stub to satisfy aliases)
    public function api_plugins_install(): void
    {
        $this->__respond(400, ['ok'=>false,'error'=>'install not supported']);
    }

    // POST ?p=api.plugins.toggle&key=<k>&enabled=1|true|on|yes|0|false|off|no
    public function api_plugins_toggle(): void
    {
        $key = isset($_REQUEST['key']) ? (string)$_REQUEST['key'] : '';
        $raw = $_REQUEST['enabled'] ?? '';
        $s = strtolower(trim((string)$raw));
        $enabled = in_array($s, ['1','true','on','yes'], true) || $raw === 1 || $raw === true;

        if ($key === '') { $this->__respond(400, ['ok'=>false,'error'=>'missing key']); return; }
        $this->__setEnabled($key, $enabled);
        $this->__respond(200, ['ok'=>true, 'enabled'=>$enabled]);
    }

    // POST ?p=api.plugins.remove&key=<k>
    public function api_plugins_remove(): void
    {
        $key = isset($_REQUEST['key']) ? (string)$_REQUEST['key'] : '';
        if ($key === '') { $this->__respond(400, ['ok'=>false,'error'=>'missing key']); return; }
        // Remove global/plugin-local configs, leave code intact
        @unlink($this->__pluginConfigPath($key));
        $self = $this->__pluginSelfConfigPath($key);
        if ($self !== '' && is_file($self)) { @unlink($self); }
        $this->__respond(200, ['ok'=>true]);
    }

    // POST ?p=api.plugins.config.set  (key, config=JSON, optional csrf at top or inside JSON)
    public function api_plugins_config_set(): void
    {
        $key = isset($_POST['key']) ? (string)$_POST['key'] : '';
        $json = isset($_POST['config']) ? (string)$_POST['config'] : '';

        if ($key === '') { $this->__respond(400, ['ok'=>false,'error'=>'missing key']); return; }
        $cfg = json_decode($json, true);
        if (!is_array($cfg)) { $this->__respond(400, ['ok'=>false,'error'=>'invalid config']); return; }

        $csrf = $_POST['csrf'] ?? ($cfg['csrf'] ?? '');
        if (isset($cfg['csrf'])) unset($cfg['csrf']);
        if (class_exists('\\TrackEm\\Core\\Security') && method_exists('\\TrackEm\\Core\\Security', 'verifyCsrf')) {
            if (!\TrackEm\Core\Security::verifyCsrf($csrf)) {
                $this->__respond(400, ['ok'=>false,'error'=>'bad csrf']); return;
            }
        }

        // Normalize basic types
        foreach ($cfg as $k=>$v) {
            if ($v === 'true') $cfg[$k] = true;
            elseif ($v === 'false') $cfg[$k] = false;
            elseif ($v === '1') $cfg[$k] = 1;
            elseif ($v === '0') $cfg[$k] = 0;
        }

        // Save to global plugin config
        $this->__setPluginConfig($key, $cfg);
        // Save to plugin-local config if present
        $selfPath = $this->__pluginSelfConfigPath($key);
        if ($selfPath !== '') {
            $dir = dirname($selfPath);
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            @file_put_contents($selfPath, json_encode($cfg, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        }

        $this->__respond(200, ['ok'=>true]);
    }

    // GET ?p=api.plugins.asset&key=<k>&file=assets/widget.js
    public function api_plugins_asset(): void
    {
        $key = isset($_GET['key']) ? (string)$_GET['key'] : '';
        $file = isset($_GET['file']) ? (string)$_GET['file'] : '';
        if ($key === '' || $file === '') { http_response_code(400); echo 'missing'; return; }

        $safeKey = preg_replace('/[^a-z0-9_\-\.]/i','_', $key);
        $safeFile = preg_replace('/[^a-z0-9_\-\.\/]/i','_', $file);
        $base = rtrim($this->__pluginsDir(), '/\\') . '/' . $safeKey . '/';
        $baseReal = realpath($base);
        $path = $baseReal ? realpath($baseReal . '/' . $safeFile) : false;

        if ($path === false || strpos($path, $baseReal) !== 0 || !is_file($path)) { http_response_code(404); echo 'not found'; return; }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $ct = 'application/octet-stream';
        if ($ext === 'js') $ct = 'application/javascript';
        elseif ($ext === 'css') $ct = 'text/css';
        elseif ($ext === 'json') $ct = 'application/json';

        header('Content-Type: ' . $ct);
        header('Cache-Control: no-store');
        readfile($path);
    }

    // GET ?p=api.plugins.configs  -> { configs: { <key>: {...} } }
    public function api_plugins_configs(): void
    {
        $dir = $this->__pluginsDir();
        $out = [];
        foreach ((glob($dir . '/*/plugin.json') ?: []) as $mf) {
            $key  = basename(dirname($mf));
            $g = $this->__getPluginConfig($key);
            $s = $this->__getPluginSelfConfig($key);
            if (is_array($s) && $s) { $g = array_merge($g, $s); }
            $out[$key] = $g;
        }
        $this->__respond(200, ['configs' => $out]);
    }

    // -------------- Private helpers --------------

    private function __respond(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    private function __pluginsDir(): string
    {
        $d = realpath(__DIR__ . '/../../plugins');
        if ($d === false) { $d = __DIR__ . '/../../plugins'; }
        return $d;
    }

    private function __configDir(): string
    {
        $d = realpath(__DIR__ . '/../../config/plugins');
        if ($d === false) { $d = __DIR__ . '/../../config/plugins'; }
        if (!is_dir($d)) { @mkdir($d, 0775, true); }
        return $d;
    }

    private function __pluginConfigPath(string $key): string
    {
        $safe = preg_replace('/[^a-z0-9_\-\.]/i','_', $key);
        return rtrim($this->__configDir(), '/\\') . '/' . $safe . '.json';
    }

    private function __getPluginConfig(string $key): array
    {
        $p = $this->__pluginConfigPath($key);
        if (!is_file($p)) return [];
        $j = @file_get_contents($p);
        $cfg = json_decode((string)$j, true);
        return is_array($cfg) ? $cfg : [];
    }

    private function __pluginSelfConfigPath(string $key): string
    {
        $plugDir = rtrim($this->__pluginsDir(), '/\\') . '/' . preg_replace('/[^a-z0-9_\-\.]/i','_', $key);
        $p = $plugDir . '/config.json';
        return is_dir($plugDir) ? $p : '';
    }

    private function __getPluginSelfConfig(string $key): array
    {
        $p = $this->__pluginSelfConfigPath($key);
        if ($p === '' || !is_file($p)) return [];
        $j = @file_get_contents($p);
        $cfg = json_decode((string)$j, true);
        return is_array($cfg) ? $cfg : [];
    }

    private function __statePath(): string
    {
        return rtrim($this->__configDir(), '/\\') . '/__global.json';
    }

    private function __stateLoad(): array
    {
        $p = $this->__statePath();
        if (!is_file($p)) return ['enabled'=>[]];
        $j = @file_get_contents($p);
        $st = json_decode((string)$j, true);
        return is_array($st) ? $st : ['enabled'=>[]];
    }

    private function __stateSave(array $st): void
    {
        file_put_contents($this->__statePath(), json_encode($st, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    }

    private function __enabled(string $key): bool
    {
        $st = $this->__stateLoad();
        return !empty($st['enabled'][$key]);
    }

    private function __setEnabled(string $key, bool $on): void
    {
        $st = $this->__stateLoad();
        $st['enabled'][$key] = $on;
        $this->__stateSave($st);
    }
}
