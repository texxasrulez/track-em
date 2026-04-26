<?php
declare(strict_types=1);

namespace TrackEm\Plugins\PublicWidgets;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TrackEm\Core\DB;
use TrackEm\Core\Security;

final class PublicWidgetsService
{
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

    public function basemapOptions(): array
    {
        return [
            'roads' => 'Roads',
            'light' => 'Light',
            'terrain' => 'Terrain',
            'satellite' => 'Satellite',
        ];
    }

    public function loadConfig(): array
    {
        $config = $this->defaults();
        $saved = $this->loadSavedConfig();
        return $this->mergeRecursive($config, $saved);
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
        return dirname(__DIR__, 2) .
            '/config/plugins/' .
            $this->pluginId .
            '.json';
    }

    public function isPluginEnabled(): bool
    {
        try {
            $st = DB::pdo()->prepare(
                'SELECT enabled FROM plugins WHERE id = ? LIMIT 1',
            );
            $st->execute([$this->pluginId]);
            $row = $st->fetch();
            if (!$row) {
                return false;
            }
            return (int) ($row['enabled'] ?? 0) === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    public function basePath(): string
    {
        $base = rtrim(
            str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')),
            '/',
        );
        return $base === '/' ? '' : $base;
    }

    public function routeUrl(string $route, array $params = []): string
    {
        $params = ['p' => $route] + $params;
        return $this->basePath() . '/index.php?' . http_build_query($params);
    }

    public function assetUrl(string $file): string
    {
        return $this->routeUrl('api.plugins.asset', [
            'key' => $this->pluginId,
            'file' => $file,
        ]);
    }

    public function counterSnippet(array $config): string
    {
        $mode = $config['counter']['mode'] ?? 'site';
        $scope = $mode === 'path' ? 'path' : 'site';
        return '<span data-trackem-counter="' .
            $scope .
            '"></span>' .
            "\n" .
            '<script async src="' .
            $this->assetUrl('assets/counter.js') .
            '"></script>';
    }

    public function mapSnippet(array $config): string
    {
        $profile = $config['map']['profile'] ?? [];
        $id = $this->sanitizeProfileId((string) ($profile['id'] ?? 'main'));
        $height = max(240, min(1200, (int) ($profile['height'] ?? 520)));
        $title = $this->sanitizeText(
            (string) ($profile['title'] ?? 'Visitor Map'),
            80,
            'Visitor Map',
        );

        return '<iframe' .
            "\n" .
            '  src="' .
            $this->routeUrl('public_widgets.map_embed', ['id' => $id]) .
            '"' .
            "\n" .
            '  width="100%"' .
            "\n" .
            '  height="' .
            $height .
            '"' .
            "\n" .
            '  loading="lazy"' .
            "\n" .
            '  referrerpolicy="no-referrer-when-downgrade"' .
            "\n" .
            '  style="border:0; border-radius:12px; overflow:hidden;"' .
            "\n" .
            '  title="' .
            htmlspecialchars($title, ENT_QUOTES) .
            '">' .
            "\n" .
            '</iframe>';
    }

    public function formatCount(int $count, string $format): string
    {
        $count = max(0, $count);
        if ($format === 'compact') {
            return $this->formatCompact($count);
        }
        if ($format === 'rounded') {
            return $this->formatRounded($count);
        }
        return number_format($count);
    }

    public function counterPayload(array $config, ?string $requestedScope, ?string $requestedPath): array
    {
        $counter = $config['counter'] ?? [];
        $scope = ($counter['mode'] ?? 'site') === 'path' ? 'path' : 'site';
        $path = null;
        if ($scope === 'path') {
            $path = $this->normalizePath((string) ($requestedPath ?? ''));
            if ($path === '') {
                $path = '/';
            }
        }

        $count = $this->queryCounter(
            $scope,
            $path,
            (string) ($counter['range'] ?? '30d'),
        );

        return [
            'ok' => true,
            'scope' => $scope,
            'label' => $this->sanitizeText(
                (string) ($counter['label'] ?? 'Visits'),
                48,
                'Visits',
            ),
            'count' => $count,
            'display' => $this->formatCount(
                $count,
                (string) ($counter['format'] ?? 'compact'),
            ),
        ];
    }

    public function mapPayload(array $config, string $requestedId): array
    {
        $profile = $config['map']['profile'] ?? [];
        $profileId = $this->sanitizeProfileId((string) ($profile['id'] ?? 'main'));
        if ($requestedId !== $profileId) {
            throw new \RuntimeException('unknown_map');
        }

        $range = (string) ($profile['range'] ?? '30d');
        $maxPoints = max(10, min(500, (int) ($profile['max_points'] ?? 120)));
        $minBucket = max(3, min(100, (int) ($profile['min_bucket_size'] ?? 3)));
        $privacyMode = $this->sanitizeEnum(
            (string) ($profile['privacy_mode'] ?? 'bucketed'),
            ['country', 'rounded', 'bucketed'],
            'bucketed',
        );
        $precision = max(
            0,
            min(3, (int) ($profile['coordinate_precision'] ?? 1)),
        );
        $bucketSize = max(
            0.1,
            min(15.0, (float) ($profile['bucket_size_deg'] ?? 2.5)),
        );
        $jitter = max(0.0, min(1.0, (float) ($profile['jitter'] ?? 0.18)));
        $showCounts = !empty($profile['show_counts']);

        $cacheKey = sha1(
            json_encode(
                [
                    'map',
                    $profileId,
                    $range,
                    $maxPoints,
                    $minBucket,
                    $privacyMode,
                    $precision,
                    $bucketSize,
                    $jitter,
                    $showCounts,
                ],
                JSON_UNESCAPED_SLASHES,
            ),
        );
        $cached = $this->cacheGet('map_' . $cacheKey, 60);
        if (is_array($cached)) {
            return $cached;
        }

        $points = $this->queryMapPoints(
            $range,
            $maxPoints,
            $minBucket,
            $privacyMode,
            $precision,
            $bucketSize,
            $jitter,
            $profileId,
        );

        $payload = [
            'ok' => true,
            'profile' => [
                'id' => $profileId,
                'title' => $this->sanitizeText(
                    (string) ($profile['title'] ?? 'Visitor Map'),
                    80,
                    'Visitor Map',
                ),
                'show_counts' => $showCounts,
            ],
            'points' => $points,
        ];

        $this->cacheSet('map_' . $cacheKey, $payload);
        return $payload;
    }

    public function mapEmbedContext(array $config, string $requestedId): array
    {
        $profile = $config['map']['profile'] ?? [];
        $profileId = $this->sanitizeProfileId((string) ($profile['id'] ?? 'main'));
        if ($requestedId !== $profileId) {
            throw new \RuntimeException('unknown_map');
        }

        return [
            'profile_id' => $profileId,
            'title' => $this->sanitizeText(
                (string) ($profile['title'] ?? 'Visitor Map'),
                80,
                'Visitor Map',
            ),
            'height' => max(240, min(1200, (int) ($profile['height'] ?? 520))),
            'defaultTileLayer' => $this->sanitizeEnum(
                (string) ($profile['tile_layer'] ?? 'roads'),
                array_keys($this->basemapOptions()),
                'roads',
            ),
            'basemapOptions' => $this->basemapOptions(),
            'mapDataUrl' => $this->routeUrl('public_widgets.map_data', [
                'id' => $profileId,
            ]),
            'mapCssUrl' => $this->assetUrl('assets/public-map.css'),
            'mapJsUrl' => $this->assetUrl('assets/public-map.js'),
        ];
    }

    public function sanitizeConfig(array $src): array
    {
        return [
            'counter' => [
                'enabled' => $this->toBool($src['counter_enabled'] ?? false),
                'mode' => $this->sanitizeEnum(
                    (string) ($src['counter_mode'] ?? 'site'),
                    ['site', 'path'],
                    'site',
                ),
                'range' => $this->sanitizeEnum(
                    (string) ($src['counter_range'] ?? '30d'),
                    ['all', 'today', '7d', '30d'],
                    '30d',
                ),
                'format' => $this->sanitizeEnum(
                    (string) ($src['counter_format'] ?? 'compact'),
                    ['exact', 'compact', 'rounded'],
                    'compact',
                ),
                'label' => $this->sanitizeText(
                    (string) ($src['counter_label'] ?? 'Visits'),
                    48,
                    'Visits',
                ),
            ],
            'map' => [
                'enabled' => $this->toBool($src['map_enabled'] ?? false),
                'profile' => [
                    'id' => $this->sanitizeProfileId(
                        (string) ($src['map_profile_id'] ?? 'main'),
                    ),
                    'title' => $this->sanitizeText(
                        (string) ($src['map_title'] ?? 'Visitor Map'),
                        80,
                        'Visitor Map',
                    ),
                    'range' => $this->sanitizeEnum(
                        (string) ($src['map_range'] ?? '30d'),
                        ['today', '7d', '30d', 'all'],
                        '30d',
                    ),
                    'max_points' => max(
                        10,
                        min(500, (int) ($src['map_max_points'] ?? 120)),
                    ),
                    'privacy_mode' => $this->sanitizeEnum(
                        (string) ($src['map_privacy_mode'] ?? 'bucketed'),
                        ['country', 'rounded', 'bucketed'],
                        'bucketed',
                    ),
                    'tile_layer' => $this->sanitizeEnum(
                        (string) ($src['map_tile_layer'] ?? 'roads'),
                        array_keys($this->basemapOptions()),
                        'roads',
                    ),
                    'coordinate_precision' => max(
                        0,
                        min(3, (int) ($src['map_coordinate_precision'] ?? 1)),
                    ),
                    'bucket_size_deg' => max(
                        0.1,
                        min(15.0, (float) ($src['map_bucket_size_deg'] ?? 2.5)),
                    ),
                    'jitter' => max(
                        0.0,
                        min(1.0, (float) ($src['map_jitter'] ?? 0.18)),
                    ),
                    'min_bucket_size' => max(
                        3,
                        min(100, (int) ($src['map_min_bucket_size'] ?? 3)),
                    ),
                    'show_counts' => $this->toBool(
                        $src['map_show_counts'] ?? false,
                    ),
                    'height' => max(
                        240,
                        min(1200, (int) ($src['map_height'] ?? 520)),
                    ),
                ],
            ],
        ];
    }

    public function sanitizeProfileId(string $id): string
    {
        $id = strtolower(trim($id));
        $id = preg_replace('/[^a-z0-9_-]+/', '-', $id) ?? '';
        $id = trim($id, '-_');
        return $id !== '' ? substr($id, 0, 48) : 'main';
    }

    public function csrfToken(): string
    {
        Security::startSecureSession();
        return Security::csrfToken();
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
                is_array($value)
            ) {
                $base[$key] = $this->mergeRecursive($base[$key], $value);
                continue;
            }
            $base[$key] = $value;
        }
        return $base;
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

    private function sanitizeEnum(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function sanitizeText(string $value, int $maxLen, string $default): string
    {
        $value = trim(strip_tags($value));
        if ($value === '') {
            return $default;
        }
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLen);
        }
        return substr($value, 0, $maxLen);
    }

    private function normalizePath(string $path): string
    {
        $path = trim(strip_tags($path));
        if ($path === '') {
            return '';
        }
        $parts = @parse_url($path);
        if (is_array($parts)) {
            $path = (string) ($parts['path'] ?? '');
            $query = (string) ($parts['query'] ?? '');
            if ($query !== '') {
                $path .= '?' . $query;
            }
        }
        if ($path === '') {
            return '';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        return substr($path, 0, 512);
    }

    private function queryCounter(string $scope, ?string $path, string $range): int
    {
        $cacheKey = sha1(
            json_encode(['counter', $scope, $path, $range], JSON_UNESCAPED_SLASHES),
        );
        $cached = $this->cacheGet('counter_' . $cacheKey, 30);
        if (is_int($cached)) {
            return $cached;
        }

        $sql = 'SELECT COUNT(*) FROM visits';
        $where = [];
        $params = [];
        $since = $this->rangeStart($range);
        if ($since !== null) {
            $where[] = 'ts >= :since';
            $params[':since'] = $since;
        }
        if ($scope === 'path' && $path !== null) {
            $where[] = 'path = :path';
            $params[':path'] = $path;
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $st = DB::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $st->bindValue(
                $key,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR,
            );
        }
        $st->execute();
        $count = (int) $st->fetchColumn();
        $this->cacheSet('counter_' . $cacheKey, $count);
        return $count;
    }

    private function queryMapPoints(
        string $range,
        int $maxPoints,
        int $minBucket,
        string $privacyMode,
        int $precision,
        float $bucketSize,
        float $jitter,
        string $seedPrefix,
    ): array {
        $where = [
            'lat IS NOT NULL',
            'lon IS NOT NULL',
            'lat BETWEEN -90 AND 90',
            'lon BETWEEN -180 AND 180',
        ];
        $params = [];
        $since = $this->rangeStart($range);
        if ($since !== null) {
            $where[] = 'ts >= :since';
            $params[':since'] = $since;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        if ($privacyMode === 'country') {
            $whereSql .= " AND country IS NOT NULL AND country <> ''";
            $sql = "
                SELECT
                    country AS bucket_key,
                    ROUND(AVG(lat), 4) AS lat,
                    ROUND(AVG(lon), 4) AS lon,
                    COUNT(*) AS point_count
                FROM visits
                {$whereSql}
                GROUP BY country
                HAVING COUNT(*) >= :min_bucket
                ORDER BY point_count DESC
                LIMIT :max_points
            ";
        } elseif ($privacyMode === 'rounded') {
            $sql = "
                SELECT
                    CONCAT(ROUND(lat, {$precision}), ',', ROUND(lon, {$precision})) AS bucket_key,
                    ROUND(AVG(lat), 4) AS lat,
                    ROUND(AVG(lon), 4) AS lon,
                    COUNT(*) AS point_count
                FROM visits
                {$whereSql}
                GROUP BY ROUND(lat, {$precision}), ROUND(lon, {$precision})
                HAVING COUNT(*) >= :min_bucket
                ORDER BY point_count DESC
                LIMIT :max_points
            ";
        } else {
            $size = number_format($bucketSize, 4, '.', '');
            $half = number_format($bucketSize / 2, 4, '.', '');
            $latExpr = "ROUND((FLOOR(lat / {$size}) * {$size}) + {$half}, 4)";
            $lonExpr = "ROUND((FLOOR(lon / {$size}) * {$size}) + {$half}, 4)";
            $sql = "
                SELECT
                    CONCAT({$latExpr}, ',', {$lonExpr}) AS bucket_key,
                    {$latExpr} AS lat,
                    {$lonExpr} AS lon,
                    COUNT(*) AS point_count
                FROM visits
                {$whereSql}
                GROUP BY {$latExpr}, {$lonExpr}
                HAVING COUNT(*) >= :min_bucket
                ORDER BY point_count DESC
                LIMIT :max_points
            ";
        }

        $st = DB::pdo()->prepare($sql);
        if (isset($params[':since'])) {
            $st->bindValue(':since', $params[':since'], PDO::PARAM_INT);
        }
        $st->bindValue(':min_bucket', $minBucket, PDO::PARAM_INT);
        $st->bindValue(':max_points', $maxPoints, PDO::PARAM_INT);
        $st->execute();

        $points = [];
        foreach ($st->fetchAll() as $row) {
            $count = (int) ($row['point_count'] ?? 0);
            if ($count < $minBucket) {
                continue;
            }

            $lat = isset($row['lat']) ? (float) $row['lat'] : null;
            $lon = isset($row['lon']) ? (float) $row['lon'] : null;
            if ($lat === null || $lon === null) {
                continue;
            }

            if ($jitter > 0.0) {
                [$lat, $lon] = $this->applyDeterministicJitter(
                    $lat,
                    $lon,
                    $jitter,
                    $seedPrefix . '|' . (string) ($row['bucket_key'] ?? ''),
                );
            }

            $points[] = [
                'lat' => round(max(-90.0, min(90.0, $lat)), 4),
                'lon' => round(max(-180.0, min(180.0, $lon)), 4),
                'count' => $count,
            ];
        }

        return $points;
    }

    private function applyDeterministicJitter(float $lat, float $lon, float $maxOffset, string $seed): array
    {
        $hash = sha1($seed);
        $latFrac = hexdec(substr($hash, 0, 8)) / 0xffffffff;
        $lonFrac = hexdec(substr($hash, 8, 8)) / 0xffffffff;
        $lat += ($latFrac * 2.0 - 1.0) * $maxOffset;
        $lon += ($lonFrac * 2.0 - 1.0) * $maxOffset;
        return [$lat, $lon];
    }

    private function rangeStart(string $range): ?int
    {
        $range = $this->sanitizeEnum($range, ['all', 'today', '7d', '30d'], '30d');
        if ($range === 'all') {
            return null;
        }

        $tz = new DateTimeZone(date_default_timezone_get() ?: 'UTC');
        $now = new DateTimeImmutable('now', $tz);
        if ($range === 'today') {
            return $now->setTime(0, 0, 0)->getTimestamp();
        }
        if ($range === '7d') {
            return $now->modify('-7 days')->getTimestamp();
        }
        return $now->modify('-30 days')->getTimestamp();
    }

    private function formatCompact(int $count): string
    {
        if ($count < 1000) {
            return (string) $count;
        }
        if ($count < 1000000) {
            $value = round($count / 1000, $count < 10000 ? 1 : 0);
            return rtrim(rtrim((string) $value, '0'), '.') . 'k';
        }
        $value = round($count / 1000000, $count < 10000000 ? 1 : 0);
        return rtrim(rtrim((string) $value, '0'), '.') . 'm';
    }

    private function formatRounded(int $count): string
    {
        if ($count < 1000) {
            return $count < 10 ? (string) $count : ((string) (int) floor($count / 10) * 10) . '+';
        }
        $digits = strlen((string) $count);
        $magnitude = (int) pow(10, max(0, $digits - 1));
        $rounded = (int) floor($count / $magnitude) * $magnitude;
        return $this->formatCompact($rounded) . '+';
    }

    private function cacheDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private function cacheGet(string $key, int $ttl): mixed
    {
        $file = $this->cacheDir() . '/public_widgets_' . sha1($key) . '.json';
        if (!is_file($file)) {
            return null;
        }
        if (filemtime($file) + $ttl < time()) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        return $data['payload'] ?? null;
    }

    private function cacheSet(string $key, mixed $payload): void
    {
        $file = $this->cacheDir() . '/public_widgets_' . sha1($key) . '.json';
        @file_put_contents(
            $file,
            json_encode(
                ['payload' => $payload],
                JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            ),
            LOCK_EX,
        );
    }
}
