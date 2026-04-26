<?php
declare(strict_types=1);

namespace TrackEm\Plugins\PluginHealth;

use TrackEm\Core\DB;
use TrackEm\Core\Security;

final class PluginHealthService
{
    private const CACHE_TTL = 300;

    private string $pluginId;
    private string $pluginDir;

    public function __construct(string $pluginId, string $pluginDir)
    {
        $this->pluginId = $pluginId;
        $this->pluginDir = rtrim($pluginDir, '/\\');
    }

    public function defaults(): array
    {
        $path = $this->pluginDir . '/config.json';
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    public function loadConfig(): array
    {
        return $this->mergeRecursive($this->defaults(), $this->loadSavedConfig());
    }

    public function saveFromRequest(array $src): array
    {
        $config = $this->sanitizeConfig($src);
        $path = $this->configPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents(
            $path,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX,
        );
        $this->clearCache();
        return $config;
    }

    public function resetConfig(): void
    {
        @unlink($this->configPath());
        $this->clearCache();
    }

    public function configPath(): string
    {
        return $this->storageDir() . '/config.json';
    }

    public function cachePath(): string
    {
        return $this->storageDir() . '/cache.json';
    }

    public function storageDir(): string
    {
        $dir = dirname(__DIR__, 3) . '/storage/plugins/' . $this->pluginId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public function csrfToken(): string
    {
        Security::startSecureSession();
        return Security::csrfToken();
    }

    public function routeUrl(string $route, array $params = []): string
    {
        $base = rtrim(
            str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')),
            '/',
        );
        if ($base === '/') {
            $base = '';
        }
        $params = ['p' => $route] + $params;
        return $base . '/index.php?' . http_build_query($params);
    }

    public function isPluginEnabled(): bool
    {
        try {
            $st = DB::pdo()->prepare(
                'SELECT enabled FROM plugins WHERE id = ? LIMIT 1',
            );
            $st->execute([$this->pluginId]);
            $row = $st->fetch();
            return $row ? (int) ($row['enabled'] ?? 0) === 1 : false;
        } catch (\Throwable) {
            return false;
        }
    }

    public function sanitizeConfig(array $src): array
    {
        return [
            'enabled' => $this->toBool($src['enabled'] ?? false),
            'strict_mode' => $this->toBool($src['strict_mode'] ?? false),
            'include_passes' => $this->toBool($src['include_passes'] ?? false),
            'scan_disabled_plugins' => $this->toBool($src['scan_disabled_plugins'] ?? false),
            'stale_cache_hours' => max(1, min(720, (int) ($src['stale_cache_hours'] ?? 24))),
        ];
    }

    public function report(array $config, bool $forceRefresh = false): array
    {
        if (!$this->isPluginEnabled() || empty($config['enabled'])) {
            return [
                'summary' => ['high' => 0, 'medium' => 0, 'low' => 0, 'pass' => 0],
                'findings' => [],
                'plugins' => [],
                'notes' => ['Plugin Health is disabled.'],
            ];
        }

        $cacheKey = sha1(
            json_encode(
                [
                    'config' => $config,
                    'plugins' => $this->sourceFingerprint(),
                ],
                JSON_UNESCAPED_SLASHES,
            ),
        );
        if (!$forceRefresh) {
            $cached = $this->loadCache($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $strict = !empty($config['strict_mode']);
        $includePasses = !empty($config['include_passes']);
        $scanDisabled = !empty($config['scan_disabled_plugins']);
        $staleHours = (int) ($config['stale_cache_hours'] ?? 24);

        $plugins = $this->installedPlugins();
        $findings = [];
        $pluginRows = [];

        foreach ($plugins as $plugin) {
            $enabled = !empty($plugin['enabled']);
            if (!$enabled && !$scanDisabled) {
                continue;
            }
            $pluginRows[] = [
                'id' => $plugin['id'],
                'name' => $plugin['name'],
                'enabled' => $enabled,
            ];
            $this->auditPluginStructure($findings, $plugin, $includePasses);
            $this->auditPluginStorage($findings, $plugin, $includePasses, $staleHours, $strict);
            $this->auditPluginState($findings, $plugin, $includePasses);
        }

        $this->auditDependencies($findings, $plugins, $includePasses);

        $summary = ['high' => 0, 'medium' => 0, 'low' => 0, 'pass' => 0];
        foreach ($findings as $finding) {
            $severity = (string) ($finding['severity'] ?? 'low');
            if (isset($summary[$severity])) {
                $summary[$severity]++;
            }
        }

        usort(
            $findings,
            static function (array $a, array $b): int {
                $rank = ['high' => 4, 'medium' => 3, 'low' => 2, 'pass' => 1];
                $ar = $rank[(string) ($a['severity'] ?? 'low')] ?? 2;
                $br = $rank[(string) ($b['severity'] ?? 'low')] ?? 2;
                if ($ar === $br) {
                    return strcmp((string) ($a['plugin'] ?? ''), (string) ($b['plugin'] ?? ''));
                }
                return $br <=> $ar;
            },
        );

        $payload = [
            'summary' => $summary,
            'findings' => $findings,
            'plugins' => $pluginRows,
            'notes' => [
                'Plugin Health checks manifests, storage paths, config/state file readability, simple dependency expectations, stale caches, and recent delivery errors.',
                'This plugin is heuristic and meant to catch obvious admin-side maintenance issues, not to replace full integration testing.',
            ],
        ];
        $this->saveCache($cacheKey, $payload);
        return $payload;
    }

    private function installedPlugins(): array
    {
        $base = dirname(__DIR__);
        $dirs = glob($base . '/*', GLOB_ONLYDIR) ?: [];
        $enabledMap = $this->enabledPluginMap();
        $out = [];
        foreach ($dirs as $dir) {
            $manifestPath = $dir . '/plugin.json';
            if (!is_file($manifestPath)) {
                continue;
            }
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            $id = is_array($manifest)
                ? strtolower((string) ($manifest['id'] ?? $manifest['key'] ?? basename($dir)))
                : strtolower(basename($dir));
            $out[] = [
                'id' => $id,
                'dir' => $dir,
                'name' => is_array($manifest) ? (string) ($manifest['name'] ?? $id) : $id,
                'manifest' => $manifestPath,
                'manifest_ok' => is_array($manifest) &&
                    (!empty($manifest['id']) || !empty($manifest['key'])) &&
                    !empty($manifest['name']),
                'has_config_schema' => is_array($manifest) && !empty($manifest['configSchema']) && is_array($manifest['configSchema']),
                'has_admin_route' => is_array($manifest) && !empty($manifest['admin_route']),
                'has_widget_asset' => is_file($dir . '/assets/widget.js'),
                'enabled' => !empty($enabledMap[$id]),
            ];
        }
        usort($out, static fn(array $a, array $b): int => strcmp($a['id'], $b['id']));
        return $out;
    }

    private function enabledPluginMap(): array
    {
        try {
            $rows = DB::pdo()->query('SELECT id, enabled FROM plugins')->fetchAll();
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $id = strtolower((string) ($row['id'] ?? ''));
            if ($id !== '') {
                $out[$id] = (int) ($row['enabled'] ?? 0) === 1;
            }
        }
        return $out;
    }

    private function auditPluginStructure(array &$findings, array $plugin, bool $includePasses): void
    {
        $pluginId = (string) ($plugin['id'] ?? '');
        if (empty($plugin['manifest_ok'])) {
            $findings[] = $this->finding(
                $pluginId,
                'high',
                'Plugin manifest is missing required fields.',
                'Ensure `plugin.json` contains at least `id` and `name`.',
            );
        } elseif ($includePasses) {
            $findings[] = $this->finding(
                $pluginId,
                'pass',
                'Plugin manifest is readable.',
                'Manifest fields look valid.',
            );
        }

        $controller = (string) ($plugin['dir'] ?? '') . '/PluginController.php';
        $hasController = is_file($controller);
        $hasConfigSchema = !empty($plugin['has_config_schema']);
        $hasAdminRoute = !empty($plugin['has_admin_route']);
        $hasWidgetAsset = !empty($plugin['has_widget_asset']);
        if (!$hasController && ($hasAdminRoute || (!$hasConfigSchema && !$hasWidgetAsset))) {
            $findings[] = $this->finding(
                $pluginId,
                'medium',
                'Plugin controller file is missing.',
                'Add `PluginController.php` if the plugin is meant to expose routes or admin UI.',
            );
        } elseif ($includePasses && $hasController) {
            $findings[] = $this->finding(
                $pluginId,
                'pass',
                'Plugin controller file exists.',
                'Route handling file is present.',
            );
        } elseif ($includePasses && $hasConfigSchema) {
            $findings[] = $this->finding(
                $pluginId,
                'pass',
                'Plugin uses schema-only configuration.',
                'This plugin does not require a controller because it exposes settings through `configSchema`.',
            );
        } elseif ($includePasses && $hasWidgetAsset && !$hasAdminRoute) {
            $findings[] = $this->finding(
                $pluginId,
                'pass',
                'Plugin is a widget-only plugin.',
                'This plugin exposes a dashboard/widget asset and does not require a controller.',
            );
        }
    }

    private function auditPluginStorage(
        array &$findings,
        array $plugin,
        bool $includePasses,
        int $staleHours,
        bool $strict
    ): void {
        $pluginId = (string) ($plugin['id'] ?? '');
        $storageDir = dirname(__DIR__, 3) . '/storage/plugins/' . $pluginId;

        if (!is_dir($storageDir)) {
            if (!@mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
                $findings[] = $this->finding(
                    $pluginId,
                    'high',
                    'Plugin storage directory is missing and could not be created.',
                    'Ensure `storage/plugins/' . $pluginId . '` is writable by the app.',
                );
            } elseif ($includePasses) {
                $findings[] = $this->finding(
                    $pluginId,
                    'pass',
                    'Plugin storage directory is available.',
                    'Storage path exists or was created successfully.',
                );
            }
        } elseif (!is_writable($storageDir)) {
            $findings[] = $this->finding(
                $pluginId,
                'high',
                'Plugin storage directory is not writable.',
                'Fix filesystem permissions for `storage/plugins/' . $pluginId . '`.',
            );
        } elseif ($includePasses) {
            $findings[] = $this->finding(
                $pluginId,
                'pass',
                'Plugin storage directory is writable.',
                'Filesystem permissions look correct.',
            );
        }

        $configPath = $storageDir . '/config.json';
        if (is_file($configPath)) {
            $this->auditJsonFile($findings, $pluginId, $configPath, 'Saved config JSON', $includePasses);
        }

        foreach (['cache.json', 'state.json', 'rollups.json'] as $name) {
            $path = $storageDir . '/' . $name;
            if (!is_file($path)) {
                continue;
            }
            $this->auditJsonFile($findings, $pluginId, $path, $name, $includePasses);
            if ($name === 'cache.json') {
                $ageHours = (time() - ((int) @filemtime($path))) / 3600;
                $limit = $strict ? max(1, $staleHours / 2) : $staleHours;
                if ($ageHours > $limit) {
                    $findings[] = $this->finding(
                        $pluginId,
                        'low',
                        'Plugin cache appears stale.',
                        'Refresh or rebuild the plugin cache if this data should be current.',
                    );
                } elseif ($includePasses) {
                    $findings[] = $this->finding(
                        $pluginId,
                        'pass',
                        'Plugin cache age is within the current threshold.',
                        'Recent cache activity was detected.',
                    );
                }
            }
        }
    }

    private function auditJsonFile(array &$findings, string $pluginId, string $path, string $label, bool $includePasses): void
    {
        $raw = @file_get_contents($path);
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        if ($raw === false || (trim((string) $raw) !== '' && json_last_error() !== JSON_ERROR_NONE)) {
            $findings[] = $this->finding(
                $pluginId,
                'medium',
                $label . ' is not valid JSON.',
                'Rewrite or reset `' . basename($path) . '` so the plugin can read it safely.',
            );
            return;
        }
        if ($includePasses) {
            $findings[] = $this->finding(
                $pluginId,
                'pass',
                $label . ' is readable.',
                'JSON structure looks valid.',
            );
        }
    }

    private function auditPluginState(array &$findings, array $plugin, bool $includePasses): void
    {
        $pluginId = (string) ($plugin['id'] ?? '');
        $storageDir = dirname(__DIR__, 3) . '/storage/plugins/' . $pluginId;

        if ($pluginId === 'traffic_alerts') {
            $state = $this->loadJsonFile($storageDir . '/state.json');
            $error = (string) ($state['last_delivery_error'] ?? '');
            if ($error !== '') {
                $findings[] = $this->finding(
                    $pluginId,
                    'medium',
                    'Traffic Alerts recorded a recent delivery error.',
                    'Review the configured email/webhook channel and retry delivery.',
                );
            } elseif ($includePasses && is_array($state)) {
                $findings[] = $this->finding(
                    $pluginId,
                    'pass',
                    'Traffic Alerts has no recent delivery error.',
                    'Recent state does not show channel failures.',
                );
            }
        }

        if ($pluginId === 'static_reports') {
            $state = $this->loadJsonFile($storageDir . '/state.json');
            $error = (string) ($state['last_email_error'] ?? '');
            if ($error !== '') {
                $findings[] = $this->finding(
                    $pluginId,
                    'medium',
                    'Static Reports recorded a recent email delivery error.',
                    'Check the configured recipient and the server mail setup.',
                );
            } elseif ($includePasses && is_array($state) && !empty($state['last_email_ts'])) {
                $findings[] = $this->finding(
                    $pluginId,
                    'pass',
                    'Static Reports email delivery does not show a recent error.',
                    'Last email attempt completed without a stored failure code.',
                );
            }
        }

        if ($pluginId === 'scheduler') {
            $state = $this->loadJsonFile($storageDir . '/state.json');
            $error = (string) ($state['last_error'] ?? '');
            if ($error !== '') {
                $findings[] = $this->finding(
                    $pluginId,
                    'medium',
                    'Scheduler recorded a recent job error.',
                    'Review the scheduler run log and the affected job configuration.',
                );
            } elseif ($includePasses && is_array($state)) {
                $findings[] = $this->finding(
                    $pluginId,
                    'pass',
                    'Scheduler has no recent recorded job error.',
                    'State does not show a current scheduler fault.',
                );
            }
        }
    }

    private function auditDependencies(array &$findings, array $plugins, bool $includePasses): void
    {
        $byId = [];
        foreach ($plugins as $plugin) {
            $byId[(string) $plugin['id']] = $plugin;
        }

        $eventInstalled = isset($byId['event_tracking']);

        $goalsConfig = $this->loadJsonFile(dirname(__DIR__, 3) . '/storage/plugins/goals/config.json');
        if (is_array($goalsConfig)) {
            $hasEventGoal = false;
            foreach ((array) ($goalsConfig['goals'] ?? []) as $goal) {
                if (is_array($goal) && (($goal['type'] ?? '') === 'event_name')) {
                    $hasEventGoal = true;
                    break;
                }
            }
            if ($hasEventGoal && !$eventInstalled) {
                $findings[] = $this->finding(
                    'goals',
                    'medium',
                    'Goals is configured with event-based goals but event_tracking is missing.',
                    'Install or re-enable `event_tracking`, or remove event-based goal definitions.',
                );
            } elseif ($hasEventGoal && $includePasses) {
                $findings[] = $this->finding(
                    'goals',
                    'pass',
                    'Goals event dependency is satisfied.',
                    'Event-based goals have the required plugin available.',
                );
            }
        }

        $funnelConfig = $this->loadJsonFile(dirname(__DIR__, 3) . '/storage/plugins/funnel_reports/config.json');
        if (is_array($funnelConfig)) {
            $needsEvents = false;
            foreach ((array) ($funnelConfig['funnels'] ?? []) as $funnel) {
                foreach ((array) ($funnel['steps'] ?? []) as $step) {
                    if (is_array($step) && (($step['type'] ?? '') === 'event_name')) {
                        $needsEvents = true;
                        break 2;
                    }
                }
            }
            if ($needsEvents && !$eventInstalled) {
                $findings[] = $this->finding(
                    'funnel_reports',
                    'medium',
                    'Funnel Reports is configured with event steps but event_tracking is missing.',
                    'Install or re-enable `event_tracking`, or remove event-based funnel steps.',
                );
            } elseif ($needsEvents && $includePasses) {
                $findings[] = $this->finding(
                    'funnel_reports',
                    'pass',
                    'Funnel Reports event dependency is satisfied.',
                    'Event-based funnel steps have the required plugin available.',
                );
            }
        }

        $schedulerConfig = $this->loadJsonFile(dirname(__DIR__, 3) . '/storage/plugins/scheduler/config.json');
        if (is_array($schedulerConfig) && isset($byId['scheduler'])) {
            $availableJobs = array_keys($this->schedulerAvailableJobs());
            foreach ((array) ($schedulerConfig['jobs'] ?? []) as $job) {
                if (!is_array($job)) {
                    continue;
                }
                $jobId = (string) ($job['job_id'] ?? '');
                if ($jobId !== '' && !in_array($jobId, $availableJobs, true)) {
                    $findings[] = $this->finding(
                        'scheduler',
                        'low',
                        'Scheduler contains an unknown job id.',
                        'Remove or replace unsupported scheduler job `' . $jobId . '`.',
                    );
                }
            }
        }
    }

    private function schedulerAvailableJobs(): array
    {
        $path = dirname(__DIR__) . '/scheduler/SchedulerService.php';
        if (!is_file($path)) {
            return [];
        }
        require_once $path;
        $service = new \TrackEm\Plugins\Scheduler\SchedulerService(
            'scheduler',
            dirname(__DIR__) . '/scheduler',
        );
        return $service->availableJobs();
    }

    private function loadJsonFile(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    private function finding(string $plugin, string $severity, string $title, string $recommendation): array
    {
        return [
            'plugin' => $plugin,
            'severity' => $severity,
            'title' => $title,
            'recommendation' => $recommendation,
        ];
    }

    private function sourceFingerprint(): array
    {
        $base = dirname(__DIR__);
        $files = glob($base . '/*/plugin.json') ?: [];
        $maxTs = 0;
        foreach ($files as $file) {
            $ts = (int) @filemtime($file);
            if ($ts > $maxTs) {
                $maxTs = $ts;
            }
        }
        return [
            'count' => count($files),
            'max_ts' => $maxTs,
        ];
    }

    private function loadCache(string $key): ?array
    {
        $path = $this->cachePath();
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            return null;
        }
        if ((string) ($data['key'] ?? '') !== $key) {
            return null;
        }
        if ((time() - (int) ($data['ts'] ?? 0)) > self::CACHE_TTL) {
            return null;
        }
        return is_array($data['payload'] ?? null) ? $data['payload'] : null;
    }

    private function saveCache(string $key, array $payload): void
    {
        @file_put_contents(
            $this->cachePath(),
            json_encode(
                ['key' => $key, 'ts' => time(), 'payload' => $payload],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ),
            LOCK_EX,
        );
    }

    private function clearCache(): void
    {
        @unlink($this->cachePath());
    }

    private function loadSavedConfig(): array
    {
        $path = $this->configPath();
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private function mergeRecursive(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (
                isset($base[$key]) &&
                is_array($base[$key]) &&
                is_array($value) &&
                !$this->isListArray($base[$key]) &&
                !$this->isListArray($value)
            ) {
                $base[$key] = $this->mergeRecursive($base[$key], $value);
                continue;
            }
            $base[$key] = $value;
        }
        return $base;
    }

    private function isListArray(array $value): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }
        return array_keys($value) === range(0, count($value) - 1);
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(
            strtolower(trim((string) $value)),
            ['1', 'true', 'on', 'yes'],
            true,
        );
    }
}
