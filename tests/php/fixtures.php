<?php
declare(strict_types=1);

function lead_input_fixture(): array
{
    return [
        'answers' => [
            'cilj' => 'Izgubiti kilograme i dodati mišićnu masu',
            'koliko_dugo' => '1–3 godine',
            'probala' => ['Dijete', 'Drugog trenera'],
            'prepreke' => ['Nemam plan', 'Gubim motivaciju'],
            'vaznost' => 9,
            'spremnost' => 'Da, spremna sam',
        ],
        'contact' => [
            'ime' => 'Milica',
            'email' => 'milica@example.com',
            'telefon' => '064 123 4567',
            'vreme_poziva' => 'Popodne',
            'kontakt_kanal' => 'WhatsApp',
        ],
        'saglasnost' => true,
        'utm' => ['utm_source' => 'ig', 'utm_medium' => 'social', 'utm_content' => 'link_in_bio'],
        'website' => '',
        'elapsed_ms' => 42000,
    ];
}

function lead_fixture(): array
{
    return [
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
    ];
}

function config_fixture(): array
{
    return [
        'mailerlite_api_key' => 'test-key',
        'mailerlite_group_prijave' => '111',
        'mailerlite_group_nurture' => '222',
        'mail_to' => 'nikola@example.com',
        'mail_from_address' => 'prijave@example.com',
        'mail_from_name' => 'Sajt Nikola Dišić',
        'allowed_origins' => ['https://example.com'],
        'storage_dir' => sys_get_temp_dir(),
        'dry_run' => false,
    ];
}
