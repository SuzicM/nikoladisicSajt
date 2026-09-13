<?php
declare(strict_types=1);

function header_safe(string $value): string
{
    return str_replace(["\r", "\n"], '', $value);
}

function whatsapp_number(string $telefon): string
{
    if (str_starts_with($telefon, '0')) {
        return '381' . substr($telefon, 1);
    }
    return ltrim($telefon, '+');
}

function notify_build(array $lead, array $cfg, int $now): array
{
    $e = fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $subject = sprintf(
        '🔥 Nova prijava: %s (spremnost: %s, važnost %d/10)',
        $lead['ime'],
        $lead['spremnost'],
        $lead['vaznost']
    );

    $phoneLinks = sprintf(
        '<a href="tel:%1$s">%1$s</a> · <a href="https://wa.me/%2$s">WhatsApp</a>',
        $e($lead['telefon']),
        $e(whatsapp_number($lead['telefon']))
    );

    $rows = [
        'Ime' => $e($lead['ime']),
        'Telefon' => $phoneLinks,
        'Email' => $e($lead['email']),
        'Najbolje vreme za poziv' => $e($lead['vreme_poziva']),
        'Kontakt preko' => $e($lead['kontakt_kanal']),
        'Glavni cilj' => $e($lead['cilj']),
        'Koliko dugo pokušava' => $e($lead['koliko_dugo']),
        'Šta je probala' => $e(implode(', ', $lead['probala'])),
        'Prepreke' => $e(implode(', ', $lead['prepreke'])),
        'Važnost' => $e($lead['vaznost'] . '/10'),
        'Spremnost na ulaganje' => $e($lead['spremnost']),
        'Izvor' => $e($lead['izvor']),
        'Vreme prijave' => $e(date('d.m.Y. H:i', $now)),
    ];

    $html = '<table cellpadding="6" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:15px">';
    foreach ($rows as $label => $value) {
        $html .= '<tr><td style="color:#666;vertical-align:top">' . $e($label) . '</td>'
            . '<td style="font-weight:600">' . $value . '</td></tr>';
    }
    $html .= '</table>';

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: =?UTF-8?B?' . base64_encode(header_safe($cfg['mail_from_name'])) . '?= <'
            . header_safe($cfg['mail_from_address']) . '>',
        'Reply-To: ' . header_safe($lead['email']),
    ];

    return [
        'to' => $cfg['mail_to'],
        'subject' => '=?UTF-8?B?' . base64_encode($subject) . '?=',
        'subject_plain' => $subject,
        'body' => $html,
        'headers' => implode("\r\n", $headers),
    ];
}

function notify_send(array $lead, array $cfg, int $now, ?callable $mailer = null): bool
{
    $message = notify_build($lead, $cfg, $now);
    $envelopeFrom = '-f' . header_safe($cfg['mail_from_address']);
    $mailer ??= fn(string $to, string $subject, string $body, string $headers): bool
        => mail($to, $subject, $body, $headers, $envelopeFrom);

    $ok = (bool) $mailer($message['to'], $message['subject'], $message['body'], $message['headers']);
    if (!$ok) {
        error_log('notify_send: slanje email-a nije uspelo');
    }
    return $ok;
}
