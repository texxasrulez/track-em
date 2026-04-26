<?php
declare(strict_types=1);

namespace TrackEm\Plugins\PrivacyAudit;

use TrackEm\Core\Config;
use TrackEm\Core\DB;
use TrackEm\Core\Security;

final class PrivacyAuditService
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
        return [
            'enabled' => $this->toBool($src['enabled'] ?? false),
            'strict_mode' => $this->toBool($src['strict_mode'] ?? false),
            'scan_plugin_settings' => $this->toBool($src['scan_plugin_settings'] ?? false),
            'include_passes' => $this->toBool($src['include_passes'] ?? false),
        ];
    }

    public function report(array $config, bool $forceRefresh = false): array
    {
        if (!$this->isPluginEnabled() || empty($config['enabled'])) {
            return [
                'summary' => [
                    'high' => 0,
                    'medium' => 0,
                    'low' => 0,
                    'pass' => 0,
                ],
                'findings' => [],
                'notes' => ['Privacy Audit is disabled.'],
            ];
        }

        $cacheKey = sha1(
            json_encode(
                [
                    'strict' => !empty($config['strict_mode']),
                    'scan_plugin_settings' => !empty($config['scan_plugin_settings']),
                    'include_passes' => !empty($config['include_passes']),
                    'source' => $this->sourceFingerprint(),
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

        $findings = [];
        $appConfig = Config::instance()->all();
        $strict = !empty($config['strict_mode']);
        $includePasses = !empty($config['include_passes']);

        $this->auditCorePrivacy($findings, $appConfig, $strict, $includePasses);
        if (!empty($config['scan_plugin_settings'])) {
            $this->auditPlugins($findings, $strict, $includePasses);
        }

        $summary = ['high' => 0, 'medium' => 0, 'low' => 0, 'pass' => 0];
        foreach ($findings as $finding) {
            $severity = (string) ($finding['severity'] ?? 'low');
            if (isset($summary[$severity])) {
                $summary[$severity]++;
            }
        }

        $payload = [
            'summary' => $summary,
            'findings' => $findings,
            'notes' => [
                'This audit is heuristic and config-based. It is meant to highlight obvious privacy-sensitive settings, not to replace a full security review.',
                'Public-facing plugins should expose only aggregate or sanitized data by default.',
            ],
        ];
        $this->saveCache($cacheKey, $payload);
        return $payload;
    }

    private function auditCorePrivacy(array &$findings, array $appConfig, bool $strict, bool $includePasses): void
    {
        $privacy = is_array($appConfig['privacy'] ?? null) ? $appConfig['privacy'] : [];
        $geo = is_array($appConfig['geo'] ?? null) ? $appConfig['geo'] : [];

        if (empty($privacy['ip_anonymize'])) {
            $findings[] = $this->finding(
                'core',
                'high',
                'IP anonymization is disabled.',
                'Enable `privacy.ip_anonymize` so visitor IPs are masked before storage.',
            );
        } elseif ($includePasses) {
            $findings[] = $this->finding(
                'core',
                'pass',
                'IP anonymization is enabled.',
                'Current masking is active.',
            );
        }

        $maskBits = (int) ($privacy['ip_mask_bits'] ?? 16);
        $minBits = $strict ? 16 : 8;
        if ($maskBits < $minBits) {
            $findings[] = $this->finding(
                'core',
                'medium',
                'IP masking is weaker than the current audit threshold.',
                'Increase `privacy.ip_mask_bits` to at least ' . $minBits . '.',
            );
        } elseif ($includePasses) {
            $findings[] = $this->finding(
                'core',
                'pass',
                'IP mask bits meet the current audit threshold.',
                'Current value: ' . $maskBits . '.',
            );
        }

        if (empty($privacy['respect_dnt'])) {
            $findings[] = $this->finding(
                'core',
                $strict ? 'medium' : 'low',
                'Do-Not-Track is not respected.',
                'Enable `privacy.respect_dnt` if you want a more privacy-conservative default.',
            );
        } elseif ($includePasses) {
            $findings[] = $this->finding(
                'core',
                'pass',
                'Do-Not-Track is respected.',
                'Current setting is privacy-friendly.',
            );
        }

        if (!empty($geo['allow_insecure_http'])) {
            $findings[] = $this->finding(
                'geo',
                'high',
                'Insecure HTTP geo lookups are allowed.',
                'Disable `geo.allow_insecure_http` or proxy the provider behind HTTPS.',
            );
        } elseif ($includePasses) {
            $findings[] = $this->finding(
                'geo',
                'pass',
                'Insecure HTTP geo lookups are disabled.',
                'Geo lookups are not explicitly allowed over plaintext HTTP.',
            );
        }
    }

    private function auditPlugins(array &$findings, bool $strict, bool $includePasses): void
    {
        $enabled = $this->enabledPluginMap();

        if (!empty($enabled['public_widgets'])) {
            $config = $this->pluginConfig('public_widgets');
            $counter = is_array($config['counter'] ?? null) ? $config['counter'] : [];
            $map = is_array($config['map'] ?? null) ? $config['map'] : [];
            $profile = is_array($map['profile'] ?? null) ? $map['profile'] : [];

            if (!empty($counter['enabled'])) {
                $findings[] = $this->finding(
                    'public_widgets',
                    'low',
                    'Public counter output is enabled.',
                    'Confirm that the chosen range and mode expose only aggregate counts you are comfortable making public.',
                );
            }
            if (!empty($map['enabled']) && (int) ($profile['min_bucket_size'] ?? 0) < 3) {
                $findings[] = $this->finding(
                    'public_widgets',
                    'medium',
                    'Public map bucket size is below the privacy-safe default.',
                    'Use a `min_bucket_size` of at least 3 for public map output.',
                );
            } elseif (!empty($map['enabled']) && $includePasses) {
                $findings[] = $this->finding(
                    'public_widgets',
                    'pass',
                    'Public map bucket threshold meets the privacy-safe baseline.',
                    'Current `min_bucket_size` is ' . (int) ($profile['min_bucket_size'] ?? 0) . '.',
                );
            }
        }

        if (!empty($enabled['event_tracking'])) {
            $config = $this->pluginConfig('event_tracking');
            $allowed = is_array($config['allowed_event_names'] ?? null) ? $config['allowed_event_names'] : [];
            if (!$allowed) {
                $findings[] = $this->finding(
                    'event_tracking',
                    $strict ? 'medium' : 'low',
                    'Event Tracking accepts any valid event name.',
                    'Consider setting an allowlist if you want tighter event collection controls.',
                );
            }
            if ((int) ($config['max_metadata_value_length'] ?? 100) > 100) {
                $findings[] = $this->finding(
                    'event_tracking',
                    'low',
                    'Event metadata values are longer than the default limit.',
                    'Keep metadata values short to reduce accidental sensitive data capture.',
                );
            } elseif ($includePasses) {
                $findings[] = $this->finding(
                    'event_tracking',
                    'pass',
                    'Event metadata length is constrained.',
                    'Current max metadata value length is ' . (int) ($config['max_metadata_value_length'] ?? 100) . '.',
                );
            }
        }

        if (!empty($enabled['referrer_intel'])) {
            $config = $this->pluginConfig('referrer_intel');
            if (!empty($config['show_referrer_paths'])) {
                $findings[] = $this->finding(
                    'referrer_intel',
                    'medium',
                    'Referrer path summaries are enabled.',
                    'Keep this off unless you need sanitized referrer paths for internal analysis.',
                );
            }
            if (!empty($config['include_query_strings'])) {
                $findings[] = $this->finding(
                    'referrer_intel',
                    'high',
                    'Referrer query strings are enabled.',
                    'Disable query strings unless you explicitly need them and have reviewed the privacy impact.',
                );
            } elseif ($includePasses) {
                $findings[] = $this->finding(
                    'referrer_intel',
                    'pass',
                    'Referrer query strings are disabled.',
                    'Current reporting avoids exposing raw query parameters.',
                );
            }
        }

        if (!empty($enabled['static_reports'])) {
            $config = $this->pluginConfig('static_reports');
            if (!empty($config['include_private_detail'])) {
                $findings[] = $this->finding(
                    'static_reports',
                    'medium',
                    'Static Reports includes private detail.',
                    'Disable private detail unless there is a specific internal need for it.',
                );
            } elseif ($includePasses) {
                $findings[] = $this->finding(
                    'static_reports',
                    'pass',
                    'Static Reports private detail is disabled.',
                    'Stored report snapshots stay on the safer aggregate side by default.',
                );
            }
        }

        if (!empty($enabled['traffic_alerts'])) {
            $config = $this->pluginConfig('traffic_alerts');
            if (!empty($config['webhook_enabled']) && !empty($config['webhook_include_detail'])) {
                $findings[] = $this->finding(
                    'traffic_alerts',
                    'low',
                    'Traffic alert webhooks include extra detail.',
                    'Keep webhook payloads summary-only unless the receiver is fully trusted.',
                );
            }
        }
    }

    private function finding(string $area, string $severity, string $title, string $recommendation): array
    {
        return [
            'area' => $area,
            'severity' => $severity,
            'title' => $title,
            'recommendation' => $recommendation,
        ];
    }

    private function pluginConfig(string $pluginId): array
    {
        $defaultPath = dirname(__DIR__) . '/' . $pluginId . '/config.json';
        $storedPath = dirname(__DIR__, 3) . '/storage/plugins/' . $pluginId . '/config.json';
        $base = [];
        $stored = [];
        if (is_file($defaultPath)) {
            $data = json_decode((string) file_get_contents($defaultPath), true);
            $base = is_array($data) ? $data : [];
        }
        if (is_file($storedPath)) {
            $data = json_decode((string) file_get_contents($storedPath), true);
            $stored = is_array($data) ? $data : [];
        }
        return $this->mergeRecursive($base, $stored);
    }

    private function enabledPluginMap(): array
    {
        try {
            $rows = DB::pdo()->query('SELECT id, enabled FROM plugins')->fetchAll();
        } catch (\Throwable) {
            $rows = [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[(string) ($row['id'] ?? '')] = (int) ($row['enabled'] ?? 0) === 1;
        }
        return $out;
    }

    private function sourceFingerprint(): array
    {
        $config = Config::instance()->all();
        $enabled = $this->enabledPluginMap();
        return [
            'config_hash' => sha1(json_encode($config, JSON_UNESCAPED_SLASHES) ?: ''),
            'plugins_hash' => sha1(json_encode($enabled, JSON_UNESCAPED_SLASHES) ?: ''),
        ];
    }

    private function loadCache(string $key): ?array
    {
        $path = $this->cachePath();
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || (string) ($data['key'] ?? '') !== $key) {
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
}
