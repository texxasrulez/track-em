<?php
declare(strict_types=1);
namespace TrackEm\Models;
use PDO;
final class User {
    public function __construct(private PDO $pdo){}
    public function findByUsername(string $u): ?array {
        $st=$this->pdo->prepare("SELECT * FROM users WHERE username=? LIMIT 1"); $st->execute([$u]); $row=$st->fetch(); return $row?:null;
    }
    public function verify(string $u,string $p): ?array {
        $row=$this->findByUsername($u); if($row && password_verify($p,$row['password_hash'])) return $row; return null;
    }
}
