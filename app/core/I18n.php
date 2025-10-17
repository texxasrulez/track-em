<?php
declare(strict_types=1);
namespace TrackEm\Core;
final class I18n {
    private static array $labels=[]; private static string $lang='en_US';
    public static function boot(string $forced=''): void {
        $cfg = Config::instance();
        $lang = $forced ?: ($_GET['lang'] ?? $_SESSION['lang'] ?? $cfg->get('i18n.default','en_US'));
        $_SESSION['lang']=$lang; self::$lang=$lang;
        $file = __DIR__ . '/../../i18n/' . $lang . '.php';
        self::$labels = is_file($file) ? (require $file) : [];
    }
    public static function t(string $k,string $fallback=''): string { return self::$labels[$k] ?? ($fallback ?: $k); }
    public static function lang(): string { return self::$lang; }
    public static function languages(): array {
        $dir = __DIR__ . '/../../i18n'; $langs=[];
        foreach (scandir($dir) as $f) if (preg_match('/^([a-z]{2}_[A-Z]{2})\.php$/',$f,$m)) $langs[]=$m[1];
        sort($langs); return $langs;
    }
}
