<?php
declare(strict_types=1);

namespace TrackEm\Plugins\PublicWidgets;

use TrackEm\Core\Security;

require_once dirname(__DIR__, 2) . '/core/Security.php';
require_once dirname(__DIR__, 2) . '/core/DB.php';
require_once __DIR__ . '/PublicWidgetsService.php';

final class PluginController
{
    private string $pluginId;
    private string $pluginDir;
    private PublicWidgetsService $service;

    public function __construct(string $pluginId, string $pluginDir)
    {
        $this->pluginId = $pluginId;
        $this->pluginDir = rtrim($pluginDir, '/\\');
        $this->service = new PublicWidgetsService($pluginId, $pluginDir);
    }

    public function dispatch(string $action): bool
    {
        return match ($action) {
            'admin' => $this->admin(),
            'save' => $this->save(),
            'reset' => $this->reset(),
            'counter_data' => $this->counterData(),
            'map_embed' => $this->mapEmbed(),
            'map_data' => $this->mapData(),
            default => false,
        };
    }

    private function admin(): bool
    {
        if (!$this->requireAdmin()) {
            return true;
        }

        $config = $this->service->loadConfig();
        $csrf = $this->service->csrfToken();
        $counterSnippet = $this->service->counterSnippet($config);
        $mapSnippet = $this->service->mapSnippet($config);
        $basemapOptions = $this->service->basemapOptions();

        require $this->pluginDir . '/views/admin_fragment.php';
        return true;
    }

    private function save(): bool
    {
        if (!$this->requireAdminPost()) {
            return true;
        }

        $config = $this->service->saveFromRequest($_POST);
        $this->json(
            200,
            [
                'ok' => true,
                'config' => $config,
                'counter_snippet' => $this->service->counterSnippet($config),
                'map_snippet' => $this->service->mapSnippet($config),
            ],
        );
        return true;
    }

    private function reset(): bool
    {
        if (!$this->requireAdminPost()) {
            return true;
        }

        $this->service->resetConfig();
        $config = $this->service->loadConfig();
        $this->json(
            200,
            [
                'ok' => true,
                'config' => $config,
                'counter_snippet' => $this->service->counterSnippet($config),
                'map_snippet' => $this->service->mapSnippet($config),
            ],
        );
        return true;
    }

    private function counterData(): bool
    {
        if (!$this->allowPublicRate('counter', 60, 90)) {
            return true;
        }

        $config = $this->service->loadConfig();
        if (
            !$this->service->isPluginEnabled() ||
            empty($config['counter']['enabled'])
        ) {
            $this->json(404, ['ok' => false, 'error' => 'counter_disabled']);
            return true;
        }

        $payload = $this->service->counterPayload(
            $config,
            isset($_GET['scope']) ? (string) $_GET['scope'] : null,
            isset($_GET['path']) ? (string) $_GET['path'] : null,
        );
        header('Cache-Control: public, max-age=30');
        $this->json(200, $payload);
        return true;
    }

    private function mapEmbed(): bool
    {
        if (!$this->allowPublicRate('map_embed', 60, 60)) {
            return true;
        }

        $config = $this->service->loadConfig();
        if (!$this->service->isPluginEnabled() || empty($config['map']['enabled'])) {
            $this->htmlError(404, 'Map unavailable');
            return true;
        }

        try {
            $ctx = $this->service->mapEmbedContext(
                $config,
                $this->service->sanitizeProfileId((string) ($_GET['id'] ?? '')),
            );
        } catch (\RuntimeException) {
            $this->htmlError(404, 'Map not found');
            return true;
        }

        require $this->pluginDir . '/views/map_embed.php';
        return true;
    }

    private function mapData(): bool
    {
        if (!$this->allowPublicRate('map_data', 60, 60)) {
            return true;
        }

        $config = $this->service->loadConfig();
        if (!$this->service->isPluginEnabled() || empty($config['map']['enabled'])) {
            $this->json(404, ['ok' => false, 'error' => 'map_disabled']);
            return true;
        }

        try {
            $payload = $this->service->mapPayload(
                $config,
                $this->service->sanitizeProfileId((string) ($_GET['id'] ?? '')),
            );
        } catch (\RuntimeException) {
            $this->json(404, ['ok' => false, 'error' => 'map_not_found']);
            return true;
        }

        header('Cache-Control: public, max-age=60');
        $this->json(200, $payload);
        return true;
    }

    private function requireAdmin(): bool
    {
        Security::startSecureSession();
        if (!isset($_SESSION['uid'])) {
            http_response_code(401);
            echo 'Unauthorized';
            return false;
        }
        return true;
    }

    private function requireAdminPost(): bool
    {
        if (!$this->requireAdmin()) {
            return false;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json(405, ['ok' => false, 'error' => 'method_not_allowed']);
            return false;
        }
        if (!$this->allowAdminRate()) {
            $this->json(429, ['ok' => false, 'error' => 'rate_limited']);
            return false;
        }
        if (!Security::verifyCsrf((string) ($_POST['csrf'] ?? ''))) {
            $this->json(400, ['ok' => false, 'error' => 'bad_csrf']);
            return false;
        }
        return true;
    }

    private function allowAdminRate(): bool
    {
        return Security::rateLimit(
            'public_widgets_admin:' . Security::clientIpMasked(),
            60,
            30,
        );
    }

    private function allowPublicRate(string $suffix, int $window, int $max): bool
    {
        $allowed = Security::rateLimit(
            'public_widgets_' . $suffix . ':' . Security::clientIpMasked(),
            $window,
            $max,
        );
        if (!$allowed) {
            $this->json(429, ['ok' => false, 'error' => 'rate_limited']);
        }
        return $allowed;
    }

    private function json(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    private function htmlError(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo '<!doctype html><meta charset="utf-8"><title>Public Widgets</title><body style="font:14px system-ui,sans-serif;padding:24px;color:#1f2937">' .
            htmlspecialchars($message, ENT_QUOTES) .
            '</body>';
    }
}
