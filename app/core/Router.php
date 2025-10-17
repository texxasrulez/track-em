<?php
declare(strict_types=1);

namespace TrackEm\Core;

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/AdminController.php';
require_once __DIR__ . '/../controllers/ApiController.php';

final class Router
{
    public function dispatch(): void
    {
        $p = $_GET['p'] ?? 'admin';
        switch ($p) {
            case 'login':
                (new \TrackEm\Controllers\AuthController())->login();
                break;
            case 'logout':
                (new \TrackEm\Controllers\AuthController())->logout();
                break;
            case 'admin':
                (new \TrackEm\Controllers\AdminController())->dashboard();
                break;
            case 'admin.settings':
                (new \TrackEm\Controllers\AdminController())->settings();
                break;
            case 'admin.visitors':
                (new \TrackEm\Controllers\AdminController())->visitors();
                break;
            case 'admin.users':
                (new \TrackEm\Controllers\AdminController())->users();
                break;
            case 'admin.themes':
                (new \TrackEm\Controllers\AdminController())->themes();
                break;
            case 'admin.plugins':
                (new \TrackEm\Controllers\AdminController())->plugins();
                break;
            case 'admin.help':
                (new \TrackEm\Controllers\AdminController())->help();
                break;

            // NEW: server-sent events stream and geo JSON
            case 'api.stream':
                (new \TrackEm\Controllers\ApiController())->stream();
                break;
			case 'api.realtime':
				(new \TrackEm\Controllers\ApiController())->realtime();
				break;
			case 'api.geo':
				(new \TrackEm\Controllers\ApiController())->geo();
				break;
			case 'api.health':
				(new \TrackEm\Controllers\ApiController())->health();
				break;
			case 'api.plugins':
				(new \TrackEm\Controllers\ApiController())->pluginConfigs();
				break;
			case 'api.layout.get':
				(new \TrackEm\Controllers\ApiController())->layoutGet();
				break;
			case 'api.layout.save':
				(new \TrackEm\Controllers\ApiController())->layoutSave();
				break;
			case 'api.geo.test':
				(new \TrackEm\Controllers\ApiController())->geoTest();
				break;

            case 'api.plugins.list':
                (new \TrackEm\Controllers\ApiController())->api_plugins_list(); break;
            case 'api.plugins.configs':
                (new \TrackEm\Controllers\ApiController())->pluginConfigs(); break;
                (new \TrackEm\Controllers\ApiController())->api_plugins_list(); break;
            case 'api.plugins.install':
                (new \TrackEm\Controllers\ApiController())->api_plugins_install(); break;
            case 'api.plugins.toggle':
                (new \TrackEm\Controllers\ApiController())->api_plugins_toggle(); break;
            case 'api.plugins.remove':
                (new \TrackEm\Controllers\ApiController())->api_plugins_remove(); break;
            case 'api.plugins.config.set':
                (new \TrackEm\Controllers\ApiController())->api_plugins_config_set(); break;
            case 'api.plugins.asset':
                (new \TrackEm\Controllers\ApiController())->api_plugins_asset(); break;

            default:
                http_response_code(404);
                echo "Not found";
                break;
        }
    }
}
