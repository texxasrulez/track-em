<?php
declare(strict_types=1);

namespace TrackEm\Core;

// Ensure dependencies are loaded before we render
require_once __DIR__ . '/Theme.php';
require_once __DIR__ . '/I18n.php';

abstract class Controller
{
    protected function render(string $view, array $vars = []): void
    {
        // Pre-load labels so views/layout can call I18n::t safely
        I18n::boot();

        // Resolve layout + view paths
        $layout   = __DIR__ . '/../views/layouts/default.php';
        $viewFile = __DIR__ . '/../views/' . $view . '.php';

        // Make $vars available to the included templates
        extract($vars, EXTR_SKIP);

        // Render layout (which includes $viewFile)
        require $layout;
    }
}
