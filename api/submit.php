<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Belgrade');
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

foreach (['options', 'validate', 'ratelimit', 'leadlog', 'mailerlite', 'notify', 'handler'] as $lib) {
    require __DIR__ . "/lib/{$lib}.php";
}

$configPath = getenv('LEAD_CONFIG') ?: dirname(__DIR__, 2) . '/config.php';
if (!is_file($configPath)) {
    error_log('submit.php: config nije pronađen na ' . $configPath);
    http_response_code(500);
    echo json_encode(['ok' => false]);
    exit;
}
$cfg = require $configPath;
$options = quiz_options();

if ($cfg['dry_run']) {
    $sendToMailerlite = fn(array $lead): bool =>
        dry_run_log($cfg['storage_dir'], 'mailerlite', mailerlite_payload($lead, $cfg, $options['nurture_answer']))
        && !$cfg['dry_run_fail_mailerlite'];
    $sendMail = fn(array $lead): bool =>
        dry_run_log($cfg['storage_dir'], 'mail', notify_build($lead, $cfg, time()));
} else {
    $sendToMailerlite = fn(array $lead): bool =>
        mailerlite_send(mailerlite_payload($lead, $cfg, $options['nurture_answer']), $cfg['mailerlite_api_key']);
    $sendMail = fn(array $lead): bool => notify_send($lead, $cfg, time());
}

$rawBody = file_get_contents('php://input', false, null, 0, MAX_BODY_BYTES + 1);

$result = handle_submit(
    [
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? '',
        'raw_body' => $rawBody === false ? '' : $rawBody,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
    ],
    $cfg,
    [
        'now' => time(),
        'options' => $options,
        'mailerlite' => $sendToMailerlite,
        'mail' => $sendMail,
    ]
);

http_response_code($result['status']);
echo json_encode($result['body']);
