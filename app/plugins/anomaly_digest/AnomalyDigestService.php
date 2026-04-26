<?php
declare(strict_types=1);

namespace TrackEm\Plugins\AnomalyDigest;

use TrackEm\Core\DB;
use TrackEm\Core\Security;

final class AnomalyDigestService
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
            'include_traffic' => $this->toBool($src['include_traffic'] ?? false),
            'include_alerts' => $this->toBool($src['include_alerts'] ?? false),
            'include_bot_watch' => $this->toBool($src['include_bot_watch'] ?? false),
            'include_goals' => $this->toBool($src['include_goals'] ?? false),
            'include_referrers' => $this->toBool($src['include_referrers'] ?? false),
            'max_items' => max(3, min(25, (int) ($src['max_items'] ?? 10))),
        ];
    }

    public function report(array $config, bool $forceRefresh = false): array
    {
        if (!$this->isPluginEnabled() || empty($config['enabled'])) {
            return [
                'summary' => [
                    'high' => 0,
                    'medium' => 0,
                    'low' => 0,
                ],
                'items' => [],
                'notes' => ['Anomaly Digest is disabled.'],
            ];
        }

        $cacheKey = sha1(
            json_encode(
                [
                    'config' => $config,
                    'source' => $this->sourceFingerprint(),
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

        $items = [];
        if (!empty($config['include_traffic'])) {
            $items = array_merge($items, $this->trafficItems());
        }
        if (!empty($config['include_alerts'])) {
            $items = array_merge($items, $this->trafficAlertItems());
        }
        if (!empty($config['include_bot_watch'])) {
            $items = array_merge($items, $this->botItems());
        }
        if (!empty($config['include_goals'])) {
            $items = array_merge($items, $this->goalItems());
        }
        if (!empty($config['include_referrers'])) {
            $items = array_merge($items, $this->referrerItems());
        }

        usort(
            $items,
            static function (array $a, array $b): int {
                $rank = ['high' => 3, 'medium' => 2, 'low' => 1];
                $ar = $rank[(string) ($a['severity'] ?? 'low')] ?? 1;
                $br = $rank[(string) ($b['severity'] ?? 'low')] ?? 1;
                if ($ar === $br) {
                    return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
                }
                return $br <=> $ar;
            },
        );
        $items = array_slice($items, 0, (int) ($config['max_items'] ?? 10));

        $summary = ['high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($items as $item) {
            $severity = (string) ($item['severity'] ?? 'low');
            if (isset($summary[$severity])) {
                $summary[$severity]++;
            }
        }

        $payload = [
            'summary' => $summary,
            'items' => $items,
            'notes' => [
                'This digest is a lightweight summary, not a realtime monitor.',
                'Items are derived from existing reports, alerts, and a few cheap visit comparisons.',
            ],
        ];
        $this->saveCache($cacheKey, $payload);
        return $payload;
    }

    private function trafficItems(): array
    {
        $now = time();
        $todayStart = strtotime(gmdate('Y-m-d 00:00:00')) ?: $now;
        $today = $this->countVisits($todayStart, $now);
        $yesterday = $this->countVisits($todayStart - 86400, $todayStart);
        $last7 = $this->countVisits($now - 7 * 86400, $now);
        $prev7 = $this->countVisits($now - 14 * 86400, $now - 7 * 86400);

        $items = [];
        if ($yesterday > 0) {
            $delta = $this->percentDelta($today, $yesterday);
            if (abs($delta) >= 35) {
                $items[] = [
                    'source' => 'traffic',
                    'severity' => abs($delta) >= 60 ? 'high' : 'medium',
                    'title' => $delta > 0 ? 'Today traffic is up sharply' : 'Today traffic is down sharply',
                    'detail' => 'Today: ' . $today . ', yesterday same window: ' . $yesterday . ', change: ' . $delta . '%.',
                ];
            }
        }
        if ($prev7 > 0) {
            $delta = $this->percentDelta($last7, $prev7);
            if (abs($delta) >= 20) {
                $items[] = [
                    'source' => 'traffic',
                    'severity' => abs($delta) >= 40 ? 'high' : 'low',
                    'title' => $delta > 0 ? 'Last 7 days are trending up' : 'Last 7 days are trending down',
                    'detail' => 'Last 7 days: ' . $last7 . ', previous 7 days: ' . $prev7 . ', change: ' . $delta . '%.',
                ];
            }
        }
        return $items;
    }

    private function trafficAlertItems(): array
    {
        $service = $this->loadPluginService('traffic_alerts', 'TrafficAlertsService');
        if ($service === null || !method_exists($service, 'loadConfig') || !method_exists($service, 'recentAlerts')) {
            return [];
        }
        $config = $service->loadConfig();
        if (empty($config['enabled'])) {
            return [];
        }
        $alerts = $service->recentAlerts(5);
        $items = [];
        foreach ($alerts as $alert) {
            if (!is_array($alert)) {
                continue;
            }
            $items[] = [
                'source' => 'traffic_alerts',
                'severity' => $this->normalizeSeverity((string) ($alert['severity'] ?? 'medium')),
                'title' => (string) ($alert['title'] ?? 'Traffic alert'),
                'detail' => (string) ($alert['summary'] ?? ''),
            ];
        }
        return $items;
    }

    private function botItems(): array
    {
        $service = $this->loadPluginService('bot_watch', 'BotWatchService');
        if ($service === null || !method_exists($service, 'loadConfig') || !method_exists($service, 'report')) {
            return [];
        }
        $config = $service->loadConfig();
        $report = $service->report($config, false);
        $flagged = (int) ($report['summary']['sources_flagged'] ?? 0);
        if ($flagged <= 0) {
            return [];
        }
        $top = $report['suspicious_sources'][0] ?? null;
        $items = [[
            'source' => 'bot_watch',
            'severity' => $flagged >= 5 ? 'high' : 'medium',
            'title' => 'Bot Watch flagged suspicious sources',
            'detail' => 'Flagged sources: ' . $flagged . ($top ? ', top score: ' . (int) ($top['score'] ?? 0) : '') . '.',
        ]];
        return $items;
    }

    private function goalItems(): array
    {
        $service = $this->loadPluginService('goals', 'GoalsService');
        if ($service === null || !method_exists($service, 'loadConfig') || !method_exists($service, 'report')) {
            return [];
        }
        $config = $service->loadConfig();
        $config['report_range'] = '7d';
        $report = $service->report($config);
        $items = [];
        foreach (array_slice((array) ($report['goals'] ?? []), 0, 3) as $goal) {
            if (!is_array($goal) || empty($goal['active'])) {
                continue;
            }
            $completions = (int) ($goal['completions'] ?? 0);
            $rate = (float) ($goal['conversion_rate'] ?? 0);
            if ($completions <= 0) {
                continue;
            }
            $items[] = [
                'source' => 'goals',
                'severity' => $rate >= 10 ? 'medium' : 'low',
                'title' => 'Goal activity: ' . (string) ($goal['name'] ?? 'Goal'),
                'detail' => 'Completions: ' . $completions . ', conversion rate: ' . number_format($rate, 2) . '%.',
            ];
        }
        return $items;
    }

    private function referrerItems(): array
    {
        $service = $this->loadPluginService('referrer_intel', 'ReferrerIntelService');
        if ($service === null || !method_exists($service, 'loadConfig') || !method_exists($service, 'report')) {
            return [];
        }
        $config = $service->loadConfig();
        $config['report_range'] = '7d';
        $report = $service->report($config, false);
        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        $topExternal = $report['top_domains'][0] ?? null;
        $items = [];
        if (($summary['social'] ?? 0) > 0) {
            $items[] = [
                'source' => 'referrer_intel',
                'severity' => ($summary['social'] ?? 0) >= 25 ? 'medium' : 'low',
                'title' => 'Social traffic detected in the last 7 days',
                'detail' => 'Social visits: ' . (int) ($summary['social'] ?? 0) . '.',
            ];
        }
        if (is_array($topExternal)) {
            $items[] = [
                'source' => 'referrer_intel',
                'severity' => 'low',
                'title' => 'Top referring domain',
                'detail' => (string) ($topExternal['name'] ?? '[unknown]') . ' with ' . (int) ($topExternal['count'] ?? 0) . ' visits.',
            ];
        }
        return $items;
    }

    private function loadPluginService(string $pluginId, string $serviceClass): ?object
    {
        $file = dirname(__DIR__) . '/' . $pluginId . '/' . $serviceClass . '.php';
        if (!is_file($file)) {
            return null;
        }
        require_once $file;
        $parts = preg_split('/[_\-]+/', $pluginId) ?: [];
        $parts = array_map(
            static fn(string $part): string => ucfirst(strtolower($part)),
            array_filter($parts, static fn(string $part): bool => $part !== ''),
        );
        $class = 'TrackEm\\Plugins\\' . implode('', $parts) . '\\' . $serviceClass;
        if (!class_exists($class)) {
            return null;
        }
        return new $class($pluginId, dirname(__DIR__) . '/' . $pluginId);
    }

    private function countVisits(int $from, int $to): int
    {
        try {
            $st = DB::pdo()->prepare(
                'SELECT COUNT(*) FROM visits WHERE ts >= ? AND ts < ?',
            );
            $st->execute([$from, $to]);
            return (int) $st->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function percentDelta(int $current, int $previous): int
    {
        if ($previous <= 0) {
            return 0;
        }
        return (int) round((($current - $previous) / $previous) * 100);
    }

    private function normalizeSeverity(string $severity): string
    {
        return match ($severity) {
            'warning' => 'medium',
            'info' => 'low',
            'high', 'medium', 'low' => $severity,
            default => 'low',
        };
    }

    private function sourceFingerprint(): array
    {
        try {
            $row = DB::pdo()
                ->query('SELECT COUNT(*) AS c, MAX(ts) AS max_ts FROM visits')
                ->fetch();
        } catch (\Throwable) {
            $row = false;
        }
        return [
            'count' => (int) ($row['c'] ?? 0),
            'max_ts' => (int) ($row['max_ts'] ?? 0),
        ];
    }

    private function loadCache(string $key): ?array
    {
        $path = $this->cachePath();
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || (string) ($data['key'] ?? '') !== $key) {
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
                [
                    'key' => $key,
                    'ts' => time(),
                    'payload' => $payload,
                ],
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
