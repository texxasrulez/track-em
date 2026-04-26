<?php
declare(strict_types=1);
require_once __DIR__ . "/app/core/Bootstrap.php";
use TrackEm\Core\DB;
use TrackEm\Core\Config;

if (
    PHP_SAPI !== "cli" &&
    !in_array((string) ($_SERVER["REMOTE_ADDR"] ?? ""), ["127.0.0.1", "::1"], true)
) {
    http_response_code(403);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Forbidden";
    exit();
}

header("Content-Type: application/json");

$out = [
    "php" => PHP_VERSION,
    "path" => __FILE__,
    "config" => [
        "db_name" => (string) (Config::instance()->get("database.name") ?? ""),
        "privacy" => Config::instance()->get("privacy", []),
    ],
    "request" => [
        "script_name" => $_SERVER["SCRIPT_NAME"] ?? null,
        "host" => $_SERVER["HTTP_HOST"] ?? null,
        "dnt" => $_SERVER["HTTP_DNT"] ?? null,
    ],
];

try {
    $pdo = DB::pdo();
    $c = $pdo->query("SELECT COUNT(*) AS c FROM visits")->fetch()["c"] ?? 0;
    $out["db"] = ["ok" => true, "visits_count" => (int) $c];
} catch (Throwable $e) {
    $out["db"] = ["ok" => false, "error" => "connection failed"];
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
