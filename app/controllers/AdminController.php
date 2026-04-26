<?php
declare(strict_types=1);

namespace TrackEm\Controllers;

require_once __DIR__ . "/_addons/AdminPluginsAddon.php";
use TrackEm\Controllers\_addons\AdminPluginsAddon;
require_once __DIR__ . "/../core/Controller.php";
require_once __DIR__ . "/../core/DB.php";
require_once __DIR__ . "/../core/I18n.php";
require_once __DIR__ . "/../core/Theme.php";
require_once __DIR__ . "/../core/Config.php";
require_once __DIR__ . "/../core/Security.php";
require_once __DIR__ . "/../core/PluginRegistry.php";
require_once __DIR__ . "/../models/User.php";
use TrackEm\Models\User;
require_once __DIR__ . "/../core/Geo.php";
require_once __DIR__ . "/../models/Visit.php";

use TrackEm\Core\Controller;
use TrackEm\Core\DB;
use TrackEm\Core\I18n;
use TrackEm\Core\Theme;
use TrackEm\Core\Config;
use TrackEm\Core\Security;
use TrackEm\Core\PluginRegistry;
use TrackEm\Core\Geo;
use TrackEm\Models\Visit;

final class AdminController extends Controller
{
    use AdminPluginsAddon;

    private function guard(): void
    {
        if (!isset($_SESSION["uid"])) {
            header("Location: ?p=login");
            exit();
        }
    }

    public function dashboard(): void
    {
        $this->guard();
        I18n::boot();
        $visits = (new Visit(DB::pdo()))->lastN(25);
        $this->render("admin/dashboard", compact("visits"));
    }

    public function settings(): void
    {
        $this->guard();
        I18n::boot();
        $cfg = Config::instance()->all();

        // Handle POST for Geo settings & download
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $cfg = \TrackEm\Core\Config::instance();
            if ((bool) $cfg->get("rate_limit.enabled", true)) {
                $window = (int) $cfg->get("rate_limit.window", 60);
                $max = (int) $cfg->get("rate_limit.max_events", 120);
                $key =
                    "admin_settings:" .
                    \TrackEm\Core\Security::clientIpMasked();
                if (!\TrackEm\Core\Security::rateLimit($key, $window, $max)) {
                    http_response_code(429);
                    echo "Rate limit";
                    return;
                }
            }
            if (!Security::verifyCsrf($_POST["csrf"] ?? "")) {
                http_response_code(400);
                echo "Bad CSRF";
                return;
            }

            $section = $_POST["section"] ?? "";
            if ($section === "security") {
                $enabled =
                    isset($_POST["rl_enabled"]) &&
                    ($_POST["rl_enabled"] === "1" ||
                        $_POST["rl_enabled"] === "on" ||
                        $_POST["rl_enabled"] === "true");
                $window = max(1, (int) ($_POST["rl_window"] ?? 60));
                $max = max(1, (int) ($_POST["rl_max"] ?? 120));
                $retDays = max(1, (int) ($_POST["ret_days"] ?? 90));

                $this->writeConfigPatch([
                    "rate_limit" => [
                        "enabled" => $enabled,
                        "window" => $window,
                        "max_events" => $max,
                    ],
                    "retention" => ["days" => $retDays],
                ]);

                header("Location: ?p=admin.settings");
                exit();
            }

            if ($section === "dashboard") {
                $_SESSION["flash_debug_settings"] = [
                    "branch" => "dashboard",
                    "post" => $_POST,
                ];
                $rowLimit = max(
                    10,
                    min(10000, (int) ($_POST["dash_row_limit"] ?? 200)),
                );
                $showIcons = isset($_POST["dash_show_icons"]);
                $ipTips = isset($_POST["dash_ip_tooltips"]);
                $this->writeConfigAll([
                    "dashboard" => [
                        "row_limit" => $rowLimit,
                        "show_icons" => $showIcons,
                        "ip_tooltips" => $ipTips,
                    ],
                ]);
                header("Location: ?p=admin.settings");
                exit();
            }
            $action = $_POST["action"] ?? "";
            if ($action === "save_geo") {
                $_SESSION["flash_debug_settings"] = [
                    "branch" => "save_geo",
                    "post" => $_POST,
                ];
                $geo = [
                    "enabled" => isset($_POST["geo_enabled"]) ? true : false,
                    "provider" => $_POST["geo_provider"] ?? "ip-api",
                    "ip_api_base" =>
                        $_POST["ip_api_base"] ?? "http://ip-api.com/json/",
                    "allow_insecure_http" => isset(
                        $_POST["geo_allow_insecure_http"],
                    )
                        ? true
                        : false,
                    "timeout_sec" => (float) ($_POST["geo_timeout"] ?? 0.8),
                    "max_lookups" => (int) ($_POST["geo_maxlookups"] ?? 15),
                    "mm_account_id" => $_POST["mm_account_id"] ?? "",
                    "mm_license_key" => $_POST["mm_license_key"] ?? "",
                    "mmdb_path" =>
                        $_POST["mmdb_path"] ??
                        __DIR__ . "/../../data/GeoLite2-City.mmdb",
                ];
                $this->writeConfigGeo($geo);
                header("Location: ?p=admin.settings");
                exit();
            } elseif ($action === "download_mmdb") {
                $_SESSION["flash_debug_settings"] = [
                    "branch" => "download_mmdb",
                    "post" => $_POST,
                ];
                $license = trim($_POST["mm_license_key"] ?? "");
                $mmdbDir = realpath(__DIR__ . "/../../") . "/data";
                if (!is_dir($mmdbDir)) {
                    @mkdir($mmdbDir, 0775, true);
                }
                $res =
                    $license !== ""
                        ? Geo::downloadGeoLite2($license, $mmdbDir)
                        : [
                            "ok" => false,
                            "msg" => "License key required",
                            "path" => null,
                        ];
                $_SESSION["flash_geo"] = $res;
                header("Location: ?p=admin.settings");
                exit();
            }
        }

        $flash_geo = $_SESSION["flash_geo"] ?? null;
        unset($_SESSION["flash_geo"]);
        $langs = I18n::languages();
        $this->render("admin/settings", compact("cfg", "langs", "flash_geo"));
    }

    public function users(): void
    {
        $this->guard();
        I18n::boot();

        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            if (!\TrackEm\Core\Security::verifyCsrf($_POST["csrf"] ?? "")) {
                http_response_code(400);
                echo "Bad CSRF";
                exit();
            }
            $action = $_POST["action"] ?? "";
            $id = (int) ($_POST["id"] ?? 0);

            if ($action === "create") {
                $username = trim((string) ($_POST["username"] ?? ""));
                $role =
                    ($_POST["role"] ?? "user") === "admin" ? "admin" : "user";
                $password = (string) ($_POST["password"] ?? "");
                if ($username !== "") {
                    $st = \TrackEm\Core\DB::pdo()->prepare(
                        "INSERT INTO users (username, role, password_hash) VALUES (?, ?, ?)",
                    );
                    $st->execute([
                        $username,
                        $role,
                        $password !== ""
                            ? password_hash($password, PASSWORD_DEFAULT)
                            : password_hash(
                                bin2hex(random_bytes(8)),
                                PASSWORD_DEFAULT,
                            ),
                    ]);
                }
                header("Location: ?p=admin.users");
                exit();
            }

            if ($action === "update" && $id > 0) {
                $username = trim((string) ($_POST["username"] ?? ""));
                $role =
                    ($_POST["role"] ?? "user") === "admin" ? "admin" : "user";
                $password = (string) ($_POST["password"] ?? "");

                if ($password !== "") {
                    $st = \TrackEm\Core\DB::pdo()->prepare(
                        "UPDATE users SET username=?, role=?, password_hash=? WHERE id=?",
                    );
                    $st->execute([
                        $username,
                        $role,
                        password_hash($password, PASSWORD_DEFAULT),
                        $id,
                    ]);
                } else {
                    $st = \TrackEm\Core\DB::pdo()->prepare(
                        "UPDATE users SET username=?, role=? WHERE id=?",
                    );
                    $st->execute([$username, $role, $id]);
                }
                header("Location: ?p=admin.users");
                exit();
            }

            if ($action === "delete" && $id > 0) {
                $st = \TrackEm\Core\DB::pdo()->prepare(
                    "DELETE FROM users WHERE id=? LIMIT 1",
                );
                $st->execute([$id]);
                header("Location: ?p=admin.users");
                exit();
            }

            header("Location: ?p=admin.users");
            exit();
        }

        $users = \TrackEm\Core\DB::pdo()
            ->query(
                "SELECT id,username,role,created_at FROM users ORDER BY id DESC",
            )
            ->fetchAll();
        $this->render("admin/users", compact("users"));
    }

    public function themes(): void
    {
        $this->guard();
        \TrackEm\Core\I18n::boot();

        // POST: activate
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $section = $_POST["section"] ?? "";
            if ($section === "dashboard") {
                $_SESSION["flash_debug_settings"] = [
                    "branch" => "dashboard",
                    "post" => $_POST,
                ];
                $rowLimit = max(
                    10,
                    min(10000, (int) ($_POST["dash_row_limit"] ?? 200)),
                );
                $showIcons = isset($_POST["dash_show_icons"]);
                $ipTips = isset($_POST["dash_ip_tooltips"]);
                $this->writeConfigAll([
                    "dashboard" => [
                        "row_limit" => $rowLimit,
                        "show_icons" => $showIcons,
                        "ip_tooltips" => $ipTips,
                    ],
                ]);
                header("Location: ?p=admin.settings");
                exit();
            }
            if (!\TrackEm\Core\Security::verifyCsrf($_POST["csrf"] ?? "")) {
                http_response_code(400);
                echo "Bad CSRF";
                return;
            }
            if (($_POST["action"] ?? "") === "activate") {
                \TrackEm\Core\Theme::setActive(
                    (string) ($_POST["theme_id"] ?? ""),
                );
                header("Location: ?p=admin.themes");
                exit();
            }
        }

        // GET: preview=theme_id → set preview cookie (1h) then redirect
        if (isset($_GET["preview"])) {
            $id = preg_replace("/[^a-z0-9_-]/i", "", (string) $_GET["preview"]);
            if ($id !== "") {
                \TrackEm\Core\Theme::setPreview($id, time() + 3600);
            }
            header("Location: ?p=admin.themes");
            exit();
        }

        // Render view (it will call Theme::list/activeId internally for freshness)
        $this->render("admin/themes", []);
    }

    public function plugins(): void
    {
        $this->guard();
        I18n::boot();
        $reg = new PluginRegistry();
        $pluginQuery = preg_replace(
            "/[^a-z0-9_\-\.]/i",
            "",
            (string) ($_REQUEST["plugin"] ?? ""),
        );
        $filterQuery = strtolower(trim((string) ($_REQUEST["filter"] ?? "all")));
        if (!in_array($filterQuery, ["all", "enabled", "disabled", "settings"], true)) {
            $filterQuery = "all";
        }
        $searchQuery = trim((string) ($_REQUEST["q"] ?? ""));
        if (function_exists("mb_substr")) {
            $searchQuery = mb_substr($searchQuery, 0, 100);
        } else {
            $searchQuery = substr($searchQuery, 0, 100);
        }

        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $section = $_POST["section"] ?? "";
            if ($section === "dashboard") {
                $_SESSION["flash_debug_settings"] = [
                    "branch" => "dashboard",
                    "post" => $_POST,
                ];
                $rowLimit = max(
                    10,
                    min(10000, (int) ($_POST["dash_row_limit"] ?? 200)),
                );
                $showIcons = isset($_POST["dash_show_icons"]);
                $ipTips = isset($_POST["dash_ip_tooltips"]);
                $this->writeConfigAll([
                    "dashboard" => [
                        "row_limit" => $rowLimit,
                        "show_icons" => $showIcons,
                        "ip_tooltips" => $ipTips,
                    ],
                ]);
                header("Location: ?p=admin.settings");
                exit();
            }
            if (!Security::verifyCsrf($_POST["csrf"] ?? "")) {
                http_response_code(400);
                echo "Bad CSRF";
                return;
            }
            $action = $_POST["action"] ?? "";
            $id = (string) ($_POST["plugin_id"] ?? "");
            if ($id !== "") {
                if ($action === "toggle") {
                    $enabled =
                        isset($_POST["enabled"]) && $_POST["enabled"] === "1";
                    $reg->setEnabled($id, $enabled);
                } elseif ($action === "save") {
                    $cfg = $_POST["cfg"][$id] ?? [];
                    if (is_array($cfg)) {
                        foreach ($cfg as $k => $v) {
                            if ($v === "on") {
                                $cfg[$k] = true;
                            }
                        }
                    }
                    $reg->saveConfig($id, is_array($cfg) ? $cfg : []);
                }
            }
            $redirect = "?p=admin.plugins";
            $qs = [];
            if ($pluginQuery !== "") {
                $qs["plugin"] = $pluginQuery;
            }
            if ($filterQuery !== "all") {
                $qs["filter"] = $filterQuery;
            }
            if ($searchQuery !== "") {
                $qs["q"] = $searchQuery;
            }
            if ($qs) {
                $redirect .= "&" . http_build_query($qs);
            }
            header("Location: " . $redirect);
            exit();
        }

        $plugins = $this->pluginManagerItems();
        $filteredPlugins = $this->filterPluginManagerItems(
            $plugins,
            $filterQuery,
            $searchQuery,
        );

        $selectedPlugin = null;
        $invalidSelectedPlugin = false;
        if ($pluginQuery !== "") {
            foreach ($plugins as $plugin) {
                if (($plugin["key"] ?? "") === $pluginQuery) {
                    $selectedPlugin = $plugin;
                    break;
                }
            }
            $invalidSelectedPlugin = $selectedPlugin === null;
        }

        if ($selectedPlugin === null && $filteredPlugins) {
            $selectedPlugin = $filteredPlugins[0];
        }

        $selectedPluginHtml = "";
        $selectedPluginMessage = "";
        if ($invalidSelectedPlugin) {
            $selectedPluginMessage = "Selected plugin was not found.";
        } elseif ($selectedPlugin !== null) {
            $selectedPluginHtml = $this->renderPluginAdminPanel($selectedPlugin);
            if (
                !$this->pluginMatchesFilters(
                    $selectedPlugin,
                    $filterQuery,
                    $searchQuery,
                )
            ) {
                $selectedPluginMessage =
                    "The selected plugin does not match the current sidebar filters.";
            }
        }

        $this->render(
            "admin/plugins",
            compact(
                "plugins",
                "filteredPlugins",
                "selectedPlugin",
                "selectedPluginHtml",
                "selectedPluginMessage",
                "filterQuery",
                "searchQuery",
                "pluginQuery",
                "invalidSelectedPlugin",
            ),
        );
    }

    public function help(): void
    {
        $this->guard();
        I18n::boot();
        $this->render("admin/help", []);
    }

    /** Merge geo settings into config.php (preserving others). */
    private function writeConfigAll(array $delta): void
    {
        $c = \TrackEm\Core\Config::instance()->all();
        foreach ($delta as $k => $v) {
            if (is_array($v)) {
                $c[$k] = array_merge(
                    is_array($c[$k] ?? null) ? $c[$k] : [],
                    $v,
                );
            } else {
                $c[$k] = $v;
            }
        }
        $dir = dirname(__DIR__, 2) . "/config";
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = rtrim($dir, "/\\") . "/config.php";
        $php = "<?php\nreturn " . var_export($c, true) . ";\n";
        $ok = @file_put_contents($path, $php, LOCK_EX);
        if (function_exists("opcache_invalidate")) {
            @opcache_invalidate($path, true);
        }
        @clearstatcache(true, $path);
        if ($ok === false) {
            $_SESSION["flash_settings"] = [
                "ok" => false,
                "msg" => "Failed writing config",
                "path" => $path,
            ];
        } else {
            @chmod($path, 0664);
            $_SESSION["flash_settings"] = [
                "ok" => true,
                "msg" => "Settings saved",
                "path" => $path,
            ];
        }
    }

    private function writeConfigGeo(array $geo): void
    {
        // Load existing config and update 'geo'
        $c = \TrackEm\Core\Config::instance()->all();
        $c["geo"] = $geo;

        // Build path deterministically; don't rely on realpath
        $dir = dirname(__DIR__, 2) . "/config";
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = rtrim($dir, "/\\") . "/config.php";

        $php = "<?php\nreturn " . var_export($c, true) . ";\n";
        $ok = @file_put_contents($path, $php, LOCK_EX);
        if (function_exists("opcache_invalidate")) {
            @opcache_invalidate($path, true);
        }
        @clearstatcache(true, $path);

        if ($ok === false) {
            $_SESSION["flash_geo"] = [
                "ok" => false,
                "msg" => "Failed writing config",
                "path" => $path,
            ];
        } else {
            @chmod($path, 0664);
            $_SESSION["flash_geo"] = [
                "ok" => true,
                "msg" => "Geo config saved",
                "path" => $path,
            ];
        }
    }

    public function visitors(): void
    {
        $this->guard();
        I18n::boot();
        // Render the admin/visitors view through the default layout
        $viewFile = __DIR__ . "/../views/admin/visitors.php";
        require __DIR__ . "/../views/layouts/default.php";
    }

    /**
     * Deterministic config writer used by security/geos settings.
     * Merges a patch into current config and writes to app/config/config.php with LOCK_EX.
     */
    private function writeConfigPatch(array $patch): bool
    {
        $c = \TrackEm\Core\Config::instance()->all();
        $merge = function (array $a, array $b) use (&$merge): array {
            foreach ($b as $k => $v) {
                if (is_array($v) && isset($a[$k]) && is_array($a[$k])) {
                    $a[$k] = $merge($a[$k], $v);
                } else {
                    $a[$k] = $v;
                }
            }
            return $a;
        };
        $c = $merge($c, $patch);

        $dir = dirname(__DIR__, 2) . "/config";
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = rtrim($dir, "/\\") . "/config.php";
        $php = "<?php\nreturn " . var_export($c, true) . ";\n";

        $ok = @file_put_contents($path, $php, LOCK_EX);
        if (function_exists("opcache_invalidate")) {
            @opcache_invalidate($path, true);
        }
        @clearstatcache(true, $path);

        if ($ok === false) {
            $_SESSION["flash_settings"] = [
                "ok" => false,
                "msg" => "Failed writing config",
                "path" => $path,
            ];
            return false;
        }
        @chmod($path, 0664);
        $_SESSION["flash_settings"] = [
            "ok" => true,
            "msg" => "Settings saved",
            "path" => $path,
        ];
        return true;
    }

    private function pluginManagerItems(): array
    {
        $dir = realpath(__DIR__ . "/../plugins");
        if ($dir === false || !is_dir($dir)) {
            return [];
        }

        $enabledMap = [];
        try {
            foreach (DB::pdo()->query("SELECT id, enabled FROM plugins") ?: [] as $row) {
                $enabledMap[(string) ($row["id"] ?? "")] = (int) ($row["enabled"] ?? 0) === 1;
            }
        } catch (\Throwable $e) {
        }

        $out = [];
        foreach (glob($dir . "/*/plugin.json") ?: [] as $manifestFile) {
            $key = basename(dirname($manifestFile));
            $meta =
                json_decode((string) @file_get_contents($manifestFile), true) ?: [];
            $config = $this->pluginManagerConfig($key);
            $self = $this->pluginManagerSelfConfig($key, $dir);
            if ($self) {
                $config = array_merge($config, $self);
            }
            $schema = $this->normalizePluginSchema($meta["configSchema"] ?? []);
            $hasSettings =
                !empty($meta["admin_route"]) || !empty($schema["fields"]);

            $out[] = [
                "key" => $key,
                "meta" => $meta,
                "enabled" => array_key_exists($key, $enabledMap)
                    ? $enabledMap[$key]
                    : true,
                "config" => $config,
                "schema" => $schema,
                "has_settings" => $hasSettings,
            ];
        }

        usort($out, function (array $a, array $b): int {
            $an = strtolower(
                (string) (($a["meta"]["name"] ?? "") ?: ($a["key"] ?? "")),
            );
            $bn = strtolower(
                (string) (($b["meta"]["name"] ?? "") ?: ($b["key"] ?? "")),
            );
            return strcmp($an, $bn);
        });
        return $out;
    }

    private function filterPluginManagerItems(
        array $plugins,
        string $filter,
        string $search
    ): array {
        $out = [];
        foreach ($plugins as $plugin) {
            if (!$this->pluginMatchesFilters($plugin, $filter, $search)) {
                continue;
            }
            $out[] = $plugin;
        }
        return $out;
    }

    private function pluginMatchesFilters(
        array $plugin,
        string $filter,
        string $search
    ): bool {
        if ($filter === "enabled" && empty($plugin["enabled"])) {
            return false;
        }
        if ($filter === "disabled" && !empty($plugin["enabled"])) {
            return false;
        }
        if ($filter === "settings" && empty($plugin["has_settings"])) {
            return false;
        }
        if ($search !== "") {
            $haystack = strtolower(
                implode(
                    "\n",
                    [
                        (string) ($plugin["key"] ?? ""),
                        (string) ($plugin["meta"]["name"] ?? ""),
                        (string) ($plugin["meta"]["description"] ?? ""),
                    ],
                ),
            );
            if (strpos($haystack, strtolower($search)) === false) {
                return false;
            }
        }
        return true;
    }

    private function renderPluginAdminPanel(array $plugin): string
    {
        $route = (string) ($plugin["meta"]["admin_route"] ?? "");
        if ($route === "") {
            return "";
        }

        ob_start();
        $ok = \TrackEm\Core\PluginDispatcher::dispatch($route);
        $html = (string) ob_get_clean();
        return $ok ? $html : "";
    }

    private function pluginManagerConfig(string $key): array
    {
        $path = dirname(__DIR__) . "/config/plugins/" . preg_replace("/[^a-z0-9_\-\.]/i", "_", $key) . ".json";
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) @file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private function pluginManagerSelfConfig(string $key, string $pluginsDir): array
    {
        $path =
            rtrim($pluginsDir, "/\\") .
            "/" .
            preg_replace("/[^a-z0-9_\-\.]/i", "_", $key) .
            "/config.json";
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) @file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private function normalizePluginSchema($schema): array
    {
        if (!is_array($schema) || !isset($schema["fields"]) || !is_array($schema["fields"])) {
            return ["fields" => []];
        }
        $fields = [];
        foreach ($schema["fields"] as $field) {
            if (!is_array($field) || empty($field["name"])) {
                continue;
            }
            $fields[] = [
                "name" => (string) $field["name"],
                "label" => (string) ($field["label"] ?? $field["name"]),
                "type" => (string) ($field["type"] ?? "text"),
                "help" => (string) ($field["help"] ?? ""),
                "default" => $field["default"] ?? null,
                "options" =>
                    isset($field["options"]) && is_array($field["options"])
                        ? $field["options"]
                        : [],
            ];
        }
        return ["fields" => $fields];
    }
}
