<?php
declare(strict_types=1);

namespace TrackEm\Plugins\ContentGroups;

use TrackEm\Core\DB;
use TrackEm\Core\Security;

final class ContentGroupsService
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
        $maxGroups = max(1, min(100, (int) ($src['max_groups'] ?? 40)));
        return [
            'enabled' => $this->toBool($src['enabled'] ?? false),
            'report_range' => $this->sanitizeEnum(
                (string) ($src['report_range'] ?? '30d'),
                ['today', '7d', '30d', 'all'],
                '30d',
            ),
            'max_groups' => $maxGroups,
            'groups' => $this->sanitizeGroups($src['groups'] ?? [], $maxGroups),
        ];
    }

    public function report(array $config, bool $forceRefresh = false): array
    {
        if (!$this->isPluginEnabled() || empty($config['enabled'])) {
            return [
                'range' => (string) ($config['report_range'] ?? '30d'),
                'summary' => [
                    'visits' => 0,
                    'matched_visits' => 0,
                    'unmatched_visits' => 0,
                ],
                'groups' => [],
                'top_unmatched_paths' => [],
                'notes' => ['Content Groups is disabled.'],
            ];
        }

        $cacheKey = sha1(
            json_encode(
                [
                    'range' => $config['report_range'] ?? '30d',
                    'groups' => $config['groups'] ?? [],
                    'source' => $this->sourceFingerprint((string) ($config['report_range'] ?? '30d')),
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

        $range = (string) ($config['report_range'] ?? '30d');
        $since = $this->rangeStart($range);
        $groups = $this->seedGroups($config['groups'] ?? []);
        $totalVisits = 0;
        $matchedVisits = 0;
        $unmatchedPaths = [];

        foreach ($this->iterateVisits($since) as $visit) {
            $totalVisits++;
            $path = $this->normalizePath((string) ($visit['path'] ?? ''));
            $matchedId = $this->matchGroupId($path, $groups);
            if ($matchedId === null) {
                $unmatchedPaths[$path] = ($unmatchedPaths[$path] ?? 0) + 1;
                continue;
            }
            $matchedVisits++;
            $groups[$matchedId]['visits']++;
            $groups[$matchedId]['top_paths'][$path] = ($groups[$matchedId]['top_paths'][$path] ?? 0) + 1;
        }

        $groupRows = [];
        foreach ($groups as $group) {
            arsort($group['top_paths']);
            $groupRows[] = [
                'id' => $group['id'],
                'name' => $group['name'],
                'match_type' => $group['match_type'],
                'rule' => $group['rule'],
                'active' => $group['active'],
                'visits' => $group['visits'],
                'share' => $totalVisits > 0 ? round(($group['visits'] / $totalVisits) * 100, 2) : 0.0,
                'top_paths' => $this->sliceAssoc($group['top_paths'], 5),
            ];
        }

        usort(
            $groupRows,
            static fn(array $a, array $b): int => (int) ($b['visits'] ?? 0) <=> (int) ($a['visits'] ?? 0),
        );
        arsort($unmatchedPaths);

        $payload = [
            'range' => $range,
            'summary' => [
                'visits' => $totalVisits,
                'matched_visits' => $matchedVisits,
                'unmatched_visits' => max(0, $totalVisits - $matchedVisits),
            ],
            'groups' => $groupRows,
            'top_unmatched_paths' => $this->sliceAssoc($unmatchedPaths, 12),
            'notes' => [
                'Groups are matched in the order listed. The first matching active rule wins.',
                'Use broad groups sparingly so narrower groups can match first.',
            ],
        ];

        $this->saveCache($cacheKey, $payload);
        return $payload;
    }

    public function matchTypes(): array
    {
        return ['wildcard', 'exact', 'contains', 'prefix'];
    }

    private function sanitizeGroups(mixed $groups, int $maxGroups): array
    {
        if (!is_array($groups)) {
            return [];
        }
        $out = [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $name = $this->sanitizeText((string) ($group['name'] ?? ''), 80);
            $rule = $this->sanitizeRule((string) ($group['rule'] ?? ''));
            if ($name === '' || $rule === '') {
                continue;
            }
            $out[] = [
                'id' => $this->sanitizeId((string) ($group['id'] ?? '')),
                'name' => $name,
                'match_type' => $this->sanitizeEnum(
                    (string) ($group['match_type'] ?? 'wildcard'),
                    $this->matchTypes(),
                    'wildcard',
                ),
                'rule' => $rule,
                'active' => $this->toBool($group['active'] ?? false),
            ];
            if (count($out) >= $maxGroups) {
                break;
            }
        }
        return $out;
    }

    private function seedGroups(array $groups): array
    {
        $out = [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $id = (string) ($group['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $out[$id] = [
                'id' => $id,
                'name' => (string) ($group['name'] ?? ''),
                'match_type' => (string) ($group['match_type'] ?? 'wildcard'),
                'rule' => (string) ($group['rule'] ?? ''),
                'active' => !empty($group['active']),
                'visits' => 0,
                'top_paths' => [],
            ];
        }
        return $out;
    }

    private function matchGroupId(string $path, array $groups): ?string
    {
        foreach ($groups as $id => $group) {
            if (empty($group['active'])) {
                continue;
            }
            if ($this->pathMatchesRule($path, (string) $group['rule'], (string) $group['match_type'])) {
                return (string) $id;
            }
        }
        return null;
    }

    private function pathMatchesRule(string $path, string $rule, string $type): bool
    {
        if ($path === '' || $rule === '') {
            return false;
        }
        return match ($type) {
            'exact' => $path === $rule,
            'contains' => strpos($path, $rule) !== false,
            'prefix' => str_starts_with($path, $rule),
            default => fnmatch($rule, $path),
        };
    }

    private function iterateVisits(?int $since): \Generator
    {
        try {
            if ($since === null) {
                $st = DB::pdo()->query(
                    'SELECT path, ts FROM visits ORDER BY ts DESC LIMIT 25000',
                );
            } else {
                $st = DB::pdo()->prepare(
                    'SELECT path, ts FROM visits WHERE ts >= ? ORDER BY ts DESC LIMIT 25000',
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

    private function sanitizeText(string $value, int $maxLen): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $maxLen)
            : substr($value, 0, $maxLen);
    }

    private function sanitizeRule(string $rule): string
    {
        $rule = trim(strip_tags($rule));
        $rule = preg_replace('#/+#', '/', $rule) ?? '';
        if ($rule === '') {
            return '';
        }
        if ($rule[0] !== '/' && strpos($rule, '*') !== 0) {
            $rule = '/' . $rule;
        }
        return substr($rule, 0, 255);
    }

    private function sanitizeId(string $id): string
    {
        $id = strtolower(trim($id));
        $id = preg_replace('/[^a-z0-9_-]/', '', $id) ?? '';
        if ($id !== '') {
            return substr($id, 0, 40);
        }
        return 'grp_' . substr(sha1((string) microtime(true) . random_int(1, 999999)), 0, 12);
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        $parts = @parse_url($path);
        if (is_array($parts)) {
            $path = (string) ($parts['path'] ?? '');
        }
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
