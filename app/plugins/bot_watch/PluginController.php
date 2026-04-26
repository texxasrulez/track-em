<?php
declare(strict_types=1);

namespace TrackEm\Plugins\BotWatch;

use TrackEm\Core\Security;

require_once dirname(__DIR__, 2) . '/core/Security.php';
require_once dirname(__DIR__, 2) . '/core/DB.php';
require_once __DIR__ . '/BotWatchService.php';

final class PluginController
{
    private BotWatchService $service;
    private string $pluginDir;

    public function __construct(string $pluginId, string $pluginDir)
    {
        $this->pluginDir = rtrim($pluginDir, '/\\');
        $this->service = new BotWatchService($pluginId, $pluginDir);
    }

    public function dispatch(string $action): bool
    {
        return match ($action) {
            'admin' => $this->admin(),
            'save' => $this->save(),
            'reset' => $this->reset(),
            'rebuild' => $this->rebuild(),
            default => false,
        };
    }

    private function admin(): bool
    {
        if (!$this->requireAdmin()) {
            return true;
        }

        $config = $this->service->loadConfig();
        $report = $this->service->report($config, false);
        $state = $this->service->loadState();
        $csrf = $this->service->csrfToken();

        require $this->pluginDir . '/views/admin_fragment.php';
        return true;
    }

    private function save(): bool
    {
        if (!$this->requireAdminPost()) {
            return true;
        }

        $config = $this->service->saveFromRequest($_POST);
        $this->service->clearCachedReport();
        $this->json(200, ['ok' => true, 'config' => $config]);
        return true;
    }

    private function reset(): bool
    {
        if (!$this->requireAdminPost()) {
            return true;
        }

        $this->service->resetConfig();
        $this->service->clearCachedReport();
        $this->json(200, ['ok' => true, 'config' => $this->service->loadConfig()]);
        return true;
    }

    private function rebuild(): bool
    {
        if (!$this->requireAdminPost()) {
            return true;
        }

        $config = $this->service->loadConfig();
        $report = $this->service->report($config, true);
        $this->json(200, ['ok' => true, 'report' => $report]);
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
        if (
            !Security::rateLimit(
                'bot_watch_admin:' . Security::clientIpMasked(),
                60,
                30,
            )
        ) {
            $this->json(429, ['ok' => false, 'error' => 'rate_limited']);
            return false;
        }
        if (!Security::verifyCsrf((string) ($_POST['csrf'] ?? ''))) {
            $this->json(400, ['ok' => false, 'error' => 'bad_csrf']);
            return false;
        }
        return true;
    }

    private function json(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
}
