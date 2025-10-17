<?php
declare(strict_types=1);
namespace TrackEm\Core;
final class RateLimiter {
    private string $key;
    private int $window;
    private int $max;
    private string $storePath;
    public function __construct(string $key, int $window, int $max, ?string $storeDir=null) {
        $this->key = preg_replace('/[^a-zA-Z0-9._:-]/','_', $key);
        $this->window = max(1, $window);
        $this->max = max(1, $max);
        $dir = $storeDir ?? (__DIR__ . '/../../storage/ratelimit');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $this->storePath = rtrim($dir,'/').'/'.sha1($this->key).'.json';
    }
    public function allow(): bool {
        $now = time();
        $bucket = ['start'=>$now, 'count'=>0];
        if (is_file($this->storePath)) {
            $raw = @file_get_contents($this->storePath);
            if ($raw !== false) {
                $tmp = json_decode($raw, true);
                if (is_array($tmp) && isset($tmp['start'],$tmp['count'])) $bucket = $tmp;
            }
        }
        if ($now - (int)$bucket['start'] >= $this->window) { $bucket = ['start'=>$now, 'count'=>0]; }
        $bucket['count']++;
        @file_put_contents($this->storePath, json_encode($bucket), LOCK_EX);
        return $bucket['count'] <= $this->max;
    }
}
