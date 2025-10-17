<?php
declare(strict_types=1);

namespace TrackEm\Models;

use PDO;

final class Visit
{
    private PDO $pdo;
    public function __construct(PDO $pdo){ $this->pdo = $pdo; }

    public function record(array $v): int
    {
        $this->ensureColumns();
        $st = $this->pdo->prepare("INSERT INTO visits (ip, user_agent, referrer, path, ts, meta, lat, lon, city, country)
                                   VALUES (?,?,?,?,?,?,?,?,?,?)");
        $st->execute([
            (string)$v['ip'],
            (string)$v['user_agent'],
            (string)$v['referrer'],
            (string)$v['path'],
            (int)$v['ts'],
            (string)($v['meta'] ?? '{}'),
            isset($v['lat']) ? $v['lat'] : null,
            isset($v['lon']) ? $v['lon'] : null,
            isset($v['city']) ? $v['city'] : null,
            isset($v['country']) ? $v['country'] : null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function lastN(int $n): array
    {
        $st = $this->pdo->prepare("SELECT id, ip, path, ts FROM visits ORDER BY id DESC LIMIT ?");
        $st->bindValue(1, $n, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    private function ensureColumns(): void
    {
        try {
            $this->pdo->query("SELECT lat, lon, city, country FROM visits LIMIT 0");
        } catch (\Throwable $e) {
            $this->pdo->exec("ALTER TABLE visits ADD COLUMN lat DECIMAL(9,6) NULL");
            $this->pdo->exec("ALTER TABLE visits ADD COLUMN lon DECIMAL(9,6) NULL");
            $this->pdo->exec("ALTER TABLE visits ADD COLUMN city VARCHAR(64) NULL");
            $this->pdo->exec("ALTER TABLE visits ADD COLUMN country VARCHAR(64) NULL");
        }
    }
}
