<?php
declare(strict_types=1);

namespace TrackEm\Plugins\FunnelReports;

use DateTimeImmutable;
use DateTimeZone;
use TrackEm\Core\DB;
use TrackEm\Core\Security;

final class FunnelReportsService
{
    private const CACHE_TTL = 300;
    private const MAX_FUNNELS = 25;
    private const MAX_STEPS = 12;

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
        @unlink($this->cachePath());
        return $config;
    }

    public function resetConfig(): void
    {
        @unlink($this->configPath());
        @unlink($this->cachePath());
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
            $st = DB::pdo()->prepare('SELECT enabled FROM plugins WHERE id = ? LIMIT 1');
            $st->execute([$this->pluginId]);
            $row = $st->fetch();
            return $row ? (int) ($row['enabled'] ?? 0) === 1 : false;
        } catch (\Throwable) {
            return false;
        }
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
            'funnels' => $this->sanitizeFunnels($src['funnels'] ?? []),
        ];
    }

    public function report(array $config, bool $forceRefresh = false): array
    {
        $range = (string) ($config['report_range'] ?? '30d');
        if (!$this->isPluginEnabled() || empty($config['enabled'])) {
            return [
                'range' => $range,
                'funnels' => [],
                'event_tracking_available' => $this->eventTrackingAvailable(),
                'notes' => ['Funnel Reports is disabled.'],
            ];
        }

        $cacheKey = sha1(json_encode([
            'range' => $range,
            'funnels' => $config['funnels'] ?? [],
            'event' => $this->eventTrackingAvailable(),
            'source' => $this->sourceFingerprint($range),
        ], JSON_UNESCAPED_SLASHES));

        if (!$forceRefresh) {
            $cached = $this->loadCache($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $funnels = [];
        foreach ((array) ($config['funnels'] ?? []) as $funnel) {
            if (!is_array($funnel) || empty($funnel['id'])) {
                continue;
            }
            $steps = is_array($funnel['steps'] ?? null) ? $funnel['steps'] : [];
            $funnels[(string) $funnel['id']] = [
                'id' => (string) $funnel['id'],
                'name' => (string) ($funnel['name'] ?? ''),
                'active' => !empty($funnel['active']),
                'steps' => array_map(
                    static fn(array $step): array => $step + ['count' => 0, 'drop_off' => 0, 'conversion_rate' => 0.0],
                    $steps,
                ),
                'conversion_rate' => 0.0,
                'final_count' => 0,
                'trend' => [],
                'event_unavailable' => false,
            ];
        }

        $since = $this->rangeStart($range);
        foreach ($this->iterateVisits($since) as $visit) {
            $path = $this->normalizePath((string) ($visit['path'] ?? ''));
            foreach ($funnels as $id => $funnel) {
                if (empty($funnel['active'])) {
                    continue;
                }
                foreach ($funnel['steps'] as $idx => $step) {
                    if (($step['type'] ?? '') === 'event_name') {
                        continue;
                    }
                    if ($this->pathMatchesStep($path, $step)) {
                        $funnels[$id]['steps'][$idx]['count']++;
                    }
                }
            }
        }

        $eventAvailable = $this->eventTrackingAvailable();
        if ($eventAvailable) {
            foreach ($this->iterateEventRows($since) as $event) {
                $eventName = strtolower(trim((string) ($event['event'] ?? '')));
                $label = strtolower(trim((string) ($event['label'] ?? '')));
                $day = gmdate('Y-m-d', (int) ($event['ts'] ?? 0));
                foreach ($funnels as $id => $funnel) {
                    if (empty($funnel['active'])) {
                        continue;
                    }
                    $lastEventStepMatched = false;
                    foreach ($funnel['steps'] as $idx => $step) {
                        if (($step['type'] ?? '') !== 'event_name') {
                            continue;
                        }
                        if ($eventName !== strtolower((string) ($step['match_value'] ?? ''))) {
                            continue;
                        }
                        $labelMatch = strtolower(trim((string) ($step['label_match'] ?? '')));
                        if ($labelMatch !== '' && strpos($label, $labelMatch) === false) {
                            continue;
                        }
                        $funnels[$id]['steps'][$idx]['count']++;
                        $lastEventStepMatched = ($idx === array_key_last($funnel['steps']));
                    }
                    if ($lastEventStepMatched) {
                        $funnels[$id]['trend'][$day] = ($funnels[$id]['trend'][$day] ?? 0) + 1;
                    }
                }
            }
        } else {
            foreach ($funnels as $id => $funnel) {
                foreach ($funnel['steps'] as $step) {
                    if (($step['type'] ?? '') === 'event_name') {
                        $funnels[$id]['event_unavailable'] = true;
                        break;
                    }
                }
            }
        }

        foreach ($funnels as $id => $funnel) {
            $firstCount = (int) (($funnel['steps'][0]['count'] ?? 0));
            $prev = null;
            foreach ($funnel['steps'] as $idx => $step) {
                $count = (int) ($step['count'] ?? 0);
                $funnels[$id]['steps'][$idx]['drop_off'] = $prev === null ? 0 : max(0, $prev - $count);
                $funnels[$id]['steps'][$idx]['conversion_rate'] = $firstCount > 0
                    ? round(($count / $firstCount) * 100, 2)
                    : 0.0;
                $prev = $count;
            }
            $finalCount = !empty($funnel['steps']) ? (int) ($funnel['steps'][count($funnel['steps']) - 1]['count'] ?? 0) : 0;
            $funnels[$id]['final_count'] = $finalCount;
            $funnels[$id]['conversion_rate'] = $firstCount > 0
                ? round(($finalCount / $firstCount) * 100, 2)
                : 0.0;
            ksort($funnels[$id]['trend']);
            $funnels[$id]['trend'] = $this->trendRows($funnels[$id]['trend']);
        }

        $payload = [
            'range' => $range,
            'funnels' => array_values($funnels),
            'event_tracking_available' => $eventAvailable,
            'notes' => [
                'Funnels are aggregate step counts over the selected range, not stitched user journeys or sessions.',
                'Use path steps for low-cost page funnels and event steps only when the event_tracking plugin is installed.',
            ],
        ];

        $this->saveCache($cacheKey, $payload);
        return $payload;
    }

    private function sanitizeFunnels(mixed $funnels): array
    {
        if (!is_array($funnels)) {
            return [];
        }
        $out = [];
        foreach ($funnels as $funnel) {
            if (!is_array($funnel)) {
                continue;
            }
            $name = $this->sanitizeText((string) ($funnel['name'] ?? ''), 80);
            $stepsText = trim((string) ($funnel['steps_text'] ?? ''));
            if ($name === '' || $stepsText === '') {
                continue;
            }
            $steps = $this->parseStepsText($stepsText);
            if (count($steps) < 2) {
                continue;
            }
            $out[] = [
                'id' => $this->sanitizeId((string) ($funnel['id'] ?? '')),
                'name' => $name,
                'active' => $this->toBool($funnel['active'] ?? false),
                'steps_text' => $this->rebuildStepsText($steps),
                'steps' => $steps,
            ];
            if (count($out) >= self::MAX_FUNNELS) {
                break;
            }
        }
        return $out;
    }

    private function parseStepsText(string $text): array
    {
        $lines = preg_split('/\r?\n/', $text) ?: [];
        $steps = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 3) {
                continue;
            }
            $name = $this->sanitizeText((string) $parts[0], 80);
            $type = $this->sanitizeEnum((string) $parts[1], ['path_match', 'exact_path', 'contains_path', 'event_name'], 'exact_path');
            $matchValue = $this->sanitizeText((string) $parts[2], 255);
            $labelMatch = count($parts) >= 4 ? $this->sanitizeText((string) $parts[3], 100) : '';
            if ($name === '' || $matchValue === '') {
                continue;
            }
            $steps[] = [
                'name' => $name,
                'type' => $type,
                'match_value' => $type === 'event_name'
                    ? strtolower($matchValue)
                    : $this->normalizePathOrPattern($matchValue, $type),
                'label_match' => $type === 'event_name' ? strtolower($labelMatch) : '',
            ];
            if (count($steps) >= self::MAX_STEPS) {
                break;
            }
        }
        return $steps;
    }

    private function rebuildStepsText(array $steps): string
    {
        $lines = [];
        foreach ($steps as $step) {
            $line = [
                (string) ($step['name'] ?? ''),
                (string) ($step['type'] ?? ''),
                (string) ($step['match_value'] ?? ''),
            ];
            if (($step['type'] ?? '') === 'event_name') {
                $line[] = (string) ($step['label_match'] ?? '');
            }
            $lines[] = implode('|', $line);
        }
        return implode("\n", $lines);
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
        try {
            $st = DB::pdo()->prepare($sql);
            $st->execute($params);
            while ($row = $st->fetch()) {
                yield $row;
            }
        } catch (\Throwable) {
            return;
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

    private function pathMatchesStep(string $path, array $step): bool
    {
        return match ((string) ($step['type'] ?? '')) {
            'exact_path' => $path === (string) ($step['match_value'] ?? ''),
            'contains_path' => strpos($path, (string) ($step['match_value'] ?? '')) !== false,
            'path_match' => fnmatch((string) ($step['match_value'] ?? ''), $path),
            default => false,
        };
    }

    private function sourceFingerprint(string $range): array
    {
        $since = $this->rangeStart($range);
        try {
            if ($since === null) {
                $row = DB::pdo()->query('SELECT COUNT(*) AS c, MAX(ts) AS max_ts FROM visits')->fetch();
            } else {
                $st = DB::pdo()->prepare('SELECT COUNT(*) AS c, MAX(ts) AS max_ts FROM visits WHERE ts >= ?');
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
            json_encode(['key' => $key, 'ts' => time(), 'payload' => $payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX,
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

    private function sanitizeText(string $value, int $maxLen): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $maxLen) : substr($value, 0, $maxLen);
    }

    private function sanitizeEnum(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function sanitizeId(string $id): string
    {
        $id = strtolower(trim($id));
        $id = preg_replace('/[^a-z0-9_-]/', '', $id) ?? '';
        if ($id !== '') {
            return substr($id, 0, 40);
        }
        return 'fun_' . substr(sha1((string) microtime(true) . random_int(1, 999999)), 0, 12);
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
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

    private function normalizePathOrPattern(string $value, string $type): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if ($type === 'contains_path') {
            return substr($value, 0, 255);
        }
        if ($value[0] !== '/' && strpos($value, '*') !== 0) {
            $value = '/' . $value;
        }
        return substr($value, 0, 255);
    }

    private function trendRows(array $trend): array
    {
        $out = [];
        foreach ($trend as $day => $count) {
            $out[] = ['day' => (string) $day, 'count' => (int) $count];
        }
        return $out;
    }
}
