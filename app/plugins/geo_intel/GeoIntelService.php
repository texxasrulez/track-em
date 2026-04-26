<?php
declare(strict_types=1);

namespace TrackEm\Plugins\GeoIntel;

use TrackEm\Core\DB;
use TrackEm\Core\Security;

final class GeoIntelService
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
            'max_rows' => max(5, min(100, (int) ($src['max_rows'] ?? 20))),
            'include_city_summary' => $this->toBool($src['include_city_summary'] ?? false),
            'group_unknown' => $this->toBool($src['group_unknown'] ?? false),
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
                    'geo_visits' => 0,
                    'countries' => 0,
                    'cities' => 0,
                ],
                'top_countries' => [],
                'top_cities' => [],
                'trend' => [],
                'notes' => ['Geo Intel is disabled.'],
            ];
        }

        $cacheKey = sha1(
            json_encode(
                [
                    'range' => $range,
                    'max_rows' => $config['max_rows'] ?? 20,
                    'include_city_summary' => $config['include_city_summary'] ?? false,
                    'group_unknown' => $config['group_unknown'] ?? true,
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
        $includeCities = !empty($config['include_city_summary']);
        $groupUnknown = !empty($config['group_unknown']);

        $visitsScanned = 0;
        $geoVisits = 0;
        $countries = [];
        $cities = [];
        $trend = [];

        foreach ($this->iterateVisits($since) as $visit) {
            $visitsScanned++;
            $country = $this->sanitizePlace((string) ($visit['country'] ?? ''));
            $city = $this->sanitizePlace((string) ($visit['city'] ?? ''));
            $ts = (int) ($visit['ts'] ?? 0);

            if ($country === '' && !$groupUnknown) {
                continue;
            }

            $bucketCountry = $country !== '' ? $country : 'Unknown';
            $countries[$bucketCountry] = ($countries[$bucketCountry] ?? 0) + 1;
            $geoVisits++;

            if ($includeCities) {
                $bucketCity = $city !== '' ? $city : ($groupUnknown ? 'Unknown' : '');
                if ($bucketCity !== '') {
                    $cities[$bucketCity] = ($cities[$bucketCity] ?? 0) + 1;
                }
            }

            $day = gmdate('Y-m-d', $ts > 0 ? $ts : time());
            $trend[$day] = ($trend[$day] ?? 0) + 1;
        }

        arsort($countries);
        arsort($cities);
        ksort($trend);

        $payload = [
            'range' => $range,
            'summary' => [
                'visits_scanned' => $visitsScanned,
                'geo_visits' => $geoVisits,
                'countries' => count($countries),
                'cities' => count($cities),
            ],
            'top_countries' => $this->sliceAssoc($countries, $maxRows),
            'top_cities' => $includeCities ? $this->sliceAssoc($cities, $maxRows) : [],
            'trend' => $this->sliceAssoc($trend, 31),
            'notes' => $this->buildNotes($includeCities, $groupUnknown),
        ];

        $this->saveCache($cacheKey, $payload);
        return $payload;
    }

    private function buildNotes(bool $includeCities, bool $groupUnknown): array
    {
        $notes = [
            'Geo Intel uses existing visit-level country and city fields and reports only aggregate counts.',
        ];
        if (!$includeCities) {
            $notes[] = 'City summaries are off by default to keep reporting more privacy-preserving and compact.';
        }
        if ($groupUnknown) {
            $notes[] = 'Visits without geo enrichment are grouped into an Unknown bucket.';
        }
        return $notes;
    }

    private function iterateVisits(?int $since): \Generator
    {
        try {
            if ($since === null) {
                $st = DB::pdo()->query(
                    'SELECT country, city, ts FROM visits ORDER BY ts DESC LIMIT ' . self::MAX_VISITS,
                );
            } else {
                $st = DB::pdo()->prepare(
                    'SELECT country, city, ts FROM visits WHERE ts >= ? ORDER BY ts DESC LIMIT ' . self::MAX_VISITS,
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

    private function sanitizePlace(string $value): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr')
            ? mb_substr($value, 0, 80)
            : substr($value, 0, 80);
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
