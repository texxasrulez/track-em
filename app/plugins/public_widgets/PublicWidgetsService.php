<?php
declare(strict_types=1);

namespace TrackEm\Plugins\PublicWidgets;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TrackEm\Core\DB;
use TrackEm\Core\Security;
use ZipArchive;

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

    public function storageDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/plugins/' . $this->pluginId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public function digitThemesStorageDir(): string
    {
        $dir = $this->storageDir() . '/digit_themes';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public function builtInDigitThemesDir(): string
    {
        return $this->pluginDir . '/assets/digit_themes';
    }

    public function zipUploadAvailable(): bool
    {
        return class_exists(ZipArchive::class);
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
            'display_mode' => $this->sanitizeEnum(
                (string) ($counter['display_mode'] ?? 'text'),
                ['text', 'image_digits'],
                'text',
            ),
            'digit_theme' => $this->resolveActiveDigitThemeId($config),
            'digit_height' => max(12, min(128, (int) ($counter['digit_height'] ?? 24))),
            'digit_url_base' => $this->routeUrl('public_widgets.digit', [
                'id' => $this->resolveActiveDigitThemeId($config),
            ]),
        ];
    }

    public function counterPreviewContext(array $config): array
    {
        $counter = $config['counter'] ?? [];
        return [
            'display_mode' => $this->sanitizeEnum(
                (string) ($counter['display_mode'] ?? 'text'),
                ['text', 'image_digits'],
                'text',
            ),
            'digit_theme' => $this->resolveActiveDigitThemeId($config),
            'digit_height' => max(12, min(128, (int) ($counter['digit_height'] ?? 24))),
            'digits' => str_split('0123456789'),
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
        $themeId = $this->sanitizeThemeId((string) ($src['counter_digit_theme'] ?? 'default'));
        if (!$this->digitThemeExists($themeId)) {
            $themeId = 'default';
        }
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
                'display_mode' => $this->sanitizeEnum(
                    (string) ($src['counter_display_mode'] ?? 'text'),
                    ['text', 'image_digits'],
                    'text',
                ),
                'digit_theme' => $themeId,
                'digit_height' => max(
                    12,
                    min(128, (int) ($src['counter_digit_height'] ?? 24)),
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

    public function digitThemes(): array
    {
        $themes = [];
        foreach ($this->builtInDigitThemes() as $theme) {
            $themes[$theme['id']] = $theme + ['source' => 'built_in', 'deletable' => false];
        }
        foreach ($this->uploadedDigitThemes() as $theme) {
            $themes[$theme['id']] = $theme + ['source' => 'uploaded', 'deletable' => true];
        }
        uasort(
            $themes,
            static function (array $a, array $b): int {
                if (($a['source'] ?? '') !== ($b['source'] ?? '')) {
                    return ($a['source'] ?? '') === 'built_in' ? -1 : 1;
                }
                return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            },
        );
        return array_values($themes);
    }

    public function resolveDigitThemeFile(string $themeId, string $digit): ?string
    {
        $themeId = $this->sanitizeThemeId($themeId);
        if ($themeId === '' || preg_match('/^[0-9]$/', $digit) !== 1) {
            return null;
        }
        foreach ($this->digitThemes() as $theme) {
            if (($theme['id'] ?? '') !== $themeId) {
                continue;
            }
            $base = (string) ($theme['path'] ?? '');
            if ($base === '') {
                return null;
            }
            $path = $base . '/' . $digit . '.png';
            return is_file($path) ? $path : null;
        }
        return null;
    }

    public function uploadDigitTheme(array $post, array $files, array $config): array
    {
        if (!$this->zipUploadAvailable()) {
            throw new \RuntimeException('zip_unavailable');
        }
        $name = $this->sanitizeText((string) ($post['digit_theme_name'] ?? ''), 80, '');
        if ($name === '') {
            throw new \RuntimeException('theme_name_invalid');
        }
        $upload = $files['digit_theme_zip'] ?? null;
        if (!is_array($upload)) {
            throw new \RuntimeException('zip_missing');
        }
        $uploadError = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->uploadErrorCode($uploadError));
        }
        $tmpPath = (string) ($upload['tmp_name'] ?? '');
        $originalName = (string) ($upload['name'] ?? '');
        $size = (int) ($upload['size'] ?? 0);
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            if (!is_file($tmpPath)) {
                throw new \RuntimeException('zip_missing');
            }
        }
        if (!preg_match('/\.zip$/i', $originalName)) {
            throw new \RuntimeException('zip_invalid');
        }
        if ($size <= 0 || $size > 2 * 1024 * 1024) {
            throw new \RuntimeException('zip_too_large');
        }

        $themeId = $this->uniqueUploadedThemeId($name);
        if ($themeId === '') {
            throw new \RuntimeException('theme_id_conflict');
        }

        $tempDir = $this->makeTempDir();
        $finalDir = $this->digitThemesStorageDir() . '/' . $themeId;
        try {
            $digits = $this->validateDigitZip($tmpPath);
            @mkdir($tempDir, 0775, true);
            foreach ($digits as $digit => $payload) {
                @file_put_contents($tempDir . '/' . $digit . '.png', $payload, LOCK_EX);
                @chmod($tempDir . '/' . $digit . '.png', 0644);
            }
            @file_put_contents(
                $tempDir . '/manifest.json',
                json_encode(
                    [
                        'id' => $themeId,
                        'name' => $name,
                        'format' => 'png',
                        'created_at' => gmdate('Y-m-d'),
                    ],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
                ),
                LOCK_EX,
            );
            @chmod($tempDir . '/manifest.json', 0644);
            if (is_dir($finalDir)) {
                throw new \RuntimeException('theme_id_conflict');
            }
            if (!@rename($tempDir, $finalDir)) {
                @mkdir($finalDir, 0775, true);
                foreach (array_merge(range(0, 9), ['manifest']) as $entry) {
                    $namePart = $entry === 'manifest' ? 'manifest.json' : $entry . '.png';
                    @copy($tempDir . '/' . $namePart, $finalDir . '/' . $namePart);
                }
                $this->removeTree($tempDir);
            }
        } catch (\RuntimeException $e) {
            $this->removeTree($tempDir);
            throw $e;
        } catch (\Throwable) {
            $this->removeTree($tempDir);
            throw new \RuntimeException('theme_upload_failed');
        }

        return [
            'theme_id' => $themeId,
            'name' => $name,
        ];
    }

    public function deleteDigitTheme(string $themeId, array $config): array
    {
        $themeId = $this->sanitizeThemeId($themeId);
        if ($themeId === '' || $themeId === 'default') {
            throw new \RuntimeException('built_in_theme_protected');
        }
        foreach ($this->builtInDigitThemes() as $theme) {
            if (($theme['id'] ?? '') === $themeId) {
                throw new \RuntimeException('built_in_theme_protected');
            }
        }
        if ($this->resolveActiveDigitThemeId($config) === $themeId) {
            throw new \RuntimeException('active_theme_protected');
        }
        $dir = $this->digitThemesStorageDir() . '/' . $themeId;
        if (!is_dir($dir)) {
            throw new \RuntimeException('theme_not_found');
        }
        $this->removeTree($dir);
        return ['theme_id' => $themeId];
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

    private function sanitizeThemeId(string $id): string
    {
        $id = strtolower(trim($id));
        $id = preg_replace('/[^a-z0-9_-]+/', '-', $id) ?? '';
        $id = trim($id, '-_');
        return $id !== '' ? substr($id, 0, 48) : '';
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

    private function resolveActiveDigitThemeId(array $config): string
    {
        $themeId = $this->sanitizeThemeId((string) (($config['counter']['digit_theme'] ?? 'default')));
        if ($themeId === '' || !$this->digitThemeExists($themeId)) {
            return 'default';
        }
        return $themeId;
    }

    private function digitThemeExists(string $themeId): bool
    {
        foreach ($this->digitThemes() as $theme) {
            if (($theme['id'] ?? '') === $themeId) {
                return true;
            }
        }
        return false;
    }

    private function builtInDigitThemes(): array
    {
        $dir = $this->builtInDigitThemesDir();
        if (!is_dir($dir)) {
            return [];
        }
        $themes = [];
        foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $themeDir) {
            $manifest = $this->loadThemeManifest($themeDir . '/manifest.json');
            if ($manifest === null || !$this->validateThemeDigitsExist($themeDir)) {
                continue;
            }
            $themes[] = $manifest + ['path' => $themeDir];
        }
        return $themes;
    }

    private function uploadedDigitThemes(): array
    {
        $dir = $this->digitThemesStorageDir();
        $themes = [];
        foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $themeDir) {
            $manifest = $this->loadThemeManifest($themeDir . '/manifest.json');
            if ($manifest === null || !$this->validateThemeDigitsExist($themeDir)) {
                continue;
            }
            $themes[] = $manifest + ['path' => $themeDir];
        }
        return $themes;
    }

    private function loadThemeManifest(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            return null;
        }
        $id = $this->sanitizeThemeId((string) ($data['id'] ?? ''));
        $name = $this->sanitizeText((string) ($data['name'] ?? ''), 80, '');
        if ($id === '' || $name === '') {
            return null;
        }
        return [
            'id' => $id,
            'name' => $name,
            'format' => 'png',
            'created_at' => (string) ($data['created_at'] ?? ''),
        ];
    }

    private function validateThemeDigitsExist(string $dir): bool
    {
        for ($i = 0; $i <= 9; $i++) {
            if (!is_file($dir . '/' . $i . '.png')) {
                return false;
            }
        }
        return true;
    }

    private function uniqueUploadedThemeId(string $name): string
    {
        $base = $this->sanitizeThemeId($name);
        if ($base === '' || $base === 'default') {
            $base = 'theme';
        }
        $ids = [];
        foreach ($this->digitThemes() as $theme) {
            $ids[(string) ($theme['id'] ?? '')] = true;
        }
        if (!isset($ids[$base])) {
            return $base;
        }
        for ($i = 0; $i < 20; $i++) {
            $candidate = substr($base, 0, 40) . '-' . substr(sha1($base . '|' . microtime(true) . '|' . $i), 0, 6);
            if (!isset($ids[$candidate])) {
                return $candidate;
            }
        }
        return '';
    }

    private function makeTempDir(): string
    {
        $base = rtrim(sys_get_temp_dir(), '/\\') . '/trackem_public_widgets_' . bin2hex(random_bytes(8));
        return $base;
    }

    private function uploadErrorCode(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'zip_too_large',
            UPLOAD_ERR_PARTIAL => 'zip_upload_incomplete',
            UPLOAD_ERR_NO_FILE => 'zip_missing',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'theme_upload_failed',
            default => 'theme_upload_failed',
        };
    }

    private function validateDigitZip(string $tmpPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            throw new \RuntimeException('zip_invalid');
        }

        $allowed = [];
        for ($i = 0; $i <= 9; $i++) {
            $allowed[$i . '.png'] = true;
        }
        $found = [];
        $out = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if ($name === '' || str_contains($name, "\0") || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, '..') || str_starts_with($name, '.')) {
                $zip->close();
                throw new \RuntimeException('zip_nested_path');
            }
            if (!isset($allowed[$name])) {
                $zip->close();
                throw new \RuntimeException('zip_extra_files');
            }
            $stat = $zip->statIndex($i);
            $size = (int) ($stat['size'] ?? 0);
            if ($size <= 0 || $size > 100 * 1024) {
                $zip->close();
                throw new \RuntimeException('digit_file_too_large');
            }
            $bytes = $zip->getFromIndex($i);
            if (!is_string($bytes) || $bytes === '') {
                $zip->close();
                throw new \RuntimeException('png_invalid');
            }
            $found[$name] = true;
            $out[(int) $name[0]] = $this->validateDigitPng($bytes);
        }
        $zip->close();

        if (count($found) !== 10) {
            throw new \RuntimeException('zip_missing_digits');
        }
        foreach ($allowed as $name => $_) {
            if (!isset($found[$name])) {
                throw new \RuntimeException('zip_missing_digits');
            }
        }
        ksort($out);
        return $out;
    }

    private function validateDigitPng(string $bytes): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? (string) finfo_buffer($finfo, $bytes) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
            if ($mime !== '' && $mime !== 'image/png') {
                throw new \RuntimeException('png_invalid');
            }
        }

        $imageInfo = @getimagesizefromstring($bytes);
        if (!is_array($imageInfo) || (int) ($imageInfo[2] ?? 0) !== IMAGETYPE_PNG) {
            throw new \RuntimeException('png_invalid');
        }
        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        if ($width < 1 || $height < 1 || $width > 128 || $height > 128) {
            throw new \RuntimeException('png_dimensions_invalid');
        }

        if (extension_loaded('gd')) {
            $image = @imagecreatefromstring($bytes);
            if (!$image) {
                throw new \RuntimeException('png_invalid');
            }
            ob_start();
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagepng($image);
            imagedestroy($image);
            $reencoded = (string) ob_get_clean();
            if ($reencoded === '' || strlen($reencoded) > 100 * 1024) {
                throw new \RuntimeException('digit_file_too_large');
            }
            return $reencoded;
        }

        return $bytes;
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
