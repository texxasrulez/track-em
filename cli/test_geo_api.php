#!/usr/bin/env php
<?php
declare(strict_types=1);

$_SERVER["SCRIPT_NAME"] = "/cli/test_geo_api.php";
$_SERVER["REQUEST_METHOD"] = "GET";
$_SERVER["HTTP_HOST"] = "localhost";

$sessionDir = sys_get_temp_dir() . "/trackem_test_sessions";
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0775, true);
}
ini_set("session.save_path", $sessionDir);

$dataFile = __DIR__ . "/../app/data/visits.json";
$cleanupDataFile = !is_file($dataFile);
$seedRows = [
    [
        "id" => 1,
        "ip" => "192.0.2.10",
        "path" => "/",
        "ts" => 1714600000,
        "lat" => 32.7,
        "lon" => -94.0,
        "city" => "Longview",
        "country" => "United States",
        "user_agent" => "CLI Test",
    ],
    [
        "id" => 2,
        "ip" => "192.0.2.11",
        "path" => "/pricing",
        "ts" => 1714600100,
        "lat" => 40.7,
        "lon" => -74.0,
        "city" => "New York",
        "country" => "United States",
        "user_agent" => "CLI Test",
    ],
    [
        "id" => 3,
        "ip" => "198.51.100.8",
        "path" => "/docs",
        "ts" => 1714600200,
        "lat" => 51.5,
        "lon" => -0.1,
        "city" => "London",
        "country" => "United Kingdom",
        "user_agent" => "CLI Test",
    ],
    [
        "id" => 4,
        "ip" => "203.0.113.22",
        "path" => "/contact",
        "ts" => 1714600300,
        "lat" => 48.8,
        "lon" => 2.3,
        "city" => "Paris",
        "country" => "France",
        "user_agent" => "CLI Test",
    ],
    [
        "id" => 5,
        "ip" => "203.0.113.23",
        "path" => "/blog",
        "ts" => 1714600400,
        "lat" => 35.7,
        "lon" => 139.7,
        "city" => "Tokyo",
        "country" => "Japan",
        "user_agent" => "CLI Test",
    ],
    [
        "id" => 6,
        "ip" => "203.0.113.24",
        "path" => "/checkout",
        "ts" => 1714600500,
        "lat" => -33.9,
        "lon" => 151.2,
        "city" => "Sydney",
        "country" => "Australia",
        "user_agent" => "CLI Test",
    ],
];
@file_put_contents(
    $dataFile,
    json_encode(["items" => $seedRows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    LOCK_EX,
);
register_shutdown_function(static function () use ($dataFile, $cleanupDataFile): void {
    if ($cleanupDataFile && is_file($dataFile)) {
        @unlink($dataFile);
    }
});

require_once __DIR__ . "/../app/core/Bootstrap.php";

use TrackEm\Controllers\ApiController;
use TrackEm\Core\Security;

Security::startSecureSession();
$_SESSION["uid"] = 1;

$cases = [
    [
        "label" => "default",
        "params" => [],
        "assert" => function (array $json): bool {
            return isset(
                $json["ok"],
                $json["items"],
                $json["page"],
                $json["pages"],
            ) && $json["ok"] === true;
        },
    ],
    [
        "label" => "paged-limit",
        "params" => ["limit" => 5, "page" => 2],
        "assert" => function (array $json): bool {
            if (($json["page"] ?? null) !== 2 || ($json["pages"] ?? 0) < 2) {
                return false;
            }
            return ($json["count"] ?? 0) <= 5;
        },
    ],
    [
        "label" => "fields-trim",
        "params" => ["fields" => "ip,path,ts", "limit" => 3],
        "assert" => function (array $json): bool {
            $items = $json["items"] ?? [];
            if (!is_array($items)) {
                return false;
            }
            foreach ($items as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $keys = array_keys($row);
                if (array_diff($keys, ["ip", "path", "ts"])) {
                    return false;
                }
            }
            return true;
        },
    ],
    [
        "label" => "ascending-order",
        "params" => ["order" => "asc", "limit" => 5],
        "assert" => function (array $json): bool {
            $items = $json["items"] ?? [];
            $prev = null;
            foreach ($items as $row) {
                if (!isset($row["ts"])) {
                    continue;
                }
                $ts = (int) $row["ts"];
                if ($prev !== null && $ts < $prev) {
                    return false;
                }
                $prev = $ts;
            }
            return true;
        },
    ],
    [
        "label" => "invalid-order",
        "params" => ["order" => "not-real", "limit" => 3],
        "assert" => function (array $json): bool {
            return ($json["count"] ?? 0) <= 3 && ($json["page"] ?? 1) === 1;
        },
    ],
    [
        "label" => "page-clamp",
        "params" => ["limit" => 1, "page" => 999999],
        "assert" => function (array $json): bool {
            return ($json["page"] ?? 1) <= ($json["pages"] ?? 1);
        },
    ],
];

$allPass = true;
foreach ($cases as $case) {
    $_GET = $case["params"];
    ob_start();
    try {
        (new ApiController())->geo();
    } catch (Throwable $e) {
        ob_end_clean();
        fwrite(STDERR, "[FAIL] {$case["label"]} threw {$e->getMessage()}\n");
        $allPass = false;
        continue;
    }
    $raw = ob_get_clean();
    $json = json_decode((string) $raw, true);
    if (!is_array($json)) {
        fwrite(STDERR, "[FAIL] {$case["label"]} invalid JSON\n");
        $allPass = false;
        continue;
    }
    $assert = $case["assert"];
    if (!$assert($json)) {
        fwrite(STDERR, "[FAIL] {$case["label"]} assertion failed\n");
        $allPass = false;
        continue;
    }
    fwrite(STDOUT, "[PASS] {$case["label"]}\n");
}

exit($allPass ? 0 : 1);
