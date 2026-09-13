<?php
// Šablon. Pravi config.php ide IZNAD public_html (lokalno: dev/config.php).
// Pravi config.php se nikad ne commit-uje.
return [
    // MailerLite → Integrations → API → Generate new token
    'mailerlite_api_key' => '',
    // MailerLite → Subscribers → Groups → ID grupe iz URL-a
    'mailerlite_group_prijave' => '',
    'mailerlite_group_nurture' => '',

    'mail_to' => 'nikola@example.com',
    // Adresa mora postojati na domenu sajta (Hostinger → Emails)
    'mail_from_address' => 'prijave@example.com',
    'mail_from_name' => 'Sajt Nikola Dišić',

    // Tačni origin-i sa kojih sajt radi (bez kose crte na kraju)
    'allowed_origins' => ['https://example.com', 'https://www.example.com'],

    'storage_dir' => __DIR__ . '/storage',

    // true = ništa se ne šalje, sve ide u storage/dry-run.log (samo lokalno)
    'dry_run' => false,
    // true = u dry-run režimu simulira pad MailerLite-a (test fallback loga)
    'dry_run_fail_mailerlite' => false,
];
