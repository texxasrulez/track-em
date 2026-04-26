<?php
declare(strict_types=1);

namespace TrackEm\Plugins\Scheduler;

use TrackEm\Core\DB;
use TrackEm\Core\Security;

final class SchedulerService
{
    private const LOG_LIMIT = 50;

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

    public function runsPath(): string
    {
        return $this->storageDir() . '/runs.jsonl';
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

    public function availableJobs(): array
    {
        $jobs = [
            'traffic_alerts.checks' => [
                'plugin' => 'traffic_alerts',
                'label' => 'Traffic Alerts Checks',
                'description' => 'Run low-cost traffic alert checks and delivery logic.',
            ],
            'anomaly_digest.refresh' => [
                'plugin' => 'anomaly_digest',
                'label' => 'Anomaly Digest Refresh',
                'description' => 'Refresh the cached anomaly digest.',
            ],
            'static_reports.generate' => [
                'plugin' => 'static_reports',
                'label' => 'Static Reports Generate',
                'description' => 'Generate configured daily and weekly static reports.',
            ],
            'privacy_audit.refresh' => [
                'plugin' => 'privacy_audit',
                'label' => 'Privacy Audit Refresh',
                'description' => 'Refresh the privacy audit cache.',
            ],
            'content_groups.refresh' => [
                'plugin' => 'content_groups',
                'label' => 'Content Groups Refresh',
                'description' => 'Refresh grouped path reporting cache.',
            ],
            'referrer_intel.refresh' => [
                'plugin' => 'referrer_intel',
                'label' => 'Referrer Intel Refresh',
                'description' => 'Refresh referrer source summaries.',
            ],
            'search_terms.refresh' => [
                'plugin' => 'search_terms',
                'label' => 'Search Terms Refresh',
                'description' => 'Refresh internal search-term summaries.',
            ],
            'utm_intel.refresh' => [
                'plugin' => 'utm_intel',
                'label' => 'UTM Intel Refresh',
                'description' => 'Refresh lightweight campaign summaries.',
            ],
            'geo_intel.refresh' => [
                'plugin' => 'geo_intel',
                'label' => 'Geo Intel Refresh',
                'description' => 'Refresh cached geo summaries.',
            ],
            'funnel_reports.refresh' => [
                'plugin' => 'funnel_reports',
                'label' => 'Funnel Reports Refresh',
                'description' => 'Refresh aggregate funnel reporting.',
            ],
            'export_center.generate' => [
                'plugin' => 'export_center',
                'label' => 'Export Center Generate',
                'description' => 'Generate cached JSON and CSV exports.',
            ],
        ];

        $out = [];
        foreach ($jobs as $id => $job) {
            if (!is_dir(dirname(__DIR__) . '/' . $job['plugin'])) {
                continue;
            }
            $out[$id] = $job + ['job_id' => $id];
        }
        return $out;
    }

    public function sanitizeConfig(array $src): array
    {
        return [
            'enabled' => $this->toBool($src['enabled'] ?? false),
            'run_on_admin_load' => $this->toBool($src['run_on_admin_load'] ?? false),
            'admin_cooldown_minutes' => max(1, min(1440, (int) ($src['admin_cooldown_minutes'] ?? 5))),
            'max_jobs_per_tick' => max(1, min(10, (int) ($src['max_jobs_per_tick'] ?? 3))),
            'jobs' => $this->sanitizeJobs($src['jobs'] ?? []),
        ];
    }

    public function loadState(): array
    {
        $path = $this->statePath();
        if (!is_file($path)) {
            return $this->defaultState();
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? ($data + $this->defaultState()) : $this->defaultState();
    }

    public function saveState(array $state): void
    {
        @file_put_contents(
            $this->statePath(),
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX,
        );
    }

    public function recentRuns(int $limit = self::LOG_LIMIT): array
    {
        $path = $this->runsPath();
        if (!is_file($path)) {
            return [];
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $lines = array_slice($lines, -$limit);
        $rows = [];
        foreach (array_reverse($lines) as $line) {
            $row = json_decode((string) $line, true);
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    public function autoRunDueJobs(array $config): array
    {
        if (
            !$this->isPluginEnabled() ||
            empty($config['enabled']) ||
            empty($config['run_on_admin_load'])
        ) {
            return ['ran' => [], 'reason' => 'disabled'];
        }

        $state = $this->loadState();
        $now = time();
        $cooldown = max(60, (int) ($config['admin_cooldown_minutes'] ?? 5) * 60);
        if (($now - (int) ($state['last_admin_tick_ts'] ?? 0)) < $cooldown) {
            return ['ran' => [], 'reason' => 'cooldown'];
        }

        $state['last_admin_tick_ts'] = $now;
        $this->saveState($state);
        return $this->runDueJobs($config, false);
    }

    public function runDueJobs(array $config, bool $forceAll = false): array
    {
        if (!$this->isPluginEnabled() || empty($config['enabled'])) {
            return ['ran' => [], 'reason' => 'disabled'];
        }

        $state = $this->loadState();
        $now = time();
        $maxJobs = max(1, (int) ($config['max_jobs_per_tick'] ?? 3));
        $ran = [];

        foreach ((array) ($config['jobs'] ?? []) as $job) {
            if (!is_array($job) || empty($job['job_id']) || empty($job['active'])) {
                continue;
            }
            $jobId = (string) $job['job_id'];
            $interval = max(1, (int) ($job['interval_minutes'] ?? 60)) * 60;
            $lastRun = (int) (($state['last_run_ts_by_job'][$jobId] ?? 0));
            if (!$forceAll && $lastRun > 0 && ($now - $lastRun) < $interval) {
                continue;
            }
            $result = $this->runJob($jobId);
            $state['last_run_ts_by_job'][$jobId] = $now;
            $state['last_result_by_job'][$jobId] = [
                'ok' => !empty($result['ok']),
                'summary' => (string) ($result['summary'] ?? ''),
                'ts' => $now,
            ];
            $state['last_error'] = !empty($result['ok']) ? '' : (string) ($result['summary'] ?? 'job_failed');
            $this->appendRun([
                'ts' => $now,
                'job_id' => $jobId,
                'ok' => !empty($result['ok']),
                'summary' => (string) ($result['summary'] ?? ''),
            ]);
            $ran[] = ['job_id' => $jobId] + $result;
            if (count($ran) >= $maxJobs && !$forceAll) {
                break;
            }
        }

        $this->saveState($state);
        return ['ran' => $ran, 'reason' => $ran ? 'ok' : 'no_due_jobs'];
    }

    public function runJob(string $jobId): array
    {
        return match ($jobId) {
            'traffic_alerts.checks' => $this->runTrafficAlertsChecks(),
            'anomaly_digest.refresh' => $this->runSimpleReportRefresh(
                'anomaly_digest',
                'AnomalyDigest',
                'AnomalyDigestService',
            ),
            'static_reports.generate' => $this->runStaticReportsGenerate(),
            'privacy_audit.refresh' => $this->runSimpleReportRefresh(
                'privacy_audit',
                'PrivacyAudit',
                'PrivacyAuditService',
            ),
            'content_groups.refresh' => $this->runSimpleReportRefresh(
                'content_groups',
                'ContentGroups',
                'ContentGroupsService',
            ),
            'referrer_intel.refresh' => $this->runSimpleReportRefresh(
                'referrer_intel',
                'ReferrerIntel',
                'ReferrerIntelService',
            ),
            'search_terms.refresh' => $this->runSimpleReportRefresh(
                'search_terms',
                'SearchTerms',
                'SearchTermsService',
            ),
            'utm_intel.refresh' => $this->runSimpleReportRefresh(
                'utm_intel',
                'UtmIntel',
                'UtmIntelService',
            ),
            'geo_intel.refresh' => $this->runSimpleReportRefresh(
                'geo_intel',
                'GeoIntel',
                'GeoIntelService',
            ),
            'funnel_reports.refresh' => $this->runSimpleReportRefresh(
                'funnel_reports',
                'FunnelReports',
                'FunnelReportsService',
            ),
            'export_center.generate' => $this->runExportCenterGenerate(),
            default => ['ok' => false, 'summary' => 'unknown_job'],
        };
    }

    private function runTrafficAlertsChecks(): array
    {
        $path = dirname(__DIR__) . '/traffic_alerts/TrafficAlertsService.php';
        if (!is_file($path)) {
            return ['ok' => false, 'summary' => 'plugin_missing'];
        }
        require_once $path;
        $service = new \TrackEm\Plugins\TrafficAlerts\TrafficAlertsService(
            'traffic_alerts',
            dirname(__DIR__) . '/traffic_alerts',
        );
        $result = $service->runChecks($service->loadConfig(), false);
        return [
            'ok' => true,
            'summary' => 'checks=' . (int) count((array) ($result['alerts'] ?? [])),
        ];
    }

    private function runStaticReportsGenerate(): array
    {
        $path = dirname(__DIR__) . '/static_reports/StaticReportsService.php';
        if (!is_file($path)) {
            return ['ok' => false, 'summary' => 'plugin_missing'];
        }
        require_once $path;
        $service = new \TrackEm\Plugins\StaticReports\StaticReportsService(
            'static_reports',
            dirname(__DIR__) . '/static_reports',
        );
        $config = $service->loadConfig();
        $result = $service->generateRequestedReports($config, [
            'daily' => !empty($config['generate_daily']),
            'weekly' => !empty($config['generate_weekly']),
        ]);
        return [
            'ok' => true,
            'summary' => 'generated=' . (int) count((array) ($result['generated'] ?? [])),
        ];
    }

    private function runExportCenterGenerate(): array
    {
        $path = dirname(__DIR__) . '/export_center/ExportCenterService.php';
        if (!is_file($path)) {
            return ['ok' => false, 'summary' => 'plugin_missing'];
        }
        require_once $path;
        $service = new \TrackEm\Plugins\ExportCenter\ExportCenterService(
            'export_center',
            dirname(__DIR__) . '/export_center',
        );
        $result = $service->generateExports($service->loadConfig());
        return [
            'ok' => true,
            'summary' => 'files=' . (int) count((array) ($result['files'] ?? [])),
        ];
    }

    private function runSimpleReportRefresh(string $pluginId, string $nsPart, string $classBase): array
    {
        $path = dirname(__DIR__) . '/' . $pluginId . '/' . $classBase . '.php';
        if (!is_file($path)) {
            return ['ok' => false, 'summary' => 'plugin_missing'];
        }
        require_once $path;
        $class = 'TrackEm\\Plugins\\' . $nsPart . '\\' . $classBase;
        if (!class_exists($class)) {
            return ['ok' => false, 'summary' => 'class_missing'];
        }
        $service = new $class($pluginId, dirname(__DIR__) . '/' . $pluginId);
        $service->report($service->loadConfig(), true);
        return ['ok' => true, 'summary' => 'refreshed'];
    }

    private function sanitizeJobs(mixed $jobs): array
    {
        if (!is_array($jobs)) {
            return [];
        }
        $available = $this->availableJobs();
        $out = [];
        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }
            $jobId = (string) ($job['job_id'] ?? '');
            if ($jobId === '' || !isset($available[$jobId])) {
                continue;
            }
            $out[] = [
                'job_id' => $jobId,
                'interval_minutes' => max(1, min(10080, (int) ($job['interval_minutes'] ?? 60))),
                'active' => $this->toBool($job['active'] ?? false),
            ];
        }
        return $out;
    }

    private function defaultState(): array
    {
        return [
            'last_admin_tick_ts' => 0,
            'last_run_ts_by_job' => [],
            'last_result_by_job' => [],
            'last_error' => '',
        ];
    }

    private function appendRun(array $row): void
    {
        @file_put_contents(
            $this->runsPath(),
            json_encode($row, JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX,
        );
        $lines = @file($this->runsPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        if (count($lines) > 500) {
            $lines = array_slice($lines, -500);
            @file_put_contents($this->runsPath(), implode("\n", $lines) . "\n", LOCK_EX);
        }
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
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
    }
}
