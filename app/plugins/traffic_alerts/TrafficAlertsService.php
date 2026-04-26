<?php
declare(strict_types=1);

namespace TrackEm\Plugins\TrafficAlerts;

use TrackEm\Core\DB;
use TrackEm\Core\Security;

final class TrafficAlertsService
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

    public function statePath(): string
    {
        return $this->storageDir() . '/state.json';
    }

    public function alertsPath(): string
    {
        return $this->storageDir() . '/alerts.jsonl';
    }

    public function storageDir(): string
    {
        $dir = dirname(__DIR__, 3) . '/storage/plugins/' . $this->pluginId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
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

    public function csrfToken(): string
    {
        Security::startSecureSession();
        return Security::csrfToken();
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
        $webhook = trim((string) ($src['webhook_url'] ?? ''));
        if (!$this->isAllowedWebhookUrl($webhook)) {
            $webhook = '';
        }

        return [
            'enabled' => $this->toBool($src['enabled'] ?? false),
            'dashboard_notice' => $this->toBool(
                $src['dashboard_notice'] ?? false,
            ),
            'email_enabled' => $this->toBool($src['email_enabled'] ?? false),
            'email_recipient' => $this->sanitizeEmail(
                (string) ($src['email_recipient'] ?? ''),
            ),
            'webhook_enabled' => $this->toBool(
                $src['webhook_enabled'] ?? false,
            ),
            'webhook_url' => $webhook,
            'webhook_include_detail' => $this->toBool(
                $src['webhook_include_detail'] ?? false,
            ),
            'spike_threshold_percent' => max(
                110,
                min(1000, (int) ($src['spike_threshold_percent'] ?? 200)),
            ),
            'drop_threshold_percent' => max(
                10,
                min(95, (int) ($src['drop_threshold_percent'] ?? 60)),
            ),
            'new_country_alert' => $this->toBool(
                $src['new_country_alert'] ?? false,
            ),
            'same_source_threshold' => max(
                5,
                min(10000, (int) ($src['same_source_threshold'] ?? 40)),
            ),
            'bot_like_threshold' => max(
                10,
                min(10000, (int) ($src['bot_like_threshold'] ?? 60)),
            ),
            'probing_threshold' => max(
                5,
                min(10000, (int) ($src['probing_threshold'] ?? 25)),
            ),
            'quiet_hours_enabled' => $this->toBool(
                $src['quiet_hours_enabled'] ?? false,
            ),
            'quiet_hours_start' => $this->sanitizeTime(
                (string) ($src['quiet_hours_start'] ?? '23:00'),
                '23:00',
            ),
            'quiet_hours_end' => $this->sanitizeTime(
                (string) ($src['quiet_hours_end'] ?? '07:00'),
                '07:00',
            ),
            'cooldown_minutes' => max(
                1,
                min(10080, (int) ($src['cooldown_minutes'] ?? 60)),
            ),
            'check_interval_minutes' => max(
                1,
                min(1440, (int) ($src['check_interval_minutes'] ?? 5)),
            ),
        ];
    }

    public function channelAvailability(array $config): array
    {
        return [
            'dashboard' => !empty($config['dashboard_notice']),
            'email' => !empty($config['email_enabled']) && $this->canSendEmail($config),
            'webhook' => !empty($config['webhook_enabled']) && $this->isAllowedWebhookUrl((string) ($config['webhook_url'] ?? '')),
        ];
    }

    public function canSendEmail(array $config): bool
    {
        return function_exists('mail') &&
            !empty($config['email_recipient']);
    }

    public function loadState(): array
    {
        $path = $this->statePath();
        if (!is_file($path)) {
            return [
                'last_check_ts' => 0,
                'last_alert_ts_by_key' => [],
                'known_countries' => [],
                'last_delivery_error' => '',
            ];
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data)
            ? $data
            : [
                'last_check_ts' => 0,
                'last_alert_ts_by_key' => [],
                'known_countries' => [],
                'last_delivery_error' => '',
            ];
    }

    public function saveState(array $state): void
    {
        @file_put_contents(
            $this->statePath(),
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX,
        );
    }

    public function runChecks(array $config, bool $force = false): array
    {
        $state = $this->loadState();
        $now = time();

        if (!$this->isPluginEnabled() || empty($config['enabled'])) {
            return [
                'checked' => false,
                'reason' => 'disabled',
                'alerts' => [],
                'state' => $state,
            ];
        }

        $interval = max(60, (int) ($config['check_interval_minutes'] ?? 5) * 60);
        if (!$force && $now - (int) ($state['last_check_ts'] ?? 0) < $interval) {
            return [
                'checked' => false,
                'reason' => 'interval',
                'alerts' => [],
                'state' => $state,
            ];
        }

        $window = 15 * 60;
        $alerts = [];
        $alerts = array_merge($alerts, $this->spikeAlerts($config, $state, $now, $window));
        $alerts = array_merge($alerts, $this->dropAlerts($config, $state, $now, $window));
        if (!empty($config['new_country_alert'])) {
            $alerts = array_merge($alerts, $this->newCountryAlerts($config, $state, $now));
        }
        $alerts = array_merge($alerts, $this->sameSourceAlerts($config, $state, $now));
        $alerts = array_merge($alerts, $this->botLikeAlerts($config, $state, $now, $window));
        $alerts = array_merge($alerts, $this->probingAlerts($config, $state, $now));

        $alerts = $this->applyCooldown($alerts, $config, $state, $now);
        foreach ($alerts as $alert) {
            $this->appendAlert($alert);
            $this->deliverAlert($alert, $config, $state);
        }

        $state['last_check_ts'] = $now;
        $state['known_countries'] = $this->mergeKnownCountries(
            $state['known_countries'] ?? [],
            $this->recentCountries($now - 30 * 86400),
        );
        $this->saveState($state);

        return [
            'checked' => true,
            'reason' => 'ran',
            'alerts' => $alerts,
            'state' => $state,
        ];
    }

    public function recentAlerts(int $limit = 10): array
    {
        $path = $this->alertsPath();
        if (!is_file($path)) {
            return [];
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }
        $lines = array_slice($lines, -1 * $limit);
        $out = [];
        foreach (array_reverse($lines) as $line) {
            $row = json_decode((string) $line, true);
            if (is_array($row)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    public function dashboardPayload(array $config): array
    {
        $this->runChecks($config, false);
        $recent = [];
        foreach ($this->recentAlerts(8) as $row) {
            if ((int) ($row['ts'] ?? 0) < time() - 86400) {
                continue;
            }
            $recent[] = $row;
        }
        return [
            'ok' => true,
            'enabled' => $this->isPluginEnabled() && !empty($config['enabled']),
            'dashboard_notice' => !empty($config['dashboard_notice']),
            'alerts' => $recent,
        ];
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

    private function sanitizeTime(string $value, string $default): string
    {
        $value = trim($value);
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value) !== 1) {
            return $default;
        }
        return $value;
    }

    private function sanitizeEmail(string $value): string
    {
        $value = trim($value);
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
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

    private function isAllowedWebhookUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme === 'https') {
            return true;
        }
        if ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true)) {
            return true;
        }
        return false;
    }

    private function spikeAlerts(array $config, array $state, int $now, int $window): array
    {
        $current = $this->visitCountBetween($now - $window, $now);
        $previous = $this->visitCountBetween($now - (2 * $window), $now - $window);
        if ($previous <= 0 || $current <= $previous) {
            return [];
        }
        $percent = (int) round(($current / max(1, $previous)) * 100);
        if ($percent < (int) ($config['spike_threshold_percent'] ?? 200)) {
            return [];
        }
        return [[
            'ts' => $now,
            'key' => 'spike',
            'type' => 'spike',
            'severity' => 'warning',
            'title' => 'Traffic spike detected',
            'summary' => "Visits increased to {$current} from {$previous} in the last 15 minutes.",
            'detail' => [
                'current_visits' => $current,
                'previous_visits' => $previous,
                'percent' => $percent,
            ],
        ]];
    }

    private function dropAlerts(array $config, array $state, int $now, int $window): array
    {
        $current = $this->visitCountBetween($now - $window, $now);
        $previous = $this->visitCountBetween($now - (2 * $window), $now - $window);
        if ($previous < 10) {
            return [];
        }
        $percentRemaining = (int) round(($current / max(1, $previous)) * 100);
        if ($percentRemaining > (100 - (int) ($config['drop_threshold_percent'] ?? 60))) {
            return [];
        }
        return [[
            'ts' => $now,
            'key' => 'drop',
            'type' => 'drop',
            'severity' => 'warning',
            'title' => 'Traffic drop detected',
            'summary' => "Visits dropped to {$current} from {$previous} in the last 15 minutes.",
            'detail' => [
                'current_visits' => $current,
                'previous_visits' => $previous,
                'percent_remaining' => $percentRemaining,
            ],
        ]];
    }

    private function newCountryAlerts(array $config, array $state, int $now): array
    {
        $known = array_map('strtolower', is_array($state['known_countries'] ?? null) ? $state['known_countries'] : []);
        $knownMap = array_fill_keys($known, true);
        $recent = $this->recentCountries($now - 3600);
        $alerts = [];
        foreach ($recent as $country) {
            $norm = strtolower($country);
            if ($norm === '' || isset($knownMap[$norm])) {
                continue;
            }
            $alerts[] = [
                'ts' => $now,
                'key' => 'country:' . $norm,
                'type' => 'new_country',
                'severity' => 'info',
                'title' => 'New country seen',
                'summary' => "Traffic was seen from {$country} for the first time.",
                'detail' => ['country' => $country],
            ];
            $knownMap[$norm] = true;
        }
        return $alerts;
    }

    private function sameSourceAlerts(array $config, array $state, int $now): array
    {
        $threshold = (int) ($config['same_source_threshold'] ?? 40);
        $windowStart = $now - 15 * 60;
        try {
            $st = DB::pdo()->prepare(
                "SELECT ip, COUNT(*) AS c
                 FROM visits
                 WHERE ts >= ?
                 GROUP BY ip
                 HAVING c >= ?
                 ORDER BY c DESC
                 LIMIT 1",
            );
            $st->execute([$windowStart, $threshold]);
            $row = $st->fetch();
        } catch (\Throwable) {
            $row = false;
        }
        if (!$row) {
            return [];
        }
        $count = (int) ($row['c'] ?? 0);
        return [[
            'ts' => $now,
            'key' => 'same_source:' . sha1((string) ($row['ip'] ?? '')),
            'type' => 'same_source',
            'severity' => 'warning',
            'title' => 'Repeated hits from one source',
            'summary' => "{$count} visits came from the same masked source in the last 15 minutes.",
            'detail' => ['count' => $count],
        ]];
    }

    private function botLikeAlerts(array $config, array $state, int $now, int $window): array
    {
        $current = $this->visitCountBetween($now - $window, $now);
        $threshold = (int) ($config['bot_like_threshold'] ?? 60);
        if ($current < $threshold) {
            return [];
        }
        try {
            $st = DB::pdo()->prepare(
                "SELECT ip, COUNT(*) AS c
                 FROM visits
                 WHERE ts >= ?
                 GROUP BY ip
                 ORDER BY c DESC
                 LIMIT 1",
            );
            $st->execute([$now - $window]);
            $row = $st->fetch();
        } catch (\Throwable) {
            $row = false;
        }
        $topCount = (int) ($row['c'] ?? 0);
        if ($topCount < (int) ceil($current * 0.7)) {
            return [];
        }
        return [[
            'ts' => $now,
            'key' => 'bot_like',
            'type' => 'bot_like',
            'severity' => 'warning',
            'title' => 'Bot-like spike detected',
            'summary' => "A concentrated spike was detected: {$topCount} of {$current} visits came from one masked source in the last 15 minutes.",
            'detail' => [
                'current_visits' => $current,
                'top_source_visits' => $topCount,
            ],
        ]];
    }

    private function probingAlerts(array $config, array $state, int $now): array
    {
        $threshold = (int) ($config['probing_threshold'] ?? 25);
        $windowStart = $now - 30 * 60;
        $patterns = [
            '%wp-%',
            '%.php%',
            '%xmlrpc%',
            '%phpmyadmin%',
            '%.env%',
            '%admin%',
            '%login%',
        ];
        try {
            $where = implode(' OR ', array_fill(0, count($patterns), 'path LIKE ?'));
            $sql = "SELECT COUNT(*) AS c FROM visits WHERE ts >= ? AND ({$where})";
            $st = DB::pdo()->prepare($sql);
            $st->execute(array_merge([$windowStart], $patterns));
            $count = (int) $st->fetchColumn();
        } catch (\Throwable) {
            $count = 0;
        }
        if ($count < $threshold) {
            return [];
        }
        return [[
            'ts' => $now,
            'key' => 'probing',
            'type' => 'probing',
            'severity' => 'warning',
            'title' => 'Suspicious path probing detected',
            'summary' => "{$count} suspicious path hits were seen in the last 30 minutes.",
            'detail' => ['count' => $count],
        ]];
    }

    private function visitCountBetween(int $from, int $to): int
    {
        try {
            $st = DB::pdo()->prepare(
                "SELECT COUNT(*) FROM visits WHERE ts >= ? AND ts < ?",
            );
            $st->execute([$from, $to]);
            return (int) $st->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function recentCountries(int $since): array
    {
        try {
            $st = DB::pdo()->prepare(
                "SELECT DISTINCT country FROM visits WHERE ts >= ? AND country IS NOT NULL AND country <> ''",
            );
            $st->execute([$since]);
            $rows = $st->fetchAll();
        } catch (\Throwable) {
            $rows = [];
        }
        $out = [];
        foreach ($rows as $row) {
            $country = trim((string) ($row['country'] ?? ''));
            if ($country !== '') {
                $out[] = $country;
            }
        }
        return $out;
    }

    private function mergeKnownCountries(array $known, array $recent): array
    {
        $map = [];
        foreach (array_merge($known, $recent) as $country) {
            $country = trim((string) $country);
            if ($country === '') {
                continue;
            }
            $map[strtolower($country)] = $country;
        }
        return array_values($map);
    }

    private function applyCooldown(array $alerts, array $config, array &$state, int $now): array
    {
        if (!$alerts) {
            return [];
        }
        $cooldown = max(60, (int) ($config['cooldown_minutes'] ?? 60) * 60);
        $quiet = $this->inQuietHours($config);
        $out = [];
        foreach ($alerts as $alert) {
            $key = (string) ($alert['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $last = (int) (($state['last_alert_ts_by_key'][$key] ?? 0));
            if ($last > 0 && $now - $last < $cooldown) {
                continue;
            }
            $state['last_alert_ts_by_key'][$key] = $now;
            if ($quiet) {
                $alert['quiet_hours'] = true;
            }
            $out[] = $alert;
        }
        return $out;
    }

    private function inQuietHours(array $config): bool
    {
        if (empty($config['quiet_hours_enabled'])) {
            return false;
        }
        $start = (string) ($config['quiet_hours_start'] ?? '23:00');
        $end = (string) ($config['quiet_hours_end'] ?? '07:00');
        $now = date('H:i');
        if ($start === $end) {
            return false;
        }
        if ($start < $end) {
            return $now >= $start && $now < $end;
        }
        return $now >= $start || $now < $end;
    }

    private function appendAlert(array $alert): void
    {
        $line = json_encode($alert, JSON_UNESCAPED_SLASHES) . "\n";
        @file_put_contents($this->alertsPath(), $line, FILE_APPEND | LOCK_EX);
    }

    private function deliverAlert(array $alert, array $config, array &$state): void
    {
        $state['last_delivery_error'] = '';
        if (!empty($config['email_enabled']) && $this->canSendEmail($config)) {
            $ok = @mail(
                (string) $config['email_recipient'],
                '[Track Em] ' . (string) ($alert['title'] ?? 'Traffic alert'),
                (string) ($alert['summary'] ?? ''),
            );
            if (!$ok) {
                $state['last_delivery_error'] = 'email_failed';
            }
        }

        if (!empty($config['webhook_enabled']) && $this->isAllowedWebhookUrl((string) ($config['webhook_url'] ?? ''))) {
            $payload = [
                'plugin' => 'traffic_alerts',
                'type' => (string) ($alert['type'] ?? ''),
                'severity' => (string) ($alert['severity'] ?? 'info'),
                'title' => (string) ($alert['title'] ?? ''),
                'summary' => (string) ($alert['summary'] ?? ''),
                'ts' => (int) ($alert['ts'] ?? time()),
            ];
            if (!empty($config['webhook_include_detail'])) {
                $payload['detail'] = $alert['detail'] ?? [];
            }
            if (!$this->postWebhook((string) $config['webhook_url'], $payload)) {
                $state['last_delivery_error'] = 'webhook_failed';
            }
        }
    }

    private function postWebhook(string $url, array $payload): bool
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return false;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $body,
            ]);
            $result = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $result !== false && $status >= 200 && $status < 300;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 5,
            ],
        ]);
        $result = @file_get_contents($url, false, $context);
        return $result !== false;
    }
}
