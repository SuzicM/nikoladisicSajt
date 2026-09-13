<?php
declare(strict_types=1);

function rate_limit_exceeded(string $dir, string $ip, int $now, int $max = 5, int $window = 3600): bool
{
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    clearstatcache();
    foreach (glob($dir . '/*.json') ?: [] as $stale) {
        if (filemtime($stale) < $now - $window) {
            @unlink($stale);
        }
    }

    $file = $dir . '/' . hash('sha256', $ip) . '.json';
    $handle = fopen($file, 'c+');
    if ($handle === false) {
        return false;
    }
    flock($handle, LOCK_EX);

    $hits = json_decode((string) stream_get_contents($handle), true);
    $hits = is_array($hits) ? $hits : [];
    $hits = array_values(array_filter($hits, fn($t) => is_int($t) && $t > $now - $window));

    $exceeded = count($hits) >= $max;
    if (!$exceeded) {
        $hits[] = $now;
    }

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($hits));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    touch($file, $now);
    return $exceeded;
}
