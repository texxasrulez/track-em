<?php
declare(strict_types=1);

namespace TrackEm\Plugins\BotWatch;

use TrackEm\Core\DB;
use TrackEm\Core\Security;

final class BotWatchService
{
    private const ANALYSIS_WINDOW = 86400;
    private const CACHE_TTL = 300;
    private const DETECTION_COOLDOWN = 21600;
    private const MAX_ROWS = 12000;

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

    public function clearCachedReport(): void
    {
        $state = $this->loadState();
        unset($state['cached_report'], $state['cached_report_fingerprint']);
        $state['last_scan_ts'] = 0;
        $this->saveState($state);
    }

    public function configPath(): string
    {
        return $this->storageDir() . '/config.json';
    }

    public function detectionsPath(): string
    {
        return $this->storageDir() . '/detections.jsonl';
    }

    public function statePath(): string
    {
        return $this->storageDir() . '/state.json';
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

    public function sanitizeConfig(array $src): array
    {
        return [
            'enabled' => $this->toBool($src['enabled'] ?? false),
            'sensitivity' => $this->sanitizeEnum(
                (string) ($src['sensitivity'] ?? 'normal'),
                ['low', 'normal', 'high'],
                'normal',
            ),
            'ignore_known_good_bots' => $this->toBool(
                $src['ignore_known_good_bots'] ?? false,
            ),
            'known_bot_allowlist' => $this->parseList(
                $src['known_bot_allowlist'] ?? '',
                40,
                80,
            ),
            'suspicious_path_patterns' => $this->parseList(
                $src['suspicious_path_patterns'] ?? '',
                60,
                120,
            ),
            'max_hits_per_minute_threshold' => max(
                5,
                min(500, (int) ($src['max_hits_per_minute_threshold'] ?? 20)),
            ),
            'status_404_threshold' => max(
                3,
                min(500, (int) ($src['status_404_threshold'] ?? 12)),
            ),
        ];
    }

    public function loadState(): array
    {
        $path = $this->statePath();
        if (!is_file($path)) {
            return $this->defaultState();
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            return $this->defaultState();
        }
        return $data + $this->defaultState();
    }

    public function saveState(array $state): void
    {
        @file_put_contents(
            $this->statePath(),
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX,
        );
    }

    public function report(array $config, bool $forceRefresh = false): array
    {
        if (!$this->isPluginEnabled() || empty($config['enabled'])) {
            return [
                'enabled' => false,
                'scan_window_hours' => 24,
                'status_data_available' => false,
                'summary' => [
                    'sources_flagged' => 0,
                    'recent_detections' => 0,
                    'analysis_rows' => 0,
                ],
                'suspicious_sources' => [],
                'suspicious_user_agents' => [],
                'suspicious_paths' => [],
                'top_patterns' => [],
                'recent_detections' => [],
                'score_legend' => $this->scoreLegend((string) ($config['sensitivity'] ?? 'normal')),
                'notes' => ['Bot Watch is disabled.'],
            ];
        }

        $state = $this->loadState();
        $fingerprint = $this->sourceFingerprint();
        if (
            !$forceRefresh &&
            is_array($state['cached_report'] ?? null) &&
            (string) ($state['cached_report_fingerprint'] ?? '') === $fingerprint &&
            (time() - (int) ($state['last_scan_ts'] ?? 0)) < self::CACHE_TTL
        ) {
            return $state['cached_report'];
        }

        $statusAvailable = $this->statusColumnAvailable();
        $rows = $this->loadRecentVisitRows($statusAvailable);
        $report = $this->buildReport($rows, $config, $statusAvailable, $state);
        $state['last_scan_ts'] = time();
        $state['cached_report_fingerprint'] = $fingerprint;
        $state['cached_report'] = $report;
        $state['status_column_available'] = $statusAvailable;
        $this->saveState($state);
        return $report;
    }

    public function recentDetections(int $limit = 20): array
    {
        $path = $this->detectionsPath();
        if (!is_file($path)) {
            return [];
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }
        $out = [];
        foreach (array_reverse(array_slice($lines, -1 * $limit)) as $line) {
            $row = json_decode((string) $line, true);
            if (is_array($row)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    private function buildReport(array $rows, array $config, bool $statusAvailable, array &$state): array
    {
        $sources = [];
        $uaTotals = [];
        $pathTotals = [];
        $patternTotals = [];
        $detections = [];

        foreach ($rows as $row) {
            $source = trim((string) ($row['ip'] ?? ''));
            if ($source === '') {
                continue;
            }

            $ua = $this->normalizeUserAgent((string) ($row['user_agent'] ?? ''));
            if (!isset($sources[$source])) {
                $sources[$source] = $this->blankSourceBucket($source, $ua);
            }

            $sources[$source]['hits']++;
            $sources[$source]['last_ts'] = max($sources[$source]['last_ts'], (int) ($row['ts'] ?? 0));
            $sources[$source]['first_ts'] = min(
                $sources[$source]['first_ts'] === 0 ? (int) ($row['ts'] ?? 0) : $sources[$source]['first_ts'],
                (int) ($row['ts'] ?? 0),
            );

            $path = $this->normalizePath((string) ($row['path'] ?? ''));
            if ($path !== '') {
                $sources[$source]['paths'][$path] = true;
                $sources[$source]['path_counts'][$path] = ($sources[$source]['path_counts'][$path] ?? 0) + 1;
            }

            $minuteKey = gmdate('Y-m-d H:i', (int) ($row['ts'] ?? 0));
            $sources[$source]['minute_hits'][$minuteKey] = ($sources[$source]['minute_hits'][$minuteKey] ?? 0) + 1;

            if ($ua === '') {
                $sources[$source]['empty_ua_hits']++;
            } elseif ($this->isScannerUserAgent($ua)) {
                $sources[$source]['scanner_ua_hits']++;
            } elseif ($this->isWeirdUserAgent($ua)) {
                $sources[$source]['weird_ua_hits']++;
            }

            if ($this->isProbePath($path, $config)) {
                $sources[$source]['probe_hits']++;
                $pathTotals[$path] = ($pathTotals[$path] ?? 0) + 1;
            }

            if ($statusAvailable && $this->is404Status($row['status'] ?? null)) {
                $sources[$source]['status_404_hits']++;
            }
        }

        foreach ($sources as $source => $bucket) {
            if (!empty($config['ignore_known_good_bots']) && $this->isAllowedUserAgent((string) $bucket['user_agent'], $config)) {
                continue;
            }

            $scored = $this->scoreSource($bucket, $config, $statusAvailable);
            if ($scored['score'] < $scored['threshold']) {
                continue;
            }

            $record = [
                'ts' => time(),
                'key' => 'source:' . sha1($source . '|' . implode('|', array_keys($scored['reason_map']))),
                'source' => $source,
                'source_range' => $this->sourceRangeLabel($source),
                'score' => $scored['score'],
                'severity' => $scored['severity'],
                'user_agent' => $this->truncate((string) $bucket['user_agent'], 180),
                'hits' => (int) $bucket['hits'],
                'max_hits_per_minute' => (int) $scored['max_hits_per_minute'],
                'unique_paths' => (int) $scored['unique_paths'],
                'probe_hits' => (int) $bucket['probe_hits'],
                'status_404_hits' => (int) $bucket['status_404_hits'],
                'reasons' => array_values($scored['reason_map']),
                'path_samples' => array_slice(array_keys($this->sortDescAssoc($bucket['path_counts'])), 0, 5),
            ];

            $uaKey = $record['user_agent'] !== '' ? $record['user_agent'] : '[empty user agent]';
            $uaTotals[$uaKey] = ($uaTotals[$uaKey] ?? 0) + 1;

            foreach ($record['reasons'] as $reason) {
                $patternTotals[$reason['code']] = ($patternTotals[$reason['code']] ?? 0) + 1;
            }

            $detections[] = $record;
        }

        usort($detections, static function (array $a, array $b): int {
            if ((int) $a['score'] === (int) $b['score']) {
                return (int) $b['hits'] <=> (int) $a['hits'];
            }
            return (int) $b['score'] <=> (int) $a['score'];
        });

        $newDetections = $this->appendFreshDetections($detections, $state);
        $recentDetections = $this->recentDetections(20);

        return [
            'enabled' => true,
            'scan_window_hours' => 24,
            'status_data_available' => $statusAvailable,
            'summary' => [
                'sources_flagged' => count($detections),
                'recent_detections' => count($recentDetections),
                'analysis_rows' => count($rows),
                'new_detections' => count($newDetections),
            ],
            'suspicious_sources' => array_slice($detections, 0, 12),
            'suspicious_user_agents' => $this->formatAssocRows($uaTotals, 12, 'user_agent'),
            'suspicious_paths' => $this->formatAssocRows($pathTotals, 12, 'path'),
            'top_patterns' => $this->patternRows($patternTotals, 12),
            'recent_detections' => $recentDetections,
            'score_legend' => $this->scoreLegend((string) ($config['sensitivity'] ?? 'normal')),
            'notes' => $this->reportNotes($config, $statusAvailable, count($rows)),
        ];
    }

    private function appendFreshDetections(array $detections, array &$state): array
    {
        if (!$detections) {
            return [];
        }

        $new = [];
        $map = is_array($state['last_detection_ts_by_key'] ?? null)
            ? $state['last_detection_ts_by_key']
            : [];
        $now = time();
        foreach ($detections as $detection) {
            $key = (string) ($detection['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $last = (int) ($map[$key] ?? 0);
            if ($last > 0 && ($now - $last) < self::DETECTION_COOLDOWN) {
                continue;
            }
            $map[$key] = $now;
            $new[] = $detection;
            $this->appendDetectionLine($detection);
        }
        $state['last_detection_ts_by_key'] = $map;
        return $new;
    }

    private function appendDetectionLine(array $detection): void
    {
        $line = json_encode($detection, JSON_UNESCAPED_SLASHES) . "\n";
        @file_put_contents($this->detectionsPath(), $line, FILE_APPEND | LOCK_EX);
    }

    private function scoreSource(array $bucket, array $config, bool $statusAvailable): array
    {
        $profile = $this->sensitivityProfile((string) ($config['sensitivity'] ?? 'normal'));
        $threshold = $profile['score_threshold'];
        $maxHitsPerMinute = $bucket['minute_hits'] ? max($bucket['minute_hits']) : 0;
        $uniquePaths = count($bucket['paths']);
        $score = 0;
        $reasons = [];

        $hitThreshold = max(1, (int) round((int) ($config['max_hits_per_minute_threshold'] ?? 20) * $profile['hit_multiplier']));
        if ($maxHitsPerMinute >= $hitThreshold) {
            $score += 4;
            $reasons['hits_per_minute'] = [
                'code' => 'hits_per_minute',
                'label' => 'High hit rate',
                'detail' => "{$maxHitsPerMinute}/min against threshold {$hitThreshold}.",
            ];
        }

        if ($uniquePaths >= $profile['unique_paths_threshold']) {
            $score += 3;
            $reasons['many_unique_paths'] = [
                'code' => 'many_unique_paths',
                'label' => 'Many unique paths',
                'detail' => "{$uniquePaths} unique paths in the scan window.",
            ];
        }

        if ((int) $bucket['probe_hits'] >= $profile['probe_hits_threshold']) {
            $score += 4;
            $reasons['probe_paths'] = [
                'code' => 'probe_paths',
                'label' => 'Probe paths',
                'detail' => "{$bucket['probe_hits']} probe-path hits matched configured patterns.",
            ];
        }

        if ((int) $bucket['scanner_ua_hits'] > 0) {
            $score += 3;
            $reasons['scanner_ua'] = [
                'code' => 'scanner_ua',
                'label' => 'Scanner user-agent',
                'detail' => 'User-agent matches a known scanner or scraping signature.',
            ];
        }

        if ((int) $bucket['empty_ua_hits'] > 0) {
            $score += 2;
            $reasons['empty_ua'] = [
                'code' => 'empty_ua',
                'label' => 'Empty user-agent',
                'detail' => 'Requests were sent without a user-agent string.',
            ];
        } elseif ((int) $bucket['weird_ua_hits'] > 0) {
            $score += 2;
            $reasons['weird_ua'] = [
                'code' => 'weird_ua',
                'label' => 'Weird user-agent',
                'detail' => 'User-agent is short, malformed, or automation-like.',
            ];
        }

        if ($statusAvailable && (int) $bucket['status_404_hits'] >= (int) ($config['status_404_threshold'] ?? 12)) {
            $score += 3;
            $reasons['repeated_404s'] = [
                'code' => 'repeated_404s',
                'label' => 'Repeated 404s',
                'detail' => "{$bucket['status_404_hits']} 404 responses were seen for this source.",
            ];
        }

        return [
            'score' => $score,
            'threshold' => $threshold,
            'severity' => $score >= ($threshold + 3) ? 'high' : 'medium',
            'max_hits_per_minute' => $maxHitsPerMinute,
            'unique_paths' => $uniquePaths,
            'reason_map' => $reasons,
        ];
    }

    private function blankSourceBucket(string $source, string $ua): array
    {
        return [
            'source' => $source,
            'user_agent' => $ua,
            'hits' => 0,
            'probe_hits' => 0,
            'empty_ua_hits' => 0,
            'weird_ua_hits' => 0,
            'scanner_ua_hits' => 0,
            'status_404_hits' => 0,
            'first_ts' => 0,
            'last_ts' => 0,
            'paths' => [],
            'path_counts' => [],
            'minute_hits' => [],
        ];
    }

    private function reportNotes(array $config, bool $statusAvailable, int $rowCount): array
    {
        $notes = [];
        $notes[] = 'Bot Watch is detection-only. It does not block traffic.';
        $notes[] = 'Scans are limited to the most recent 24 hours and capped to keep reporting cheap.';
        if (!empty($config['ignore_known_good_bots'])) {
            $notes[] = 'Known good bots from the allowlist are ignored before scoring.';
        }
        if (!$statusAvailable) {
            $notes[] = 'No visit status column was detected, so repeated 404 scoring is inactive.';
        }
        if ($rowCount >= self::MAX_ROWS) {
            $notes[] = 'Analysis hit the row cap. Rebuild again later if traffic volume is very high.';
        }
        return $notes;
    }

    private function patternRows(array $totals, int $limit): array
    {
        arsort($totals);
        $rows = [];
        foreach (array_slice($totals, 0, $limit, true) as $key => $count) {
            $rows[] = [
                'pattern' => $this->patternLabel((string) $key),
                'count' => (int) $count,
            ];
        }
        return $rows;
    }

    private function patternLabel(string $code): string
    {
        return match ($code) {
            'hits_per_minute' => 'High hit rate',
            'many_unique_paths' => 'Many unique paths',
            'probe_paths' => 'Probe paths',
            'scanner_ua' => 'Scanner user-agent',
            'empty_ua' => 'Empty user-agent',
            'weird_ua' => 'Weird user-agent',
            'repeated_404s' => 'Repeated 404s',
            default => $code,
        };
    }

    private function formatAssocRows(array $totals, int $limit, string $field): array
    {
        arsort($totals);
        $rows = [];
        foreach (array_slice($totals, 0, $limit, true) as $key => $count) {
            $rows[] = [
                $field => (string) $key,
                'count' => (int) $count,
            ];
        }
        return $rows;
    }

    private function scoreLegend(string $sensitivity): array
    {
        $profile = $this->sensitivityProfile($sensitivity);
        return [
            'sensitivity' => $sensitivity,
            'score_threshold' => $profile['score_threshold'],
            'rules' => [
                ['label' => 'High hit rate', 'points' => 4],
                ['label' => 'Many unique paths', 'points' => 3],
                ['label' => 'Probe paths', 'points' => 4],
                ['label' => 'Scanner user-agent', 'points' => 3],
                ['label' => 'Empty or weird user-agent', 'points' => 2],
                ['label' => 'Repeated 404s when available', 'points' => 3],
            ],
        ];
    }

    private function sensitivityProfile(string $sensitivity): array
    {
        return match ($sensitivity) {
            'low' => [
                'score_threshold' => 7,
                'hit_multiplier' => 1.5,
                'unique_paths_threshold' => 24,
                'probe_hits_threshold' => 3,
            ],
            'high' => [
                'score_threshold' => 4,
                'hit_multiplier' => 0.75,
                'unique_paths_threshold' => 10,
                'probe_hits_threshold' => 1,
            ],
            default => [
                'score_threshold' => 5,
                'hit_multiplier' => 1.0,
                'unique_paths_threshold' => 16,
                'probe_hits_threshold' => 2,
            ],
        };
    }

    private function loadRecentVisitRows(bool $statusAvailable): array
    {
        $since = time() - self::ANALYSIS_WINDOW;
        $fields = 'ip, user_agent, path, ts';
        if ($statusAvailable) {
            $fields .= ', status';
        }
        try {
            $sql = "SELECT {$fields} FROM visits WHERE ts >= ? ORDER BY ts DESC LIMIT " . self::MAX_ROWS;
            $st = DB::pdo()->prepare($sql);
            $st->execute([$since]);
            $rows = $st->fetchAll();
        } catch (\Throwable) {
            $rows = [];
        }
        return is_array($rows) ? $rows : [];
    }

    private function sourceFingerprint(): string
    {
        $since = time() - self::ANALYSIS_WINDOW;
        try {
            $st = DB::pdo()->prepare(
                'SELECT COUNT(*) AS c, MAX(ts) AS mts FROM visits WHERE ts >= ?',
            );
            $st->execute([$since]);
            $row = $st->fetch();
        } catch (\Throwable) {
            $row = false;
        }
        if (!$row) {
            return 'none';
        }
        return sha1(
            json_encode(
                [
                    'c' => (int) ($row['c'] ?? 0),
                    'mts' => (int) ($row['mts'] ?? 0),
                ],
                JSON_UNESCAPED_SLASHES,
            ),
        );
    }

    private function statusColumnAvailable(): bool
    {
        try {
            $st = DB::pdo()->query("SHOW COLUMNS FROM visits LIKE 'status'");
            $row = $st ? $st->fetch() : false;
            return (bool) $row;
        } catch (\Throwable) {
            return false;
        }
    }

    private function is404Status(mixed $value): bool
    {
        $string = trim((string) $value);
        return $string === '404';
    }

    private function isProbePath(string $path, array $config): bool
    {
        if ($path === '') {
            return false;
        }
        $needle = strtolower($path);
        foreach ($config['suspicious_path_patterns'] ?? [] as $pattern) {
            $pattern = strtolower(trim((string) $pattern));
            if ($pattern === '') {
                continue;
            }
            if (strpos($needle, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    private function isAllowedUserAgent(string $ua, array $config): bool
    {
        $ua = strtolower(trim($ua));
        if ($ua === '') {
            return false;
        }
        foreach ($config['known_bot_allowlist'] ?? [] as $allow) {
            $allow = strtolower(trim((string) $allow));
            if ($allow !== '' && strpos($ua, $allow) !== false) {
                return true;
            }
        }
        return false;
    }

    private function isScannerUserAgent(string $ua): bool
    {
        $ua = strtolower($ua);
        foreach ([
            'sqlmap',
            'nikto',
            'nmap',
            'masscan',
            'zgrab',
            'python-requests',
            'python-urllib',
            'go-http-client',
            'curl/',
            'wget/',
            'httpclient',
            'scanner',
            'scrapy',
            'headless',
            'httpx',
        ] as $needle) {
            if (strpos($ua, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    private function isWeirdUserAgent(string $ua): bool
    {
        $ua = trim($ua);
        if ($ua === '') {
            return false;
        }
        if (strlen($ua) < 8) {
            return true;
        }
        return preg_match('/^[a-z0-9._\-\/ ]+$/i', $ua) === 1
            && stripos($ua, 'mozilla/') !== 0
            && strpos($ua, '(') === false;
    }

    private function normalizeUserAgent(string $ua): string
    {
        $ua = trim(preg_replace('/\s+/', ' ', $ua) ?? '');
        return $this->truncate($ua, 220);
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        $parts = parse_url($path);
        if (is_array($parts) && isset($parts['path'])) {
            $path = (string) $parts['path'];
        }
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        return $this->truncate($path, 220);
    }

    private function sourceRangeLabel(string $source): string
    {
        if (filter_var($source, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $source);
            if (count($parts) === 4) {
                return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.x';
            }
        }
        if (strpos($source, ':') !== false) {
            $parts = explode(':', $source);
            return implode(':', array_slice($parts, 0, 4)) . '::/64';
        }
        return $source;
    }

    private function parseList(mixed $value, int $maxItems, int $maxLen): array
    {
        if (is_array($value)) {
            $lines = $value;
        } else {
            $raw = str_replace(["\r\n", "\r"], "\n", (string) $value);
            $lines = preg_split('/[\n,]+/', $raw) ?: [];
        }

        $out = [];
        $seen = [];
        foreach ($lines as $line) {
            $item = strtolower(trim((string) $line));
            $item = preg_replace('/[^a-z0-9._\-\/:]+/i', '', $item) ?? '';
            if ($item === '') {
                continue;
            }
            $item = substr($item, 0, $maxLen);
            if (isset($seen[$item])) {
                continue;
            }
            $seen[$item] = true;
            $out[] = $item;
            if (count($out) >= $maxItems) {
                break;
            }
        }
        return $out;
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

    private function truncate(string $value, int $maxLen): string
    {
        return strlen($value) > $maxLen ? substr($value, 0, $maxLen) : $value;
    }

    private function sortDescAssoc(array $map): array
    {
        arsort($map);
        return $map;
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

    private function defaultState(): array
    {
        return [
            'last_scan_ts' => 0,
            'cached_report_fingerprint' => '',
            'cached_report' => null,
            'last_detection_ts_by_key' => [],
            'status_column_available' => false,
        ];
    }
}
