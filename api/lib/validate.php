<?php
declare(strict_types=1);

const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_content', 'utm_campaign'];

function validate_lead(mixed $input, array $options): array
{
    $fail = ['ok' => false, 'lead' => null];

    if (!is_array($input)) {
        return $fail;
    }
    $answers = $input['answers'] ?? null;
    $contact = $input['contact'] ?? null;
    if (!is_array($answers) || !is_array($contact) || ($input['saglasnost'] ?? null) !== true) {
        return $fail;
    }

    $ime = is_string($contact['ime'] ?? null) ? trim($contact['ime']) : '';
    $nameLength = mb_strlen($ime);
    if ($nameLength < 1 || $nameLength > 60 || preg_match("/^\\p{L}[\\p{L} '\\-]*$/u", $ime) !== 1) {
        return $fail;
    }

    $email = is_string($contact['email'] ?? null) ? trim($contact['email']) : '';
    if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return $fail;
    }

    $telefon = is_string($contact['telefon'] ?? null) ? preg_replace('/\s+/', '', $contact['telefon']) : '';
    if (preg_match('/^\+?[0-9]{8,15}$/', $telefon) !== 1) {
        return $fail;
    }

    $vreme = $contact['vreme_poziva'] ?? null;
    $kanal = $contact['kontakt_kanal'] ?? null;
    if (!in_array($vreme, $options['contact']['vreme_poziva'], true)
        || !in_array($kanal, $options['contact']['kontakt_kanal'], true)) {
        return $fail;
    }

    $lead = [
        'ime' => $ime,
        'email' => $email,
        'telefon' => $telefon,
        'vreme_poziva' => $vreme,
        'kontakt_kanal' => $kanal,
    ];

    foreach ($options['questions'] as $question) {
        $value = $answers[$question['id']] ?? null;
        if (!answer_is_valid($value, $question)) {
            return $fail;
        }
        $lead[$question['id']] = $value;
    }

    $lead['izvor'] = lead_source($input['utm'] ?? null);

    return ['ok' => true, 'lead' => $lead];
}

function answer_is_valid(mixed $value, array $question): bool
{
    switch ($question['type']) {
        case 'single':
            return is_string($value) && in_array($value, $question['options'], true);
        case 'multi':
            if (!is_array($value) || $value === [] || !array_is_list($value)) {
                return false;
            }
            foreach ($value as $item) {
                if (!is_string($item) || !in_array($item, $question['options'], true)) {
                    return false;
                }
            }
            return count(array_unique($value)) === count($value);
        case 'scale':
            return is_int($value) && $value >= $question['min'] && $value <= $question['max'];
        default:
            return false;
    }
}

function lead_source(mixed $utm): string
{
    if (!is_array($utm)) {
        return 'direktno';
    }
    $parts = [];
    foreach (UTM_KEYS as $key) {
        $value = $utm[$key] ?? null;
        if (is_string($value) && preg_match('/^[A-Za-z0-9_\-.]{1,100}$/', $value) === 1) {
            $parts[] = $value;
        }
    }
    return $parts === [] ? 'direktno' : implode(' / ', $parts);
}
