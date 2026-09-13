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
