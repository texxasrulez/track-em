<?php
declare(strict_types=1);

namespace TrackEm\Plugins\EventTracking;

use TrackEm\Core\Security;

require_once dirname(__DIR__, 2) . '/core/Security.php';
require_once dirname(__DIR__, 2) . '/core/DB.php';
require_once __DIR__ . '/EventTrackingService.php';

final class PluginController
{
    private string $pluginId;
    private string $pluginDir;
    private EventTrackingService $service;

    public function __construct(string $pluginId, string $pluginDir)
    {
        $this->pluginId = $pluginId;
        $this->pluginDir = rtrim($pluginDir, '/\\');
        $this->service = new EventTrackingService($pluginId, $pluginDir);
    }

    public function dispatch(string $action): bool
    {
        return match ($action) {
            'admin' => $this->admin(),
            'save' => $this->save(),
            'reset' => $this->reset(),
            'asset' => $this->asset(),
            'collect' => $this->collect(),
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
        $scriptSnippet = $this->service->scriptSnippet();
        $declarativeSnippet = $this->service->declarativeSnippet();
        $report = $this->service->adminReport($config);

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
                'script_snippet' => $this->service->scriptSnippet(),
                'declarative_snippet' => $this->service->declarativeSnippet(),
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
                'script_snippet' => $this->service->scriptSnippet(),
                'declarative_snippet' => $this->service->declarativeSnippet(),
            ],
        );
        return true;
    }

    private function asset(): bool
    {
        $path = $this->service->assetPath((string) ($_GET['file'] ?? ''));
        if ($path === null) {
            http_response_code(404);
            echo 'not found';
            return true;
        }

        header('Content-Type: ' . $this->service->assetContentType($path));
        header('Cache-Control: public, max-age=300');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        return true;
    }

    private function collect(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json(405, ['ok' => false, 'error' => 'method_not_allowed']);
            return true;
        }
        if (!$this->allowCollectRate()) {
            $this->json(429, ['ok' => false, 'error' => 'rate_limited']);
            return true;
        }

        $config = $this->service->loadConfig();
        if (!$this->service->collectionEnabled($config)) {
            $this->json(404, ['ok' => false, 'error' => 'collection_disabled']);
            return true;
        }

        $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        $raw = file_get_contents('php://input') ?: '';
        if (strpos($type, 'application/json') === false && strpos($type, 'text/plain') === false) {
            $this->json(400, ['ok' => false, 'error' => 'invalid_content_type']);
            return true;
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $this->json(400, ['ok' => false, 'error' => 'invalid_json']);
            return true;
        }

        try {
            $event = $this->service->collectEvent($payload, $config);
        } catch (\RuntimeException $e) {
            $this->json(400, ['ok' => false, 'error' => $e->getMessage()]);
            return true;
        }

        $this->json(
            200,
            [
                'ok' => true,
                'stored' => [
                    'event' => $event['event'],
                    'label' => $event['label'],
                    'path' => $event['path'],
                ],
            ],
        );
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
                'event_tracking_admin:' . Security::clientIpMasked(),
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

    private function allowCollectRate(): bool
    {
        return Security::rateLimit(
            'event_tracking_collect:' . Security::clientIpMasked(),
            60,
            180,
        );
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
