<?php
declare(strict_types=1);

function lead_log_append(string $file, array $lead, int $now, int $retention = 2592000): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    $handle = fopen($file, 'c+');
    if ($handle === false) {
        error_log('lead_log_append: ne mogu da otvorim ' . $file);
        return;
    }
    flock($handle, LOCK_EX);

    $kept = [];
    while (($line = fgets($handle)) !== false) {
        $row = json_decode($line, true);
        if (is_array($row) && is_int($row['ts'] ?? null) && $row['ts'] >= $now - $retention) {
            $kept[] = rtrim($line, "\n");
        }
    }
    $kept[] = json_encode(['ts' => $now, 'lead' => $lead], JSON_UNESCAPED_UNICODE);

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, implode("\n", $kept) . "\n");
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    chmod($file, 0600);
}
