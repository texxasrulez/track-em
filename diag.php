<?php
declare(strict_types=1);
require_once __DIR__ . '/app/core/Bootstrap.php';
use TrackEm\Core\DB;
use TrackEm\Core\Config;

header('Content-Type: application/json');

$out = [
  'php'    => PHP_VERSION,
  'path'   => __FILE__,
  'config' => [
    'db_name' => (string) (Config::instance()->get('database.name') ?? ''),
    'privacy' => Config::instance()->get('privacy', []),
  ],
  'request'=> [
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? null,
    'host'        => $_SERVER['HTTP_HOST'] ?? null,
    'dnt'         => $_SERVER['HTTP_DNT'] ?? null,
    'cookie'      => $_SERVER['HTTP_COOKIE'] ?? '',
  ],
];

try {
  $pdo = DB::pdo();
  $c = $pdo->query("SELECT COUNT(*) AS c FROM visits")->fetch()['c'] ?? 0;
  $out['db'] = ['ok'=>true,'visits_count'=>(int)$c];
} catch (Throwable $e) {
  $out['db'] = ['ok'=>false,'error'=>$e->getMessage()];
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
