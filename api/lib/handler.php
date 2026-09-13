<?php
declare(strict_types=1);

const MAX_BODY_BYTES = 10240;
const MIN_ELAPSED_MS = 5000;

function handle_submit(array $req, array $cfg, array $deps): array
{
    $respond = fn(int $status, bool $ok): array => ['status' => $status, 'body' => ['ok' => $ok]];

    if ($req['method'] !== 'POST') {
        return $respond(405, false);
    }
    if (!str_starts_with(strtolower(trim($req['content_type'])), 'application/json')) {
        return $respond(415, false);
    }
    if (strlen($req['raw_body']) > MAX_BODY_BYTES) {
        return $respond(413, false);
    }
    if ($req['origin'] === '' || !in_array($req['origin'], $cfg['allowed_origins'], true)) {
        return $respond(403, false);
    }

    $input = json_decode($req['raw_body'], true);
    if (is_array($input) && looks_like_bot($input)) {
        return $respond(200, true);
    }

    if (rate_limit_exceeded($cfg['storage_dir'] . '/ratelimit', $req['ip'], $deps['now'])) {
        return $respond(429, false);
    }

    $result = validate_lead($input, $deps['options']);
    if (!$result['ok']) {
        return $respond(422, false);
    }
    $lead = $result['lead'];

    $mailerliteOk = $deps['mailerlite']($lead);
    $mailOk = $deps['mail']($lead);

    if (!$mailerliteOk) {
        lead_log_append($cfg['storage_dir'] . '/leads.log', $lead, $deps['now']);
    }
    if (!$mailerliteOk && !$mailOk) {
        return $respond(500, false);
    }
    return $respond(200, true);
}

function looks_like_bot(array $input): bool
{
    $honeypot = $input['website'] ?? '';
    if (!is_string($honeypot) || $honeypot !== '') {
        return true;
    }
    $elapsed = $input['elapsed_ms'] ?? null;
    return !is_int($elapsed) || $elapsed < MIN_ELAPSED_MS;
}

function dry_run_log(string $storageDir, string $channel, array $data): bool
{
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0700, true);
    }
    $line = json_encode(['ts' => time(), 'channel' => $channel, 'data' => $data], JSON_UNESCAPED_UNICODE);
    file_put_contents($storageDir . '/dry-run.log', $line . "\n", FILE_APPEND | LOCK_EX);
    return true;
}
