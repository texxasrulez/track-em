<?php
declare(strict_types=1);

namespace TrackEm\Plugins\EventTracking;

use DateTimeImmutable;
use DateTimeZone;
use TrackEm\Core\DB;
use TrackEm\Core\Security;

final class EventTrackingService
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
        return dirname(__DIR__, 2) .
            '/config/plugins/' .
            $this->pluginId .
            '.json';
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

    public function collectionEnabled(array $config): bool
    {
        return $this->isPluginEnabled() && !empty($config['enabled']);
    }

    public function csrfToken(): string
    {
        Security::startSecureSession();
        return Security::csrfToken();
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

    public function assetRouteUrl(string $file): string
    {
        return $this->routeUrl($this->pluginId . '.asset', ['file' => $file]);
    }

    public function scriptSnippet(): string
    {
        return '<script async src="' .
            $this->assetRouteUrl('trackem-events.js') .
            '"></script>' .
            "\n\n" .
            '<script>' .
            "\n" .
            '  window.trackemEvent && window.trackemEvent("download_pdf", {' .
            "\n" .
            '    label: "brochure",' .
            "\n" .
            '    meta: { type: "pdf" }' .
            "\n" .
            '  });' .
            "\n" .
            '</script>';
    }

    public function declarativeSnippet(): string
    {
        return '<a href="/file.pdf"' .
            "\n" .
            '   data-trackem-event="download_pdf"' .
            "\n" .
            '   data-trackem-label="Brochure"' .
            "\n" .
            '   data-trackem-meta-type="pdf">' .
            "\n" .
            '   Download PDF' .
            "\n" .
            '</a>';
    }

    public function sanitizeConfig(array $src): array
    {
        return [
            'enabled' => $this->toBool($src['enabled'] ?? false),
            'allowed_event_names' => $this->parseAllowedEventNames(
                (string) ($src['allowed_event_names'] ?? ''),
            ),
            'validation_rule' => $this->sanitizeEnum(
                (string) ($src['validation_rule'] ?? 'extended'),
                ['strict', 'extended'],
                'extended',
            ),
            'retention_days' => max(
                1,
                min(3650, (int) ($src['retention_days'] ?? 90)),
            ),
            'max_event_name_length' => max(
                8,
                min(128, (int) ($src['max_event_name_length'] ?? 64)),
            ),
            'max_metadata_keys' => max(
                0,
                min(20, (int) ($src['max_metadata_keys'] ?? 5)),
            ),
            'max_metadata_value_length' => max(
                10,
                min(500, (int) ($src['max_metadata_value_length'] ?? 100)),
            ),
        ];
    }

    public function collectEvent(array $payload, array $config): array
    {
        $eventName = $this->sanitizeEventName(
            (string) ($payload['event'] ?? ''),
            $config,
        );
        if ($eventName === '') {
            throw new \RuntimeException('invalid_event');
        }

        $allowed = $config['allowed_event_names'] ?? [];
        if (is_array($allowed) && $allowed) {
            if (!in_array($eventName, $allowed, true)) {
                throw new \RuntimeException('event_not_allowed');
            }
        }

        $maxValueLength = (int) ($config['max_metadata_value_length'] ?? 100);
        $event = [
            'ts' => time(),
            'event' => $eventName,
            'label' => $this->sanitizeText(
                (string) ($payload['label'] ?? ''),
                $maxValueLength,
            ),
            'path' => $this->normalizePath((string) ($payload['path'] ?? '')),
            'meta' => $this->sanitizeMeta($payload['meta'] ?? [], $config),
        ];

        $this->appendEvent($event);
        $this->pruneIfNeeded((int) ($config['retention_days'] ?? 90));
        return $event;
    }

    public function adminReport(array $config): array
    {
        $now = time();
        $todayStart = $this->dayStartTs($now);
        $sevenStart = $now - 7 * 86400;
        $thirtyStart = $now - 30 * 86400;
        $retentionStart = $now - max(1, (int) ($config['retention_days'] ?? 90)) * 86400;
        $since = max($retentionStart, $thirtyStart);

        $eventCounts = [];
        $labelCounts = [];
        $recent = [];
        $today = 0;
        $seven = 0;
        $thirty = 0;

        foreach ($this->iterateEventsSince($since) as $row) {
            $ts = (int) ($row['ts'] ?? 0);
            if ($ts < $since) {
                continue;
            }

            $event = $this->sanitizeText((string) ($row['event'] ?? ''), 128);
            if ($event === '') {
                continue;
            }

            $label = $this->sanitizeText(
                (string) ($row['label'] ?? ''),
                (int) ($config['max_metadata_value_length'] ?? 100),
            );
            $path = $this->normalizePath((string) ($row['path'] ?? ''));
            $meta = $this->sanitizeMeta($row['meta'] ?? [], $config);

            if ($ts >= $todayStart) {
                $today++;
            }
            if ($ts >= $sevenStart) {
                $seven++;
            }
            if ($ts >= $thirtyStart) {
                $thirty++;
            }

            $eventCounts[$event] = ($eventCounts[$event] ?? 0) + 1;
            if ($label !== '') {
                $labelCounts[$label] = ($labelCounts[$label] ?? 0) + 1;
            }

            $recent[] = [
                'ts' => $ts,
                'event' => $event,
                'label' => $label,
                'path' => $path,
                'meta' => $meta,
            ];
        }

        usort(
            $recent,
            static fn(array $a, array $b): int => $b['ts'] <=> $a['ts'],
        );
        arsort($eventCounts);
        arsort($labelCounts);

        return [
            'totals' => [
                'today' => $today,
                'last_7_days' => $seven,
                'last_30_days' => $thirty,
            ],
            'top_events' => $this->sliceAssoc($eventCounts, 8),
            'top_labels' => $this->sliceAssoc($labelCounts, 8),
            'recent' => array_slice($recent, 0, 15),
        ];
    }

    public function assetPath(string $file): ?string
    {
        $file = preg_replace('/[^a-z0-9._-]/i', '', basename($file)) ?? '';
        if ($file === '') {
            return null;
        }
        $path = $this->pluginDir . '/assets/' . $file;
        return is_file($path) ? $path : null;
    }

    public function assetContentType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            default => 'application/octet-stream',
        };
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

    private function parseAllowedEventNames(string $input): array
    {
        $parts = preg_split('/[\r\n,]+/', $input) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $name = strtolower(trim($part));
            if ($name === '' || isset($out[$name])) {
                continue;
            }
            $out[$name] = $name;
        }
        return array_values($out);
    }

    private function sanitizeEventName(string $name, array $config): string
    {
        $name = strtolower(trim($name));
        if ($name === '') {
            return '';
        }
        $max = (int) ($config['max_event_name_length'] ?? 64);
        $name = substr($name, 0, $max);
        $rule = (string) ($config['validation_rule'] ?? 'extended');
        $pattern = $rule === 'strict'
            ? '/^[a-z0-9][a-z0-9_]*$/'
            : '/^[a-z0-9][a-z0-9_.:-]*$/';
        return preg_match($pattern, $name) === 1 ? $name : '';
    }

    private function sanitizeMeta(mixed $meta, array $config): array
    {
        if (!is_array($meta)) {
            return [];
        }

        $maxKeys = (int) ($config['max_metadata_keys'] ?? 5);
        $maxLen = (int) ($config['max_metadata_value_length'] ?? 100);
        $out = [];
        foreach ($meta as $key => $value) {
            if (count($out) >= $maxKeys) {
                break;
            }
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $safeKey = strtolower(trim((string) $key));
            $safeKey = preg_replace('/[^a-z0-9_.:-]/', '_', $safeKey) ?? '';
            $safeKey = trim($safeKey, '._:-');
            if ($safeKey === '' || strlen($safeKey) > 40) {
                continue;
            }
            if ($this->looksSensitiveKey($safeKey)) {
                continue;
            }

            $safeValue = $this->sanitizeScalar($value, $maxLen);
            if ($safeValue === '') {
                continue;
            }
            $out[$safeKey] = $safeValue;
        }
        return $out;
    }

    private function sanitizeScalar(mixed $value, int $maxLen): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (!is_scalar($value)) {
            return '';
        }
        return $this->sanitizeText((string) $value, $maxLen);
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

    private function appendEvent(array $event): void
    {
        $month = gmdate('Y-m', (int) $event['ts']);
        $path = $this->storageDir() . '/events-' . $month . '.jsonl';
        $line = json_encode($event, JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }

    private function pruneIfNeeded(int $retentionDays): void
    {
        $marker = $this->storageDir() . '/prune-state.json';
        $today = gmdate('Y-m-d');
        $state = [];
        if (is_file($marker)) {
            $state = json_decode((string) file_get_contents($marker), true);
            if (!is_array($state)) {
                $state = [];
            }
        }
        if (($state['last_pruned'] ?? '') === $today) {
            return;
        }

        $cutoff = time() - max(1, $retentionDays) * 86400;
        foreach (glob($this->storageDir() . '/events-*.jsonl') ?: [] as $file) {
            if (!preg_match('/events-(\d{4})-(\d{2})\.jsonl$/', $file, $m)) {
                continue;
            }
            $monthStart = gmmktime(0, 0, 0, (int) $m[2], 1, (int) $m[1]);
            $monthEnd = gmmktime(23, 59, 59, (int) $m[2] + 1, 0, (int) $m[1]);
            if ($monthEnd < $cutoff) {
                @unlink($file);
                continue;
            }
            if ($monthStart < $cutoff && $monthEnd >= $cutoff) {
                $this->rewriteFileWithCutoff($file, $cutoff);
            }
        }

        @file_put_contents(
            $marker,
            json_encode(['last_pruned' => $today], JSON_UNESCAPED_SLASHES),
            LOCK_EX,
        );
    }

    private function rewriteFileWithCutoff(string $file, int $cutoff): void
    {
        $tmp = $file . '.tmp';
        $in = @fopen($file, 'rb');
        $out = @fopen($tmp, 'wb');
        if (!$in || !$out) {
            if (is_resource($in)) {
                fclose($in);
            }
            if (is_resource($out)) {
                fclose($out);
            }
            @unlink($tmp);
            return;
        }
        while (($line = fgets($in)) !== false) {
            $row = json_decode(trim($line), true);
            if (!is_array($row) || (int) ($row['ts'] ?? 0) < $cutoff) {
                continue;
            }
            fwrite($out, json_encode($row, JSON_UNESCAPED_SLASHES) . "\n");
        }
        fclose($in);
        fclose($out);
        @rename($tmp, $file);
    }

    private function iterateEventsSince(int $since): \Generator
    {
        $months = $this->monthsBetween($since, time());
        foreach ($months as $month) {
            $file = $this->storageDir() . '/events-' . $month . '.jsonl';
            if (!is_file($file)) {
                continue;
            }
            $fh = @fopen($file, 'rb');
            if (!$fh) {
                continue;
            }
            while (($line = fgets($fh)) !== false) {
                $row = json_decode(trim($line), true);
                if (is_array($row)) {
                    yield $row;
                }
            }
            fclose($fh);
        }
    }

    private function monthsBetween(int $since, int $until): array
    {
        $tz = new DateTimeZone('UTC');
        $start = (new DateTimeImmutable('@' . $since, $tz))->setTime(0, 0)->modify('first day of this month');
        $end = (new DateTimeImmutable('@' . $until, $tz))->setTime(0, 0)->modify('first day of this month');
        $months = [];
        while ($start <= $end) {
            $months[] = $start->format('Y-m');
            $start = $start->modify('+1 month');
        }
        return $months;
    }

    private function dayStartTs(int $ts): int
    {
        return strtotime('today', $ts) ?: $ts;
    }

    private function looksSensitiveKey(string $key): bool
    {
        foreach (
            ['password', 'passwd', 'pass', 'secret', 'token', 'auth', 'cookie', 'session', 'email', 'textarea', 'form', 'field', 'input', 'value']
            as $needle
        ) {
            if (strpos($key, $needle) !== false) {
                return true;
            }
        }
        return false;
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
