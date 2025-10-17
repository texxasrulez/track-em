<?php
declare(strict_types=1);
namespace TrackEm\Core;
final class Url {
  public static function basePath(): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '/';
    $dir = str_replace('\\\\', '/', rtrim(dirname($script), '/\\\\'));
    return ($dir === '/' || $dir === '.' ? '' : $dir);
  }
  public static function baseUrl(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    $port = $_SERVER['HTTP_X_FORWARDED_PORT'] ?? $_SERVER['SERVER_PORT'] ?? null;
    $bp   = self::basePath();
    $portPart = ($port && !in_array((int)$port, [80,443], true)) ? ':' . (int)$port : '';
    return $scheme . '://' . $host . $portPart . $bp;
  }
  public static function path(string $rel): string {
    $rel = '/' . ltrim($rel, '/');
    return self::basePath() . $rel;
  }
  public static function asset(string $rel): string { return self::path('assets/' . ltrim($rel, '/')); }
}
