<?php
declare(strict_types=1);
namespace TrackEm\Core;
final class HookManager {
    private static array $listeners=[];
    public static function on(string $hook, callable $fn): void { self::$listeners[$hook][]=$fn; }
    public static function emit(string $hook, array $payload=[]): void {
        foreach (self::$listeners[$hook] ?? [] as $fn) { try { $fn($payload); } catch (\Throwable $e) {} }
    }
}
