<?php
declare(strict_types=1);
namespace TrackEm\Core;
use PDO, PDOException;
final class DB {
    private static ?PDO $pdo=null;
    public static function pdo(): PDO {
        if (self::$pdo) return self::$pdo;
        $c = Config::instance()->get('database', []);
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $c['host'] ?? '127.0.0.1', (int)($c['port'] ?? 3306), $c['name'] ?? 'trackem');
        try {
            $pdo = new PDO($dsn, $c['user'] ?? 'root', $c['pass'] ?? '', [
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES=>false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500); echo "DB connection failed."; exit;
        }
        return self::$pdo = $pdo;
    }
}
