<?php
declare(strict_types=1);

namespace TrackEm\Controllers;

use TrackEm\Core\Config;


require_once __DIR__ . '/_addons/ApiPluginsAddon.php';
final class ApiController
{
    private function rl_check(): bool {
        $cfg = \TrackEm\Core\Config::instance();
        if (!(bool)$cfg->get('rate_limit.enabled', true)) return true;
        $window = (int)$cfg->get('rate_limit.window', 60);
        $max    = (int)$cfg->get('rate_limit.max_events', 120);
        $key = 'api_mut:' . \TrackEm\Core\Security::clientIpMasked();
        return \TrackEm\Core\Security::rateLimit($key, $window, $max);
    }

    use \TrackEm\Controllers\_addons\ApiPluginsAddon {
        api_plugins_configs as trait_api_plugins_configs;
    api_plugins_list as trait_api_plugins_list;
    api_plugins_install as trait_api_plugins_install;
    api_plugins_toggle as trait_api_plugins_toggle;
    api_plugins_remove as trait_api_plugins_remove;
}

    private function jsonOK(array $data = []): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_SLASHES);
    }
    private function jsonErr(string $err, int $code = 400, array $extra = []): void {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode(['ok' => false, 'err' => $err] + $extra, JSON_UNESCAPED_SLASHES);
    }

    private function layoutFilePath(): string { return __DIR__ . '/../data/layout.json'; }
    private function isListArray(array $arr): bool {
        if (function_exists('array_is_list')) return array_is_list($arr);
        $i=0; foreach ($arr as $k=>$_) { if ($k !== $i++) return false; } return true;
    }
    private function coerceTs($row): int {
        if (isset($row['ts']) && is_numeric($row['ts']) && (int)$row['ts']>0) return (int)$row['ts'];
        foreach (['created_at','time','timestamp','date'] as $k) {
            if (!isset($row[$k])) continue;
            if (is_numeric($row[$k]) && (int)$row[$k]>0) return (int)$row[$k];
            $t = @strtotime((string)$row[$k]); if ($t && $t>0) return (int)$t;
        }
        return 0;
    }
    private function normalizeRows(array $rows): array {
        $out = [];
        foreach ($rows as $r) {
            $ts = $this->coerceTs($r);
            $out[] = [
                'id'      => isset($r['id']) ? (int)$r['id'] : 0,
                'ip'      => isset($r['ip']) ? (string)$r['ip'] : '',
                'path'    => isset($r['path']) ? (string)$r['path'] : '',
                'ts'      => $ts,
                'lat'     => array_key_exists('lat',$r) ? (is_numeric($r['lat'])?(float)$r['lat']:null) : null,
                'lon'     => array_key_exists('lon',$r) ? (is_numeric($r['lon'])?(float)$r['lon']:null) : null,
                'city'    => $r['city']   ?? null,
                'country' => $r['country']?? null,
                'ua'      => $r['ua'] ?? ($r['user_agent'] ?? null),
            ];
        }
        return $out;
    }

    private function pdoMysql(?array $cfg): ?\PDO {
        if (!$cfg) return null;
        $driver = strtolower((string)($cfg['driver'] ?? 'mysql'));
        if ($driver && $driver !== 'mysql') return null;
        $host = (string)($cfg['host'] ?? '127.0.0.1');
        $name = (string)($cfg['name'] ?? $cfg['database'] ?? '');
        $user = (string)($cfg['user'] ?? $cfg['username'] ?? '');
        $pass = (string)($cfg['pass'] ?? $cfg['password'] ?? '');
        $port = (int)($cfg['port'] ?? 3306);
        if ($name === '' || $user === '') return null;
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        try {
            return new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
        } catch (\Throwable) { return null; }
    }
    private function mysqliMysql(?array $cfg): ?\mysqli {
        if (!$cfg) return null;
        $host = (string)($cfg['host'] ?? '127.0.0.1');
        $name = (string)($cfg['name'] ?? $cfg['database'] ?? '');
        $user = (string)($cfg['user'] ?? $cfg['username'] ?? '');
        $pass = (string)($cfg['pass'] ?? $cfg['password'] ?? '');
        $port = (int)($cfg['port'] ?? 3306);
        if ($name === '' || $user === '') return null;
        $m = @new \mysqli($host, $user, $pass, $name, $port);
        if ($m && !$m->connect_errno) { @$m->set_charset('utf8mb4'); return $m; }
        return null;
    }
    private function pdoSqlite(string $file): ?\PDO {
        if (!is_file($file)) return null;
        try {
            return new \PDO('sqlite:' . $file, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
        } catch (\Throwable) { return null; }
    }

    public function layoutGet(): void {
        try {
            $cfg = Config::instance();
            $dash = $cfg->get('dashboard') ?? [];
            $order = (isset($dash['order']) && is_array($dash['order'])) ? array_values($dash['order']) : [];
            $cols  = isset($dash['cols']) ? (int)$dash['cols'] : 2;
            if (!$order) {
                $file = $this->layoutFilePath();
                if (is_file($file)) {
                    $raw = @file_get_contents($file);
                    $json = json_decode((string)$raw, true);
                    if (is_array($json)) {
                        if (!empty($json['order']) && is_array($json['order'])) $order = array_values($json['order']);
                        if (isset($json['cols'])) $cols = max(1, (int)$json['cols']);
                    }
                }
            }
            $this->jsonOK(['order'=>$order,'cols'=>$cols]);
        } catch (\Throwable) {
            $file = $this->layoutFilePath();
            if (is_file($file)) {
                $raw = @file_get_contents($file);
                $json = json_decode((string)$raw, true);
                $order = (isset($json['order']) && is_array($json['order'])) ? array_values($json['order']) : [];
                $cols  = isset($json['cols']) ? max(1, (int)$json['cols']) : 2;
                $this->jsonOK(['order'=>$order,'cols'=>$cols]); return;
            }
            $this->jsonOK(['order'=>[],'cols'=>2]);
        }
    }

    public function layoutSave(): void {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) { $this->jsonErr('bad_json', 400); return; }
        $order = (isset($data['order']) && is_array($data['order'])) ? $data['order'] : [];
        $order = array_values(array_filter($order, static fn($v)=>is_string($v) && $v!==''));
        $cols = isset($data['cols']) ? (int)$data['cols'] : 2;
        $cols = max(1, min(6, $cols));

        $saved = ['order'=>$order,'cols'=>$cols];
        $configSaved = false;
        try {
            $cfg = Config::instance();
            $dash = $cfg->get('dashboard') ?? [];
            $dash['order'] = $order; $dash['cols'] = $cols;
            if (method_exists($cfg,'set'))  $cfg->set('dashboard', $dash);
            if (method_exists($cfg,'save')) { $cfg->save(); $configSaved = true; }
        } catch (\Throwable) { $configSaved = false; }

        if (!$configSaved) {
            $file = $this->layoutFilePath();
            $dir  = dirname($file);
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            @file_put_contents($file, json_encode($saved, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
        }
        $this->jsonOK(['saved'=>$saved]);
    }

    public function geo(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $limitReq = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
        $limitReq = max(1, min(200000, $limitReq));
        $since = isset($_GET['since']) ? (int)$_GET['since'] : null;
        $until = isset($_GET['until']) ? (int)$_GET['until'] : null;
        if ($since && $until && $until < $since) { $tmp=$since; $since=$until; $until=$tmp; }

        $debug = isset($_GET['debug']) ? (int)$_GET['debug'] : 0;
        $meta = ['probes'=>[]];
        $accum = [];

        try {
            $cfgAll = Config::instance()->all();
            $dbc = $cfgAll['db'] ?? $cfgAll['database'] ?? null;
            $table = 'visits';
            if (is_array($dbc) && !empty($dbc['table'])) $table = (string)$dbc['table'];

            // ---- PDO path (preferred)
            $pdo = $this->pdoMysql(is_array($dbc) ? $dbc : null);
            if ($pdo) {
                $pull = 200000;

                // WHERE for timeframe (applied in SQL)
                $w = [];
                if ($since !== null) $w[] = 'ts >= :since';
                if ($until !== null) $w[] = 'ts <= :until';
                $where = $w ? ('WHERE ' . implode(' AND ', $w)) : '';

                $sql = "
                    SELECT id, ip, path, user_agent AS ua, ts, lat, lon, city, country
                    FROM `{$table}`
                    {$where}
                    ORDER BY (CASE WHEN ts IS NULL OR ts=0 THEN 0 ELSE ts END) DESC, id DESC
                    LIMIT :lim
                ";
                $st = $pdo->prepare($sql);
                if ($since !== null) $st->bindValue(':since', $since, \PDO::PARAM_INT);
                if ($until !== null) $st->bindValue(':until', $until, \PDO::PARAM_INT);
                $st->bindValue(':lim', $pull, \PDO::PARAM_INT);
                $st->execute();
                $rows = $st->fetchAll();
                $meta['probes'][] = ['mysql/pdo', is_array($rows)?count($rows):0, $table, $where];
                if ($rows) $accum = array_merge($accum, $this->normalizeRows($rows));
            } else {
                // ---- mysqli fallback
                $m = $this->mysqliMysql(is_array($dbc) ? $dbc : null);
                if ($m) {
                    $pull = 200000;
                    $w = [];
                    if ($since !== null) $w[] = 'ts >= '.(int)$since;
                    if ($until !== null) $w[] = 'ts <= '.(int)$until;
                    $where = $w ? ('WHERE ' . implode(' AND ', $w)) : '';
                    $sql = "SELECT id, ip, path, user_agent AS ua, ts, lat, lon, city, country
                            FROM `{$table}`
                            {$where}
                            ORDER BY (CASE WHEN ts IS NULL OR ts=0 THEN 0 ELSE ts END) DESC, id DESC
                            LIMIT {$pull}";
                    if ($res = $m->query($sql)) {
                        $buf = [];
                        while ($row = $res->fetch_assoc()) { $buf[] = $row; }
                        $res->free();
                        $meta['probes'][] = ['mysql/mysqli', count($buf), $table, $where];
                        if ($buf) $accum = array_merge($accum, $this->normalizeRows($buf));
                    } else {
                        $meta['probes'][] = ['mysql/mysqli_error', $m->error];
                    }
                }
            }
        } catch (\Throwable $e) {
            $meta['probes'][] = ['mysql/throw', $e->getMessage()];
        }

        // Fallback files (unchanged)
        if (!$accum) {
            $dataDir = __DIR__ . '/../data';
            foreach (['visits.json','geo.json','data.json'] as $name) {
                $f = $dataDir.'/'.$name;
                if (!is_file($f)) { $meta['probes'][]=['json/miss',$f]; continue; }
                $raw = @file_get_contents($f);
                $j = json_decode((string)$raw, true);
                $list = $j['items'] ?? $j['rows'] ?? $j['data'] ?? ($this->isListArray($j)?$j:[]);
                $meta['probes'][] = ['json/rows',$f,is_array($list)?count($list):0];
                if ($list) { $accum = array_merge($accum, $this->normalizeRows($list)); break; }
            }
            if (!$accum && is_file($dataDir.'/visits.ndjson')) {
                $h = @fopen($dataDir.'/visits.ndjson','r');
                if ($h) {
                    $tmp=[]; $n=0;
                    while (!feof($h) && $n<200000) {
                        $line = trim((string)fgets($h)); if ($line==='') continue;
                        $j = json_decode($line,true); if (is_array($j)) {$tmp[]=$j;$n++;}
                    }
                    fclose($h);
                    $meta['probes'][] = ['ndjson/rows',$dataDir.'/visits.ndjson',count($tmp)];
                    if ($tmp) $accum = array_merge($accum, $this->normalizeRows($tmp));
                }
            }
            if (!$accum && is_file($dataDir.'/visits.csv')) {
                $h = @fopen($dataDir.'/visits.csv','r');
                if ($h) {
                    $header = fgetcsv($h);
                    $hasHeader = $header && preg_match('~ip|path|ts|created_at~i', implode(',', (array)$header));
                    if (!$hasHeader && $header) rewind($h);
                    $tmp=[]; $n=0;
                    while (($row = fgetcsv($h)) !== false && $n<200000) {
                        $tmp[] = [
                            'id'=>(int)($row[0]??0),
                            'ip'=>(string)($row[1]??''),
                            'path'=>(string)($row[2]??''),
                            'ts'=>(int)($row[3]??0),
                            'lat'=>isset($row[4])?(float)$row[4]:null,
                            'lon'=>isset($row[5])?(float)$row[5]:null,
                            'city'=>$row[6]??null,
                            'country'=>$row[7]??null,
                        ];
                        $n++;
                    }
                    fclose($h);
                    $meta['probes'][] = ['csv/rows',$dataDir.'/visits.csv',count($tmp)];
                    if ($tmp) $accum = array_merge($accum, $this->normalizeRows($tmp));
                }
            }
        }

        // De-dupe + final sort + limit (still keep as safety)
        $seen=[]; $dedup=[];
        foreach ($accum as $r) {
            $key = ($r['id'] ?? 0).'|'.($r['ts'] ?? 0).'|'.($r['ip'] ?? '').'|'.($r['path'] ?? '');
            if (isset($seen[$key])) continue;
            $seen[$key]=true; $dedup[]=$r;
        }
        usort($dedup, fn($a,$b)=> ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0));
        $items = array_slice($dedup, 0, $limitReq);

        $out = ['items'=>$items, 'count'=>count($items)];

        // Debug extras: min/max + bucket counts to prove the timeframe
        if ($debug >= 1) {
            $tsVals = array_values(array_filter(array_map(fn($x)=> (int)($x['ts']??0), $dedup)));
            sort($tsVals);
            $now = time();
            $meta['range'] = [
                'min_ts' => $tsVals ? $tsVals[0] : null,
                'max_ts' => $tsVals ? $tsVals[count($tsVals)-1] : null,
            ];
        }
        if ($debug >= 2) {
            $now = time();
            $b = [
                'last_hour'  => [$now-3600, $now],
                'last_day'   => [$now-86400, $now],
                'last_week'  => [$now-7*86400, $now],
                'last_month' => [$now-30*86400, $now],
                'last_year'  => [$now-365*86400, $now],
            ];
            $buck = [];
            foreach ($b as $k=>[$s,$u]) {
                $c=0; foreach ($dedup as $r){ $t=(int)($r['ts']??0); if ($t && $t>=$s && $t<=$u) $c++; }
                $buck[$k]=$c;
            }
            $meta['buckets'] = $buck;
        }
        if ($debug) $out['meta'] = $meta;

        $this->jsonOK($out);
    }

    public function api_plugins_list(): void { $this->trait_api_plugins_list(); }

    public function api_plugins_install(): void { $this->trait_api_plugins_install(); }
    public function api_plugins_toggle(): void { $this->trait_api_plugins_toggle(); }
    public function api_plugins_remove(): void { $this->trait_api_plugins_remove(); }
    public function stream(): void { $this->jsonOK(['stream'=>false]); }
    public function realtime(): void { $this->jsonOK(['stream'=>false]); }
    public function geoTest(): void {
        try {
            // Input IP, fallback to server remote addr
            $ip = isset($_GET['ip']) ? (string)$_GET['ip'] : (string)($_SERVER['REMOTE_ADDR'] ?? '');
            $ip = trim($ip);
            if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
                $this->jsonErr('invalid_ip', 400, ['msg'=>'Provide a valid IPv4/IPv6 address']);
                return;
            }

            // Use current geo config
            $cfgAll = \TrackEm\Core\Config::instance()->get('geo', []);
            $res = \TrackEm\Core\Geo::lookup($ip, is_array($cfgAll) ? $cfgAll : null);

            if ($res === null) {
                $this->jsonErr('lookup_failed', 502, ['msg'=>'No data returned by provider', 'provider'=>$prov]);
                return;
            }

            // Include provider and minimal echo for transparency
            $prov = (string)($cfgAll['provider'] ?? 'ip-api');
            $this->jsonOK(['ok'=>true, 'provider'=>$prov, 'ip'=>$ip, 'result'=>$res]);
        } catch (\Throwable $e) {
            $this->jsonErr('exception', 500, ['msg'=>$e->getMessage()]);
        }
    }
    

public function geoDownload(): void {
    try {
        // Prefer explicit querystring values so admin can download without saving settings
        $license = isset($_GET['license']) ? (string)$_GET['license'] : '';
        $mmdbPath = isset($_GET['mmdb_path']) ? (string)$_GET['mmdb_path'] : '';

        // Pull from config if not provided
        $cfgGeo = \TrackEm\Core\Config::instance()->get('geo', []);
        if ($license === '') { $license = (string)($cfgGeo['mm_license_key'] ?? ''); }
        if ($mmdbPath === '') { $mmdbPath = (string)($cfgGeo['mmdb_path'] ?? (__DIR__ . '/../data/GeoLite2-City.mmdb')); }

        $destDir = dirname($mmdbPath);
        if ($license === '') {
            $this->jsonErr('missing_license', 400, ['msg'=>'MaxMind license key is required']);
            return;
        }
        $res = \TrackEm\Core\Geo::downloadGeoLite2($license, $destDir);
        if (!is_array($res) || empty($res['ok'])) {
            $this->jsonErr('download_failed', 502, ['msg'=>$res['msg'] ?? 'Unknown failure']);
            return;
        }
        $out = ['msg'=>$res['msg'] ?? 'Downloaded', 'path'=>$res['path'] ?? null];
        // Verify final path matches configured/desired location
        $out['exists'] = is_file($mmdbPath);
        $this->jsonOK($out);
    } catch (\Throwable $e) {
        $this->jsonErr('exception', 500, ['msg'=>$e->getMessage()]);
    }
}

    public function health(): void { $this->jsonOK(['status'=>'ok']); }


// Legacy compatibility for Router->ApiController->pluginConfigs()
public function pluginConfigs(): void { $this->trait_api_plugins_configs(); }


    // === BEGIN: plugin-config helpers required by ApiPluginsAddon trait ===
    private function __configDir(): string {
        return __DIR__ . '/../config/plugins';
    }
    private function __ensureDir(string $dir): void {
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    }
    private function __pluginConfigPath(string $plugin): string {
        $safe = preg_replace('~[^a-zA-Z0-9._-]+~', '-', (string)$plugin) ?? 'unknown';
        return rtrim($this->__configDir(), '/\\') . '/' . $safe . '.json';
    }
    private function __getPluginConfig(string $plugin): array {
        $p = $this->__pluginConfigPath($plugin);
        if (!is_file($p)) return [];
        $txt = @file_get_contents($p);
        $j = json_decode((string)$txt, true);
        return is_array($j) ? $j : [];
    }
    private function __setPluginConfig(string $plugin, array $cfg): bool {
        $p = $this->__pluginConfigPath($plugin);
        $this->__ensureDir(dirname($p));
        $json = json_encode($cfg, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        if ($json === false) return false;
        $tmp = $p . '.tmp';
        if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
        if (!@rename($tmp, $p)) {
            $ok = @copy($tmp, $p);
            @unlink($tmp);
            if (!$ok) return false;
        }
        return true;
    }
    // === END: plugin-config helpers ===

}
