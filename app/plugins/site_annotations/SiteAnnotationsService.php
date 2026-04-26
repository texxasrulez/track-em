<?php
declare(strict_types=1);

namespace TrackEm\Plugins\SiteAnnotations;

use TrackEm\Core\DB;
use TrackEm\Core\Security;

final class SiteAnnotationsService
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
        $maxAnnotations = max(
            25,
            min(1000, (int) ($src['max_annotations'] ?? 250)),
        );
        $annotations = $this->sanitizeAnnotations(
            $src['annotations'] ?? [],
            $maxAnnotations,
        );

        return [
            'enabled' => $this->toBool($src['enabled'] ?? false),
            'report_range' => $this->sanitizeEnum(
                (string) ($src['report_range'] ?? '30d'),
                ['today', '7d', '30d', 'all'],
                '30d',
            ),
            'default_type' => $this->sanitizeEnum(
                (string) ($src['default_type'] ?? 'note'),
                $this->annotationTypes(),
                'note',
            ),
            'max_annotations' => $maxAnnotations,
            'annotations' => $annotations,
        ];
    }

    public function report(array $config): array
    {
        $annotations = is_array($config['annotations'] ?? null)
            ? $config['annotations']
            : [];
        if (!$this->isPluginEnabled() || empty($config['enabled'])) {
            return [
                'range' => (string) ($config['report_range'] ?? '30d'),
                'summary' => [
                    'total' => 0,
                    'active' => 0,
                    'upcoming' => 0,
                ],
                'types' => [],
                'recent' => [],
                'notes' => ['Site Annotations is disabled.'],
            ];
        }

        $range = (string) ($config['report_range'] ?? '30d');
        $since = $this->rangeStart($range);
        $now = time();
        $recent = [];
        $typeCounts = [];
        $active = 0;
        $upcoming = 0;

        foreach ($annotations as $annotation) {
            if (!is_array($annotation)) {
                continue;
            }
            $ts = (int) ($annotation['date_ts'] ?? 0);
            if ($since !== null && $ts < $since) {
                continue;
            }
            $recent[] = $annotation;
            $type = (string) ($annotation['type'] ?? 'note');
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
            if (!empty($annotation['active'])) {
                $active++;
            }
            if ($ts > $now) {
                $upcoming++;
            }
        }

        usort(
            $recent,
            static fn(array $a, array $b): int => (int) ($b['date_ts'] ?? 0) <=> (int) ($a['date_ts'] ?? 0),
        );
        arsort($typeCounts);

        return [
            'range' => $range,
            'summary' => [
                'total' => count($recent),
                'active' => $active,
                'upcoming' => $upcoming,
            ],
            'types' => $this->assocRows($typeCounts),
            'recent' => array_slice($recent, 0, 40),
            'notes' => [
                'Annotations are admin-only and intended to add context to traffic, goals, alerts, and reports.',
            ],
        ];
    }

    public function annotationTypes(): array
    {
        return ['note', 'deploy', 'campaign', 'content', 'outage'];
    }

    private function sanitizeAnnotations(mixed $annotations, int $maxAnnotations): array
    {
        if (!is_array($annotations)) {
            return [];
        }
        $out = [];
        foreach ($annotations as $annotation) {
            if (!is_array($annotation)) {
                continue;
            }
            $date = $this->sanitizeDate((string) ($annotation['date'] ?? ''));
            $title = $this->sanitizeText((string) ($annotation['title'] ?? ''), 120);
            if ($date === '' || $title === '') {
                continue;
            }
            $type = $this->sanitizeEnum(
                (string) ($annotation['type'] ?? 'note'),
                $this->annotationTypes(),
                'note',
            );
            $note = $this->sanitizeText((string) ($annotation['note'] ?? ''), 400);
            $path = $this->sanitizePath((string) ($annotation['path'] ?? ''));
            $active = $this->toBool($annotation['active'] ?? false);
            $out[] = [
                'id' => $this->sanitizeId((string) ($annotation['id'] ?? '')),
                'date' => $date,
                'date_ts' => strtotime($date . ' 00:00:00 UTC') ?: 0,
                'type' => $type,
                'title' => $title,
                'note' => $note,
                'path' => $path,
                'active' => $active,
            ];
            if (count($out) >= $maxAnnotations) {
                break;
            }
        }
        usort(
            $out,
            static fn(array $a, array $b): int => (int) ($b['date_ts'] ?? 0) <=> (int) ($a['date_ts'] ?? 0),
        );
        return $out;
    }

    private function sanitizeDate(string $date): string
    {
        $date = trim($date);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return '';
        }
        return $date;
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

    private function sanitizePath(string $path): string
    {
        $path = trim(strip_tags($path));
        if ($path === '') {
            return '';
        }
        $parts = @parse_url($path);
        if (is_array($parts) && isset($parts['path'])) {
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

    private function sanitizeId(string $id): string
    {
        $id = strtolower(trim($id));
        $id = preg_replace('/[^a-z0-9_-]/', '', $id) ?? '';
        if ($id !== '') {
            return substr($id, 0, 40);
        }
        return 'ann_' . substr(sha1((string) microtime(true) . random_int(1, 999999)), 0, 12);
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

    private function assocRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $name => $count) {
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
