<?php
declare(strict_types=1);

require_once __DIR__ . '/../../api/lib/options.php';
require_once __DIR__ . '/../../api/lib/validate.php';
require_once __DIR__ . '/fixtures.php';

function validate_with(callable $mutate): array
{
    $input = lead_input_fixture();
    $mutate($input);
    return validate_lead($input, quiz_options());
}

test('validan ulaz prolazi i normalizuje se', function () {
    $r = validate_lead(lead_input_fixture(), quiz_options());
    assert_true($r['ok']);
    assert_same([
        'ime' => 'Milica',
        'email' => 'milica@example.com',
        'telefon' => '0641234567',
        'vreme_poziva' => 'Popodne',
        'kontakt_kanal' => 'WhatsApp',
        'cilj' => 'Izgubiti kilograme i dodati mišićnu masu',
        'koliko_dugo' => '1–3 godine',
        'probala' => ['Dijete', 'Drugog trenera'],
        'prepreke' => ['Nemam plan', 'Gubim motivaciju'],
        'vaznost' => 9,
        'spremnost' => 'Da, spremna sam',
        'izvor' => 'ig / social / link_in_bio',
    ], $r['lead']);
});

test('ulaz koji nije niz pada', function () {
    assert_same(false, validate_lead('tekst', quiz_options())['ok']);
    assert_same(false, validate_lead(null, quiz_options())['ok']);
});

test('bez saglasnosti pada', function () {
    assert_same(false, validate_with(fn(&$i) => $i['saglasnost'] = false)['ok']);
    assert_same(false, validate_with(fn(&$i) => $i['saglasnost'] = 'true')['ok']);
    assert_same(false, validate_with(function (&$i) { unset($i['saglasnost']); })['ok']);
});

test('nepostojeći single odgovor pada', function () {
    assert_same(false, validate_with(fn(&$i) => $i['answers']['cilj'] = 'Nešto treće')['ok']);
    assert_same(false, validate_with(fn(&$i) => $i['answers']['spremnost'] = ['Da, spremna sam'])['ok']);
});

test('multi: prazan, duplikat, nepoznat ili ne-lista pada', function () {
    assert_same(false, validate_with(fn(&$i) => $i['answers']['probala'] = [])['ok']);
    assert_same(false, validate_with(fn(&$i) => $i['answers']['probala'] = ['Dijete', 'Dijete'])['ok']);
    assert_same(false, validate_with(fn(&$i) => $i['answers']['probala'] = ['Dijete', '<b>x</b>'])['ok']);
    assert_same(false, validate_with(fn(&$i) => $i['answers']['probala'] = ['a' => 'Dijete'])['ok']);
    assert_same(false, validate_with(fn(&$i) => $i['answers']['probala'] = 'Dijete')['ok']);
});

test('skala: van opsega ili string pada', function () {
    assert_same(false, validate_with(fn(&$i) => $i['answers']['vaznost'] = 0)['ok']);
    assert_same(false, validate_with(fn(&$i) => $i['answers']['vaznost'] = 11)['ok']);
    assert_same(false, validate_with(fn(&$i) => $i['answers']['vaznost'] = '9')['ok']);
    assert_same(true, validate_with(fn(&$i) => $i['answers']['vaznost'] = 1)['ok']);
    assert_same(true, validate_with(fn(&$i) => $i['answers']['vaznost'] = 10)['ok']);
});

test('ime: script tag, predugačko, prazno pada; srpska imena prolaze', function () {
    assert_same(false, validate_with(fn(&$i) => $i['contact']['ime'] = '<script>alert(1)</script>')['ok']);
    assert_same(false, validate_with(fn(&$i) => $i['contact']['ime'] = str_repeat('a', 61))['ok']);
    assert_same(false, validate_with(fn(&$i) => $i['contact']['ime'] = '   ')['ok']);
    assert_same(false, validate_with(fn(&$i) => $i['contact']['ime'] = "Ana\r\nBcc: x@y.z")['ok']);
    assert_same(true, validate_with(fn(&$i) => $i['contact']['ime'] = 'Ana-Marija')['ok']);
    assert_same(true, validate_with(fn(&$i) => $i['contact']['ime'] = 'Đurđa Čolić')['ok']);
    assert_same(true, validate_with(fn(&$i) => $i['contact']['ime'] = str_repeat('ž', 60))['ok']);
});

test('ime se trimuje', function () {
    assert_same('Milica', validate_with(fn(&$i) => $i['contact']['ime'] = '  Milica ')['lead']['ime']);
});

test('email: nevalidan ili sa novim redom pada', function () {
    assert_same(false, validate_with(fn(&$i) => $i['contact']['email'] = 'milica@')['ok']);
    assert_same(false, validate_with(fn(&$i) => $i['contact']['email'] = "a@b.rs\r\nBcc: x@y.z")['ok']);
    assert_same(false, validate_with(fn(&$i) => $i['contact']['email'] = str_repeat('a', 250) . '@b.rs')['ok']);
});

test('telefon: formati', function () {
    assert_same('+381641234567', validate_with(fn(&$i) => $i['contact']['telefon'] = '+381 64 123 4567')['lead']['telefon']);
    assert_same(false, validate_with(fn(&$i) => $i['contact']['telefon'] = '12345')['ok']);
    assert_same(false, validate_with(fn(&$i) => $i['contact']['telefon'] = '064-123-4567')['ok']);
    assert_same(false, validate_with(fn(&$i) => $i['contact']['telefon'] = '+3816412345678901')['ok']);
});

test('vreme poziva i kanal moraju biti sa liste', function () {
    assert_same(false, validate_with(fn(&$i) => $i['contact']['vreme_poziva'] = 'Noću')['ok']);
    assert_same(false, validate_with(fn(&$i) => $i['contact']['kontakt_kanal'] = 'Telegram')['ok']);
});

test('lead_source: redosled, preskakanje nevalidnih, direktno', function () {
    assert_same('ig / social / link_in_bio / 97760_v0',
        lead_source(['utm_campaign' => '97760_v0', 'utm_source' => 'ig', 'utm_medium' => 'social', 'utm_content' => 'link_in_bio']));
    assert_same('ig', lead_source(['utm_source' => 'ig', 'utm_medium' => '<script>']));
    assert_same('direktno', lead_source([]));
    assert_same('direktno', lead_source('ig'));
    assert_same('direktno', lead_source(['utm_source' => str_repeat('a', 101)]));
    assert_same('direktno', lead_source(['utm_source' => "ig\n"]));
    assert_same('ig', lead_source(['utm_source' => 'ig', 'utm_medium' => "social\n"]));
});
