<?php
namespace TrackEm\Models;

class VisitorRepo {
    /** @var \PDO */
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->init();
    }

    private function init() {
        $sql = "CREATE TABLE IF NOT EXISTS te_visitors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip VARCHAR(64) NOT NULL,
            ua TEXT,
            os VARCHAR(64),
            browser VARCHAR(64),
            device VARCHAR(32),
            path TEXT,
            ref TEXT,
            city VARCHAR(128),
            country VARCHAR(128),
            lat REAL,
            lon REAL,
            ts INTEGER NOT NULL
        )";
        try { $this->pdo->exec($sql); } catch (\Exception $e) {}
        // indexes
        try { $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_vis_ip ON te_visitors(ip)"); } catch (\Exception $e) {}
        try { $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_vis_ts ON te_visitors(ts)"); } catch (\Exception $e) {}
    }

    public static function parseUA($ua) {
        $ua = (string)$ua;
        $os = 'Other';
        $browser = 'Other';
        $device = 'Desktop';

        // OS detection
        if (stripos($ua, 'Windows') !== false) $os = 'Windows';
        elseif (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) $os = 'macOS';
        elseif (stripos($ua, 'Android') !== false) { $os = 'Android'; $device = 'Mobile'; }
        elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false || stripos($ua, 'iOS') !== false) { $os = 'iOS'; $device = 'Mobile'; }
        elseif (stripos($ua, 'Linux') !== false) $os = 'Linux';

        // Browser detection
        if (stripos($ua, 'Edg/') !== false || stripos($ua, 'Edge/') !== false) $browser = 'Edge';
        elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) $browser = 'Opera';
        elseif (stripos($ua, 'Chrome/') !== false && stripos($ua, 'Chromium') === false && stripos($ua, 'Edg/') === false) $browser = 'Chrome';
        elseif (stripos($ua, 'Safari/') !== false && stripos($ua, 'Chrome/') === false) $browser = 'Safari';
        elseif (stripos($ua, 'Firefox/') !== false) $browser = 'Firefox';
        elseif (stripos($ua, 'MSIE') !== false || stripos($ua, 'Trident/') !== false) $browser = 'IE';

        // Device refinement
        if (stripos($ua, 'Tablet') !== false || stripos($ua, 'iPad') !== false) $device = 'Tablet';
        elseif (stripos($ua, 'Mobile') !== false || stripos($ua, 'Android') !== false || stripos($ua, 'iPhone') !== false) $device = 'Mobile';

        return array($os, $browser, $device);
    }

    public function log($ip, $ua, $path, $ref, $geo = array()) {
        list($os, $browser, $device) = self::parseUA($ua);
        $ts = time();
        $city = isset($geo['city']) ? $geo['city'] : null;
        $country = isset($geo['country']) ? $geo['country'] : null;
        $lat = isset($geo['lat']) ? $geo['lat'] : null;
        $lon = isset($geo['lon']) ? $geo['lon'] : null;

        $sql = "INSERT INTO te_visitors (ip, ua, os, browser, device, path, ref, city, country, lat, lon, ts)
                VALUES (:ip, :ua, :os, :browser, :device, :path, :ref, :city, :country, :lat, :lon, :ts)";
        $st = $this->pdo->prepare($sql);
        $st->execute(array(
            ':ip'=>$ip, ':ua'=>$ua, ':os'=>$os, ':browser'=>$browser, ':device'=>$device,
            ':path'=>$path, ':ref'=>$ref, ':city'=>$city, ':country'=>$country, ':lat'=>$lat, ':lon'=>$lon, ':ts'=>$ts
        ));
        return $this->pdo->lastInsertId();
    }

    public function aggregateByIp($limit = 200, $search = "") {
        $w = "";
        $p = array(':limit'=>$limit);
        if ($search !== ""){
            $w = " WHERE ip LIKE :q OR os LIKE :q OR browser LIKE :q ";
            $p[':q'] = "%".$search."%";
        }
        $sql = "SELECT ip,
                       COUNT(*) as hits,
                       MAX(ts) as last_ts,
                       MAX(country) as country,
                       MAX(city) as city,
                       MAX(os) as os,
                       MAX(browser) as browser,
                       MAX(device) as device
                FROM te_visitors
                ". $w ."
                GROUP BY ip
                ORDER BY last_ts DESC
                LIMIT :limit";
        $st = $this->pdo->prepare($sql);
        foreach ($p as $k=>$v){
            if ($k === ':limit') $st->bindValue($k, (int)$v, \PDO::PARAM_INT);
            else $st->bindValue($k, $v, \PDO::PARAM_STR);
        }
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        return $rows;
    }

    public function listByIp($ip, $limit = 200) {
        $st = $this->pdo->prepare("SELECT * FROM te_visitors WHERE ip = :ip ORDER BY ts DESC LIMIT :limit");
        $st->bindValue(':ip', $ip, \PDO::PARAM_STR);
        $st->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }
}
