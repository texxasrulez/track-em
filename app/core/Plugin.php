<?php
declare(strict_types=1);
namespace TrackEm\Core;
abstract class Plugin {
    abstract public function id(): string;
    abstract public function name(): string;
    abstract public function version(): string;
    public function boot(): void {}
    public function adminCards(): array { return []; }
    public function configSchema(): array { return []; }
}
