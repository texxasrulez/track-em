<?php
declare(strict_types=1);
namespace TrackEm\Core;
final class SecurityHeaders {
    public static function send(): void {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        $csp = "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'; connect-src 'self'";
        header('Content-Security-Policy: ' . $csp);
    }
}
