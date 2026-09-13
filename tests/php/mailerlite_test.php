<?php
declare(strict_types=1);

require_once __DIR__ . '/../../api/lib/mailerlite.php';
require_once __DIR__ . '/fixtures.php';

ini_set('error_log', tmp_dir() . '/error.log');

test('payload sadrži email, polja i grupu prijava', function () {
    $p = mailerlite_payload(lead_fixture(), config_fixture(), 'Trenutno nisam u mogućnosti');
    assert_same([
        'email' => 'milica@example.com',
        'fields' => [
            'name' => 'Milica',
            'phone' => '0641234567',
            'cilj' => 'Izgubiti kilograme i dodati mišićnu masu',
            'koliko_dugo' => '1–3 godine',
            'probala' => 'Dijete, Drugog trenera',
            'prepreke' => 'Nemam plan, Gubim motivaciju',
            'vaznost' => 9,
            'spremnost' => 'Da, spremna sam',
            'vreme_poziva' => 'Popodne',
            'kontakt_kanal' => 'WhatsApp',
            'izvor' => 'ig / social / link_in_bio',
        ],
        'groups' => ['111'],
        'status' => 'active',
    ], $p);
});

test('nurture odgovor dodaje i nurture grupu', function () {
    $lead = lead_fixture();
    $lead['spremnost'] = 'Trenutno nisam u mogućnosti';
    $p = mailerlite_payload($lead, config_fixture(), 'Trenutno nisam u mogućnosti');
    assert_same(['111', '222'], $p['groups']);
});

test('send šalje POST na subscribers endpoint sa Bearer ključem', function () {
    $captured = [];
    $transport = function (string $url, array $headers, string $body) use (&$captured): array {
        $captured = compact('url', 'headers', 'body');
        return ['status' => 201, 'body' => '{}'];
    };
    $ok = mailerlite_send(['email' => 'a@b.rs', 'fields' => ['name' => 'Đurđa']], 'tajna', $transport);
    assert_true($ok);
    assert_same('https://connect.mailerlite.com/api/subscribers', $captured['url']);
    assert_true(in_array('Authorization: Bearer tajna', $captured['headers'], true));
    assert_true(in_array('Content-Type: application/json', $captured['headers'], true));
    assert_true(str_contains($captured['body'], 'Đurđa'), 'UTF-8 bez escape-ovanja');
});

test('send vraća true za 200 (postojeći kontakt)', function () {
    assert_true(mailerlite_send([], 'k', fn() => ['status' => 200, 'body' => '{}']));
});

test('send vraća false za grešku ili pad mreže', function () {
    assert_same(false, mailerlite_send([], 'k', fn() => ['status' => 422, 'body' => '{"message":"x"}']));
    assert_same(false, mailerlite_send([], 'k', fn() => ['status' => 401, 'body' => '']));
    assert_same(false, mailerlite_send([], 'k', fn() => ['status' => 0, 'body' => '']));
});
