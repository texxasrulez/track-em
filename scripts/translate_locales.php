<?php
declare(strict_types=1);

use TrackEm\I18n\MtUtils;

require __DIR__ . '/mt_utils.php';

function usage(int $exit = 0): void {
    $msg = <<<TXT
Usage:
  php scripts/translate_locales.php [--source=en_US] [--only=fr_FR,es_ES] [--dry-run] [--force] [--verbose]
Env:
  DEEPL_API_KEY    Your DeepL API key (required unless --dry-run)
  DEEPL_API_URL    Optional override. Defaults to api.deepl.com; for DeepL Free use api-free.deepl.com
  MT_FORMALITY     preferred formality: default, more, less (default: default)
Notes:
  • First run bootstraps non-English locales.
  • Later runs translate only missing keys, keys whose English changed, or keys still equal to English.
  • Unsupported DeepL target languages are skipped automatically.
  • Placeholders like {name}, %s, %1$d are preserved.
TXT;
    fwrite(STDERR, $msg . PHP_EOL);
    exit($exit);
}

$source = 'en_US';
$only = null;
$dryRun = false;
$force = false;
$verbose = false;
foreach ($argv as $arg) {
    if ($arg === '--help' || $arg === '-h') usage(0);
    if (str_starts_with($arg, '--source=')) $source = substr($arg, 9);
    if (str_starts_with($arg, '--only=')) $only = explode(',', substr($arg, 7));
    if ($arg === '--dry-run') $dryRun = true;
    if ($arg === '--force') $force = true;
    if ($arg === '--verbose' || $arg === '-v') $verbose = true;
}

$apiKey = getenv('DEEPL_API_KEY') ?: '';
if ($apiKey === '' && !$dryRun) {
    fwrite(STDERR, "ERROR: DEEPL_API_KEY is not set. Export it or run with --dry-run for previews.\n");
    exit(2);
}
$formality = getenv('MT_FORMALITY') ?: 'default';

// Endpoint autodetect: DeepL Free must use api-free.deepl.com
$apiBase = getenv('DEEPL_API_URL');
if (!$apiBase || trim($apiBase) === '') {
    $apiBase = (str_contains($apiKey, ':fx') ? 'https://api-free.deepl.com' : 'https://api.deepl.com');
}

$baseDir = dirname(__DIR__);
$i18nDir = $baseDir . '/i18n';
$stateFile = $i18nDir . '/.mt/mt_state.json';
$reportFile = $i18nDir . '/.mt/mt_report.json';

$state = MtUtils::loadState($stateFile);

$sourceFile = "{$i18nDir}/{$source}.php";
$srcArr = MtUtils::loadLocale($sourceFile);

$srcHash = [];
foreach ($srcArr as $k => $v) {
    $srcHash[$k] = MtUtils::hash((string)$v);
}
$prevHash = is_array($state['source_hash'] ?? null) ? $state['source_hash'] : [];
$bootstrap = empty($prevHash);

// Discover targets
$files = array_values(array_filter(glob($i18nDir . '/*.php'), fn($f) => basename($f) !== "{$source}.php"));
if (is_array($only) && !empty($only)) {
    $files = array_values(array_filter($files, function($f) use ($only) {
        return in_array(pathinfo($f, PATHINFO_FILENAME), $only, true);
    }));
}

// Supported target languages
$supportedTargets = [];
$deepLError = null;
if ($apiKey !== '') {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiBase . '/v2/languages?type=target',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: DeepL-Auth-Key {$apiKey}"],
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($curl);
    $http = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($resp === false) {
        $deepLError = 'languages_request_failed: ' . curl_error($curl);
    } else {
        $data = json_decode($resp, true);
        if (is_array($data)) {
            foreach ($data as $item) {
                if (isset($item['language'])) $supportedTargets[] = strtoupper($item['language']);
            }
        } else {
            $deepLError = "languages_non_json_http_{$http}: " . substr($resp, 0, 200);
        }
    }
    curl_close($curl);
}
if (empty($supportedTargets)) {
    // fallback list
    $supportedTargets = [
        'BG','CS','DA','DE','EL','EN-GB','EN-US','ES','ET','FI','FR','HU','ID','IT',
        'JA','KO','LT','LV','NB','NL','PL','PT-PT','PT-BR','RO','RU','SK','SL','SV','TR','UK','ZH'
    ];
}

function mapToDeepL(string $locale): ?string {
    $lc = str_replace('_', '-', $locale);
    if (preg_match('/^([a-z]{2})(?:[-_]([A-Z0-9]{2,3}))?$/', $lc, $m)) {
        $lang = strtoupper($m[1]);
        $region = $m[2] ?? '';
        if ($lang === 'PT' && $region === 'BR') return 'PT-BR';
        if ($lang === 'PT') return 'PT-PT';
        if ($lang === 'EN' && $region === 'GB') return 'EN-GB';
        if ($lang === 'EN' && $region === 'US') return 'EN-US';
        if ($lang === 'ES') return 'ES'; // Latin America -> ES
        if ($lang === 'ZH') return 'ZH'; // DeepL Simplified Chinese
        return $lang;
    }
    return null;
}

$report = [
    'source' => $source,
    'bootstrap' => $bootstrap,
    'api_base' => $apiBase,
    'deepl_languages_error' => $deepLError,
    'updated_locales' => [],
    'skipped_locales' => [],
    'key_results_sample' => [],
];
$totalTranslated = 0;

foreach ($files as $file) {
    $locale = pathinfo($file, PATHINFO_FILENAME);
    $targetCode = mapToDeepL($locale);

    // Skip unsupported and English targets
    if (!$targetCode || !in_array($targetCode, $supportedTargets, true) || str_starts_with($targetCode, 'EN')) {
        $report['skipped_locales'][] = ['locale' => $locale, 'reason' => 'unsupported or English', 'mapped' => $targetCode];
        continue;
    }

    $tArr = MtUtils::loadLocale($file);
    $toUpdate = [];

    foreach ($srcArr as $k => $srcStr) {
        $needs = false;
        if (!array_key_exists($k, $tArr)) {
            $needs = true;
        } elseif ($force) {
            $needs = true;
        } else {
            $prev = $prevHash[$k] ?? null;
            if ($prev !== null && $prev !== $srcHash[$k]) {
                $needs = true;
            }
            if ($bootstrap) {
                $needs = true; // first run
            }
            if (isset($tArr[$k]) && $tArr[$k] === $srcStr) {
                $needs = true; // still English
            }
        }
        if ($needs) {
            $toUpdate[$k] = (string)$srcStr;
        }
    }

    if (empty($toUpdate)) {
        $report['updated_locales'][] = ['locale' => $locale, 'updates' => 0, 'note' => 'already up to date'];
        continue;
    }

    $newVals = [];
    $keyResults = [];
    foreach ($toUpdate as $k => $srcStr) {
        [$masked, $map] = MtUtils::maskPlaceholders($srcStr);
        $translated = $srcStr;
        $status = 'skipped';
        $http = null;
        $err = null;
        if (!$dryRun && $apiKey !== '') {
            $post = http_build_query([
                'text' => $masked,
                'target_lang' => $targetCode,
                'source_lang' => 'EN',
                'formality' => $formality,
                'preserve_formatting' => 1,
            ]);
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiBase . '/v2/translate',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ["Authorization: DeepL-Auth-Key {$apiKey}"],
                CURLOPT_TIMEOUT => 30,
            ]);
            $resp = curl_exec($ch);
            if ($resp === false) {
                $err = curl_error($ch);
                $status = 'curl_error';
            } else {
                $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $data = json_decode($resp, true);
                if (isset($data['translations'][0]['text'])) {
                    $translated = $data['translations'][0]['text'];
                    $status = 'ok';
                } else {
                    $status = 'deepl_error';
                    $err = substr($resp, 0, 200);
                }
            }
            curl_close($ch);
        }
        $translated = MtUtils::unmaskPlaceholders($translated, $map);
        $newVals[$k] = $translated;
        if (count($keyResults) < 10) {
            $keyResults[] = ['key'=>$k,'status'=>$status,'http'=>$http,'error'=>$err];
        }
    }

    // Merge preserving source order
    $merged = [];
    foreach ($srcArr as $k => $_) {
        if (array_key_exists($k, $tArr)) {
            $merged[$k] = $tArr[$k];
        }
        if (array_key_exists($k, $newVals)) {
            $merged[$k] = $newVals[$k];
        }
    }
    foreach ($tArr as $k => $v) {
        if (!array_key_exists($k, $merged)) {
            $merged[$k] = $v;
        }
    }

    if (!$dryRun) {
        MtUtils::saveLocale($file, $merged);
    }
    $report['updated_locales'][] = ['locale' => $locale, 'updates' => count($newVals), 'mapped' => $targetCode];
    $report['key_results_sample'][$locale] = $keyResults;
    $totalTranslated += count($newVals);
}

$state['source'] = $source;
$state['source_hash'] = $srcHash;
MtUtils::saveState($stateFile, $state);
file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

fwrite(STDOUT, "[OK] Locales processed. Total keys translated: {$totalTranslated}\n");
fwrite(STDOUT, "Report: {$reportFile}\n");
if ($dryRun) {
    fwrite(STDOUT, "Dry run completed (no files written).\n");
} else {
    fwrite(STDOUT, "DeepL live translation used for needed keys via {$apiBase}.\n");
}
