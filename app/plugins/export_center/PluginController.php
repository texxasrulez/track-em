<?php
declare(strict_types=1);

namespace TrackEm\Plugins\ExportCenter;

use TrackEm\Core\Security;

require_once dirname(__DIR__, 2) . '/core/Security.php';
require_once dirname(__DIR__, 2) . '/core/DB.php';
require_once __DIR__ . '/ExportCenterService.php';

final class PluginController
{
    private ExportCenterService $service;
    private string $pluginDir;

    public function __construct(string $pluginId, string $pluginDir)
    {
        $this->pluginDir = rtrim($pluginDir, '/\\');
        $this->service = new ExportCenterService($pluginId, $pluginDir);
    }

    public function dispatch(string $action): bool
    {
        return match ($action) {
            'admin' => $this->admin(),
            'save' => $this->save(),
            'reset' => $this->reset(),
            'generate' => $this->generate(),
            'download' => $this->download(),
            default => false,
        };
    }

    private function admin(): bool
    {
        if (!$this->requireAdmin()) {
            return true;
        }

        $config = $this->service->loadConfig();
        $exports = $this->service->listExports();
        $capabilities = $this->service->optionalCapabilities();
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
        $this->json(200, ['ok' => true, 'config' => $config]);
        return true;
    }

    private function reset(): bool
    {
        if (!$this->requireAdminPost()) {
            return true;
        }

        $this->service->resetConfig();
        $this->json(200, ['ok' => true, 'config' => $this->service->loadConfig()]);
        return true;
    }

    private function generate(): bool
    {
        if (!$this->requireAdminPost()) {
            return true;
        }

        $config = $this->service->loadConfig();
        $result = $this->service->generateExports($config);
        $this->json(200, ['ok' => true, 'result' => $result]);
        return true;
    }

    private function download(): bool
    {
        if (!$this->requireAdmin()) {
            return true;
        }

        $path = $this->service->resolveExportPath((string) ($_GET['file'] ?? ''));
        if ($path === null) {
            http_response_code(404);
            echo 'Export not found';
            return true;
        }

        $file = basename($path);
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($ext === 'json' ? 'application/json; charset=utf-8' : 'text/csv; charset=utf-8'));
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
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
                'export_center_admin:' . Security::clientIpMasked(),
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
