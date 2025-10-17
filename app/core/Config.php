<?php
declare(strict_types=1);
namespace TrackEm\Core;
final class Config {
    private static ?self $inst=null; private array $cfg=[];
    public static function boot(): void { self::$inst = new self(); }
    public static function instance(): self { return self::$inst ??= new self(); }
    private function __construct(){
        $file = __DIR__ . '/../../config/config.php';
        $dist = __DIR__ . '/../../config/config.sample.php';
        if (is_file($file)) $this->cfg = require $file;
        elseif (is_file($dist)) $this->cfg = require $dist;
        else $this->cfg = [];
    }
    public function get(string $key, mixed $default=null): mixed {
        $cur=$this->cfg; foreach (explode('.', $key) as $p) {
            if (!is_array($cur) || !array_key_exists($p,$cur)) return $default; $cur=$cur[$p];
        } return $cur;
    }
    public function all(): array { return $this->cfg; }
}
