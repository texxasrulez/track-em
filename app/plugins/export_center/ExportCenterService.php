<?php
declare(strict_types=1);

namespace TrackEm\Plugins\ExportCenter;

use TrackEm\Core\DB;
use TrackEm\Core\Security;

final class ExportCenterService
{
    private const MAX_TOP_PATHS = 25;

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
        return $config;
    }

    public function resetConfig(): void
    {
        @unlink($this->configPath());
    }

    public function configPath(): string
    {
        return $this->storageDir() . '/config.json';
    }

    public function exportsDir(): string
    {
        $dir = $this->storageDir() . '/exports';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
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
            'report_range' => $this->sanitizeEnum(
                (string) ($src['report_range'] ?? '30d'),
                ['today', '7d', '30d', 'all'],
                '30d',
            ),
            'export_format' => $this->sanitizeEnum(
                (string) ($src['export_format'] ?? 'both'),
                ['json', 'csv', 'both'],
                'both',
            ),
            'retention_count' => max(
                1,
                min(200, (int) ($src['retention_count'] ?? 30)),
            ),
            'include_sections' => [
                'traffic_summary' => $this->toBool($src['include_traffic_summary'] ?? false),
                'top_paths' => $this->toBool($src['include_top_paths'] ?? false),
                'referrer_summary' => $this->toBool($src['include_referrer_summary'] ?? false),
                'event_summary' => $this->toBool($src['include_event_summary'] ?? false),
                'goals_summary' => $this->toBool($src['include_goals_summary'] ?? false),
            ],
        ];
    }

    public function optionalCapabilities(): array
    {
        return [
            'referrer_intel' => $this->pluginIsAvailable('referrer_intel'),
            'event_tracking' => $this->pluginIsAvailable('event_tracking'),
            'goals' => $this->pluginIsAvailable('goals'),
        ];
    }

    public function generateExports(array $config): array
    {
        if (!$this->isPluginEnabled() || empty($config['enabled'])) {
            return ['files' => [], 'reason' => 'disabled'];
        }

        $snapshot = $this->buildSnapshot($config);
        $stamp = gmdate('Ymd_His');
        $files = [];

        $format = (string) ($config['export_format'] ?? 'both');
        if ($format === 'json' || $format === 'both') {
            $name = 'export-' . $stamp . '.json';
            @file_put_contents(
                $this->exportsDir() . '/' . $name,
                json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                LOCK_EX,
            );
            $files[] = $name;
        }
        if ($format === 'csv' || $format === 'both') {
            $name = 'export-' . $stamp . '.csv';
            @file_put_contents(
                $this->exportsDir() . '/' . $name,
                $this->renderCsv($snapshot),
                LOCK_EX,
            );
            $files[] = $name;
        }

        $this->pruneExports((int) ($config['retention_count'] ?? 30));
        return ['files' => $files, 'reason' => $files ? 'ok' : 'no_output'];
    }

    public function listExports(): array
    {
        $files = glob($this->exportsDir() . '/*.{json,csv}', GLOB_BRACE) ?: [];
        $rows = [];
        foreach ($files as $path) {
            $file = basename((string) $path);
            if (!$this->isSafeExportFilename($file)) {
                continue;
            }
            $rows[] = [
                'file' => $file,
                'type' => strtolower(pathinfo($file, PATHINFO_EXTENSION)),
                'generated_at' => is_file($path) ? (int) @filemtime($path) : 0,
                'size_bytes' => is_file($path) ? (int) @filesize($path) : 0,
                'download_url' => $this->routeUrl('export_center.download', ['file' => $file]),
            ];
        }
        usort(
            $rows,
            static fn(array $a, array $b): int => (int) $b['generated_at'] <=> (int) $a['generated_at'],
        );
        return $rows;
    }

    public function resolveExportPath(string $file): ?string
    {
        $file = basename($file);
        if (!$this->isSafeExportFilename($file)) {
            return null;
        }
        $path = $this->exportsDir() . '/' . $file;
        return is_file($path) ? $path : null;
    }

    private function buildSnapshot(array $config): array
    {
        $range = (string) ($config['report_range'] ?? '30d');
        $sections = is_array($config['include_sections'] ?? null)
            ? $config['include_sections']
            : [];

        $snapshot = [
            'generated_at' => gmdate('c'),
            'range' => $range,
            'sections' => [],
        ];

        if (!empty($sections['traffic_summary'])) {
            $snapshot['sections']['traffic_summary'] = $this->buildTrafficSummary($range);
        }
        if (!empty($sections['top_paths'])) {
            $snapshot['sections']['top_paths'] = $this->buildTopPaths($range);
        }
        if (!empty($sections['referrer_summary'])) {
            $data = $this->loadPluginReport('referrer_intel');
            if (is_array($data)) {
                $snapshot['sections']['referrer_summary'] = [
                    'summary' => $data['summary'] ?? [],
                    'top_domains' => array_slice((array) ($data['top_domains'] ?? []), 0, 12),
                ];
            }
        }
        if (!empty($sections['event_summary'])) {
            $data = $this->loadPluginReport('event_tracking');
            if (is_array($data)) {
                $snapshot['sections']['event_summary'] = [
                    'summary' => $data['summary'] ?? [],
                    'top_events' => array_slice((array) ($data['top_event_names'] ?? []), 0, 12),
                    'top_labels' => array_slice((array) ($data['top_labels'] ?? []), 0, 12),
                ];
            }
        }
        if (!empty($sections['goals_summary'])) {
            $data = $this->loadPluginReport('goals');
            if (is_array($data)) {
                $snapshot['sections']['goals_summary'] = [
                    'summary' => $data['summary'] ?? [],
                    'goals' => array_slice((array) ($data['goals'] ?? []), 0, 12),
                ];
            }
        }

        return $snapshot;
    }

    private function buildTrafficSummary(string $range): array
    {
        $since = $this->rangeStart($range);
        try {
            if ($since === null) {
                $row = DB::pdo()
                    ->query('SELECT COUNT(*) AS visits, COUNT(DISTINCT ip) AS unique_sources, MAX(ts) AS max_ts FROM visits')
                    ->fetch();
            } else {
                $st = DB::pdo()->prepare(
                    'SELECT COUNT(*) AS visits, COUNT(DISTINCT ip) AS unique_sources, MAX(ts) AS max_ts FROM visits WHERE ts >= ?',
                );
                $st->execute([$since]);
                $row = $st->fetch();
            }
        } catch (\Throwable) {
            $row = [];
        }

        return [
            'visits' => (int) ($row['visits'] ?? 0),
            'unique_sources' => (int) ($row['unique_sources'] ?? 0),
            'latest_ts' => (int) ($row['max_ts'] ?? 0),
        ];
    }

    private function buildTopPaths(string $range): array
    {
        $since = $this->rangeStart($range);
        try {
            if ($since === null) {
                $sql = 'SELECT path, COUNT(*) AS c FROM visits GROUP BY path ORDER BY c DESC LIMIT ' . self::MAX_TOP_PATHS;
                $st = DB::pdo()->query($sql);
            } else {
                $sql = 'SELECT path, COUNT(*) AS c FROM visits WHERE ts >= ? GROUP BY path ORDER BY c DESC LIMIT ' . self::MAX_TOP_PATHS;
                $st = DB::pdo()->prepare($sql);
                $st->execute([$since]);
            }
            $rows = [];
            while ($row = $st->fetch()) {
                $rows[] = [
                    'path' => $this->normalizePath((string) ($row['path'] ?? '')),
                    'count' => (int) ($row['c'] ?? 0),
                ];
            }
            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    private function loadPluginReport(string $pluginId): ?array
    {
        try {
            return match ($pluginId) {
                'referrer_intel' => $this->loadReferrerIntelReport(),
                'event_tracking' => $this->loadEventTrackingReport(),
                'goals' => $this->loadGoalsReport(),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    private function loadReferrerIntelReport(): ?array
    {
        if (!$this->pluginIsAvailable('referrer_intel')) {
            return null;
        }
        require_once dirname(__DIR__) . '/referrer_intel/ReferrerIntelService.php';
        $service = new \TrackEm\Plugins\ReferrerIntel\ReferrerIntelService(
            'referrer_intel',
            dirname(__DIR__) . '/referrer_intel',
        );
        return $service->report($service->loadConfig(), false);
    }

    private function loadEventTrackingReport(): ?array
    {
        if (!$this->pluginIsAvailable('event_tracking')) {
            return null;
        }
        require_once dirname(__DIR__) . '/event_tracking/EventTrackingService.php';
        $service = new \TrackEm\Plugins\EventTracking\EventTrackingService(
            'event_tracking',
            dirname(__DIR__) . '/event_tracking',
        );
        return $service->report($service->loadConfig(), false);
    }

    private function loadGoalsReport(): ?array
    {
        if (!$this->pluginIsAvailable('goals')) {
            return null;
        }
        require_once dirname(__DIR__) . '/goals/GoalsService.php';
        $service = new \TrackEm\Plugins\Goals\GoalsService(
            'goals',
            dirname(__DIR__) . '/goals',
        );
        return $service->report($service->loadConfig(), false);
    }

    private function renderCsv(array $snapshot): string
    {
        $lines = [];
        $lines[] = $this->csvRow(['section', 'metric', 'name', 'value']);

        $summary = (array) ($snapshot['sections']['traffic_summary'] ?? []);
        foreach ($summary as $metric => $value) {
            $lines[] = $this->csvRow(['traffic_summary', (string) $metric, '', (string) $value]);
        }

        foreach ((array) ($snapshot['sections']['top_paths'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lines[] = $this->csvRow([
                'top_paths',
                'count',
                (string) ($row['path'] ?? ''),
                (string) ((int) ($row['count'] ?? 0)),
            ]);
        }

        foreach (['top_domains', 'top_events', 'top_labels', 'goals'] as $metricName) {
            foreach ($this->flattenNamedRows($snapshot, $metricName) as $row) {
                $lines[] = $this->csvRow($row);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function flattenNamedRows(array $snapshot, string $metricName): array
    {
        $sectionMap = [
            'top_domains' => 'referrer_summary',
            'top_events' => 'event_summary',
            'top_labels' => 'event_summary',
            'goals' => 'goals_summary',
        ];
        $section = $sectionMap[$metricName] ?? '';
        if ($section === '') {
            return [];
        }

        $rows = [];
        foreach ((array) ($snapshot['sections'][$section][$metricName] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = [
                $section,
                $metricName,
                (string) ($row['name'] ?? $row['goal_name'] ?? ''),
                (string) ((int) ($row['count'] ?? $row['completions'] ?? 0)),
            ];
        }
        return $rows;
    }

    private function csvRow(array $cols): string
    {
        $escaped = array_map(
            static function (string $value): string {
                return '"' . str_replace('"', '""', $value) . '"';
            },
            $cols,
        );
        return implode(',', $escaped);
    }

    private function pruneExports(int $keep): void
    {
        $files = glob($this->exportsDir() . '/*.{json,csv}', GLOB_BRACE) ?: [];
        usort(
            $files,
            static fn(string $a, string $b): int => ((int) @filemtime($b)) <=> ((int) @filemtime($a)),
        );
        foreach (array_slice($files, $keep) as $path) {
            @unlink($path);
        }
    }

    private function rangeStart(string $range): ?int
    {
        $todayStart = strtotime(gmdate('Y-m-d 00:00:00')) ?: time();
        return match ($range) {
            'today' => $todayStart,
            '7d' => time() - 7 * 86400,
            '30d' => time() - 30 * 86400,
            default => null,
        };
    }

    private function pluginIsAvailable(string $id): bool
    {
        return is_dir(dirname(__DIR__) . '/' . $id);
    }

    private function normalizePath(string $path): string
    {
        $parts = @parse_url(trim($path));
        if (!is_array($parts)) {
            return '';
        }
        $normalized = (string) ($parts['path'] ?? '');
        if ($normalized === '') {
            return '';
        }
        if ($normalized[0] !== '/') {
            $normalized = '/' . $normalized;
        }
        return substr($normalized, 0, 255);
    }

    private function isSafeExportFilename(string $file): bool
    {
        return preg_match('/^export-\d{8}_\d{6}\.(json|csv)$/', $file) === 1;
    }

    private function sanitizeEnum(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
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
}
