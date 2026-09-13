<?php
declare(strict_types=1);

require_once __DIR__ . '/../../api/lib/ratelimit.php';

test('prvih 5 pokušaja prolazi, šesti je blokiran', function () {
    $dir = tmp_dir();
    $now = 1_800_000_000;
    for ($i = 0; $i < 5; $i++) {
        assert_same(false, rate_limit_exceeded($dir, '1.2.3.4', $now + $i), "pokušaj " . ($i + 1));
    }
    assert_same(true, rate_limit_exceeded($dir, '1.2.3.4', $now + 10));
    assert_same(true, rate_limit_exceeded($dir, '1.2.3.4', $now + 20));
});

test('različite IP adrese imaju odvojene brojače', function () {
    $dir = tmp_dir();
    $now = 1_800_000_000;
    for ($i = 0; $i < 5; $i++) {
        rate_limit_exceeded($dir, '1.1.1.1', $now);
    }
    assert_same(false, rate_limit_exceeded($dir, '2.2.2.2', $now));
});

test('posle isteka prozora IP ponovo prolazi', function () {
    $dir = tmp_dir();
    $now = 1_800_000_000;
    for ($i = 0; $i < 5; $i++) {
        rate_limit_exceeded($dir, '1.2.3.4', $now);
    }
    assert_same(false, rate_limit_exceeded($dir, '1.2.3.4', $now + 3601));
});

test('stari fajlovi drugih IP adresa se brišu', function () {
    $dir = tmp_dir();
    $now = 1_800_000_000;
    rate_limit_exceeded($dir, '9.9.9.9', $now);
    $old = $dir . '/' . hash('sha256', '9.9.9.9') . '.json';
    assert_true(is_file($old), 'fajl treba da postoji');
    rate_limit_exceeded($dir, '1.2.3.4', $now + 3601);
    clearstatcache();
    assert_true(!is_file($old), 'stari fajl treba da je obrisan');
});

test('ime fajla ne sadrži IP adresu', function () {
    $dir = tmp_dir();
    rate_limit_exceeded($dir, '203.0.113.7', 1_800_000_000);
    foreach (glob($dir . '/*') as $file) {
        assert_true(!str_contains($file, '203.0.113.7'));
    }
});

test('pravi direktorijum ako ne postoji', function () {
    $dir = tmp_dir() . '/ratelimit';
    assert_same(false, rate_limit_exceeded($dir, '1.2.3.4', 1_800_000_000));
    assert_true(is_dir($dir));
});

test('čišćenje ne briše fajl IP adrese koja se trenutno obrađuje', function () {
    $dir = tmp_dir();
    $now = 1_800_000_000;
    rate_limit_exceeded($dir, '1.2.3.4', $now);
    $file = $dir . '/' . hash('sha256', '1.2.3.4') . '.json';
    clearstatcache();
    $inode = fileinode($file);
    assert_same(false, rate_limit_exceeded($dir, '1.2.3.4', $now + 3601));
    clearstatcache();
    assert_same($inode, fileinode($file), 'fajl mora ostati isti (bez unlink-a)');
    assert_same([$now + 3601], json_decode((string) file_get_contents($file), true));
});
