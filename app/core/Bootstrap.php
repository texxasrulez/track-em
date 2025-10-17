<?php
declare(strict_types=1);

namespace TrackEm\Core;

/**
 * Autoloader:
 * TrackEm\{Top}\Sub\Class => app/{top-lowercase}/Sub/Class.php
 *   e.g. TrackEm\Core\Config => app/core/Config.php
 */
spl_autoload_register(function ($class) {
    if (strncmp($class, 'TrackEm\\', 9) !== 0) {
        return;
    }
    $rel   = substr($class, 9);                 // strip 'TrackEm\'
    $parts = explode('\\', $rel);               // [Core, Config] or [Controllers, AdminController]
    if ($parts) {
        $parts[0] = strtolower($parts[0]);      // Core -> core, Controllers -> controllers, etc.
    }
    $path = __DIR__ . '/../' . implode('/', $parts) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

/** Hard-require the core we need during boot to avoid autoload timing issues. */
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/I18n.php';
require_once __DIR__ . '/Controller.php';   // <-- base Controller
require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/HookManager.php';

Config::boot();
Security::startSecureSession();

final class Bootstrap
{
    public function run(): void
    {
        I18n::boot();
        (new Router())->dispatch();
    }
}
