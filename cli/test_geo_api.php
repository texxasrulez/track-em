#!/usr/bin/env php
<?php
declare(strict_types=1);

$_SERVER["SCRIPT_NAME"] = "/cli/test_geo_api.php";
$_SERVER["REQUEST_METHOD"] = "GET";

require_once __DIR__ . "/../app/core/Bootstrap.php";

use TrackEm\Controllers\ApiController;

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
            if (($json["page"] ?? null) !== 2) {
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
