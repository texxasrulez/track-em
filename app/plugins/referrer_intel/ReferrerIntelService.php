<?php
declare(strict_types=1);

namespace TrackEm\Plugins\ReferrerIntel;

use DateTimeImmutable;
use DateTimeZone;
use TrackEm\Core\Config;
use TrackEm\Core\DB;
use TrackEm\Core\Security;

final class ReferrerIntelService
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

    public function clearRollups(): void
    {
        @unlink($this->rollupsPath());
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
            'report_range' => $this->sanitizeEnum(
                (string) ($src['report_range'] ?? '30d'),
                ['today', '7d', '30d', 'all'],
                '30d',
            ),
            'search_domains' => $this->parseDomainList(
                (string) ($src['search_domains'] ?? ''),
            ),
            'social_domains' => $this->parseDomainList(
                (string) ($src['social_domains'] ?? ''),
            ),
            'internal_domains' => $this->parseDomainList(
                (string) ($src['internal_domains'] ?? ''),
            ),
            'show_referrer_paths' => $this->toBool(
                $src['show_referrer_paths'] ?? false,
            ),
            'include_query_strings' => $this->toBool(
                $src['include_query_strings'] ?? false,
            ),
        ];
    }

    public function report(array $config, bool $forceRefresh = false): array
    {
        if (!$this->isPluginEnabled() || empty($config['enabled'])) {
            return [
                'range' => (string) ($config['report_range'] ?? '30d'),
                'summary' => $this->emptyBuckets(),
                'windows' => [
                    'today' => $this->emptyBuckets(),
                    '7d' => $this->emptyBuckets(),
                    '30d' => $this->emptyBuckets(),
                ],
                'top_domains' => [],
                'top_paths' => [],
                'notes' => ['Referrer Intel is disabled.'],
            ];
        }

        $fingerprint = $this->sourceFingerprint();
        $configHash = sha1(
            json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
        if (!$forceRefresh) {
            $cached = $this->loadCachedReport($fingerprint, $configHash);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $now = time();
        $todayStart = $this->dayStartTs($now);
        $sevenStart = $now - 7 * 86400;
        $thirtyStart = $now - 30 * 86400;
        $range = (string) ($config['report_range'] ?? '30d');
        $rangeStart = $this->rangeStart($range);
        $queryStart = $rangeStart === null ? null : min($rangeStart, $thirtyStart);

        $summary = $this->emptyBuckets();
        $windows = [
            'today' => $this->emptyBuckets(),
            '7d' => $this->emptyBuckets(),
            '30d' => $this->emptyBuckets(),
        ];
        $topDomains = [];
        $topPaths = [];
        $notes = [];

        foreach ($this->iterateVisits($queryStart) as $row) {
            $ts = (int) ($row['ts'] ?? 0);
            $referrer = (string) ($row['referrer'] ?? '');
            $info = $this->classifyReferrer($referrer, $config);
            $category = $info['category'];

            if ($ts >= $todayStart) {
                $windows['today'][$category]++;
            }
            if ($ts >= $sevenStart) {
                $windows['7d'][$category]++;
            }
            if ($ts >= $thirtyStart) {
                $windows['30d'][$category]++;
            }

            if ($rangeStart === null || $ts >= $rangeStart) {
                $summary[$category]++;
                if ($info['domain'] !== '') {
                  $topDomains[$info['domain']] = ($topDomains[$info['domain']] ?? 0) + 1;
                }
                if (!empty($config['show_referrer_paths']) && $info['display_referrer'] !== '') {
                  $topPaths[$info['display_referrer']] = ($topPaths[$info['display_referrer']] ?? 0) + 1;
                }
            }
        }

        arsort($topDomains);
        arsort($topPaths);

        if (empty($config['show_referrer_paths'])) {
            $notes[] = 'Full referrer paths are hidden by default. Domain-only reporting is shown.';
        } elseif (empty($config['include_query_strings'])) {
            $notes[] = 'Referrer path summaries are shown without query strings.';
        }

        $payload = [
            'range' => $range,
            'summary' => $summary,
            'windows' => $windows,
            'top_domains' => $this->sliceAssoc($topDomains, 12),
            'top_paths' => !empty($config['show_referrer_paths'])
                ? $this->sliceAssoc($topPaths, 12)
                : [],
            'notes' => $notes,
        ];

        $this->cacheReport($fingerprint, $configHash, $payload);
        return $payload;
    }

    public function classifyReferrer(string $referrer, array $config): array
    {
        $referrer = trim($referrer);
        if ($referrer === '') {
            return [
                'category' => 'direct',
                'domain' => '',
                'display_referrer' => '',
            ];
        }

        $parts = @parse_url($referrer);
        if (!is_array($parts)) {
            return [
                'category' => 'unknown',
                'domain' => '',
                'display_referrer' => '',
            ];
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return [
                'category' => 'unknown',
                'domain' => '',
                'display_referrer' => '',
            ];
        }

        $domain = $this->normalizeDomain($host);
        $internalDomains = $this->internalDomains($config);
        if ($this->domainMatchesList($domain, $internalDomains)) {
            $category = 'internal';
        } elseif ($this->domainMatchesList($domain, $config['search_domains'] ?? [])) {
            $category = 'search';
        } elseif ($this->domainMatchesList($domain, $config['social_domains'] ?? [])) {
            $category = 'social';
        } else {
            $category = 'external';
        }

        return [
            'category' => $category,
            'domain' => $domain,
            'display_referrer' => $this->displayReferrer($parts, $config),
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

    private function parseDomainList(string $input): array
    {
        $parts = preg_split('/[\r\n,]+/', $input) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $domain = strtolower(trim($part));
            $domain = preg_replace('/[^a-z0-9.*_-]/', '', $domain) ?? '';
            if ($domain === '' || isset($out[$domain])) {
                continue;
            }
            $out[$domain] = $domain;
        }
        return array_values($out);
    }

    private function emptyBuckets(): array
    {
        return [
            'direct' => 0,
            'search' => 0,
            'social' => 0,
            'internal' => 0,
            'external' => 0,
            'unknown' => 0,
        ];
    }

    private function sourceFingerprint(): array
    {
        try {
            $row = DB::pdo()
                ->query(
                    "SELECT COUNT(*) AS c, MAX(ts) AS max_ts FROM visits",
                )
                ->fetch();
            return [
                'count' => (int) ($row['c'] ?? 0),
                'max_ts' => (int) ($row['max_ts'] ?? 0),
            ];
        } catch (\Throwable) {
            return ['count' => 0, 'max_ts' => 0];
        }
    }

    private function loadCachedReport(array $fingerprint, string $configHash): ?array
    {
        $path = $this->rollupsPath();
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (
            !is_array($data) ||
            ($data['config_hash'] ?? '') !== $configHash ||
            ($data['fingerprint']['count'] ?? null) !== $fingerprint['count'] ||
            ($data['fingerprint']['max_ts'] ?? null) !== $fingerprint['max_ts']
        ) {
            return null;
        }
        if ((int) ($data['generated_at'] ?? 0) + 300 < time()) {
            return null;
        }
        return isset($data['payload']) && is_array($data['payload'])
            ? $data['payload']
            : null;
    }

    private function cacheReport(array $fingerprint, string $configHash, array $payload): void
    {
        @file_put_contents(
            $this->rollupsPath(),
            json_encode(
                [
                    'generated_at' => time(),
                    'config_hash' => $configHash,
                    'fingerprint' => $fingerprint,
                    'payload' => $payload,
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ),
            LOCK_EX,
        );
    }

    private function iterateVisits(?int $since): \Generator
    {
        $sql = 'SELECT referrer, ts FROM visits';
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

    private function dayStartTs(int $ts): int
    {
        return strtotime('today', $ts) ?: $ts;
    }

    private function internalDomains(array $config): array
    {
        $domains = $config['internal_domains'] ?? [];
        if (!is_array($domains)) {
            $domains = [];
        }

        $baseUrl = (string) Config::instance()->get('base_url', '');
        if ($baseUrl !== '') {
            $parts = @parse_url($baseUrl);
            $host = strtolower((string) ($parts['host'] ?? ''));
            if ($host !== '') {
                $domains[] = $host;
            }
        }

        $httpHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($httpHost !== '') {
            $httpHost = preg_replace('/:\d+$/', '', $httpHost) ?? $httpHost;
            $domains[] = $httpHost;
        }

        $out = [];
        foreach ($domains as $domain) {
            $norm = $this->normalizeDomain((string) $domain);
            if ($norm === '') {
                continue;
            }
            $out[$norm] = $norm;
        }
        return array_values($out);
    }

    private function normalizeDomain(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        return trim($host, '.');
    }

    private function domainMatchesList(string $domain, array $list): bool
    {
        foreach ($list as $candidate) {
            $candidate = strtolower(trim((string) $candidate));
            if ($candidate === '') {
                continue;
            }
            if (str_contains($candidate, '*')) {
                $pattern = '/^' .
                    str_replace('\*', '.*', preg_quote($candidate, '/')) .
                    '$/';
                if (preg_match($pattern, $domain) === 1) {
                    return true;
                }
                continue;
            }
            if (str_ends_with($candidate, '.')) {
                if (
                    str_starts_with($domain, $candidate) ||
                    strpos($domain, '.' . $candidate) !== false
                ) {
                    return true;
                }
                continue;
            }
            if ($domain === $candidate || str_ends_with($domain, '.' . $candidate)) {
                return true;
            }
        }
        return false;
    }

    private function displayReferrer(array $parts, array $config): string
    {
        if (empty($config['show_referrer_paths'])) {
            return '';
        }
        $host = $this->normalizeDomain((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return '';
        }
        $path = (string) ($parts['path'] ?? '');
        $query = '';
        if (!empty($config['include_query_strings'])) {
            $query = (string) ($parts['query'] ?? '');
        }
        $display = $host . $path;
        if ($query !== '') {
            $display .= '?' . $query;
        }
        return substr($display, 0, 255);
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
}
