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
            'upload_theme' => $this->uploadTheme(),
            'delete_theme' => $this->deleteTheme(),
            'counter_data' => $this->counterData(),
            'digit' => $this->digit(),
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
        $digitThemes = $this->service->digitThemes();
        $zipUploadAvailable = $this->service->zipUploadAvailable();
        $counterPreview = $this->service->counterPreviewContext($config);

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
                'csrf' => $this->service->csrfToken(),
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
                'csrf' => $this->service->csrfToken(),
            ],
        );
        return true;
    }

    private function uploadTheme(): bool
    {
        if (!$this->requireAdminPost(true)) {
            return true;
        }

        try {
            $result = $this->service->uploadDigitTheme($_POST, $_FILES, $this->service->loadConfig());
        } catch (\RuntimeException $e) {
            $this->json(200, [
                'ok' => false,
                'error' => $e->getMessage(),
                'message' => $this->messageForError($e->getMessage()),
                'csrf' => $this->service->csrfToken(),
            ]);
            return true;
        }

        $config = $this->service->loadConfig();
        $this->json(200, [
            'ok' => true,
            'result' => $result,
            'digit_themes' => $this->service->digitThemes(),
            'counter_preview' => $this->service->counterPreviewContext($config),
            'csrf' => $this->service->csrfToken(),
        ]);
        return true;
    }

    private function deleteTheme(): bool
    {
        if (!$this->requireAdminPost(true)) {
            return true;
        }

        try {
            $result = $this->service->deleteDigitTheme(
                (string) ($_POST['theme_id'] ?? ''),
                $this->service->loadConfig(),
            );
        } catch (\RuntimeException $e) {
            $this->json(200, [
                'ok' => false,
                'error' => $e->getMessage(),
                'message' => $this->messageForError($e->getMessage()),
                'csrf' => $this->service->csrfToken(),
            ]);
            return true;
        }

        $config = $this->service->loadConfig();
        $this->json(200, [
            'ok' => true,
            'result' => $result,
            'digit_themes' => $this->service->digitThemes(),
            'counter_preview' => $this->service->counterPreviewContext($config),
            'csrf' => $this->service->csrfToken(),
        ]);
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

    private function digit(): bool
    {
        if (!$this->allowPublicRate('digit', 60, 180)) {
            return true;
        }

        $themeId = (string) ($_GET['id'] ?? '');
        $digit = (string) ($_GET['n'] ?? '');
        $path = $this->service->resolveDigitThemeFile($themeId, $digit);
        if ($path === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            echo 'Not found';
            return true;
        }

        http_response_code(200);
        header('Content-Type: image/png');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=86400, immutable');
        readfile($path);
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

    private function requireAdminPost(bool $softErrors = false): bool
    {
        if (!$this->requireAdmin()) {
            return false;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json($softErrors ? 200 : 405, [
                'ok' => false,
                'error' => 'method_not_allowed',
                'message' => $this->messageForError('method_not_allowed'),
                'csrf' => $this->service->csrfToken(),
            ]);
            return false;
        }
        if (!$this->allowAdminRate()) {
            $this->json($softErrors ? 200 : 429, [
                'ok' => false,
                'error' => 'rate_limited',
                'message' => $this->messageForError('rate_limited'),
                'csrf' => $this->service->csrfToken(),
            ]);
            return false;
        }
        if (!Security::verifyCsrf((string) ($_POST['csrf'] ?? ''))) {
            $this->json($softErrors ? 200 : 400, [
                'ok' => false,
                'error' => 'bad_csrf',
                'message' => $this->messageForError('bad_csrf'),
                'csrf' => $this->service->csrfToken(),
            ]);
            return false;
        }
        return true;
    }

    private function messageForError(string $code): string
    {
        return match ($code) {
            'bad_csrf' => 'The plugin form token expired. Reload the plugin page and try again.',
            'zip_unavailable' => 'ZIP uploads are unavailable on this server because the PHP ZIP extension is missing.',
            'theme_name_invalid' => 'Enter a theme name before uploading the ZIP file.',
            'zip_missing' => 'Choose a ZIP file containing 0.png through 9.png.',
            'zip_invalid' => 'Upload a valid ZIP file. Only ZIP archives are accepted.',
            'zip_too_large' => 'The ZIP file is too large. Keep it under 2 MB.',
            'zip_upload_incomplete' => 'The ZIP upload did not complete. Try again.',
            'zip_nested_path' => 'The ZIP must keep 0.png through 9.png at the archive root with no folders.',
            'zip_extra_files' => 'The ZIP may contain only 0.png through 9.png. Remove all extra files.',
            'zip_missing_digits' => 'The ZIP must include every digit file from 0.png through 9.png.',
            'digit_file_too_large' => 'Each digit PNG must stay under 100 KB.',
            'png_invalid' => 'Each digit file must be a real PNG image. SVG and renamed non-images are rejected.',
            'png_dimensions_invalid' => 'Each digit PNG must be no larger than 128x128 pixels.',
            'theme_id_conflict' => 'That theme name could not be stored safely. Try a different theme name.',
            'built_in_theme_protected' => 'Built-in digit themes cannot be deleted.',
            'active_theme_protected' => 'Switch away from the active theme before deleting it.',
            'theme_not_found' => 'The selected uploaded theme was not found.',
            'method_not_allowed' => 'This action only accepts POST requests.',
            'rate_limited' => 'Too many plugin admin requests were sent too quickly. Wait a moment and try again.',
            default => 'The request could not be completed safely.',
        };
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
