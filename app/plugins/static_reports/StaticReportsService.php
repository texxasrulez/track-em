<?php
declare(strict_types=1);

namespace TrackEm\Plugins\StaticReports;

use DateTimeImmutable;
use DateTimeZone;
use TrackEm\Core\DB;
use TrackEm\Core\Security;

final class StaticReportsService
{
    private const AUTO_CHECK_INTERVAL = 3600;

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

    public function statePath(): string
    {
        return $this->storageDir() . '/state.json';
    }

    public function reportDir(): string
    {
        $dir = $this->storageDir() . '/reports';
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
            'generate_daily' => $this->toBool($src['generate_daily'] ?? false),
            'generate_weekly' => $this->toBool($src['generate_weekly'] ?? false),
            'email_enabled' => $this->toBool($src['email_enabled'] ?? false),
            'email_recipient' => $this->sanitizeEmail(
                (string) ($src['email_recipient'] ?? ''),
            ),
            'retention_daily' => max(
                1,
                min(365, (int) ($src['retention_daily'] ?? 30)),
            ),
            'retention_weekly' => max(
                1,
                min(104, (int) ($src['retention_weekly'] ?? 12)),
            ),
            'include_sections' => [
                'traffic_summary' => $this->toBool($src['include_traffic_summary'] ?? false),
                'top_paths' => $this->toBool($src['include_top_paths'] ?? false),
                'referrer_summary' => $this->toBool($src['include_referrer_summary'] ?? false),
                'event_summary' => $this->toBool($src['include_event_summary'] ?? false),
                'goals_summary' => $this->toBool($src['include_goals_summary'] ?? false),
                'bot_summary' => $this->toBool($src['include_bot_summary'] ?? false),
            ],
            'include_private_detail' => $this->toBool(
                $src['include_private_detail'] ?? false,
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

    public function optionalCapabilities(): array
    {
        return [
            'referrer_intel' => $this->pluginIsAvailable('referrer_intel'),
            'event_tracking' => $this->pluginIsAvailable('event_tracking'),
            'goals' => $this->pluginIsAvailable('goals'),
            'bot_watch' => $this->pluginIsAvailable('bot_watch'),
        ];
    }

    public function canSendEmail(array $config): bool
    {
        return function_exists('mail') &&
            !empty($config['email_enabled']) &&
            !empty($config['email_recipient']);
    }

    public function autoGenerateDueReports(array $config): array
    {
        $state = $this->loadState();
        $now = time();
        if (
            !$this->isPluginEnabled() ||
            empty($config['enabled']) ||
            ($now - (int) ($state['last_auto_check_ts'] ?? 0)) < self::AUTO_CHECK_INTERVAL
        ) {
            return ['generated' => [], 'reason' => 'skipped'];
        }

        $state['last_auto_check_ts'] = $now;
        $this->saveState($state);

        $requests = [
            'daily' => !empty($config['generate_daily']) && !$this->reportExistsForType('daily'),
            'weekly' => !empty($config['generate_weekly']) && !$this->reportExistsForType('weekly'),
        ];

        return $this->generateRequestedReports($config, $requests);
    }

    public function generateRequestedReports(array $config, array $requests): array
    {
        if (!$this->isPluginEnabled() || empty($config['enabled'])) {
            return ['generated' => [], 'reason' => 'disabled'];
        }

        $generated = [];
        foreach (['daily', 'weekly'] as $type) {
            if (empty($requests[$type])) {
                continue;
            }
            $generated[] = $this->generateReport($type, $config);
        }
        $this->pruneReports($config);
        $this->deliverGeneratedReportsEmail($generated, $config);
        return ['generated' => $generated, 'reason' => $generated ? 'ok' : 'no_types'];
    }

    public function listReports(): array
    {
        $dir = $this->reportDir();
        $files = glob($dir . '/*.html') ?: [];
        $reports = [];
        foreach ($files as $path) {
            $name = basename((string) $path);
            if (!$this->isSafeReportFilename($name)) {
                continue;
            }
            $reports[] = [
                'file' => $name,
                'type' => str_starts_with($name, 'weekly-') ? 'weekly' : 'daily',
                'label' => $this->reportLabel($name),
                'generated_at' => is_file($path) ? (int) @filemtime($path) : 0,
                'size_bytes' => is_file($path) ? (int) @filesize($path) : 0,
                'view_url' => $this->routeUrl('static_reports.view', ['file' => $name]),
            ];
        }
        usort(
            $reports,
            static fn(array $a, array $b): int => (int) $b['generated_at'] <=> (int) $a['generated_at'],
        );
        return $reports;
    }

    public function resolveReportPath(string $file): ?string
    {
        $file = basename($file);
        if (!$this->isSafeReportFilename($file)) {
            return null;
        }
        $path = $this->reportDir() . '/' . $file;
        return is_file($path) ? $path : null;
    }

    private function generateReport(string $type, array $config): array
    {
        $meta = $this->reportMeta($type);
        $data = $this->buildReportData($type, $config, $meta);
        $html = $this->renderHtmlReport($data, $config);
        $filename = $this->reportFilename($meta);
        $path = $this->reportDir() . '/' . $filename;
        @file_put_contents($path, $html, LOCK_EX);
        return [
            'type' => $type,
            'file' => $filename,
            'path' => $path,
            'label' => $meta['label'],
            'range_label' => $meta['range_label'],
            'view_url' => $this->routeUrl('static_reports.view', ['file' => $filename]),
        ];
    }

    private function deliverGeneratedReportsEmail(array $generated, array $config): bool
    {
        $state = $this->loadState();
        $state['last_email_error'] = '';
        if (!$generated || !$this->canSendEmail($config)) {
            $this->saveState($state);
            return false;
        }

        $recipient = (string) ($config['email_recipient'] ?? '');
        $subject = '[Track Em] Static reports generated';
        $body = $this->buildEmailBody($generated);
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        $ok = @mail($recipient, $subject, $body, implode("\r\n", $headers));

        $state['last_email_ts'] = time();
        $state['last_email_recipient'] = $recipient;
        $state['last_emailed_reports'] = array_values(array_map(
            static fn(array $report): string => (string) ($report['file'] ?? ''),
            array_filter($generated, static fn($report): bool => is_array($report)),
        ));
        if (!$ok) {
            $state['last_email_error'] = 'email_failed';
        }
        $this->saveState($state);
        return $ok;
    }

    private function buildEmailBody(array $generated): string
    {
        $lines = [];
        $lines[] = 'Track Em generated new static report(s).';
        $lines[] = '';
        foreach ($generated as $report) {
            if (!is_array($report)) {
                continue;
            }
            $lines[] = '- ' . (string) ($report['label'] ?? 'Report');
            $lines[] = '  File: ' . (string) ($report['file'] ?? '');
            $lines[] = '  Range: ' . (string) ($report['range_label'] ?? '');
            $absolute = $this->absoluteRouteUrl('static_reports.view', [
                'file' => (string) ($report['file'] ?? ''),
            ]);
            if ($absolute !== '') {
                $lines[] = '  View: ' . $absolute;
            }
            $lines[] = '';
        }
        $lines[] = 'These reports remain admin-only.';
        return implode("\n", $lines);
    }

    private function buildReportData(string $type, array $config, array $meta): array
    {
        $sections = is_array($config['include_sections'] ?? null)
            ? $config['include_sections']
            : [];

        $data = [
            'meta' => $meta,
            'generated_at' => time(),
            'traffic_summary' => null,
            'top_paths' => [],
            'referrer_summary' => null,
            'event_summary' => null,
            'goals_summary' => null,
            'bot_summary' => null,
            'notes' => [],
        ];

        if (!empty($sections['traffic_summary']) || !empty($sections['top_paths'])) {
            $traffic = $this->trafficSummary(
                (int) $meta['start_ts'],
                (int) $meta['end_ts'],
            );
            if (!empty($sections['traffic_summary'])) {
                $data['traffic_summary'] = $traffic['summary'];
            }
            if (!empty($sections['top_paths'])) {
                $data['top_paths'] = $traffic['top_paths'];
            }
        }

        if (!empty($sections['referrer_summary'])) {
            $data['referrer_summary'] = $this->referrerSummary($type);
            if ($data['referrer_summary'] === null) {
                $data['notes'][] = 'Referrer summary skipped because referrer_intel is unavailable.';
            }
        }

        if (!empty($sections['event_summary'])) {
            $data['event_summary'] = $this->eventSummary();
            if ($data['event_summary'] === null) {
                $data['notes'][] = 'Event summary skipped because event_tracking is unavailable.';
            }
        }

        if (!empty($sections['goals_summary'])) {
            $data['goals_summary'] = $this->goalsSummary($type);
            if ($data['goals_summary'] === null) {
                $data['notes'][] = 'Goals summary skipped because goals is unavailable.';
            }
        }

        if (!empty($sections['bot_summary'])) {
            $data['bot_summary'] = $this->botSummary((bool) ($config['include_private_detail'] ?? false));
            if ($data['bot_summary'] === null) {
                $data['notes'][] = 'Bot summary skipped because bot_watch is unavailable.';
            }
        }

        return $data;
    }

    private function renderHtmlReport(array $data, array $config): string
    {
        $h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $meta = $data['meta'];
        $out = [];
        $out[] = '<!doctype html>';
        $out[] = '<html lang="en"><head><meta charset="utf-8">';
        $out[] = '<meta name="viewport" content="width=device-width, initial-scale=1">';
        $out[] = '<title>' . $h($meta['title']) . '</title>';
        $out[] = '<style>';
        $out[] = 'body{font:15px/1.5 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:0;background:#f5f6f8;color:#1a1d21}';
        $out[] = '.wrap{max-width:1100px;margin:0 auto;padding:24px}';
        $out[] = '.card{background:#fff;border:1px solid #d8dde3;border-radius:14px;padding:18px 20px;margin:0 0 16px;box-shadow:0 2px 14px rgba(15,23,42,.04)}';
        $out[] = '.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}';
        $out[] = '.stat{border:1px solid #dde3ea;border-radius:12px;padding:12px;background:#fbfcfd}';
        $out[] = '.stat strong{display:block;font-size:24px;margin-top:6px}';
        $out[] = 'table{width:100%;border-collapse:collapse;margin-top:10px}';
        $out[] = 'th,td{text-align:left;padding:8px 10px;border-bottom:1px solid #e3e7ec;vertical-align:top;font-size:14px}';
        $out[] = '.muted{color:#5b6470;font-size:13px}';
        $out[] = '.tag{display:inline-block;border:1px solid #d7dde5;border-radius:999px;padding:2px 8px;margin:2px 6px 2px 0;font-size:12px;background:#f8fafb}';
        $out[] = '</style></head><body><div class="wrap">';
        $out[] = '<section class="card">';
        $out[] = '<h1 style="margin:0 0 8px;">' . $h($meta['title']) . '</h1>';
        $out[] = '<div class="muted">Generated ' . $h(gmdate('Y-m-d H:i:s', (int) $data['generated_at'])) . ' UTC</div>';
        $out[] = '<div class="muted">Range: ' . $h($meta['range_label']) . '</div>';
        $out[] = '<div class="muted">Admin-only cached report. Dynamic values are aggregated and sanitized.</div>';
        $out[] = '</section>';

        if (is_array($data['traffic_summary'])) {
            $summary = $data['traffic_summary'];
            $out[] = '<section class="card"><h2 style="margin-top:0;">Traffic Summary</h2><div class="stats">';
            foreach ([
                'Visits' => (int) ($summary['visits'] ?? 0),
                'Unique Sources' => (int) ($summary['unique_sources'] ?? 0),
                'Top Path Share' => (string) ($summary['top_path_share'] ?? '0%'),
            ] as $label => $value) {
                $out[] = '<div class="stat"><div>' . $h($label) . '</div><strong>' . $h((string) $value) . '</strong></div>';
            }
            $out[] = '</div></section>';
        }

        if (!empty($data['top_paths'])) {
            $out[] = '<section class="card"><h2 style="margin-top:0;">Top Paths</h2><table><thead><tr><th>Path</th><th>Visits</th></tr></thead><tbody>';
            foreach ($data['top_paths'] as $row) {
                $out[] = '<tr><td>' . $h((string) ($row['path'] ?? '')) . '</td><td>' . $h((string) ($row['count'] ?? 0)) . '</td></tr>';
            }
            $out[] = '</tbody></table></section>';
        }

        if (is_array($data['referrer_summary'])) {
            $summary = $data['referrer_summary'];
            $out[] = '<section class="card"><h2 style="margin-top:0;">Referrer Summary</h2><div class="stats">';
            foreach ((array) ($summary['summary'] ?? []) as $label => $count) {
                $out[] = '<div class="stat"><div>' . $h(ucwords(str_replace('_', ' ', (string) $label))) . '</div><strong>' . $h((string) $count) . '</strong></div>';
            }
            $out[] = '</div>';
            if (!empty($summary['top_domains'])) {
                $out[] = '<table><thead><tr><th>Domain</th><th>Count</th></tr></thead><tbody>';
                foreach ($summary['top_domains'] as $row) {
                    $out[] = '<tr><td>' . $h((string) ($row['label'] ?? '')) . '</td><td>' . $h((string) ($row['count'] ?? 0)) . '</td></tr>';
                }
                $out[] = '</tbody></table>';
            }
            $out[] = '</section>';
        }

        if (is_array($data['event_summary'])) {
            $summary = $data['event_summary'];
            $out[] = '<section class="card"><h2 style="margin-top:0;">Event Summary</h2><div class="stats">';
            foreach ((array) ($summary['totals'] ?? []) as $label => $count) {
                $out[] = '<div class="stat"><div>' . $h(ucwords(str_replace('_', ' ', (string) $label))) . '</div><strong>' . $h((string) $count) . '</strong></div>';
            }
            $out[] = '</div>';
            if (!empty($summary['top_events'])) {
                $out[] = '<table><thead><tr><th>Event</th><th>Count</th></tr></thead><tbody>';
                foreach ($summary['top_events'] as $row) {
                    $out[] = '<tr><td>' . $h((string) ($row['label'] ?? '')) . '</td><td>' . $h((string) ($row['count'] ?? 0)) . '</td></tr>';
                }
                $out[] = '</tbody></table>';
            }
            $out[] = '</section>';
        }

        if (is_array($data['goals_summary'])) {
            $summary = $data['goals_summary'];
            $out[] = '<section class="card"><h2 style="margin-top:0;">Goals Summary</h2>';
            $out[] = '<div class="stats">';
            $out[] = '<div class="stat"><div>Visits</div><strong>' . $h((string) (($summary['totals']['visits'] ?? 0))) . '</strong></div>';
            $out[] = '<div class="stat"><div>Completions</div><strong>' . $h((string) (($summary['totals']['completions'] ?? 0))) . '</strong></div>';
            $out[] = '</div>';
            if (!empty($summary['goals'])) {
                $out[] = '<table><thead><tr><th>Goal</th><th>Completions</th><th>Conversion Rate</th></tr></thead><tbody>';
                foreach ($summary['goals'] as $row) {
                    $out[] = '<tr><td>' . $h((string) ($row['name'] ?? '')) . '</td><td>' . $h((string) ($row['completions'] ?? 0)) . '</td><td>' . $h((string) ($row['conversion_rate'] ?? 0)) . '%</td></tr>';
                }
                $out[] = '</tbody></table>';
            }
            $out[] = '</section>';
        }

        if (is_array($data['bot_summary'])) {
            $summary = $data['bot_summary'];
            $out[] = '<section class="card"><h2 style="margin-top:0;">Bot Summary</h2><div class="stats">';
            foreach ((array) ($summary['summary'] ?? []) as $label => $count) {
                $out[] = '<div class="stat"><div>' . $h(ucwords(str_replace('_', ' ', (string) $label))) . '</div><strong>' . $h((string) $count) . '</strong></div>';
            }
            $out[] = '</div>';
            if (!empty($summary['sources'])) {
                $out[] = '<table><thead><tr><th>Source</th><th>Score</th><th>Signals</th></tr></thead><tbody>';
                foreach ($summary['sources'] as $row) {
                    $out[] = '<tr><td>' . $h((string) ($row['source'] ?? '')) . '</td><td>' . $h((string) ($row['score'] ?? 0)) . '</td><td>';
                    foreach ((array) ($row['reasons'] ?? []) as $reason) {
                        $out[] = '<span class="tag">' . $h((string) $reason) . '</span>';
                    }
                    $out[] = '</td></tr>';
                }
                $out[] = '</tbody></table>';
            }
            $out[] = '</section>';
        }

        if (!empty($data['notes'])) {
            $out[] = '<section class="card"><h2 style="margin-top:0;">Notes</h2>';
            foreach ($data['notes'] as $note) {
                $out[] = '<div class="muted" style="margin-bottom:6px;">' . $h((string) $note) . '</div>';
            }
            $out[] = '</section>';
        }

        $out[] = '</div></body></html>';
        return implode("\n", $out);
    }

    private function trafficSummary(int $startTs, int $endTs): array
    {
        try {
            $st = DB::pdo()->prepare(
                'SELECT COUNT(*) AS visits, COUNT(DISTINCT ip) AS unique_sources FROM visits WHERE ts >= ? AND ts < ?',
            );
            $st->execute([$startTs, $endTs]);
            $summaryRow = $st->fetch();

            $top = DB::pdo()->prepare(
                'SELECT path, COUNT(*) AS c FROM visits WHERE ts >= ? AND ts < ? GROUP BY path ORDER BY c DESC LIMIT 12',
            );
            $top->execute([$startTs, $endTs]);
            $topRows = $top->fetchAll();
        } catch (\Throwable) {
            $summaryRow = false;
            $topRows = [];
        }

        $visits = (int) ($summaryRow['visits'] ?? 0);
        $topPaths = [];
        $topCount = 0;
        foreach ($topRows as $row) {
            $path = $this->sanitizePath((string) ($row['path'] ?? ''));
            $count = (int) ($row['c'] ?? 0);
            if ($path === '' || $count <= 0) {
                continue;
            }
            if ($topCount === 0) {
                $topCount = $count;
            }
            $topPaths[] = [
                'path' => $path,
                'count' => $count,
            ];
        }

        return [
            'summary' => [
                'visits' => $visits,
                'unique_sources' => (int) ($summaryRow['unique_sources'] ?? 0),
                'top_path_share' => $visits > 0 ? round(($topCount / $visits) * 100, 1) . '%' : '0%',
            ],
            'top_paths' => $topPaths,
        ];
    }

    private function referrerSummary(string $type): ?array
    {
        $class = 'TrackEm\\Plugins\\ReferrerIntel\\ReferrerIntelService';
        $file = dirname(__DIR__) . '/referrer_intel/ReferrerIntelService.php';
        if (!$this->pluginIsAvailable('referrer_intel') || !is_file($file)) {
            return null;
        }
        require_once $file;
        if (!class_exists($class)) {
            return null;
        }
        $service = new $class('referrer_intel', dirname(__DIR__) . '/referrer_intel');
        $config = $service->loadConfig();
        $config['report_range'] = $type === 'weekly' ? '7d' : 'today';
        $report = $service->report($config, true);
        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        return [
            'summary' => $summary,
            'top_domains' => $this->assocRows($report['top_domains'] ?? []),
        ];
    }

    private function eventSummary(): ?array
    {
        $class = 'TrackEm\\Plugins\\EventTracking\\EventTrackingService';
        $file = dirname(__DIR__) . '/event_tracking/EventTrackingService.php';
        if (!$this->pluginIsAvailable('event_tracking') || !is_file($file)) {
            return null;
        }
        require_once $file;
        if (!class_exists($class)) {
            return null;
        }
        $service = new $class('event_tracking', dirname(__DIR__) . '/event_tracking');
        $config = $service->loadConfig();
        $report = $service->adminReport($config);
        return [
            'totals' => $report['totals'] ?? [],
            'top_events' => $this->assocRows($report['top_events'] ?? []),
        ];
    }

    private function goalsSummary(string $type): ?array
    {
        $class = 'TrackEm\\Plugins\\Goals\\GoalsService';
        $file = dirname(__DIR__) . '/goals/GoalsService.php';
        if (!$this->pluginIsAvailable('goals') || !is_file($file)) {
            return null;
        }
        require_once $file;
        if (!class_exists($class)) {
            return null;
        }
        $service = new $class('goals', dirname(__DIR__) . '/goals');
        $config = $service->loadConfig();
        $config['report_range'] = $type === 'weekly' ? '7d' : 'today';
        return $service->report($config);
    }

    private function botSummary(bool $includePrivateDetail): ?array
    {
        $class = 'TrackEm\\Plugins\\BotWatch\\BotWatchService';
        $file = dirname(__DIR__) . '/bot_watch/BotWatchService.php';
        if (!$this->pluginIsAvailable('bot_watch') || !is_file($file)) {
            return null;
        }
        require_once $file;
        if (!class_exists($class)) {
            return null;
        }
        $service = new $class('bot_watch', dirname(__DIR__) . '/bot_watch');
        $config = $service->loadConfig();
        $report = $service->report($config, false);
        $sources = [];
        foreach (array_slice((array) ($report['suspicious_sources'] ?? []), 0, 8) as $row) {
            $sources[] = [
                'source' => $includePrivateDetail
                    ? (string) ($row['source'] ?? '')
                    : (string) ($row['source_range'] ?? ''),
                'score' => (int) ($row['score'] ?? 0),
                'reasons' => array_map(
                    static fn(array $reason): string => (string) ($reason['label'] ?? ''),
                    array_values(array_filter(
                        (array) ($row['reasons'] ?? []),
                        static fn($reason): bool => is_array($reason),
                    )),
                ),
            ];
        }
        return [
            'summary' => [
                'sources_flagged' => (int) ($report['summary']['sources_flagged'] ?? 0),
                'recent_detections' => (int) ($report['summary']['recent_detections'] ?? 0),
                'analysis_rows' => (int) ($report['summary']['analysis_rows'] ?? 0),
            ],
            'sources' => $sources,
        ];
    }

    private function pruneReports(array $config): void
    {
        $daily = [];
        $weekly = [];
        foreach ($this->listReports() as $report) {
            if (($report['type'] ?? '') === 'weekly') {
                $weekly[] = $report;
            } else {
                $daily[] = $report;
            }
        }
        $this->pruneReportSet($daily, (int) ($config['retention_daily'] ?? 30));
        $this->pruneReportSet($weekly, (int) ($config['retention_weekly'] ?? 12));
    }

    private function pruneReportSet(array $reports, int $keep): void
    {
        if (count($reports) <= $keep) {
            return;
        }
        usort(
            $reports,
            static fn(array $a, array $b): int => (int) $b['generated_at'] <=> (int) $a['generated_at'],
        );
        foreach (array_slice($reports, $keep) as $report) {
            $path = $this->reportDir() . '/' . (string) ($report['file'] ?? '');
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function reportMeta(string $type): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($type === 'weekly') {
            $start = $now->modify('monday this week')->setTime(0, 0);
            $end = $start->modify('+7 days');
            return [
                'type' => 'weekly',
                'key' => $start->format('o-\WW'),
                'label' => 'Weekly ' . $start->format('o-\WW'),
                'title' => 'Track Em Weekly Static Report',
                'range_label' => $start->format('Y-m-d') . ' to ' . $end->modify('-1 second')->format('Y-m-d'),
                'start_ts' => $start->getTimestamp(),
                'end_ts' => $end->getTimestamp(),
            ];
        }

        $start = $now->setTime(0, 0);
        $end = $start->modify('+1 day');
        return [
            'type' => 'daily',
            'key' => $start->format('Y-m-d'),
            'label' => 'Daily ' . $start->format('Y-m-d'),
            'title' => 'Track Em Daily Static Report',
            'range_label' => $start->format('Y-m-d'),
            'start_ts' => $start->getTimestamp(),
            'end_ts' => $end->getTimestamp(),
        ];
    }

    private function reportFilename(array $meta): string
    {
        return $meta['type'] . '-' . preg_replace('/[^a-z0-9_-]/i', '_', (string) $meta['key']) . '.html';
    }

    private function absoluteRouteUrl(string $route, array $params = []): string
    {
        $relative = $this->routeUrl($route, $params);
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return '';
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            ? 'https'
            : 'http';
        return $scheme . '://' . $host . $relative;
    }

    private function reportExistsForType(string $type): bool
    {
        $meta = $this->reportMeta($type);
        return is_file($this->reportDir() . '/' . $this->reportFilename($meta));
    }

    private function reportLabel(string $filename): string
    {
        $base = preg_replace('/\.html$/', '', $filename) ?? $filename;
        return ucwords(str_replace(['-', '_'], [' ', ' '], $base));
    }

    private function isSafeReportFilename(string $filename): bool
    {
        return preg_match('/^(daily|weekly)-[a-z0-9_ -]+\.html$/i', $filename) === 1;
    }

    private function assocRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $label => $count) {
            $out[] = [
                'label' => (string) $label,
                'count' => (int) $count,
            ];
        }
        return $out;
    }

    private function sanitizePath(string $path): string
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
        return substr($path, 0, 255);
    }

    private function pluginIsAvailable(string $pluginId): bool
    {
        return is_file(dirname(__DIR__) . '/' . $pluginId . '/plugin.json');
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

    private function sanitizeEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '') {
            return '';
        }
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private function defaultState(): array
    {
        return [
            'last_auto_check_ts' => 0,
            'last_email_ts' => 0,
            'last_email_recipient' => '',
            'last_emailed_reports' => [],
            'last_email_error' => '',
        ];
    }
}
