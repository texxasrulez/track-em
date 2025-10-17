<?php
declare(strict_types=1);
namespace TrackEm\Core;
final class Security {
    public static function startSecureSession(): void {
        if (session_status()===PHP_SESSION_NONE) session_start([
            'cookie_httponly' => true,
            'cookie_secure'  => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
                               || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                               || (($_SERVER['SERVER_PORT'] ?? '') == 443),
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => true,
            'use_only_cookies'=> true,
        ]);
    }
    public static function csrfToken(): string { $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
    public static function verifyCsrf(string $t): bool { return hash_equals($_SESSION['csrf'] ?? '', $t); }
    public static function clientIp(int $maskBits=0): string {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ip = explode(',', $ip)[0];
        if ($maskBits>0 && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip); $maskOctets=intdiv($maskBits,8);
            for ($i=4-$maskOctets;$i<4;$i++) $parts[$i]='0'; return implode('.',$parts);
        }
        return $ip;
    }

public static function isHttps(): bool {
    return (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
}

public static function clientIpMasked(): string {
    $ip = self::clientIp(0);
    if (strpos($ip, ':') !== false) {
        try { $bits = (int)\TrackEm\Core\Config::instance()->get('privacy.ipv6_mask_bits', 64); } catch (\Throwable $e) { $bits = 64; }
        $packed = @inet_pton($ip);
        if ($packed === false) return $ip;
        $bytes = str_split($packed);
        $full = intdiv($bits, 8);
        $rem  = $bits % 8;
        for ($i=$full+($rem?1:0); $i<16; $i++) $bytes[$i] = chr(0);
        if ($rem) {
            $mask = 0xFF << (8 - $rem) & 0xFF;
            $bytes[$full] = chr(ord($bytes[$full]) & $mask);
        }
        return inet_ntop(implode('', $bytes));
    } else {
        $b = 0; try { $b = (int)\TrackEm\Core\Config::instance()->get('privacy.ip_mask_bits', 16); } catch (\Throwable $e) { $b = 16; }
        $b = max(0, min(32, $b));
        if ($b === 0) return $ip;
        $parts = explode('.', $ip);
        if (count($parts) !== 4) return $ip;
        $addr = ((int)$parts[0]<<24) | ((int)$parts[1]<<16) | ((int)$parts[2]<<8) | ((int)$parts[3]);
        $mask = $b === 32 ? 0xFFFFFFFF : (~((1 << (32-$b)) - 1) & 0xFFFFFFFF);
        $masked = $addr & $mask;
        return sprintf('%d.%d.%d.%d', ($masked>>24)&255, ($masked>>16)&255, ($masked>>8)&255, $masked&255);
    }
}

public static function rateLimit(string $key, int $windowSec, int $maxEvents): bool {
    if ($maxEvents <= 0) return true;
    $dir = sys_get_temp_dir() . '/trackem_rl';
    @mkdir($dir, 0775, true);
    $path = $dir . '/' . preg_replace('/[^a-z0-9._:-]/i', '_', $key);
    $now = time();
    $data = ['w'=> $now, 'hits'=> 0];
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        if ($raw !== false) {
            $dec = @json_decode($raw, true);
            if (is_array($dec) && isset($dec['w'], $dec['hits'])) $data = $dec;
        }
    }
    if ($now - (int)$data['w'] >= $windowSec) { $data['w'] = $now; $data['hits'] = 0; }
    if ((int)$data['hits'] >= $maxEvents) return false;
    $data['hits'] = (int)$data['hits'] + 1;
    @file_put_contents($path, json_encode($data), LOCK_EX);
    return true;
}

public static function emitSecurityHeaders(): void {
    try {
        $cfg = \TrackEm\Core\Config::instance();
        if (!$cfg->get('security_headers.enabled', true)) return;
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('X-Frame-Options: SAMEORIGIN');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        $csp = (string)$cfg->get('security_headers.csp', '');
        if ($csp !== '') header('Content-Security-Policy: ' . $csp);
    } catch (\Throwable $e) { }
}

}
