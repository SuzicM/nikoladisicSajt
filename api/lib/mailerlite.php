<?php
declare(strict_types=1);

const MAILERLITE_SUBSCRIBERS_URL = 'https://connect.mailerlite.com/api/subscribers';

function mailerlite_payload(array $lead, array $cfg, string $nurtureAnswer): array
{
    $groups = [$cfg['mailerlite_group_prijave']];
    if ($lead['spremnost'] === $nurtureAnswer) {
        $groups[] = $cfg['mailerlite_group_nurture'];
    }

    return [
        'email' => $lead['email'],
        'fields' => [
            'name' => $lead['ime'],
            'phone' => $lead['telefon'],
            'cilj' => $lead['cilj'],
            'koliko_dugo' => $lead['koliko_dugo'],
            'probala' => implode(', ', $lead['probala']),
            'prepreke' => implode(', ', $lead['prepreke']),
            'vaznost' => $lead['vaznost'],
            'spremnost' => $lead['spremnost'],
            'vreme_poziva' => $lead['vreme_poziva'],
            'kontakt_kanal' => $lead['kontakt_kanal'],
            'izvor' => $lead['izvor'],
        ],
        'groups' => $groups,
        'status' => 'active',
    ];
}

function mailerlite_send(array $payload, string $apiKey, ?callable $transport = null): bool
{
    $transport ??= 'mailerlite_http_post';
    $response = $transport(
        MAILERLITE_SUBSCRIBERS_URL,
        [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        json_encode($payload, JSON_UNESCAPED_UNICODE)
    );

    $ok = in_array($response['status'], [200, 201], true);
    if (!$ok) {
        error_log('MailerLite greška: HTTP ' . $response['status'] . ' ' . substr($response['body'], 0, 500));
    }
    return $ok;
}

function mailerlite_http_post(string $url, array $headers, string $body): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
    ]);
    $response = curl_exec($ch);
    $status = $response === false ? 0 : (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($response === false) {
        error_log('MailerLite curl greška: ' . curl_error($ch));
    }
    curl_close($ch);

    return ['status' => $status, 'body' => $response === false ? '' : (string) $response];
}
