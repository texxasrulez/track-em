<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/DB.php';
require_once __DIR__ . '/../app/core/Config.php';

// Avoid 'use' to ensure we're calling the exact classes we expect
\TrackEm\Core\Config::boot();

$cfg = \TrackEm\Core\Config::instance()->all();
$days = (int)($cfg['retention']['days'] ?? 90);
$cutoff = time() - ($days * 86400);

// Get PDO from the static accessor (DB::pdo), not a nonexistent instance()
$pdo = \TrackEm\Core\DB::pdo();

$stmt = $pdo->prepare('DELETE FROM visits WHERE ts < :cutoff');
$stmt->execute([':cutoff' => $cutoff]);
$affected = $stmt->rowCount();

echo "Purged {$affected} rows older than {$days} days.\n";
