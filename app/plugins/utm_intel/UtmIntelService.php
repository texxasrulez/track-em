<?php
declare(strict_types=1);

namespace TrackEm\Plugins\UtmIntel;

use TrackEm\Core\DB;
use TrackEm\Core\Security;

final class UtmIntelService
{
    private const CACHE_TTL = 300;
    private const MAX_VISITS = 25000;

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
            'report_range' => $this->sanitizeEnum(
                (string) ($src['report_range'] ?? '30d'),
                ['today', '7d', '30d', 'all'],
                '30d',
            ),
            'source_param' => $this->sanitizeParamName(
                (string) ($src['source_param'] ?? 'utm_source'),
                'utm_source',
            ),
            'medium_param' => $this->sanitizeParamName(
                (string) ($src['medium_param'] ?? 'utm_medium'),
                'utm_medium',
            ),
            'campaign_param' => $this->sanitizeParamName(
                (string) ($src['campaign_param'] ?? 'utm_campaign'),
                'utm_campaign',
            ),
            'content_param' => $this->sanitizeParamName(
                (string) ($src['content_param'] ?? 'utm_content'),
                'utm_content',
            ),
            'term_param' => $this->sanitizeParamName(
                (string) ($src['term_param'] ?? 'utm_term'),
                'utm_term',
            ),
            'max_rows' => max(5, min(100, (int) ($src['max_rows'] ?? 20))),
            'exclude_sources' => $this->sanitizeList($src['exclude_sources'] ?? '', 100),
            'exclude_mediums' => $this->sanitizeList($src['exclude_mediums'] ?? '', 100),
        ];
    }

    public function report(array $config, bool $forceRefresh = false): array
    {
        $range = (string) ($config['report_range'] ?? '30d');
        if (!$this->isPluginEnabled() || empty($config['enabled'])) {
            return [
                'range' => $range,
                'summary' => [
                    'visits_scanned' => 0,
                    'utm_visits' => 0,
                    'campaigns' => 0,
                ],
                'top_sources' => [],
                'top_mediums' => [],
                'top_campaigns' => [],
                'top_source_mediums' => [],
                'recent_examples' => [],
                'notes' => ['UTM Intel is disabled.'],
            ];
        }

        $cacheKey = sha1(
            json_encode(
                [
                    'range' => $range,
                    'source_param' => $config['source_param'] ?? 'utm_source',
                    'medium_param' => $config['medium_param'] ?? 'utm_medium',
                    'campaign_param' => $config['campaign_param'] ?? 'utm_campaign',
                    'content_param' => $config['content_param'] ?? 'utm_content',
                    'term_param' => $config['term_param'] ?? 'utm_term',
                    'exclude_sources' => $config['exclude_sources'] ?? [],
                    'exclude_mediums' => $config['exclude_mediums'] ?? [],
                    'source' => $this->sourceFingerprint($range),
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

        $since = $this->rangeStart($range);
        $maxRows = (int) ($config['max_rows'] ?? 20);
        $excludeSources = array_fill_keys((array) ($config['exclude_sources'] ?? []), true);
        $excludeMediums = array_fill_keys((array) ($config['exclude_mediums'] ?? []), true);

        $visitsScanned = 0;
        $utmVisits = 0;
        $sources = [];
        $mediums = [];
        $campaigns = [];
        $sourceMediums = [];
        $recentExamples = [];

        foreach ($this->iterateVisits($since) as $visit) {
            $visitsScanned++;
            $path = (string) ($visit['path'] ?? '');
            $row = $this->extractUtmRow($path, $config);
            if ($row === null) {
                continue;
            }
            if ($row['source'] !== '' && isset($excludeSources[$row['source']])) {
                continue;
            }
            if ($row['medium'] !== '' && isset($excludeMediums[$row['medium']])) {
                continue;
            }

            $utmVisits++;
            if ($row['source'] !== '') {
                $sources[$row['source']] = ($sources[$row['source']] ?? 0) + 1;
            }
            if ($row['medium'] !== '') {
                $mediums[$row['medium']] = ($mediums[$row['medium']] ?? 0) + 1;
            }
            if ($row['campaign'] !== '') {
                $campaigns[$row['campaign']] = ($campaigns[$row['campaign']] ?? 0) + 1;
            }

            $combo = trim(($row['source'] ?: 'unknown') . ' / ' . ($row['medium'] ?: 'unknown'));
            $sourceMediums[$combo] = ($sourceMediums[$combo] ?? 0) + 1;

            if (count($recentExamples) < 12) {
                $recentExamples[] = [
                    'path' => $this->normalizePath($path),
                    'source' => $row['source'],
                    'medium' => $row['medium'],
                    'campaign' => $row['campaign'],
                    'content' => $row['content'],
                    'term' => $row['term'],
                ];
            }
        }

        arsort($sources);
        arsort($mediums);
        arsort($campaigns);
        arsort($sourceMediums);

        $payload = [
            'range' => $range,
            'summary' => [
                'visits_scanned' => $visitsScanned,
                'utm_visits' => $utmVisits,
                'campaigns' => count($campaigns),
            ],
            'top_sources' => $this->sliceAssoc($sources, $maxRows),
            'top_mediums' => $this->sliceAssoc($mediums, $maxRows),
            'top_campaigns' => $this->sliceAssoc($campaigns, $maxRows),
            'top_source_mediums' => $this->sliceAssoc($sourceMediums, $maxRows),
            'recent_examples' => $recentExamples,
            'notes' => [
                'UTM Intel reads only campaign-style query parameters already present on tracked paths.',
                'This plugin is intended for lightweight source reporting, not full attribution modeling.',
            ],
        ];

        $this->saveCache($cacheKey, $payload);
        return $payload;
    }

    public function extractUtmRow(string $path, array $config): ?array
    {
        $parts = @parse_url(trim($path));
        if (!is_array($parts)) {
            return null;
        }
        $query = (string) ($parts['query'] ?? '');
        if ($query === '') {
            return null;
        }

        parse_str($query, $params);
        if (!is_array($params)) {
            return null;
        }

        $row = [
            'source' => $this->sanitizeValue($this->firstParamValue($params, (string) ($config['source_param'] ?? 'utm_source'))),
            'medium' => $this->sanitizeValue($this->firstParamValue($params, (string) ($config['medium_param'] ?? 'utm_medium'))),
            'campaign' => $this->sanitizeValue($this->firstParamValue($params, (string) ($config['campaign_param'] ?? 'utm_campaign'))),
            'content' => $this->sanitizeValue($this->firstParamValue($params, (string) ($config['content_param'] ?? 'utm_content'))),
            'term' => $this->sanitizeValue($this->firstParamValue($params, (string) ($config['term_param'] ?? 'utm_term'))),
        ];

        foreach ($row as $value) {
            if ($value !== '') {
                return $row;
            }
        }
        return null;
    }

    private function firstParamValue(array $params, string $key): string
    {
        if ($key === '' || !array_key_exists($key, $params)) {
            return '';
        }
        $value = $params[$key];
        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    return (string) $item;
                }
            }
            return '';
        }
        return is_scalar($value) ? (string) $value : '';
    }

    private function iterateVisits(?int $since): \Generator
    {
        try {
            if ($since === null) {
                $st = DB::pdo()->query(
                    'SELECT path, ts FROM visits ORDER BY ts DESC LIMIT ' . self::MAX_VISITS,
                );
            } else {
                $st = DB::pdo()->prepare(
                    'SELECT path, ts FROM visits WHERE ts >= ? ORDER BY ts DESC LIMIT ' . self::MAX_VISITS,
                );
                $st->execute([$since]);
            }
            while ($row = $st->fetch()) {
                yield $row;
            }
        } catch (\Throwable) {
            return;
        }
    }

    private function sourceFingerprint(string $range): array
    {
        $since = $this->rangeStart($range);
        try {
            if ($since === null) {
                $row = DB::pdo()->query('SELECT COUNT(*) AS c, MAX(ts) AS max_ts FROM visits')->fetch();
            } else {
                $st = DB::pdo()->prepare(
                    'SELECT COUNT(*) AS c, MAX(ts) AS max_ts FROM visits WHERE ts >= ?',
                );
                $st->execute([$since]);
                $row = $st->fetch();
            }
            return [
                'count' => (int) ($row['c'] ?? 0),
                'max_ts' => (int) ($row['max_ts'] ?? 0),
            ];
        } catch (\Throwable) {
            return ['count' => 0, 'max_ts' => 0];
        }
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

    private function sanitizeParamName(string $value, string $default): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]/', '', $value) ?? '';
        return $value !== '' ? substr($value, 0, 40) : $default;
    }

    private function sanitizeList(mixed $value, int $limit): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[\r\n,]+/', (string) $value) ?: [];
        }
        $out = [];
        foreach ($items as $item) {
            $item = $this->sanitizeValue((string) $item);
            if ($item === '') {
                continue;
            }
            $out[$item] = $item;
            if (count($out) >= $limit) {
                break;
            }
        }
        return array_values($out);
    }

    private function sanitizeValue(string $value): string
    {
        $value = trim(rawurldecode($value));
        $value = strip_tags($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        $value = preg_replace('/[^\pL\pN _\-\.\+]/u', '', $value) ?? '';
        $value = trim(strtolower($value));
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr')
            ? mb_substr($value, 0, 80)
            : substr($value, 0, 80);
    }

    private function normalizePath(string $path): string
    {
        $parts = @parse_url(trim($path));
        if (!is_array($parts)) {
            return '';
        }
        $path = (string) ($parts['path'] ?? '');
        if ($path === '') {
            return '';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        return substr($path, 0, 255);
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

    private function sliceAssoc(array $rows, int $limit): array
    {
        $out = [];
        foreach (array_slice($rows, 0, $limit, true) as $name => $count) {
            $out[] = [
                'name' => (string) $name,
                'count' => (int) $count,
            ];
        }
        return $out;
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
