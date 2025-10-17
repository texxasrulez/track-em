<?php
declare(strict_types=1);

// Use project-relative paths/redirects so the app works in a subfolder like /track-em
$cfgFile  = __DIR__ . '/config/config.php';
$lockFile = __DIR__ . '/config/.installed.lock';

if (!is_file($cfgFile) || !is_file($lockFile)) {
    $base = (rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') ?: '');
    header('Location: ' . $base . '/install.php');
    exit;
}

require_once __DIR__ . '/app/core/Bootstrap.php';

$bootstrap = new TrackEm\Core\Bootstrap();
$bootstrap->run();
