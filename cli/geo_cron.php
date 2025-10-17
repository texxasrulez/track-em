<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); echo "Forbidden\n"; exit(1);} 

// CLI script: refresh GeoLite2 DB (if license key present) and backfill geocodes for recent visits.
// Usage (crontab):
//   # every night at 02:17
//   17 2 * * * php /path/to/track-em/cli/geo_cron.php >> /path/to/track-em/storage/geo_cron.log 2>&1

chdir(dirname(__DIR__));

require_once __DIR__ . '/../app/core/DB.php';
require_once __DIR__ . '/../app/core/Config.php';
require_once __DIR__ . '/../app/core/Geo.php';

use TrackEm\Core\DB;
use TrackEm\Core\Config;
use TrackEm\Core\Geo;

function logln(string $s){ echo '['.date('c').'] '.$s.PHP_EOL; }

try {
    $cfg = Config::instance()->all();
    $geo = $cfg['geo'] ?? [];

    // 1) Refresh GeoLite2 DB if a license is configured
    $license = (string)($geo['mm_license_key'] ?? '');
    if ($license !== '') {
        $dest = __DIR__ . '/../data';
        @mkdir($dest, 0775, true);
        $res = Geo::downloadGeoLite2($license, $dest);
        logln('GeoLite2 download: ' . json_encode($res));
    } else {
        logln('GeoLite2 download skipped (no license key)');
    }

    // 2) Backfill geocodes for recent visits (last 7 days)
    $pdo = DB::pdo();

    // Ensure columns exist
    try { $pdo->query("SELECT lat, lon, city, country FROM visits LIMIT 0"); }
    catch (\Throwable $e) {
        $pdo->exec("ALTER TABLE visits ADD COLUMN lat DECIMAL(9,6) NULL");
        $pdo->exec("ALTER TABLE visits ADD COLUMN lon DECIMAL(9,6) NULL");
        $pdo->exec("ALTER TABLE visits ADD COLUMN city VARCHAR(64) NULL");
        $pdo->exec("ALTER TABLE visits ADD COLUMN country VARCHAR(64) NULL");
    }

    $since = time() - 7*86400;
    $st = $pdo->prepare("SELECT id, ip FROM visits WHERE ts >= ? AND (lat IS NULL OR lon IS NULL) ORDER BY id DESC LIMIT 2000");
    $st->execute([$since]);
    $rows = $st->fetchAll();

    $count=0; $ok=0;
    foreach ($rows as $r) {
        $ip = (string)$r['ip'];
        $geoRow = Geo::lookup($ip, $geo);
        $count++;
        if ($geoRow) {
            $up = $pdo->prepare("UPDATE visits SET lat=?, lon=?, city=?, country=? WHERE id=?");
            $up->execute([$geoRow['lat'],$geoRow['lon'],$geoRow['city'],$geoRow['country'], (int)$r['id']]);
            $ok++;
        }
        // Be gentle to providers
        usleep(80000); // 80ms
    }
    logln("Backfill checked=$count updated=$ok");
} catch (\Throwable $e) {
    logln('ERROR: ' . $e->getMessage());
    exit(1);
}
