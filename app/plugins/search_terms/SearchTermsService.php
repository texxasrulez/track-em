<?php
declare(strict_types=1);

namespace TrackEm\Plugins\SearchTerms;

use TrackEm\Core\DB;
use TrackEm\Core\Security;

final class SearchTermsService
{
    private const CACHE_TTL = 300;
    private const MAX_ROWS = 25000;

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
        $maxTerms = max(10, min(500, (int) ($src['max_terms'] ?? 100)));
        return [
            'enabled' => $this->toBool($src['enabled'] ?? false),
            'report_range' => $this->sanitizeEnum(
                (string) ($src['report_range'] ?? '30d'),
                ['today', '7d', '30d', 'all'],
                '30d',
            ),
            'query_params' => $this->sanitizeParamList($src['query_params'] ?? ''),
            'min_term_length' => max(1, min(20, (int) ($src['min_term_length'] ?? 2))),
            'max_terms' => $maxTerms,
            'exclude_terms' => $this->sanitizeTermList($src['exclude_terms'] ?? '', 100),
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
                    'search_visits' => 0,
                    'unique_terms' => 0,
                ],
                'top_terms' => [],
                'top_paths' => [],
                'trend' => [],
                'notes' => ['Search Terms is disabled.'],
            ];
        }

        $cacheKey = sha1(
            json_encode(
                [
                    'range' => $range,
                    'params' => $config['query_params'] ?? [],
                    'min' => $config['min_term_length'] ?? 2,
                    'max_terms' => $config['max_terms'] ?? 100,
                    'exclude' => $config['exclude_terms'] ?? [],
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
        $params = is_array($config['query_params'] ?? null) ? $config['query_params'] : [];
        $minLen = (int) ($config['min_term_length'] ?? 2);
        $maxTerms = (int) ($config['max_terms'] ?? 100);
        $excluded = array_fill_keys((array) ($config['exclude_terms'] ?? []), true);

        $visitsScanned = 0;
        $searchVisits = 0;
        $terms = [];
        $paths = [];
        $trend = [];

        foreach ($this->iterateVisits($since) as $visit) {
            $visitsScanned++;
            $path = (string) ($visit['path'] ?? '');
            $ts = (int) ($visit['ts'] ?? 0);
            $extracted = $this->extractTermsFromPath($path, $params, $minLen, $excluded);
            if ($extracted === []) {
                continue;
            }

            $searchVisits++;
            $normalizedPath = $this->normalizePath($path);
            if ($normalizedPath !== '') {
                $paths[$normalizedPath] = ($paths[$normalizedPath] ?? 0) + 1;
            }

            $day = gmdate('Y-m-d', $ts > 0 ? $ts : time());
            $trend[$day] = ($trend[$day] ?? 0) + 1;

            foreach ($extracted as $term) {
                $terms[$term] = ($terms[$term] ?? 0) + 1;
            }
        }

        arsort($terms);
        arsort($paths);
        ksort($trend);

        $payload = [
            'range' => $range,
            'summary' => [
                'visits_scanned' => $visitsScanned,
                'search_visits' => $searchVisits,
                'unique_terms' => count($terms),
            ],
            'top_terms' => $this->sliceAssoc($terms, $maxTerms),
            'top_paths' => $this->sliceAssoc($paths, 20),
            'trend' => $this->sliceAssoc($trend, 31),
            'notes' => [
                'Search terms are extracted only from configured query parameters on tracked paths.',
                'Terms are normalized, length-limited, and stored only as aggregate counts in the cached report.',
            ],
        ];

        $this->saveCache($cacheKey, $payload);
        return $payload;
    }

    public function extractTermsFromPath(
        string $path,
        array $queryParams,
        int $minLen,
        array $excluded
    ): array {
        $parts = @parse_url(trim($path));
        if (!is_array($parts)) {
            return [];
        }

        $query = (string) ($parts['query'] ?? '');
        if ($query === '') {
            return [];
        }

        parse_str($query, $params);
        if (!is_array($params)) {
            return [];
        }

        $terms = [];
        foreach ($queryParams as $key) {
            $key = strtolower(trim((string) $key));
            if ($key === '' || !array_key_exists($key, $params)) {
                continue;
            }
            foreach ($this->flattenParamValue($params[$key]) as $value) {
                $term = $this->sanitizeTerm($value, $minLen);
                if ($term === '' || isset($excluded[$term])) {
                    continue;
                }
                $terms[$term] = true;
            }
        }
        return array_keys($terms);
    }

    private function flattenParamValue(mixed $value): array
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $out[] = (string) $item;
                }
            }
            return $out;
        }
        return is_scalar($value) ? [(string) $value] : [];
    }

    private function iterateVisits(?int $since): \Generator
    {
        try {
            if ($since === null) {
                $st = DB::pdo()->query(
                    'SELECT path, ts FROM visits ORDER BY ts DESC LIMIT ' . self::MAX_ROWS,
                );
            } else {
                $st = DB::pdo()->prepare(
                    'SELECT path, ts FROM visits WHERE ts >= ? ORDER BY ts DESC LIMIT ' . self::MAX_ROWS,
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
                $row = DB::pdo()
                    ->query('SELECT COUNT(*) AS c, MAX(ts) AS max_ts FROM visits')
                    ->fetch();
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

    private function sanitizeParamList(mixed $value): array
    {
        $items = $this->splitList($value);
        $out = [];
        foreach ($items as $item) {
            $item = strtolower(trim($item));
            $item = preg_replace('/[^a-z0-9_-]/', '', $item) ?? '';
            if ($item === '') {
                continue;
            }
            $out[$item] = $item;
            if (count($out) >= 20) {
                break;
            }
        }
        return array_values($out);
    }

    private function sanitizeTermList(mixed $value, int $limit): array
    {
        $items = $this->splitList($value);
        $out = [];
        foreach ($items as $item) {
            $term = $this->sanitizeTerm($item, 1);
            if ($term === '') {
                continue;
            }
            $out[$term] = $term;
            if (count($out) >= $limit) {
                break;
            }
        }
        return array_values($out);
    }

    private function splitList(mixed $value): array
    {
        if (is_array($value)) {
            return array_map(
                static fn($item): string => is_scalar($item) ? (string) $item : '',
                $value,
            );
        }
        return preg_split('/[\r\n,]+/', (string) $value) ?: [];
    }

    private function sanitizeTerm(string $value, int $minLen): string
    {
        $value = trim(rawurldecode($value));
        $value = strip_tags($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        $value = preg_replace('/[^\pL\pN _\-\.\+]/u', '', $value) ?? '';
        $value = trim(strtolower($value));
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_strlen')) {
            if (mb_strlen($value) < $minLen) {
                return '';
            }
            return mb_substr($value, 0, 80);
        }
        if (strlen($value) < $minLen) {
            return '';
        }
        return substr($value, 0, 80);
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
