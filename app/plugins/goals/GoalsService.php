<?php
declare(strict_types=1);

namespace TrackEm\Plugins\Goals;

use DateTimeImmutable;
use DateTimeZone;
use TrackEm\Core\DB;
use TrackEm\Core\Security;

final class GoalsService
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

    public function rollupsPath(): string
    {
        return $this->storageDir() . '/rollups.json';
    }

    public function storageDir(): string
    {
        $dir = dirname(__DIR__, 3) . '/storage/plugins/' . $this->pluginId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
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

    public function eventTrackingAvailable(): bool
    {
        return is_file(dirname(__DIR__) . '/event_tracking/plugin.json');
    }

    public function eventTrackingStorageDir(): string
    {
        return dirname(__DIR__, 3) . '/storage/plugins/event_tracking';
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
            'goals' => $this->sanitizeGoals($src['goals'] ?? []),
        ];
    }

    public function report(array $config): array
    {
        if (!$this->isPluginEnabled() || empty($config['enabled'])) {
            return [
                'range' => (string) ($config['report_range'] ?? '30d'),
                'totals' => ['visits' => 0, 'completions' => 0],
                'goals' => [],
                'top_paths' => [],
                'trend' => [],
                'event_tracking_available' => $this->eventTrackingAvailable(),
                'notes' => ['Goals plugin is disabled.'],
            ];
        }

        $range = (string) ($config['report_range'] ?? '30d');
        $cacheKey = sha1(
            json_encode(
                [
                    'range' => $range,
                    'goals' => $config['goals'] ?? [],
                    'event_available' => $this->eventTrackingAvailable(),
                ],
                JSON_UNESCAPED_SLASHES,
            ),
        );
        $cached = $this->loadCachedReport($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $goals = [];
        foreach ($config['goals'] ?? [] as $goal) {
            if (!is_array($goal)) {
                continue;
            }
            $goals[(string) $goal['id']] = [
                'id' => (string) $goal['id'],
                'name' => (string) $goal['name'],
                'type' => (string) $goal['type'],
                'match_value' => (string) $goal['match_value'],
                'label_match' => (string) ($goal['label_match'] ?? ''),
                'active' => !empty($goal['active']),
                'completions' => 0,
                'conversion_rate' => 0.0,
                'event_unavailable' => false,
            ];
        }

        $since = $this->rangeStart($range);
        $notes = [];
        $visitCount = 0;
        $totalCompletions = 0;
        $topPaths = [];
        $trend = [];

        foreach ($this->iterateVisits($since) as $visit) {
            $visitCount++;
            $path = $this->normalizePath((string) ($visit['path'] ?? ''));
            $day = gmdate('Y-m-d', (int) ($visit['ts'] ?? 0));
            foreach ($goals as $id => $goal) {
                if (!$goal['active']) {
                    continue;
                }
                if ($goal['type'] === 'event_name') {
                    continue;
                }
                if ($this->pathGoalMatches($goal, $path)) {
                    $goals[$id]['completions']++;
                    $totalCompletions++;
                    $topPaths[$path] = ($topPaths[$path] ?? 0) + 1;
                    $trend[$day] = ($trend[$day] ?? 0) + 1;
                }
            }
        }

        $eventAvailable = $this->eventTrackingAvailable();
        if ($eventAvailable) {
            foreach ($this->iterateEventRows($since) as $event) {
                $eventName = strtolower(trim((string) ($event['event'] ?? '')));
                $label = strtolower(trim((string) ($event['label'] ?? '')));
                $path = $this->normalizePath((string) ($event['path'] ?? ''));
                $day = gmdate('Y-m-d', (int) ($event['ts'] ?? 0));

                foreach ($goals as $id => $goal) {
                    if (!$goal['active'] || $goal['type'] !== 'event_name') {
                        continue;
                    }
                    if ($eventName !== strtolower($goal['match_value'])) {
                        continue;
                    }
                    $labelMatch = strtolower(trim((string) $goal['label_match']));
                    if ($labelMatch !== '' && strpos($label, $labelMatch) === false) {
                        continue;
                    }
                    $goals[$id]['completions']++;
                    $totalCompletions++;
                    $topPaths[$path !== '' ? $path : '[no path]'] = ($topPaths[$path !== '' ? $path : '[no path]'] ?? 0) + 1;
                    $trend[$day] = ($trend[$day] ?? 0) + 1;
                }
            }
        } else {
            foreach ($goals as $id => $goal) {
                if ($goal['type'] === 'event_name') {
                    $goals[$id]['event_unavailable'] = true;
                }
            }
            if ($this->hasEventGoals($goals)) {
                $notes[] = 'Event-based goals are unavailable because the event_tracking plugin is not installed.';
            }
        }

        foreach ($goals as $id => $goal) {
            $goals[$id]['conversion_rate'] = $visitCount > 0
                ? round(($goal['completions'] / $visitCount) * 100, 2)
                : 0.0;
        }

        arsort($topPaths);
        ksort($trend);

        $payload = [
            'range' => $range,
            'totals' => [
                'visits' => $visitCount,
                'completions' => $totalCompletions,
            ],
            'goals' => array_values($goals),
            'top_paths' => $this->sliceAssoc($topPaths, 12),
            'trend' => $this->trendRows($trend),
            'event_tracking_available' => $eventAvailable,
            'notes' => $notes,
        ];

        $this->cacheReport($cacheKey, $payload);
        return $payload;
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

    private function sanitizeGoals(mixed $goals): array
    {
        if (!is_array($goals)) {
            return [];
        }
        $out = [];
        foreach ($goals as $goal) {
            if (!is_array($goal)) {
                continue;
            }
            $name = $this->sanitizeText((string) ($goal['name'] ?? ''), 80);
            $type = $this->sanitizeEnum(
                (string) ($goal['type'] ?? 'exact_path'),
                ['path_match', 'exact_path', 'contains_path', 'event_name'],
                'exact_path',
            );
            $matchValue = $this->sanitizeText(
                (string) ($goal['match_value'] ?? ''),
                255,
            );
            if ($name === '' || $matchValue === '') {
                continue;
            }
            $out[] = [
                'id' => $this->sanitizeGoalId((string) ($goal['id'] ?? '')),
                'name' => $name,
                'type' => $type,
                'match_value' => $type === 'event_name'
                    ? strtolower($matchValue)
                    : $this->normalizePathOrPattern($matchValue, $type),
                'label_match' => $type === 'event_name'
                    ? $this->sanitizeText(
                        (string) ($goal['label_match'] ?? ''),
                        100,
                    )
                    : '',
                'active' => $this->toBool($goal['active'] ?? false),
            ];
        }
        return $out;
    }

    private function sanitizeGoalId(string $id): string
    {
        $id = strtolower(trim($id));
        $id = preg_replace('/[^a-z0-9_-]/', '', $id) ?? '';
        if ($id !== '') {
            return substr($id, 0, 32);
        }
        return 'goal_' . substr(sha1((string) microtime(true) . random_int(1, 999999)), 0, 12);
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
        return substr($path, 0, 255);
    }

    private function normalizePathOrPattern(string $value, string $type): string
    {
        $value = trim(strip_tags($value));
        if ($value === '') {
            return '';
        }
        if ($type === 'contains_path') {
            return substr($value, 0, 255);
        }
        if ($type === 'path_match') {
            if ($value[0] !== '/') {
                $value = '/' . $value;
            }
            return substr($value, 0, 255);
        }
        return $this->normalizePath($value);
    }

    private function rangeStart(string $range): ?int
    {
        $range = $this->sanitizeEnum($range, ['today', '7d', '30d', 'all'], '30d');
        if ($range === 'all') {
            return null;
        }
        $tz = new DateTimeZone(date_default_timezone_get() ?: 'UTC');
        $now = new DateTimeImmutable('now', $tz);
        return match ($range) {
            'today' => $now->setTime(0, 0, 0)->getTimestamp(),
            '7d' => $now->modify('-7 days')->getTimestamp(),
            default => $now->modify('-30 days')->getTimestamp(),
        };
    }

    private function iterateVisits(?int $since): \Generator
    {
        $sql = 'SELECT path, ts FROM visits';
        $params = [];
        if ($since !== null) {
            $sql .= ' WHERE ts >= ?';
            $params[] = $since;
        }
        $sql .= ' ORDER BY ts ASC';
        $st = DB::pdo()->prepare($sql);
        $st->execute($params);
        while ($row = $st->fetch()) {
            yield $row;
        }
    }

    private function iterateEventRows(?int $since): \Generator
    {
        $dir = $this->eventTrackingStorageDir();
        if (!is_dir($dir)) {
            return;
        }
        $files = [];
        if ($since === null) {
            $files = glob($dir . '/events-*.jsonl') ?: [];
            sort($files);
        } else {
            foreach ($this->monthsBetween($since, time()) as $month) {
                $files[] = $dir . '/events-' . $month . '.jsonl';
            }
        }

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $fh = @fopen($file, 'rb');
            if (!$fh) {
                continue;
            }
            while (($line = fgets($fh)) !== false) {
                $row = json_decode(trim($line), true);
                if (!is_array($row)) {
                    continue;
                }
                if ($since !== null && (int) ($row['ts'] ?? 0) < $since) {
                    continue;
                }
                yield $row;
            }
            fclose($fh);
        }
    }

    private function monthsBetween(int $since, int $until): array
    {
        $tz = new DateTimeZone('UTC');
        $start = (new DateTimeImmutable('@' . $since, $tz))->modify('first day of this month')->setTime(0, 0);
        $end = (new DateTimeImmutable('@' . $until, $tz))->modify('first day of this month')->setTime(0, 0);
        $months = [];
        while ($start <= $end) {
            $months[] = $start->format('Y-m');
            $start = $start->modify('+1 month');
        }
        return $months;
    }

    private function pathGoalMatches(array $goal, string $path): bool
    {
        return match ($goal['type']) {
            'exact_path' => $path === (string) $goal['match_value'],
            'contains_path' => strpos($path, (string) $goal['match_value']) !== false,
            'path_match' => fnmatch((string) $goal['match_value'], $path),
            default => false,
        };
    }

    private function hasEventGoals(array $goals): bool
    {
        foreach ($goals as $goal) {
            if (($goal['type'] ?? '') === 'event_name') {
                return true;
            }
        }
        return false;
    }

    private function sliceAssoc(array $items, int $limit): array
    {
        $out = [];
        $i = 0;
        foreach ($items as $key => $value) {
            if ($i++ >= $limit) {
                break;
            }
            $out[] = ['name' => (string) $key, 'count' => (int) $value];
        }
        return $out;
    }

    private function trendRows(array $trend): array
    {
        $out = [];
        foreach ($trend as $day => $count) {
            $out[] = ['day' => (string) $day, 'count' => (int) $count];
        }
        return $out;
    }

    private function loadCachedReport(string $cacheKey): ?array
    {
        $path = $this->rollupsPath();
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || ($data['key'] ?? '') !== $cacheKey) {
            return null;
        }
        if ((int) ($data['generated_at'] ?? 0) + 60 < time()) {
            return null;
        }
        return isset($data['payload']) && is_array($data['payload'])
            ? $data['payload']
            : null;
    }

    private function cacheReport(string $cacheKey, array $payload): void
    {
        @file_put_contents(
            $this->rollupsPath(),
            json_encode(
                [
                    'key' => $cacheKey,
                    'generated_at' => time(),
                    'payload' => $payload,
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ),
            LOCK_EX,
        );
    }
}
