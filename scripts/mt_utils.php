<?php
declare(strict_types=1);

namespace TrackEm\I18n;

final class MtUtils
{
    /** Load a PHP locale file that returns an array.
     *  If the file doesn't return an array, we fall back to static parsing of 'key' => 'value' pairs.
     */
    public static function loadLocale(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        // Try normal include first (suppress warnings to avoid noisy notices)
        $data = @include $file;
        if (is_array($data)) {
            return $data;
        }

        // Fallback: static parse to tolerate legacy or malformed files that don't "return" an array
        $txt = file_get_contents($file);
        $parsed = self::parseLocaleText($txt);
        if (is_array($parsed)) {
            return $parsed;
        }

        // Final fallback: empty array instead of fatal
        return [];
    }

    /** Save locale array to a canonical formatted PHP file. */
    public static function saveLocale(string $file, array $arr): void
    {
        $export = "<?php\nreturn [\n";
        foreach ($arr as $k => $v) {
            $export .= "  " . self::phpKey($k) . " => " . self::phpVal($v) . ",\n";
        }
        $export .= "];\n";
        $tmp = $file . ".tmp";
        file_put_contents($tmp, $export);
        $existing = is_file($file) ? file_get_contents($file) : null;
        if ($existing !== $export) {
            $dir = dirname($file);
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            rename($tmp, $file);
        } else {
            @unlink($tmp);
        }
    }

    /** Compute stable hash for a string (to detect source changes). */
    public static function hash(string $s): string
    {
        return sha1($s);
    }

    /** Load or init state file. */
    public static function loadState(string $stateFile): array
    {
        if (is_file($stateFile)) {
            $raw = file_get_contents($stateFile);
            $data = json_decode($raw, true);
            if (is_array($data)) { return $data; }
        }
        return [
            "source" => "en_US",
            "source_hash" => new \stdClass(),
            "last_run" => null,
            "deepl_supported" => [],
        ];
    }

    public static function saveState(string $stateFile, array $state): void
    {
        $dir = dirname($stateFile);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $state["last_run"] = gmdate('c');
        file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    }

    private static function phpKey($k): string
    {
        if (is_int($k)) { return (string)$k; }
        if (is_string($k)) { return var_export($k, true); }
        return var_export((string)$k, true);
    }

    private static function phpVal($v): string
    {
        if (is_string($v) || is_int($v) || is_float($v) || is_bool($v) || is_null($v)) {
            return var_export($v, true);
        }
        return var_export((string)$v, true);
    }

    /** Very tolerant static parser for 'key' => 'value' pairs.
     *  Supports both single- and double-quoted values; ignores comments.
     *  This is best-effort and intended only as a fallback.
     */
    private static function parseLocaleText(string $txt): ?array
    {
        // Strip PHP open/close tags
        $txt = preg_replace('/^\\s*<\\?php/u', '', $txt);
        $txt = preg_replace('/\\?>\\s*$/u', '', $txt);

        // Remove /* */ block comments
        $txt = preg_replace('!/\*.*?\*/!s', '', $txt);

        // Remove // and # line comments safely while keeping left-side content
        $lines = preg_split('/\\R/u', $txt);
        $tmp = '';
        foreach ($lines as $ln) {
            // Match '//' or '#' only when they start a comment, keep what comes before
            $tmp .= preg_replace('/(^|\\s)(?:\\/\\/|#).*$/u', '$1', $ln) . "\n";
        }
        $txt = $tmp;

        $pairs = [];
        // Match 'key' => 'value' and "key" => "value" (handles escaped quotes in value)
        $re = '/[\'"]([^\'"]+)[\'"]\\s*=>\\s*[\'"]((?:\\\\.|[^\'"])+)[\'"]/u';
        if (preg_match_all($re, $txt, $m, PREG_SET_ORDER)) {
            foreach ($m as $mm) {
                $key = $mm[1];
                $val = stripcslashes($mm[2]);
                $pairs[$key] = $val;
            }
            return $pairs;
        }
        return null;
    }

    /** Return placeholders and a mapping to reinstate after translation. */
    public static function maskPlaceholders(string $s): array
    {
        $patterns = [
            '/%\\d+\\$[sd]/u', // printf positional
            '/%[sd]/u',        // printf simple
            '/\\{[A-Za-z0-9_\\.:-]+\\}/u', // {name} or {count}
            '/\\{\\{[^}]+\\}\\}/u', // {{ double }}
        ];
        $map = [];
        $i = 0;
        $masked = $s;
        foreach ($patterns as $p) {
            $masked = preg_replace_callback($p, function($m) use (&$map, &$i) {
                $token = "__PH__" . ($i++) . "__";
                $map[$token] = $m[0];
                return $token;
            }, $masked);
        }
        return [$masked, $map];
    }

    public static function unmaskPlaceholders(string $s, array $map): string
    {
        foreach ($map as $tok => $orig) {
            $s = str_replace($tok, $orig, $s);
        }
        return $s;
    }
}
