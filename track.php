<?php
declare(strict_types=1);

require_once __DIR__ . "/app/core/Bootstrap.php";
require_once __DIR__ . "/app/core/HookManager.php";
require_once __DIR__ . "/app/core/Geo.php";
require_once __DIR__ . "/app/models/Visit.php";

use TrackEm\Core\DB;
use TrackEm\Core\Config;
use TrackEm\Core\Security;
use TrackEm\Core\HookManager;
use TrackEm\Core\Geo;
use TrackEm\Models\Visit;

$debug = isset($_GET["debug"]) && $_GET["debug"] === "1";
function out_json($arr)
{
    header("Content-Type: application/json");
    echo json_encode($arr, JSON_UNESCAPED_SLASHES);
    exit();
}

try {
    Security::startSecureSession();
    $config = Config::instance();

    $ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
    $respectDnt = (bool) $config->get("privacy.respect_dnt", true);
    $requireConsent = (bool) $config->get("privacy.require_consent", false);
    $payload = [];
    if (
        ($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST" &&
        stripos($_SERVER["CONTENT_TYPE"] ?? "", "application/json") !== false
    ) {
        $payload =
            json_decode(file_get_contents("php://input") ?: "{}", true) ?: [];
    }
    if ($respectDnt && ($_SERVER["HTTP_DNT"] ?? "") === "1") {
        if ($debug) {
            out_json(["ok" => false, "why" => "dnt"]);
        }
        http_response_code(204);
        exit();
    }
    if ($requireConsent) {
        $consent = strtolower((string) ($_COOKIE["te_consent"] ?? ""));
        if ($consent === "") {
            $consent = strtolower((string) ($payload["consent"] ?? ($_GET["consent"] ?? "")));
        }
        // Banner may provide consent via cookie or payload/query fallback.
        $allowed = in_array($consent, ["allow", "granted"], true);
        if (!$allowed) {
            if ($debug) {
                out_json(["ok" => false, "why" => "consent", "consent" => $consent ?: "missing"]);
            }
            http_response_code(204);
            exit();
        }
    }

    $rawIp = Security::clientIp(0);
    $maskBits = (int) $config->get("privacy.ip_mask_bits", 16);
    $maskOn = (bool) $config->get("privacy.ip_anonymize", true);
    $ip = Security::clientIp($maskOn ? $maskBits : 0);
    $path = substr(
        $_GET["p"] ?? ($payload["path"] ?? ($_SERVER["REQUEST_URI"] ?? "/")),
        0,
        512,
    );
    $ref = substr($_SERVER["HTTP_REFERER"] ?? "", 0, 512);

    $geoCfg = $config->get("geo", []);
    $geoEnabled = array_key_exists("enabled", $geoCfg)
        ? (bool) $geoCfg["enabled"]
        : true;
    $lat = $lon = $city = $country = null;
    if ($geoEnabled) {
        $geoResult = Geo::lookup($rawIp, is_array($geoCfg) ? $geoCfg : null);
        if (is_array($geoResult)) {
            $lat = isset($geoResult["lat"]) ? (float) $geoResult["lat"] : null;
            $lon = isset($geoResult["lon"]) ? (float) $geoResult["lon"] : null;
            $city = (string) ($geoResult["city"] ?? "");
            $country = (string) ($geoResult["country"] ?? "");
        }
    }

    $pdo = DB::pdo();
    $visit = new Visit($pdo);
    $visitId = $visit->record([
        "ip" => $ip,
        "user_agent" => substr($ua, 0, 512),
        "referrer" => $ref,
        "path" => $path,
        "ts" => time(),
        "meta" => json_encode(
            [
                "screen" => $payload["screen"] ?? null,
                "lang" => $payload["lang"] ?? null,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ),
        "lat" => $lat,
        "lon" => $lon,
        "city" => $city,
        "country" => $country,
    ]);

    HookManager::emit("on_visit_recorded", ["visit_id" => $visitId]);

    if ($debug) {
        out_json([
            "ok" => true,
            "id" => $visitId,
            "ip" => $ip,
            "raw_ip" => $rawIp,
            "lat" => $lat,
            "lon" => $lon,
        ]);
    }

    header("Content-Type: image/gif");
    echo base64_decode(
        "R0lGODlhAQABAPAAAAAAAAAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==",
    );
} catch (\Throwable $e) {
    error_log("[track.php] " . $e->getMessage());
    if ($debug) {
        out_json([
            "ok" => false,
            "why" => "exception",
            "error" => $e->getMessage(),
        ]);
    }
    http_response_code(500);
    echo "/* track error */";
}
