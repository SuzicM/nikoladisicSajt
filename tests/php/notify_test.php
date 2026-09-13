<?php
declare(strict_types=1);

require_once __DIR__ . '/../../api/lib/notify.php';
require_once __DIR__ . '/fixtures.php';

ini_set('error_log', tmp_dir() . '/error.log');

test('naslov prikazuje ime, spremnost i važnost', function () {
    $m = notify_build(lead_fixture(), config_fixture(), 1_800_000_000);
    assert_same('🔥 Nova prijava: Milica (spremnost: Da, spremna sam, važnost 9/10)', $m['subject_plain']);
    assert_same('=?UTF-8?B?' . base64_encode($m['subject_plain']) . '?=', $m['subject']);
    assert_same('nikola@example.com', $m['to']);
});

test('telo sadrži sve odgovore, tel i WhatsApp link', function () {
    $body = notify_build(lead_fixture(), config_fixture(), 1_800_000_000)['body'];
    foreach (['Izgubiti kilograme i dodati mišićnu masu', '1–3 godine', 'Dijete, Drugog trenera',
              'Nemam plan, Gubim motivaciju', '9/10', 'Popodne', 'WhatsApp', 'milica@example.com',
              'ig / social / link_in_bio', 'href="tel:0641234567"', 'href="https://wa.me/381641234567"'] as $needle) {
        assert_true(str_contains($body, $needle), "telo ne sadrži: {$needle}");
    }
});

test('telo escape-uje HTML', function () {
    $lead = lead_fixture();
    $lead['ime'] = '<img src=x onerror=alert(1)>';
    $body = notify_build($lead, config_fixture(), 1_800_000_000)['body'];
    assert_true(!str_contains($body, '<img src=x'), 'sirov HTML ne sme proći');
    assert_true(str_contains($body, '&lt;img src=x onerror=alert(1)&gt;'));
});

test('headeri: UTF-8 HTML, kodiran From, Reply-To bez novih redova', function () {
    $lead = lead_fixture();
    $lead['email'] = "milica@example.com\r\nBcc: spam@example.com";
    $headers = notify_build($lead, config_fixture(), 1_800_000_000)['headers'];
    $lines = explode("\r\n", $headers);
    assert_same([
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: =?UTF-8?B?' . base64_encode('Sajt Nikola Dišić') . '?= <prijave@example.com>',
        'Reply-To: milica@example.comBcc: spam@example.com',
    ], $lines);
});

test('whatsapp_number normalizuje srpske brojeve', function () {
    assert_same('381641234567', whatsapp_number('0641234567'));
    assert_same('381641234567', whatsapp_number('+381641234567'));
    assert_same('4915112345678', whatsapp_number('+4915112345678'));
});

test('notify_send prosleđuje poruku maileru i vraća njegov rezultat', function () {
    $calls = [];
    $mailer = function (string $to, string $subject, string $body, string $headers) use (&$calls): bool {
        $calls[] = $to;
        return true;
    };
    assert_true(notify_send(lead_fixture(), config_fixture(), 1_800_000_000, $mailer));
    assert_same(['nikola@example.com'], $calls);
    assert_same(false, notify_send(lead_fixture(), config_fixture(), 1_800_000_000, fn() => false));
});
