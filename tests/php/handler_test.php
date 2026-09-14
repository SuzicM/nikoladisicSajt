<?php
declare(strict_types=1);

foreach (['options', 'validate', 'ratelimit', 'leadlog', 'handler'] as $lib) {
    require_once __DIR__ . "/../../api/lib/{$lib}.php";
}
require_once __DIR__ . '/fixtures.php';

function handler_req(array $overrides = [], ?array $input = null): array
{
    return array_merge([
        'method' => 'POST',
        'content_type' => 'application/json',
        'origin' => 'https://example.com',
        'raw_body' => json_encode($input ?? lead_input_fixture()),
        'ip' => '203.0.113.7',
    ], $overrides);
}

function handler_env(bool $mlOk = true, bool $mailOk = true): array
{
    $calls = new ArrayObject(['mailerlite' => 0, 'mail' => 0]);
    $cfg = array_merge(config_fixture(), ['storage_dir' => tmp_dir()]);
    $deps = [
        'now' => 1_800_000_000,
        'options' => quiz_options(),
        'mailerlite' => function (array $lead) use ($calls, $mlOk): bool { $calls['mailerlite']++; return $mlOk; },
        'mail' => function (array $lead) use ($calls, $mailOk): bool { $calls['mail']++; return $mailOk; },
    ];
    return [$cfg, $deps, $calls];
}

test('ne-POST metoda → 405', function () {
    [$cfg, $deps] = handler_env();
    assert_same(['status' => 405, 'body' => ['ok' => false]], handle_submit(handler_req(['method' => 'GET']), $cfg, $deps));
});

test('ne-JSON content type → 415, JSON sa charset-om prolazi', function () {
    [$cfg, $deps] = handler_env();
    assert_same(415, handle_submit(handler_req(['content_type' => 'text/plain']), $cfg, $deps)['status']);
    assert_same(415, handle_submit(handler_req(['content_type' => '']), $cfg, $deps)['status']);
    assert_same(200, handle_submit(handler_req(['content_type' => 'application/json; charset=utf-8']), $cfg, $deps)['status']);
});

test('telo veće od 10240 bajtova → 413', function () {
    [$cfg, $deps] = handler_env();
    assert_same(413, handle_submit(handler_req(['raw_body' => str_repeat('a', 10241)]), $cfg, $deps)['status']);
});

test('pogrešan ili prazan Origin → 403', function () {
    [$cfg, $deps] = handler_env();
    assert_same(403, handle_submit(handler_req(['origin' => 'https://evil.example']), $cfg, $deps)['status']);
    assert_same(403, handle_submit(handler_req(['origin' => '']), $cfg, $deps)['status']);
});

test('popunjen honeypot → tihi 200 bez slanja', function () {
    [$cfg, $deps, $calls] = handler_env();
    $input = lead_input_fixture();
    $input['website'] = 'http://spam.example';
    assert_same(['status' => 200, 'body' => ['ok' => true]], handle_submit(handler_req([], $input), $cfg, $deps));
    assert_same(0, $calls['mailerlite'] + $calls['mail']);
});

test('prebrzo ili bez elapsed_ms → tihi 200 bez slanja', function () {
    [$cfg, $deps, $calls] = handler_env();
    $input = lead_input_fixture();
    $input['elapsed_ms'] = 4999;
    assert_same(200, handle_submit(handler_req([], $input), $cfg, $deps)['status']);
    unset($input['elapsed_ms']);
    assert_same(200, handle_submit(handler_req([], $input), $cfg, $deps)['status']);
    assert_same(0, $calls['mailerlite'] + $calls['mail']);
});

test('nevalidan unos ili neispravan JSON → 422 bez slanja', function () {
    [$cfg, $deps, $calls] = handler_env();
    $input = lead_input_fixture();
    $input['contact']['ime'] = '<script>alert(1)</script>';
    assert_same(422, handle_submit(handler_req([], $input), $cfg, $deps)['status']);
    assert_same(422, handle_submit(handler_req(['raw_body' => '{nije json']), $cfg, $deps)['status']);
    assert_same(0, $calls['mailerlite'] + $calls['mail']);
});

test('uspeh: 200, MailerLite i email pozvani, nema loga', function () {
    [$cfg, $deps, $calls] = handler_env();
    assert_same(['status' => 200, 'body' => ['ok' => true]], handle_submit(handler_req(), $cfg, $deps));
    assert_same(1, $calls['mailerlite']);
    assert_same(1, $calls['mail']);
    assert_true(!is_file($cfg['storage_dir'] . '/leads.log'));
});

test('MailerLite pao, email prošao: 200 i upis u log', function () {
    [$cfg, $deps, $calls] = handler_env(false, true);
    assert_same(200, handle_submit(handler_req(), $cfg, $deps)['status']);
    assert_same(1, $calls['mail']);
    $row = json_decode(trim(file_get_contents($cfg['storage_dir'] . '/leads.log')), true);
    assert_same('milica@example.com', $row['lead']['email']);
});

test('MailerLite prošao, email pao: 200 bez loga', function () {
    [$cfg, $deps] = handler_env(true, false);
    assert_same(200, handle_submit(handler_req(), $cfg, $deps)['status']);
    assert_true(!is_file($cfg['storage_dir'] . '/leads.log'));
});

test('oba pala: 500 i upis u log', function () {
    [$cfg, $deps] = handler_env(false, false);
    assert_same(['status' => 500, 'body' => ['ok' => false]], handle_submit(handler_req(), $cfg, $deps));
    assert_true(is_file($cfg['storage_dir'] . '/leads.log'));
});

test('šesti zahtev sa iste IP adrese → 429', function () {
    [$cfg, $deps] = handler_env();
    for ($i = 0; $i < 5; $i++) {
        assert_same(200, handle_submit(handler_req(), $cfg, $deps)['status']);
    }
    assert_same(429, handle_submit(handler_req(), $cfg, $deps)['status']);
});

test('honeypot zahtevi se ne broje u rate limit', function () {
    [$cfg, $deps] = handler_env();
    $bot = lead_input_fixture();
    $bot['website'] = 'x';
    for ($i = 0; $i < 10; $i++) {
        handle_submit(handler_req([], $bot), $cfg, $deps);
    }
    assert_same(200, handle_submit(handler_req(), $cfg, $deps)['status']);
    assert_same(true, handle_submit(handler_req(), $cfg, $deps)['body']['ok']);
});

test('svaki zahtev sa ispravnim Origin-om briše stare redove iz loga', function () {
    [$cfg, $deps] = handler_env();
    $log = $cfg['storage_dir'] . '/leads.log';
    lead_log_append($log, ['ime' => 'Stara'], $deps['now'] - 2_592_001);
    handle_submit(handler_req(['method' => 'POST', 'raw_body' => '{nije json']), $cfg, $deps);
    clearstatcache();
    assert_true(!is_file($log), 'stari red treba da je obrisan');
});

test('ispravna prijava preko limita vraća 429 ali se upisuje u log; neispravna ne', function () {
    [$cfg, $deps, $calls] = handler_env();
    for ($i = 0; $i < 5; $i++) {
        handle_submit(handler_req(), $cfg, $deps);
    }
    $sentBefore = $calls['mailerlite'];
    assert_same(429, handle_submit(handler_req(), $cfg, $deps)['status']);
    assert_same($sentBefore, $calls['mailerlite'], 'preko limita se ništa ne šalje');
    $rows = array_values(array_filter(explode("\n", (string) file_get_contents($cfg['storage_dir'] . '/leads.log'))));
    $row = json_decode(end($rows), true);
    assert_same('rate_limit', $row['lead']['_razlog']);
    assert_same('milica@example.com', $row['lead']['email']);

    $bad = lead_input_fixture();
    $bad['contact']['ime'] = '<script>';
    assert_same(429, handle_submit(handler_req([], $bad), $cfg, $deps)['status']);
    $rowsAfter = array_values(array_filter(explode("\n", (string) file_get_contents($cfg['storage_dir'] . '/leads.log'))));
    assert_same(count($rows), count($rowsAfter), 'neispravna prijava se ne upisuje');
});

test('dry_run_log upisuje kanal i podatke', function () {
    $dir = tmp_dir();
    assert_true(dry_run_log($dir, 'mail', ['subject' => 'Đurđa']));
    $row = json_decode(trim(file_get_contents($dir . '/dry-run.log')), true);
    assert_same('mail', $row['channel']);
    assert_same(['subject' => 'Đurđa'], $row['data']);
});
