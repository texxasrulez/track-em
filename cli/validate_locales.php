#!/usr/bin/env php
<?php
/**
 * Locale validator: ensures all locale files share the same key set as en_US.php
 * and that printf-style placeholders match.
 */
function load_locale($file) {
    $labels = include $file;
    if (!is_array($labels)) {
        fwrite(STDERR, "Invalid locale file: $file\n");
        exit(2);
    }
    return $labels;
}
function placeholders($s) {
    // capture %s, %d, %(name)s and {name}
    preg_match_all('/%(\(\w+\))?[sd]|{\w+}/', $s, $m);
    sort($m[0]);
    return $m[0];
}
$baseFile = __DIR__ . '/../i18n/en_US.php';
$base = load_locale($baseFile);
$baseKeys = array_keys($base);
sort($baseKeys);
$exit = 0;

foreach (glob(__DIR__ . '/../i18n/*.php') as $file) {
    if (basename($file) === 'en_US.php') continue;
    $loc = load_locale($file);
    $k = array_keys($loc);
    sort($k);
    if ($k !== $baseKeys) {
        $missing = array_diff($baseKeys, $k);
        $extra   = array_diff($k, $baseKeys);
        if ($missing) {
            echo basename($file), " missing keys: ", implode(', ', $missing), "\n";
            $exit = 1;
        }
        if ($extra) {
            echo basename($file), " extra keys: ", implode(', ', $extra), "\n";
            $exit = 1;
        }
    }
    // placeholder check
    foreach ($base as $key => $enVal) {
        if (!array_key_exists($key, $loc)) continue;
        $ph1 = placeholders($enVal);
        $ph2 = placeholders($loc[$key]);
        if ($ph1 !== $ph2) {
            echo basename($file), " placeholder mismatch for '$key'\n";
            $exit = 1;
        }
    }
}
exit($exit);
