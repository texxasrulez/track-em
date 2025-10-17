<?php
declare(strict_types=1);

namespace TrackEm\Core;

require_once __DIR__ . '/Config.php';

final class Geo
{
    /** Return ['lat'=>..., 'lon'=>..., 'city'=>..., 'country'=>...] or null. */
    public static function lookup(string $ip, ?array $cfg = null): ?array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }
        $cfg = $cfg ?? Config::instance()->get('geo', []);
        $provider = (string)($cfg['provider'] ?? 'ip-api');

        try {
            if ($provider === 'maxmind_local') {
                return self::lookupMaxMindLocal($ip, $cfg);
            } elseif ($provider === 'maxmind_web') {
                return self::lookupMaxMindWeb($ip, $cfg);
            }
            return self::lookupIpApi($ip, $cfg);
        } catch (\Throwable $e) {
            error_log('[Geo] provider error: ' . $e->getMessage());
            return null;
        }
    }

    /** -------- Providers -------- */

    private static function lookupIpApi(string $ip, array $cfg): ?array
    {
        $base = (string)($cfg['ip_api_base'] ?? 'http://ip-api.com/json/');
        $timeout = (float)($cfg['timeout_sec'] ?? 0.8);
        $url = rtrim($base, '/') . '/' . rawurlencode($ip) . '?fields=status,lat,lon,city,country';
        $ctx = stream_context_create(['http'=>['timeout'=>max(0.2,$timeout)]]);
        $resp = @file_get_contents($url, false, $ctx);
        if (!$resp) return null;
        $j = json_decode($resp, true);
        if (($j['status'] ?? '') !== 'success') return null;
        if (!isset($j['lat'], $j['lon'])) return null;
        return [
            'lat' => (float)$j['lat'],
            'lon' => (float)$j['lon'],
            'city' => (string)($j['city'] ?? ''),
            'country' => (string)($j['country'] ?? ''),
        ];
    }

    private static function lookupMaxMindWeb(string $ip, array $cfg): ?array
    {
        $accountId = (string)($cfg['mm_account_id'] ?? '');
        $license   = (string)($cfg['mm_license_key'] ?? '');
        $timeout   = (float)($cfg['timeout_sec'] ?? 0.8);
        if ($accountId === '' || $license === '') return null;

        // GeoIP2 Precision City
        $url = "https://geoip.maxmind.com/geoip/v2.1/city/" . rawurlencode($ip);
        $headers = [
            'Authorization: Basic ' . base64_encode($accountId . ':' . $license),
            'Accept: application/json',
        ];
        $opts = ['http'=>['method'=>'GET','header'=>implode("\r\n",$headers), 'timeout'=>max(0.2,$timeout)]];
        $resp = @file_get_contents($url, false, stream_context_create($opts));
        if (!$resp) return null;
        $j = json_decode($resp, true);
        if (!is_array($j)) return null;

        $lat = $j['location']['latitude'] ?? null;
        $lon = $j['location']['longitude'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lon)) return null;
        return [
            'lat' => (float)$lat,
            'lon' => (float)$lon,
            'city' => (string)($j['city']['names']['en'] ?? ''),
            'country' => (string)($j['country']['names']['en'] ?? ''),
        ];
    }

    private static function lookupMaxMindLocal(string $ip, array $cfg): ?array
    {
        $path = (string)($cfg['mmdb_path'] ?? (__DIR__ . '/../../data/GeoLite2-City.mmdb'));
        if (!is_file($path)) return null;

        // Strategy:
        // 1) If the pure-PHP reader class exists (\MaxMind\Db\Reader), use it.
        // 2) Else if extension 'maxminddb' exists, use the procedural API.
        // 3) Else return null (admin can still use web service).
        if (class_exists('\\MaxMind\\Db\\Reader')) {
            $reader = new \MaxMind\Db\Reader($path);
            try {
                $r = $reader->get($ip);
            } finally {
                $reader->close();
            }
            return self::mmdbToResult($r);
        } elseif (function_exists('maxminddb_open')) {
            $db = @maxminddb_open($path, MAXMINDDB_MODE_MMAP);
            if ($db === false) return null;
            $r = maxminddb_get_record($db, $ip);
            maxminddb_close($db);
            return self::mmdbToResult($r);
        }
        return null;
    }

    private static function mmdbToResult(?array $r): ?array
    {
        if (!$r) return null;
        $lat = $r['location']['latitude'] ?? null;
        $lon = $r['location']['longitude'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lon)) return null;
        $city = '';
        if (!empty($r['city']['names'])) {
            $city = (string)($r['city']['names']['en'] ?? reset($r['city']['names']));
        }
        $country = '';
        if (!empty($r['country']['names'])) {
            $country = (string)($r['country']['names']['en'] ?? reset($r['country']['names']));
        }
        return ['lat'=>(float)$lat,'lon'=>(float)$lon,'city'=>$city,'country'=>$country];
    }

    /** -------- Utilities -------- */

    /**
     * Download and extract GeoLite2-City.mmdb into $destDir.
     * Requires MaxMind "license key" (free). Returns [ok=>bool, msg=>string, path=>string|null]
     */
    public static function downloadGeoLite2(string $license, string $destDir): array
    {
        if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }
        if (!is_dir($destDir) || !is_writable($destDir)) {
            return ['ok'=>false,'msg'=>'Destination not writable','path'=>null];
        }

        // The official download URL requires the license key.
        $gzUrl = "https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&license_key="
               . rawurlencode($license) . "&suffix=tar.gz";

        $tmpGz  = $destDir . '/GeoLite2-City.tar.gz';
        $tmpTar = $destDir . '/GeoLite2-City.tar';

        // Download
        $ctx = stream_context_create(['http'=>['timeout'=>20]]);
        $data = @file_get_contents($gzUrl, false, $ctx);
        if ($data === false || strlen($data) < 1024) {
            return ['ok'=>false,'msg'=>'Download failed (bad key or network)', 'path'=>null];
        }
        file_put_contents($tmpGz, $data);

        // Decompress and extract the .mmdb using PharData (works on PHP 8+)
        try {
            $pharGz = new \PharData($tmpGz);
            $pharGz->decompress(); // creates .tar
            unset($pharGz);
        } catch (\Throwable $e) {
            // Some PHP builds can’t decompress in-place; try shell gunzip if available
            @unlink($tmpTar);
            @shell_exec('gunzip -f ' . escapeshellarg($tmpGz));
        }

        if (!is_file($tmpTar)) {
            // On some envs, Phar leaves .tar in same path but with different name
            $altTar = preg_replace('/\\.tar\\.gz$/', '.tar', $tmpGz);
            if (is_string($altTar) && is_file($altTar)) { @rename($altTar, $tmpTar); }
        }
        if (!is_file($tmpTar)) {
            return ['ok'=>false,'msg'=>'Could not create TAR from GZ', 'path'=>null];
        }

        try {
            $pharTar = new \PharData($tmpTar);
            // find the .mmdb inside the tar (path contains a versioned dir)
            $mmdbTarget = null;
            foreach (new \RecursiveIteratorIterator($pharTar) as $file) {
                /** @var \PharFileInfo $file */
                if (preg_match('/GeoLite2-City\\.mmdb$/', $file->getFilename())) {
                    $mmdbTarget = $file->getFilename();
                    break;
                }
            }
            if ($mmdbTarget === null) {
                return ['ok'=>false,'msg'=>'MMDB not found in archive','path'=>null];
            }

            // Extract the entire tar to a temp dir, then move the mmdb
            $tmpExtract = $destDir . '/.geo_extract_' . bin2hex(random_bytes(3));
            @mkdir($tmpExtract, 0775, true);
            $pharTar->extractTo($tmpExtract, null, true);

            // Locate mmdb in extracted tree
            $mmdbPath = null;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tmpExtract));
            foreach ($it as $f) {
                if ($f->isFile() && preg_match('/GeoLite2-City\\.mmdb$/', $f->getFilename())) {
                    $mmdbPath = $f->getPathname(); break;
                }
            }
            if ($mmdbPath === null) {
                return ['ok'=>false,'msg'=>'Extracted, but no MMDB found','path'=>null];
            }

            $final = rtrim($destDir, '/\\') . '/GeoLite2-City.mmdb';
            @rename($mmdbPath, $final);

            // Cleanup
            @unlink($tmpGz);
            @unlink($tmpTar);
            self::rrmdir($tmpExtract);

            if (!is_file($final)) return ['ok'=>false,'msg'=>'Move failed', 'path'=>null];
            return ['ok'=>true,'msg'=>'Downloaded', 'path'=>$final];
        } catch (\Throwable $e) {
            return ['ok'=>false,'msg'=>'Extraction failed: '.$e->getMessage(), 'path'=>null];
        }
    }

    private static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        foreach (new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
