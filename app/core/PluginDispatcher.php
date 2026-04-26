<?php
declare(strict_types=1);

namespace TrackEm\Core;

final class PluginDispatcher
{
    public static function dispatch(string $route): bool
    {
        if (!preg_match('/^([a-z0-9_]+)\.([a-z0-9_]+)$/i', $route, $m)) {
            return false;
        }

        $pluginId = strtolower((string) $m[1]);
        $action = strtolower((string) $m[2]);
        if ($pluginId === '' || $action === '') {
            return false;
        }

        $baseDir = realpath(__DIR__ . '/../plugins');
        if ($baseDir === false) {
            return false;
        }

        $pluginDir = realpath($baseDir . '/' . $pluginId);
        if (
            $pluginDir === false ||
            strpos($pluginDir, $baseDir . DIRECTORY_SEPARATOR) !== 0
        ) {
            return false;
        }

        $controllerFile = $pluginDir . '/PluginController.php';
        if (!is_file($controllerFile)) {
            return false;
        }

        require_once $controllerFile;

        $class = self::controllerClassName($pluginId);
        if (!class_exists($class)) {
            return false;
        }

        $controller = new $class($pluginId, $pluginDir);
        if (!method_exists($controller, 'dispatch')) {
            return false;
        }

        return (bool) $controller->dispatch($action);
    }

    private static function controllerClassName(string $pluginId): string
    {
        $parts = preg_split('/[_\-]+/', $pluginId) ?: [];
        $parts = array_map(
            static fn(string $part): string => ucfirst(strtolower($part)),
            array_filter($parts, static fn(string $part): bool => $part !== ''),
        );

        return 'TrackEm\\Plugins\\' .
            implode('', $parts) .
            '\\PluginController';
    }
}
