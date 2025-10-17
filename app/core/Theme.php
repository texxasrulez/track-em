<?php
declare(strict_types=1);

namespace TrackEm\Core;

require_once __DIR__ . '/DB.php';

final class Theme
{
    private const THEME_DIR = 'assets/themes';

    private static function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function baseUrl(): string
    {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        return $base === '/' ? '' : $base;
    }

    /** Discover themes + palettes parsed from :root { --vars } */
    public static function list(): array
    {
        $fsDir  = self::projectRoot() . '/' . self::THEME_DIR;
        $webDir = self::baseUrl() . '/' . self::THEME_DIR;

        $names = [
            'matrix'=>'matrix (default)',
            'noir'=>'Noir','solar'=>'Solar','forest'=>'Forest',
            'crimson'=>'Crimson','matrix'=>'Matrix',
        ];

        $out = [];
        foreach (glob($fsDir.'/*.css') ?: [] as $css) {
            $id  = basename($css, '.css');
            $pal = self::parsePalette($css);
            $out[] = [
                'id'      => $id,
                'name'    => $names[$id] ?? ucfirst($id),
                'href'    => $webDir.'/'.$id.'.css',
                'palette' => $pal, // ['bg'=>..., 'muted'=>..., 'accent'=>...]
            ];
        }
        usort($out, fn($a,$b)=>strcmp($a['name'],$b['name']));
        return $out;
    }

    /** Cookie (preview) takes precedence; Activate writes DB */
    public static function activeId(): string
    {
        // 1) Soft preview via cookie
        if (!empty($_COOKIE['theme'])) {
            $cid = preg_replace('/[^a-z0-9_-]/i', '', (string)$_COOKIE['theme']);
            if ($cid !== '') return $cid;
        }

        // 2) DB setting (JSON)
        try {
            $pdo = DB::pdo();
            $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key`=? LIMIT 1");
            $st->execute(['ui.theme.active']);
            $row = $st->fetch();
            if ($row && is_string($row['value']) && $row['value'] !== '') {
                $val = json_decode((string)$row['value'], true);
                if (is_string($val) && $val !== '') {
                    return preg_replace('/[^a-z0-9_-]/i', '', $val);
                }
            }
        } catch (\Throwable $e) {}

        // 3) Default
        return 'matrix';
    }

    /** Persist activation (JSON) and set long-lived cookie */
    public static function setActive(string $id): void
    {
        $id = preg_replace('/[^a-z0-9_-]/i', '', $id) ?: 'matrix';

        $pdo = DB::pdo();
        $payload = json_encode($id, JSON_UNESCAPED_SLASHES);
        $st = $pdo->prepare(
            "INSERT INTO settings (`key`,`value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)"
        );
        $st->execute(['ui.theme.active', $payload]);

        self::setPreview($id, time()+3600*24*365);
    }

    /** Set preview cookie (soft, no DB write). */
    public static function setPreview(string $id, ?int $expires = null): void
    {
        $id = preg_replace('/[^a-z0-9_-]/i', '', $id) ?: 'matrix';
        @setcookie('theme', $id, [
            'expires'  => $expires ?? (time()+3600),
            'path'     => self::baseUrl() === '' ? '/' : self::baseUrl(),
            'secure'   => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
                          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                          || (($_SERVER['SERVER_PORT'] ?? '') == 443),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        // Also update the superglobal for same-request rendering
        $_COOKIE['theme'] = $id;
    }

    /** <link> tag for active theme */
    public static function cssTag(): string
    {
        $id   = self::activeId();
        $href = self::baseUrl() . '/' . self::THEME_DIR . '/' . $id . '.css?v=' . rawurlencode($id);
        return '<link rel="stylesheet" href="'.htmlspecialchars($href, ENT_QUOTES).'" />';
    }

    /** Parse :root { --bg:..; --muted:..; --accent:.. } palette from a CSS file */
    private static function parsePalette(string $cssFile): array
    {
        $def = ['bg'=>'#0f1318','muted'=>'#121923','accent'=>'#4ea1ff'];
        $txt = @file_get_contents($cssFile);
        if ($txt === false) return $def;

        // pull :root { ... } block
        if (!preg_match('/:root\s*\{([^}]*)\}/i', $txt, $m)) return $def;
        $block = $m[1];

        $vars = [];
        foreach (['bg','muted','accent'] as $k) {
            if (preg_match('/--'.preg_quote($k,'/').'\s*:\s*([^;]+);/i', $block, $mm)) {
                $vars[$k] = trim($mm[1]);
            }
        }

        // Sanitize colors (basic)
        foreach ($vars as $k => $v) {
            $v = preg_replace('/\s+/', '', $v);
            // allow hex or rgb/rgba or known keywords; keep as-is if not empty
            if ($v !== '') $def[$k] = $v;
        }
        return $def;
    }
}
