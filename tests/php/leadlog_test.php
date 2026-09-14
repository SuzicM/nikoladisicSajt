<?php
declare(strict_types=1);

require_once __DIR__ . '/../../api/lib/leadlog.php';

function leadlog_rows(string $file): array
{
    $lines = array_filter(explode("\n", (string) file_get_contents($file)));
    return array_values(array_map(fn($l) => json_decode($l, true), $lines));
}

test('prvi upis pravi fajl sa jednim redom', function () {
    $file = tmp_dir() . '/storage/leads.log';
    lead_log_append($file, ['ime' => 'Milica'], 1_800_000_000);
    assert_same([['ts' => 1_800_000_000, 'lead' => ['ime' => 'Milica']]], leadlog_rows($file));
    assert_same('0600', substr(sprintf('%o', fileperms($file)), -4));
});

test('dva upisa daju dva reda', function () {
    $file = tmp_dir() . '/leads.log';
    lead_log_append($file, ['ime' => 'A'], 1_800_000_000);
    lead_log_append($file, ['ime' => 'B'], 1_800_000_100);
    assert_same(2, count(leadlog_rows($file)));
});

test('redovi stariji od 30 dana se brišu', function () {
    $file = tmp_dir() . '/leads.log';
    $now = 1_800_000_000;
    lead_log_append($file, ['ime' => 'Stara'], $now - 2_592_001);
    lead_log_append($file, ['ime' => 'Granica'], $now - 2_592_000);
    lead_log_append($file, ['ime' => 'Nova'], $now);
    $names = array_map(fn($r) => $r['lead']['ime'], leadlog_rows($file));
    assert_same(['Granica', 'Nova'], $names);
});

test('neispravni redovi se brišu', function () {
    $file = tmp_dir() . '/leads.log';
    file_put_contents($file, "nije json\n{\"bez_ts\":1}\n");
    lead_log_append($file, ['ime' => 'Nova'], 1_800_000_000);
    assert_same(1, count(leadlog_rows($file)));
});

test('dijakritici se čuvaju bez escape-ovanja', function () {
    $file = tmp_dir() . '/leads.log';
    lead_log_append($file, ['ime' => 'Đurđa'], 1_800_000_000);
    assert_true(str_contains((string) file_get_contents($file), 'Đurđa'));
});

test('prune bez upisa briše stare redove i prazan fajl', function () {
    $file = tmp_dir() . '/leads.log';
    $now = 1_800_000_000;
    lead_log_append($file, ['ime' => 'Stara'], $now - 2_592_001);
    lead_log_append($file, ['ime' => 'Nova'], $now - 10);
    lead_log_prune($file, $now);
    assert_same(['Nova'], array_map(fn($r) => $r['lead']['ime'], leadlog_rows($file)));
    lead_log_prune($file, $now + 2_592_000);
    clearstatcache();
    assert_true(!is_file($file), 'prazan log treba da je obrisan');
});

test('prune na nepostojećem fajlu ne radi ništa', function () {
    $file = tmp_dir() . '/nema.log';
    lead_log_prune($file, 1_800_000_000);
    assert_true(!is_file($file));
});
