<?php
declare(strict_types=1);

namespace TrackEm\Core;

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/Config.php';

use PDO;

final class PluginRegistry
{
    private PDO $pdo;
    private string $dir;

    public function __construct(?PDO $pdo = null, ?string $dir = null)
    {
        $this->pdo = $pdo ?: DB::pdo();
        $this->dir = $dir ?: (__DIR__ . '/../../plugins');
        $this->ensureTable();
        $this->syncWithFilesystem();
    }

    public function list(): array
    {
        $out = [];
        $manifests = $this->discover();
        foreach ($manifests as $man) {
            $row = $this->getDbRow((string)$man['id']);
            $out[] = [
                'id'          => (string)$man['id'],
                'name'        => (string)($man['name'] ?? $man['id']),
                'version'     => (string)($man['version'] ?? '0.0.0'),
                'description' => (string)($man['description'] ?? ''),
                'schema'      => $this->normalizeSchema($man['configSchema'] ?? []),
                'enabled'     => (int)($row['enabled'] ?? 0),
                'config'      => $this->jsonDecode((string)($row['config'] ?? '{}')),
            ];
        }
        return $out;
    }

    public function setEnabled(string $id, bool $enabled): void
    {
        $st = $this->pdo->prepare(
            "INSERT INTO plugins (id, enabled, config)
             VALUES (?, ?, COALESCE((SELECT config FROM plugins WHERE id=?), '{}'))
             ON DUPLICATE KEY UPDATE enabled=VALUES(enabled)"
        );
        $st->execute([$id, $enabled ? 1 : 0, $id]);
    }

    public function saveConfig(string $id, array $config): void
    {
        $json = json_encode($config, JSON_UNESCAPED_SLASHES);
        $st = $this->pdo->prepare(
            "INSERT INTO plugins (id, enabled, config)
             VALUES (?, 1, ?)
             ON DUPLICATE KEY UPDATE config=VALUES(config)"
        );
        $st->execute([$id, $json]);
    }

    public function getConfig(string $id, array $default = []): array
    {
        $st = $this->pdo->prepare("SELECT config FROM plugins WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ? $this->jsonDecode((string)($row['config'] ?? '{}')) : $default;
    }

    /* ---------- internals ---------- */

    private function ensureTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS plugins (
                id VARCHAR(64) PRIMARY KEY,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                config JSON NULL
             )"
        );
    }

    private function syncWithFilesystem(): void
    {
        $manifests = $this->discover();
        foreach ($manifests as $man) {
            $id = (string)$man['id'];
            $st = $this->pdo->prepare("INSERT IGNORE INTO plugins (id, enabled, config) VALUES (?, 1, '{}')");
            $st->execute([$id]);
        }
    }

    private function discover(): array
    {
        $out = [];
        if (!is_dir($this->dir)) return $out;

        $list = scandir($this->dir);
        if (!is_array($list)) return $out;

        foreach ($list as $d) {
            if ($d === '.' || $d === '..') continue;
            $file = $this->dir . '/' . $d . '/plugin.json';
            if (is_file($file)) {
                $data = (string)file_get_contents($file);
                $j = $this->jsonDecode($data);
                if (isset($j['id'])) {
                    $out[] = $j;
                }
            }
        }
        usort($out, function ($a, $b) {
            $ai = (string)($a['id'] ?? '');
            $bi = (string)($b['id'] ?? '');
            return strcmp($ai, $bi);
        });
        return $out;
    }

    private function getDbRow(string $id): ?array
    {
        $st = $this->pdo->prepare("SELECT id, enabled, config FROM plugins WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    private function normalizeSchema($schema): array
    {
        if (!is_array($schema)) return ['fields' => []];
        $fieldsIn = isset($schema['fields']) && is_array($schema['fields']) ? $schema['fields'] : [];
        $fieldsOut = [];
        foreach ($fieldsIn as $f) {
            if (!is_array($f) || empty($f['name'])) continue;
            $fieldsOut[] = [
                'name'    => (string)$f['name'],
                'label'   => (string)($f['label'] ?? $f['name']),
                'type'    => (string)($f['type'] ?? 'text'), // text|number|checkbox|select
                'help'    => (string)($f['help'] ?? ''),
                'options' => isset($f['options']) && is_array($f['options']) ? $f['options'] : [],
                'default' => $f['default'] ?? null,
            ];
        }
        return ['fields' => $fieldsOut];
    }

    private function jsonDecode(string $s): array
    {
        $j = json_decode($s, true);
        return is_array($j) ? $j : [];
    }
}
