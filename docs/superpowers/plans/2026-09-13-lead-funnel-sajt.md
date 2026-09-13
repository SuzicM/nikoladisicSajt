# Nikola Dišić – lead funnel sajt – plan implementacije

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Statički sajt (landing + kviz, hvala stranica sa video FAQ, pravne stranice) koji prijave šalje kroz PHP endpoint u MailerLite i email Nikoli, hostovan na Hostingeru preko GitHub auto-deploy-a.

**Architecture:** Čist HTML/CSS/ES-module JavaScript bez build koraka. Jedan PHP endpoint (`api/submit.php`) koji koristi male čiste funkcije u `api/lib/`. Opcije kviza su u jednom izvoru istine `assets/data/quiz.json` koji čitaju i frontend (renderovanje) i backend (whitelist validacija). Konfiguracija i storage žive van `public_html`.

**Tech Stack:** HTML5, CSS (custom properties), vanilla JS (ES modules), PHP 8.1+ (curl, mbstring), Apache `.htaccess` (Hostinger), MailerLite API (`connect.mailerlite.com`), Meta Pixel. Testovi: `node --test` (Node 18+, bez paketa) i minimalni PHP test runner (bez Composera). `ffmpeg` za video.

**Spec:** `docs/superpowers/specs/2026-09-13-lead-funnel-sajt-design.md`

## Global Constraints

- Nema npm/Composer zavisnosti u produkciji; `package.json` postoji samo za test skripte i nema `dependencies`.
- Sav tekst za korisnike je na srpskom latinici sa dijakriticima (č, ć, š, ž, đ); obraćanje u ženskom rodu („spremna“, „saglasna“).
- Obećanje „6 nedelja do rezultata“ je jedini vremenski okvir obećanja.
- CTA tekst svuda: „Prijavi se za saradnju“.
- Korisnički unos se u DOM ubacuje isključivo preko `textContent` (nikad `innerHTML`); u PHP HTML izlaz isključivo kroz `htmlspecialchars`.
- Nema inline `<script>` blokova ni `style=""` atributa u HTML-u (CSP ih blokira).
- CSP: `default-src 'self'; script-src 'self' https://connect.facebook.net; img-src 'self' data: https://www.facebook.com; connect-src 'self' https://www.facebook.com https://connect.facebook.net; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'`.
- Endpoint: samo `POST`, `Content-Type: application/json`, telo ≤ 10240 bajtova, `Origin` iz `allowed_origins`.
- Rate limit: 5 zahteva po IP adresi u 3600 s. Honeypot polje `website`; minimalno `elapsed_ms` 5000.
- Fallback log: `storage/leads.log`, redovi stariji od 30 dana se brišu pri upisu.
- Consent ključ `nd_consent_v1` u `localStorage`, važi 6 meseci (15 552 000 000 ms).
- Video: H.264, 720×1280, AAC, `+faststart`; `<video playsinline preload="none">`.
- Mobile-first; Lighthouse mobilni ≥ 90; landing bez videa < 1 MB.
- Pravi sadržaj klijenta koji još ne postoji označava se markerom `{{KLIJENT: opis}}`; `scripts/prelaunch-check.sh` pada dok ijedan marker postoji.
- Vremenska zona u PHP-u: `Europe/Belgrade`.

## Struktura fajlova

```
.gitignore
package.json                      test skripte (bez zavisnosti)
README.md                         lokalni razvoj, testovi, deploy
.htaccess                         HTTPS, headeri, čisti URL-ovi, blokade, keš
index.html                        landing + kviz overlay skeleton + cookie banner
hvala.html                        potvrda + koraci + video FAQ
politika-privatnosti.html
uslovi-koriscenja.html
assets/data/quiz.json             pitanja i ponuđeni odgovori (izvor istine)
assets/css/style.css              ceo stil
assets/js/quiz-state.js           čista logika kviza (testirano)
assets/js/quiz.js                 DOM kviza: overlay, render, sessionStorage, slanje
assets/js/consent.js              consent logika + banner + Pixel + track()
assets/js/faq.js                  pozdrav (testirano) + video accordion
assets/js/landing.js              entry za index.html
assets/js/hvala-page.js           entry za hvala.html
assets/js/legal.js                entry za pravne stranice
assets/img/                       slike (WebP)
assets/video/                     FAQ MP4 + JPG poster + VTT
assets/nikola-disic.vcf           kontakt za „Sačuvaj broj“
api/submit.php                    HTTP ulaz: čita zahtev, zove handler, vraća JSON
api/config.example.php            šablon konfiguracije
api/lib/options.php               učitavanje quiz.json
api/lib/validate.php              whitelist validacija i normalizacija
api/lib/ratelimit.php             rate limit po IP adresi
api/lib/leadlog.php               fallback log sa brisanjem starih redova
api/lib/mailerlite.php            payload + HTTP poziv
api/lib/notify.php                email Nikoli (builder + slanje)
api/lib/handler.php               redosled obrade zahteva
scripts/dev-router.php            router za `php -S` (čisti URL-ovi, blokade, CSP)
scripts/encode-faq.sh             ffmpeg obrada snimaka
scripts/prelaunch-check.sh        provera markera i obaveznih fajlova
tests/php/run.php                 minimalni PHP test runner
tests/php/*_test.php              PHP testovi
tests/js/*.test.js                Node testovi
tests/security.sh                 curl testovi protiv lokalnog servera
```

Van repoa na serveru (i u `dev/` lokalno, gitignored): `config.php`, `storage/`.

---

### Task 1: Skelet repoa, test runneri i opcije kviza

**Files:**
- Create: `.gitignore`, `package.json`, `README.md`
- Create: `tests/php/run.php`
- Create: `assets/data/quiz.json`
- Create: `api/lib/options.php`
- Test: `tests/php/options_test.php`, `tests/js/quiz-json.test.js`

**Interfaces:**
- Produces:
  - `quiz.json` oblik: `{ "questions": [{ "id": string, "type": "single"|"multi"|"scale", "title": string, "options"?: string[], "min"?: int, "max"?: int }], "contact": { "vreme_poziva": string[], "kontakt_kanal": string[] }, "nurture_answer": string }`
  - PHP: `quiz_options(?string $path = null): array`, `quiz_question(array $options, string $id): ?array`
  - PHP test helperi: `test(string $name, callable $fn): void`, `assert_same(mixed $expected, mixed $actual, string $msg = ''): void`, `assert_true(bool $cond, string $msg = 'uslov nije ispunjen'): void`
  - Komande: `npm run test:js`, `npm run test:php`, `npm test`

- [ ] **Step 1: Instaliraj PHP lokalno (samo za razvoj)**

Run: `brew install php && php -v`
Expected: `PHP 8.x.x (cli)`. Proveri ekstenzije: `php -m | grep -E "curl|mbstring|json"` ispisuje sve tri.

- [ ] **Step 2: Napravi `.gitignore` i `package.json`**

`.gitignore`:
```
.DS_Store
/dev/
/storage/
node_modules/
```

`package.json`:
```json
{
  "name": "nikola-disic-sajt",
  "private": true,
  "type": "module",
  "scripts": {
    "test": "node --test tests/js/ && php tests/php/run.php",
    "test:js": "node --test tests/js/",
    "test:php": "php tests/php/run.php",
    "dev": "LEAD_CONFIG=dev/config.php php -S localhost:8000 scripts/dev-router.php"
  }
}
```

- [ ] **Step 3: Napravi PHP test runner `tests/php/run.php`**

```php
<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Belgrade');

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;

function test(string $name, callable $fn): void
{
    try {
        $fn();
        $GLOBALS['__pass']++;
        echo "  ✓ {$name}\n";
    } catch (Throwable $e) {
        $GLOBALS['__fail']++;
        echo "  ✗ {$name}\n    {$e->getMessage()}\n";
    }
}

function assert_same(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(($msg !== '' ? "{$msg}: " : '')
            . 'očekivano ' . var_export($expected, true)
            . ', dobijeno ' . var_export($actual, true));
    }
}

function assert_true(bool $cond, string $msg = 'uslov nije ispunjen'): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

function tmp_dir(): string
{
    $dir = sys_get_temp_dir() . '/nd-test-' . bin2hex(random_bytes(6));
    mkdir($dir, 0700, true);
    return $dir;
}

$filter = $argv[1] ?? '';
$files = glob(__DIR__ . '/*_test.php');
sort($files);
foreach ($files as $file) {
    if ($filter !== '' && !str_contains(basename($file), $filter)) {
        continue;
    }
    echo basename($file) . "\n";
    require $file;
}

echo "\n{$GLOBALS['__pass']} prošlo, {$GLOBALS['__fail']} palo\n";
exit($GLOBALS['__fail'] > 0 ? 1 : 0);
```

- [ ] **Step 4: Napiši testove koji padaju**

`tests/js/quiz-json.test.js`:
```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const quiz = JSON.parse(readFileSync(new URL('../../assets/data/quiz.json', import.meta.url), 'utf8'));

test('kviz ima 6 pitanja u tačnom redosledu', () => {
  assert.deepEqual(quiz.questions.map(q => q.id),
    ['cilj', 'koliko_dugo', 'probala', 'prepreke', 'vaznost', 'spremnost']);
});

test('tipovi pitanja odgovaraju spec-u', () => {
  assert.deepEqual(quiz.questions.map(q => q.type),
    ['single', 'single', 'multi', 'multi', 'scale', 'single']);
});

test('skala je 1–10', () => {
  const q = quiz.questions.find(q => q.id === 'vaznost');
  assert.equal(q.min, 1);
  assert.equal(q.max, 10);
});

test('nurture odgovor postoji među opcijama spremnosti', () => {
  const q = quiz.questions.find(q => q.id === 'spremnost');
  assert.ok(q.options.includes(quiz.nurture_answer));
});

test('kontakt opcije postoje', () => {
  assert.deepEqual(quiz.contact.vreme_poziva, ['Pre podne', 'Popodne', 'Uveče']);
  assert.deepEqual(quiz.contact.kontakt_kanal, ['Poziv', 'WhatsApp', 'Viber']);
});
```

`tests/php/options_test.php`:
```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../api/lib/options.php';

test('quiz_options učitava 6 pitanja', function () {
    $o = quiz_options();
    assert_same(6, count($o['questions']));
});

test('quiz_question pronalazi pitanje po id-u', function () {
    $q = quiz_question(quiz_options(), 'spremnost');
    assert_same('single', $q['type']);
});

test('quiz_question vraća null za nepoznat id', function () {
    assert_same(null, quiz_question(quiz_options(), 'nepostojece'));
});
```

- [ ] **Step 5: Pokreni testove i proveri da padaju**

Run: `npm run test:js; npm run test:php`
Expected: JS pada sa `ENOENT` za `quiz.json`; PHP pada sa `Failed opening required ... options.php`.

- [ ] **Step 6: Napravi `assets/data/quiz.json`**

```json
{
  "questions": [
    {
      "id": "cilj",
      "type": "single",
      "title": "Koji ti je glavni cilj?",
      "options": [
        "Izgubiti kilograme i dodati mišićnu masu",
        "Dodati kilograme i oblikovati telo",
        "Rešavanje zdravstvenih problema (insulinska rezistencija, hormoni…)"
      ]
    },
    {
      "id": "koliko_dugo",
      "type": "single",
      "title": "Koliko dugo pokušavaš da dođeš do tog cilja?",
      "options": ["Tek počinjem", "Do 1 godine", "1–3 godine", "Duže od 3 godine"]
    },
    {
      "id": "probala",
      "type": "multi",
      "title": "Šta si do sada probala?",
      "hint": "Možeš izabrati više odgovora.",
      "options": ["Dijete", "Teretanu sama", "Grupne treninge", "Drugog trenera", "Aplikacije", "Ništa od navedenog"]
    },
    {
      "id": "prepreke",
      "type": "multi",
      "title": "Šta te je najviše sprečavalo?",
      "hint": "Možeš izabrati više odgovora.",
      "options": ["Nedostatak vremena", "Ne znam šta da jedem", "Gubim motivaciju", "Zdravstveni problemi", "Nemam plan"]
    },
    {
      "id": "vaznost",
      "type": "scale",
      "title": "Koliko ti je važno da ovo rešiš u narednih 6 nedelja?",
      "hint": "1 = nije mi prioritet, 10 = želim da krenem odmah",
      "min": 1,
      "max": 10
    },
    {
      "id": "spremnost",
      "type": "single",
      "title": "Da li si spremna da uložiš u saradnju sa trenerom ako vidiš da je program za tebe?",
      "options": ["Da, spremna sam", "Želim prvo da čujem detalje", "Trenutno nisam u mogućnosti"]
    }
  ],
  "contact": {
    "vreme_poziva": ["Pre podne", "Popodne", "Uveče"],
    "kontakt_kanal": ["Poziv", "WhatsApp", "Viber"]
  },
  "nurture_answer": "Trenutno nisam u mogućnosti"
}
```

- [ ] **Step 7: Napravi `api/lib/options.php`**

```php
<?php
declare(strict_types=1);

function quiz_options(?string $path = null): array
{
    static $cache = [];
    $path ??= dirname(__DIR__, 2) . '/assets/data/quiz.json';
    if (!isset($cache[$path])) {
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('quiz.json nije pronađen');
        }
        $cache[$path] = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
    }
    return $cache[$path];
}

function quiz_question(array $options, string $id): ?array
{
    foreach ($options['questions'] as $question) {
        if ($question['id'] === $id) {
            return $question;
        }
    }
    return null;
}
```

- [ ] **Step 8: Pokreni testove i proveri da prolaze**

Run: `npm test`
Expected: 5 JS testova prolazi; PHP ispis `3 prošlo, 0 palo`.

- [ ] **Step 9: Napiši `README.md`**

````markdown
# Nikola Dišić – sajt za prijave

Landing + kviz → `api/submit.php` → MailerLite + email Nikoli → `/hvala` sa video FAQ.
Dizajn: `docs/superpowers/specs/2026-09-13-lead-funnel-sajt-design.md`

## Lokalni razvoj

Potrebno: PHP 8.1+ (`brew install php`), Node 18+, ffmpeg.

```bash
mkdir -p dev/storage
cp api/config.example.php dev/config.php   # podesi dry_run => true
npm run dev                                # http://localhost:8000
```

## Testovi

```bash
npm test                 # JS + PHP unit testovi
bash tests/security.sh   # dok radi `npm run dev`
bash scripts/prelaunch-check.sh
```

## Deploy

Push na `main` → Hostinger Git auto-deploy u `public_html`.
`config.php` i `storage/` se ručno postavljaju jednom, u folder iznad `public_html`.
Detalji: Task 15 u `docs/superpowers/plans/2026-09-13-lead-funnel-sajt.md`.

## Dodavanje FAQ videa

```bash
bash scripts/encode-faq.sh ~/Downloads/snimak.mov 01-cena
```

Zatim u `hvala.html` na odgovarajućoj `.faq-item` popuni
`data-video="/assets/video/01-cena.mp4" data-poster="/assets/video/01-cena.jpg" data-vtt="/assets/video/01-cena.vtt"`,
proveri tekst u `.vtt` fajlu i commit-uj.
````

- [ ] **Step 10: Commit**

```bash
git add .gitignore package.json README.md tests/php/run.php tests/php/options_test.php tests/js/quiz-json.test.js assets/data/quiz.json api/lib/options.php
git commit -m "Skelet repoa, test runneri i opcije kviza"
```

### Task 2: Whitelist validacija prijave (PHP)

**Files:**
- Create: `api/lib/validate.php`
- Create: `tests/php/fixtures.php`
- Test: `tests/php/validate_test.php`

**Interfaces:**
- Consumes: `quiz_options(): array` (Task 1)
- Produces:
  - Oblik ulaznog JSON-a (šalje ga frontend u Task 9, gradi ga `buildPayload` iz Task 8):
    ```json
    {
      "answers": { "cilj": "…", "koliko_dugo": "…", "probala": ["…"], "prepreke": ["…"], "vaznost": 9, "spremnost": "…" },
      "contact": { "ime": "…", "email": "…", "telefon": "…", "vreme_poziva": "…", "kontakt_kanal": "…" },
      "saglasnost": true,
      "utm": { "utm_source": "ig" },
      "website": "",
      "elapsed_ms": 42000
    }
    ```
  - `validate_lead(mixed $input, array $options): array` → `['ok' => true, 'lead' => array]` ili `['ok' => false, 'lead' => null]`
  - Normalizovan `lead` (ključevi tim redom): `ime` string, `email` string, `telefon` string (bez razmaka), `vreme_poziva` string, `kontakt_kanal` string, `cilj` string, `koliko_dugo` string, `probala` string[], `prepreke` string[], `vaznost` int, `spremnost` string, `izvor` string
  - `lead_source(mixed $utm): string` → `"ig / social / link_in_bio"` ili `"direktno"`
  - Test helper `lead_input_fixture(): array` (validan ulaz)

- [ ] **Step 1: Napravi `tests/php/fixtures.php`**

```php
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
```

- [ ] **Step 2: Napiši testove koji padaju — `tests/php/validate_test.php`**

```php
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
});
```

- [ ] **Step 3: Pokreni testove i proveri da padaju**

Run: `php tests/php/run.php validate`
Expected: FAIL sa `Failed opening required ... validate.php`.

- [ ] **Step 4: Implementiraj `api/lib/validate.php`**

```php
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
```

- [ ] **Step 5: Pokreni testove i proveri da prolaze**

Run: `php tests/php/run.php validate`
Expected: `12 prošlo, 0 palo`.

- [ ] **Step 6: Commit**

```bash
git add api/lib/validate.php tests/php/fixtures.php tests/php/validate_test.php
git commit -m "Whitelist validacija prijave"
```

### Task 3: Rate limit i fallback log (PHP)

**Files:**
- Create: `api/lib/ratelimit.php`, `api/lib/leadlog.php`
- Test: `tests/php/ratelimit_test.php`, `tests/php/leadlog_test.php`

**Interfaces:**
- Consumes: `tmp_dir(): string` iz `tests/php/run.php` (Task 1)
- Produces:
  - `rate_limit_exceeded(string $dir, string $ip, int $now, int $max = 5, int $window = 3600): bool` — beleži pokušaj i vraća `true` ako je IP već imao `$max` pokušaja u prozoru (tada se pokušaj ne beleži). Fajlovi stariji od prozora se brišu pri svakom pozivu. Ime fajla je `sha256(ip).json`.
  - `lead_log_append(string $file, array $lead, int $now, int $retention = 2592000): void` — dodaje red `{"ts": int, "lead": {...}}` (JSON Lines, UTF-8 bez escape-ovanja) i briše redove starije od `$retention` sekundi i neispravne redove. Prava fajla 0600.

- [ ] **Step 1: Napiši testove koji padaju**

`tests/php/ratelimit_test.php`:
```php
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
```

`tests/php/leadlog_test.php`:
```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../api/lib/leadlog.php';

function leadlog_rows(string $file): array
{
    $lines = array_filter(explode("\n", (string) file_get_contents($file)));
    return array_values(array_map(fn($l) => json_decode($l, true), $lines));
}

test('prvi upis pravi fajl sa jednim redom', function () {
    $file = tmp_dir() . '/storage/leads.log';
    lead_log_append($file, ['ime' => 'Milica'], 1_800_000_000);
    assert_same([['ts' => 1_800_000_000, 'lead' => ['ime' => 'Milica']]], leadlog_rows($file));
    assert_same('0600', substr(sprintf('%o', fileperms($file)), -4));
});

test('dva upisa daju dva reda', function () {
    $file = tmp_dir() . '/leads.log';
    lead_log_append($file, ['ime' => 'A'], 1_800_000_000);
    lead_log_append($file, ['ime' => 'B'], 1_800_000_100);
    assert_same(2, count(leadlog_rows($file)));
});

test('redovi stariji od 30 dana se brišu', function () {
    $file = tmp_dir() . '/leads.log';
    $now = 1_800_000_000;
    lead_log_append($file, ['ime' => 'Stara'], $now - 2_592_001);
    lead_log_append($file, ['ime' => 'Granica'], $now - 2_592_000);
    lead_log_append($file, ['ime' => 'Nova'], $now);
    $names = array_map(fn($r) => $r['lead']['ime'], leadlog_rows($file));
    assert_same(['Granica', 'Nova'], $names);
});

test('neispravni redovi se brišu', function () {
    $file = tmp_dir() . '/leads.log';
    file_put_contents($file, "nije json\n{\"bez_ts\":1}\n");
    lead_log_append($file, ['ime' => 'Nova'], 1_800_000_000);
    assert_same(1, count(leadlog_rows($file)));
});

test('dijakritici se čuvaju bez escape-ovanja', function () {
    $file = tmp_dir() . '/leads.log';
    lead_log_append($file, ['ime' => 'Đurđa'], 1_800_000_000);
    assert_true(str_contains((string) file_get_contents($file), 'Đurđa'));
});
```

- [ ] **Step 2: Pokreni testove i proveri da padaju**

Run: `php tests/php/run.php ratelimit; php tests/php/run.php leadlog`
Expected: FAIL sa `Failed opening required`.

- [ ] **Step 3: Implementiraj `api/lib/ratelimit.php`**

```php
<?php
declare(strict_types=1);

function rate_limit_exceeded(string $dir, string $ip, int $now, int $max = 5, int $window = 3600): bool
{
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    clearstatcache();
    foreach (glob($dir . '/*.json') ?: [] as $stale) {
        if (filemtime($stale) < $now - $window) {
            @unlink($stale);
        }
    }

    $file = $dir . '/' . hash('sha256', $ip) . '.json';
    $handle = fopen($file, 'c+');
    if ($handle === false) {
        return false;
    }
    flock($handle, LOCK_EX);

    $hits = json_decode((string) stream_get_contents($handle), true);
    $hits = is_array($hits) ? $hits : [];
    $hits = array_values(array_filter($hits, fn($t) => is_int($t) && $t > $now - $window));

    $exceeded = count($hits) >= $max;
    if (!$exceeded) {
        $hits[] = $now;
    }

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($hits));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    touch($file, $now);
    return $exceeded;
}
```

- [ ] **Step 4: Implementiraj `api/lib/leadlog.php`**

```php
<?php
declare(strict_types=1);

function lead_log_append(string $file, array $lead, int $now, int $retention = 2592000): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    $handle = fopen($file, 'c+');
    if ($handle === false) {
        error_log('lead_log_append: ne mogu da otvorim ' . $file);
        return;
    }
    flock($handle, LOCK_EX);

    $kept = [];
    while (($line = fgets($handle)) !== false) {
        $row = json_decode($line, true);
        if (is_array($row) && is_int($row['ts'] ?? null) && $row['ts'] >= $now - $retention) {
            $kept[] = rtrim($line, "\n");
        }
    }
    $kept[] = json_encode(['ts' => $now, 'lead' => $lead], JSON_UNESCAPED_UNICODE);

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, implode("\n", $kept) . "\n");
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    chmod($file, 0600);
}
```

- [ ] **Step 5: Pokreni testove i proveri da prolaze**

Run: `npm run test:php`
Expected: svi testovi prolaze (`… prošlo, 0 palo`).

- [ ] **Step 6: Commit**

```bash
git add api/lib/ratelimit.php api/lib/leadlog.php tests/php/ratelimit_test.php tests/php/leadlog_test.php
git commit -m "Rate limit po IP adresi i fallback log prijava"
```

### Task 4: MailerLite klijent i email Nikoli (PHP)

**Files:**
- Create: `api/lib/mailerlite.php`, `api/lib/notify.php`
- Test: `tests/php/mailerlite_test.php`, `tests/php/notify_test.php`

**Interfaces:**
- Consumes: normalizovan `lead` iz `validate_lead()` (Task 2); `tmp_dir()` (Task 1)
- Produces:
  - Config ključevi koje ovaj task čita: `mailerlite_api_key` string, `mailerlite_group_prijave` string, `mailerlite_group_nurture` string, `mail_to` string, `mail_from_address` string, `mail_from_name` string
  - `mailerlite_payload(array $lead, array $cfg, string $nurtureAnswer): array`
  - `mailerlite_send(array $payload, string $apiKey, ?callable $transport = null): bool` — `$transport(string $url, array $headers, string $body): array{status:int, body:string}`; uspeh je HTTP 200 ili 201
  - `mailerlite_http_post(string $url, array $headers, string $body): array` — pravi curl poziv (timeout 8 s)
  - `notify_build(array $lead, array $cfg, int $now): array{to:string, subject:string, subject_plain:string, body:string, headers:string}`
  - `notify_send(array $lead, array $cfg, int $now, ?callable $mailer = null): bool` — `$mailer(string $to, string $subject, string $body, string $headers): bool`
  - `header_safe(string $value): string`, `whatsapp_number(string $telefon): string`
  - Test helper `lead_fixture(): array` (normalizovan lead) u `tests/php/fixtures.php`

- [ ] **Step 1: Dodaj `lead_fixture()` na kraj `tests/php/fixtures.php`**

```php

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
```

- [ ] **Step 2: Napiši testove koji padaju**

`tests/php/mailerlite_test.php`:
```php
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
```

`tests/php/notify_test.php`:
```php
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
```

- [ ] **Step 3: Pokreni testove i proveri da padaju**

Run: `php tests/php/run.php mailerlite; php tests/php/run.php notify`
Expected: FAIL sa `Failed opening required`.

- [ ] **Step 4: Implementiraj `api/lib/mailerlite.php`**

```php
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
```

- [ ] **Step 5: Implementiraj `api/lib/notify.php`**

```php
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
```

Napomena: `style=""` atributi su dozvoljeni ovde jer je ovo HTML email, a ne stranica sajta (CSP se ne primenjuje).

- [ ] **Step 6: Pokreni testove i proveri da prolaze**

Run: `npm run test:php`
Expected: svi testovi prolaze.

- [ ] **Step 7: Commit**

```bash
git add api/lib/mailerlite.php api/lib/notify.php tests/php/mailerlite_test.php tests/php/notify_test.php tests/php/fixtures.php
git commit -m "MailerLite klijent i email obaveštenje Nikoli"
```

### Task 5: Handler zahteva, `submit.php` i konfiguracija

**Files:**
- Create: `api/lib/handler.php`, `api/submit.php`, `api/config.example.php`
- Test: `tests/php/handler_test.php`

**Interfaces:**
- Consumes: `quiz_options()` (T1), `validate_lead()` (T2), `rate_limit_exceeded()`, `lead_log_append()` (T3), `mailerlite_payload()`, `mailerlite_send()`, `notify_build()`, `notify_send()` (T4), `lead_input_fixture()`, `config_fixture()`, `tmp_dir()` (testovi)
- Produces:
  - `handle_submit(array $req, array $cfg, array $deps): array{status:int, body:array{ok:bool}}`
    - `$req`: `method` string, `content_type` string, `origin` string, `raw_body` string, `ip` string
    - `$deps`: `now` int, `options` array, `mailerlite` callable(array $lead): bool, `mail` callable(array $lead): bool
    - Statusi: 405 (ne-POST), 415 (ne-JSON), 413 (> 10240 bajtova), 403 (Origin), 200 tiho (honeypot / `elapsed_ms` < 5000 ili nedostaje), 429 (rate limit), 422 (validacija), 500 (i MailerLite i email pali), 200 (uspeh)
  - `dry_run_log(string $storageDir, string $channel, array $data): bool` — dodaje JSON red u `<storageDir>/dry-run.log`, vraća `true`
  - HTTP ugovor za frontend: `POST /api/submit.php`, telo iz Task 2, odgovor `{"ok": true|false}` sa gornjim statusima
  - Config ključevi (kompletan skup): `mailerlite_api_key`, `mailerlite_group_prijave`, `mailerlite_group_nurture`, `mail_to`, `mail_from_address`, `mail_from_name`, `allowed_origins` string[], `storage_dir` string, `dry_run` bool, `dry_run_fail_mailerlite` bool

- [ ] **Step 1: Napiši testove koji padaju — `tests/php/handler_test.php`**

```php
<?php
declare(strict_types=1);

foreach (['options', 'validate', 'ratelimit', 'leadlog', 'handler'] as $lib) {
    require_once __DIR__ . "/../../api/lib/{$lib}.php";
}
require_once __DIR__ . '/fixtures.php';

function handler_req(array $overrides = [], ?array $input = null): array
{
    return array_merge([
        'method' => 'POST',
        'content_type' => 'application/json',
        'origin' => 'https://example.com',
        'raw_body' => json_encode($input ?? lead_input_fixture()),
        'ip' => '203.0.113.7',
    ], $overrides);
}

function handler_env(bool $mlOk = true, bool $mailOk = true): array
{
    $calls = new ArrayObject(['mailerlite' => 0, 'mail' => 0]);
    $cfg = array_merge(config_fixture(), ['storage_dir' => tmp_dir()]);
    $deps = [
        'now' => 1_800_000_000,
        'options' => quiz_options(),
        'mailerlite' => function (array $lead) use ($calls, $mlOk): bool { $calls['mailerlite']++; return $mlOk; },
        'mail' => function (array $lead) use ($calls, $mailOk): bool { $calls['mail']++; return $mailOk; },
    ];
    return [$cfg, $deps, $calls];
}

test('ne-POST metoda → 405', function () {
    [$cfg, $deps] = handler_env();
    assert_same(['status' => 405, 'body' => ['ok' => false]], handle_submit(handler_req(['method' => 'GET']), $cfg, $deps));
});

test('ne-JSON content type → 415, JSON sa charset-om prolazi', function () {
    [$cfg, $deps] = handler_env();
    assert_same(415, handle_submit(handler_req(['content_type' => 'text/plain']), $cfg, $deps)['status']);
    assert_same(415, handle_submit(handler_req(['content_type' => '']), $cfg, $deps)['status']);
    assert_same(200, handle_submit(handler_req(['content_type' => 'application/json; charset=utf-8']), $cfg, $deps)['status']);
});

test('telo veće od 10240 bajtova → 413', function () {
    [$cfg, $deps] = handler_env();
    assert_same(413, handle_submit(handler_req(['raw_body' => str_repeat('a', 10241)]), $cfg, $deps)['status']);
});

test('pogrešan ili prazan Origin → 403', function () {
    [$cfg, $deps] = handler_env();
    assert_same(403, handle_submit(handler_req(['origin' => 'https://evil.example']), $cfg, $deps)['status']);
    assert_same(403, handle_submit(handler_req(['origin' => '']), $cfg, $deps)['status']);
});

test('popunjen honeypot → tihi 200 bez slanja', function () {
    [$cfg, $deps, $calls] = handler_env();
    $input = lead_input_fixture();
    $input['website'] = 'http://spam.example';
    assert_same(['status' => 200, 'body' => ['ok' => true]], handle_submit(handler_req([], $input), $cfg, $deps));
    assert_same(0, $calls['mailerlite'] + $calls['mail']);
});

test('prebrzo ili bez elapsed_ms → tihi 200 bez slanja', function () {
    [$cfg, $deps, $calls] = handler_env();
    $input = lead_input_fixture();
    $input['elapsed_ms'] = 4999;
    assert_same(200, handle_submit(handler_req([], $input), $cfg, $deps)['status']);
    unset($input['elapsed_ms']);
    assert_same(200, handle_submit(handler_req([], $input), $cfg, $deps)['status']);
    assert_same(0, $calls['mailerlite'] + $calls['mail']);
});

test('nevalidan unos ili neispravan JSON → 422 bez slanja', function () {
    [$cfg, $deps, $calls] = handler_env();
    $input = lead_input_fixture();
    $input['contact']['ime'] = '<script>alert(1)</script>';
    assert_same(422, handle_submit(handler_req([], $input), $cfg, $deps)['status']);
    assert_same(422, handle_submit(handler_req(['raw_body' => '{nije json']), $cfg, $deps)['status']);
    assert_same(0, $calls['mailerlite'] + $calls['mail']);
});

test('uspeh: 200, MailerLite i email pozvani, nema loga', function () {
    [$cfg, $deps, $calls] = handler_env();
    assert_same(['status' => 200, 'body' => ['ok' => true]], handle_submit(handler_req(), $cfg, $deps));
    assert_same(1, $calls['mailerlite']);
    assert_same(1, $calls['mail']);
    assert_true(!is_file($cfg['storage_dir'] . '/leads.log'));
});

test('MailerLite pao, email prošao: 200 i upis u log', function () {
    [$cfg, $deps, $calls] = handler_env(false, true);
    assert_same(200, handle_submit(handler_req(), $cfg, $deps)['status']);
    assert_same(1, $calls['mail']);
    $row = json_decode(trim(file_get_contents($cfg['storage_dir'] . '/leads.log')), true);
    assert_same('milica@example.com', $row['lead']['email']);
});

test('MailerLite prošao, email pao: 200 bez loga', function () {
    [$cfg, $deps] = handler_env(true, false);
    assert_same(200, handle_submit(handler_req(), $cfg, $deps)['status']);
    assert_true(!is_file($cfg['storage_dir'] . '/leads.log'));
});

test('oba pala: 500 i upis u log', function () {
    [$cfg, $deps] = handler_env(false, false);
    assert_same(['status' => 500, 'body' => ['ok' => false]], handle_submit(handler_req(), $cfg, $deps));
    assert_true(is_file($cfg['storage_dir'] . '/leads.log'));
});

test('šesti zahtev sa iste IP adrese → 429', function () {
    [$cfg, $deps] = handler_env();
    for ($i = 0; $i < 5; $i++) {
        assert_same(200, handle_submit(handler_req(), $cfg, $deps)['status']);
    }
    assert_same(429, handle_submit(handler_req(), $cfg, $deps)['status']);
});

test('honeypot zahtevi se ne broje u rate limit', function () {
    [$cfg, $deps] = handler_env();
    $bot = lead_input_fixture();
    $bot['website'] = 'x';
    for ($i = 0; $i < 10; $i++) {
        handle_submit(handler_req([], $bot), $cfg, $deps);
    }
    assert_same(200, handle_submit(handler_req(), $cfg, $deps)['status']);
    assert_same(true, handle_submit(handler_req(), $cfg, $deps)['body']['ok']);
});

test('dry_run_log upisuje kanal i podatke', function () {
    $dir = tmp_dir();
    assert_true(dry_run_log($dir, 'mail', ['subject' => 'Đurđa']));
    $row = json_decode(trim(file_get_contents($dir . '/dry-run.log')), true);
    assert_same('mail', $row['channel']);
    assert_same(['subject' => 'Đurđa'], $row['data']);
});
```

- [ ] **Step 2: Pokreni testove i proveri da padaju**

Run: `php tests/php/run.php handler`
Expected: FAIL sa `Failed opening required ... handler.php`.

- [ ] **Step 3: Implementiraj `api/lib/handler.php`**

```php
<?php
declare(strict_types=1);

const MAX_BODY_BYTES = 10240;
const MIN_ELAPSED_MS = 5000;

function handle_submit(array $req, array $cfg, array $deps): array
{
    $respond = fn(int $status, bool $ok): array => ['status' => $status, 'body' => ['ok' => $ok]];

    if ($req['method'] !== 'POST') {
        return $respond(405, false);
    }
    if (!str_starts_with(strtolower(trim($req['content_type'])), 'application/json')) {
        return $respond(415, false);
    }
    if (strlen($req['raw_body']) > MAX_BODY_BYTES) {
        return $respond(413, false);
    }
    if ($req['origin'] === '' || !in_array($req['origin'], $cfg['allowed_origins'], true)) {
        return $respond(403, false);
    }

    $input = json_decode($req['raw_body'], true);
    if (is_array($input) && looks_like_bot($input)) {
        return $respond(200, true);
    }

    if (rate_limit_exceeded($cfg['storage_dir'] . '/ratelimit', $req['ip'], $deps['now'])) {
        return $respond(429, false);
    }

    $result = validate_lead($input, $deps['options']);
    if (!$result['ok']) {
        return $respond(422, false);
    }
    $lead = $result['lead'];

    $mailerliteOk = $deps['mailerlite']($lead);
    $mailOk = $deps['mail']($lead);

    if (!$mailerliteOk) {
        lead_log_append($cfg['storage_dir'] . '/leads.log', $lead, $deps['now']);
    }
    if (!$mailerliteOk && !$mailOk) {
        return $respond(500, false);
    }
    return $respond(200, true);
}

function looks_like_bot(array $input): bool
{
    $honeypot = $input['website'] ?? '';
    if (!is_string($honeypot) || $honeypot !== '') {
        return true;
    }
    $elapsed = $input['elapsed_ms'] ?? null;
    return !is_int($elapsed) || $elapsed < MIN_ELAPSED_MS;
}

function dry_run_log(string $storageDir, string $channel, array $data): bool
{
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0700, true);
    }
    $line = json_encode(['ts' => time(), 'channel' => $channel, 'data' => $data], JSON_UNESCAPED_UNICODE);
    file_put_contents($storageDir . '/dry-run.log', $line . "\n", FILE_APPEND | LOCK_EX);
    return true;
}
```

- [ ] **Step 4: Pokreni testove i proveri da prolaze**

Run: `npm run test:php`
Expected: svi testovi prolaze.

- [ ] **Step 5: Napravi `api/config.example.php`**

```php
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
```

- [ ] **Step 6: Implementiraj `api/submit.php`**

```php
<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Belgrade');
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

foreach (['options', 'validate', 'ratelimit', 'leadlog', 'mailerlite', 'notify', 'handler'] as $lib) {
    require __DIR__ . "/lib/{$lib}.php";
}

$configPath = getenv('LEAD_CONFIG') ?: dirname(__DIR__, 2) . '/config.php';
if (!is_file($configPath)) {
    error_log('submit.php: config nije pronađen na ' . $configPath);
    http_response_code(500);
    echo json_encode(['ok' => false]);
    exit;
}
$cfg = require $configPath;
$options = quiz_options();

if ($cfg['dry_run']) {
    $sendToMailerlite = fn(array $lead): bool =>
        dry_run_log($cfg['storage_dir'], 'mailerlite', mailerlite_payload($lead, $cfg, $options['nurture_answer']))
        && !$cfg['dry_run_fail_mailerlite'];
    $sendMail = fn(array $lead): bool =>
        dry_run_log($cfg['storage_dir'], 'mail', notify_build($lead, $cfg, time()));
} else {
    $sendToMailerlite = fn(array $lead): bool =>
        mailerlite_send(mailerlite_payload($lead, $cfg, $options['nurture_answer']), $cfg['mailerlite_api_key']);
    $sendMail = fn(array $lead): bool => notify_send($lead, $cfg, time());
}

$rawBody = file_get_contents('php://input', false, null, 0, MAX_BODY_BYTES + 1);

$result = handle_submit(
    [
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? '',
        'raw_body' => $rawBody === false ? '' : $rawBody,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
    ],
    $cfg,
    [
        'now' => time(),
        'options' => $options,
        'mailerlite' => $sendToMailerlite,
        'mail' => $sendMail,
    ]
);

http_response_code($result['status']);
echo json_encode($result['body']);
```

- [ ] **Step 7: Proveri sintaksu i ceo PHP paket**

Run: `php -l api/submit.php && php -l api/config.example.php && npm run test:php`
Expected: `No syntax errors detected` za oba fajla; svi testovi prolaze.

- [ ] **Step 8: Commit**

```bash
git add api/lib/handler.php api/submit.php api/config.example.php tests/php/handler_test.php
git commit -m "Handler prijave, submit endpoint i šablon konfiguracije"
```

### Task 6: Lokalni dev server i bezbednosni curl testovi

**Files:**
- Create: `scripts/dev-router.php`
- Create: `tests/security.sh`
- Create (lokalno, gitignored): `dev/config.php`

**Interfaces:**
- Consumes: `api/submit.php`, `api/config.example.php` (Task 5); `npm run dev` (Task 1)
- Produces:
  - Dev server na `http://localhost:8000` sa istim čistim URL-ovima, blokadama i sigurnosnim headerima kao `.htaccess` (Task 14): `/` → `index.html`, `/hvala`, `/politika-privatnosti`, `/uslovi-koriscenja`; `*.html` → 301 na čist URL
  - `bash tests/security.sh` — izlaz 0 kad sve provere prođu; env: `BASE_URL` (podrazumevano `http://localhost:8000`), `STORAGE_DIR` (podrazumevano `dev/storage`)
  - Konstanta CSP stringa (identična u `dev-router.php` i `.htaccess`)

- [ ] **Step 1: Napravi `dev/config.php`**

```bash
mkdir -p dev/storage
```

`dev/config.php`:
```php
<?php
return [
    'mailerlite_api_key' => '',
    'mailerlite_group_prijave' => 'dev-prijave',
    'mailerlite_group_nurture' => 'dev-nurture',
    'mail_to' => 'nikola@example.com',
    'mail_from_address' => 'prijave@example.com',
    'mail_from_name' => 'Sajt Nikola Dišić',
    'allowed_origins' => ['http://localhost:8000'],
    'storage_dir' => __DIR__ . '/storage',
    'dry_run' => true,
    'dry_run_fail_mailerlite' => false,
];
```

- [ ] **Step 2: Napiši bezbednosni test `tests/security.sh` (pada jer server još ne postoji)**

```bash
#!/usr/bin/env bash
# Pokreće se protiv lokalnog dev servera: `npm run dev` u drugom terminalu.
set -uo pipefail

BASE_URL="${BASE_URL:-http://localhost:8000}"
STORAGE_DIR="${STORAGE_DIR:-dev/storage}"
ENDPOINT="$BASE_URL/api/submit.php"
FAILED=0

rm -rf "$STORAGE_DIR/ratelimit" "$STORAGE_DIR/dry-run.log"

check() {
  local name="$1" expected="$2" actual="$3"
  if [[ "$expected" == "$actual" ]]; then
    echo "  ✓ $name ($actual)"
  else
    echo "  ✗ $name: očekivano $expected, dobijeno $actual"
    FAILED=1
  fi
}

post() {
  local body="$1" type="${2:-application/json}" origin="${3:-$BASE_URL}"
  curl -s -o /dev/null -w '%{http_code}' -X POST "$ENDPOINT" \
    -H "Content-Type: $type" -H "Origin: $origin" --data "$body"
}

status_of() {
  curl -s -o /dev/null -w '%{http_code}' "$BASE_URL$1"
}

VALID='{"answers":{"cilj":"Izgubiti kilograme i dodati mišićnu masu","koliko_dugo":"1–3 godine","probala":["Dijete"],"prepreke":["Nemam plan"],"vaznost":9,"spremnost":"Da, spremna sam"},"contact":{"ime":"Milica","email":"milica@example.com","telefon":"064 123 4567","vreme_poziva":"Popodne","kontakt_kanal":"WhatsApp"},"saglasnost":true,"utm":{"utm_source":"ig"},"website":"","elapsed_ms":42000}'
LONG_NAME=$(printf 'a%.0s' {1..61})

echo "Endpoint"
check "GET je odbijen" 405 "$(curl -s -o /dev/null -w '%{http_code}' "$ENDPOINT")"
check "text/plain je odbijen" 415 "$(post "$VALID" text/plain)"
check "tuđi Origin je odbijen" 403 "$(post "$VALID" application/json https://evil.example)"
check "prevelik body je odbijen" 413 "$(post "$(printf 'a%.0s' {1..10300})")"
check "honeypot tiho prihvaćen" 200 "$(post "${VALID/\"website\":\"\"/\"website\":\"http://spam\"}")"
check "<script> u imenu je odbijen" 422 "$(post "${VALID/\"ime\":\"Milica\"/\"ime\":\"<script>alert(1)</script>\"}")"
check "predugačko ime je odbijeno" 422 "$(post "${VALID/\"ime\":\"Milica\"/\"ime\":\"$LONG_NAME\"}")"
check "nepostojeći odgovor je odbijen" 422 "$(post "${VALID/\"Dijete\"/\"Nešto treće\"}")"
check "validna prijava prolazi" 200 "$(post "$VALID")"
check "druga validna prijava prolazi" 200 "$(post "$VALID")"
check "šesti zahtev je rate-limitovan" 429 "$(post "$VALID")"

if grep -q 'http://spam' "$STORAGE_DIR/dry-run.log" 2>/dev/null; then
  echo "  ✗ honeypot prijava je poslata"; FAILED=1
else
  echo "  ✓ honeypot prijava nije poslata"
fi
check "dry-run log ima 4 reda (2 prijave × MailerLite + email)" 4 "$(wc -l < "$STORAGE_DIR/dry-run.log" | tr -d ' ')"

echo "Blokirani fajlovi"
for path in /api/lib/handler.php /api/config.example.php /docs/ /tests/security.sh /scripts/dev-router.php /package.json /README.md /.git/config /.htaccess; do
  check "$path" 403 "$(status_of "$path")"
done

echo "Headeri"
HEADERS=$(curl -s -D - -o /dev/null "$BASE_URL/")
for header in "Content-Security-Policy: default-src 'self'" "X-Content-Type-Options: nosniff" "Referrer-Policy: strict-origin-when-cross-origin"; do
  if grep -qi "$header" <<<"$HEADERS"; then echo "  ✓ $header"; else echo "  ✗ nedostaje: $header"; FAILED=1; fi
done

echo "Čisti URL-ovi"
check "/hvala.html → 301" 301 "$(status_of /hvala.html)"

rm -rf "$STORAGE_DIR/ratelimit"
exit $FAILED
```

- [ ] **Step 3: Pokreni i proveri da pada**

Run: `bash tests/security.sh`
Expected: FAIL, sve provere vraćaju `000` (server ne radi).

- [ ] **Step 4: Implementiraj `scripts/dev-router.php`**

```php
<?php
// Samo za lokalni razvoj (npm run dev). Na produkciji isto radi .htaccess.
declare(strict_types=1);

$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

const CSP = "default-src 'self'; script-src 'self' https://connect.facebook.net; "
    . "img-src 'self' data: https://www.facebook.com; "
    . "connect-src 'self' https://www.facebook.com https://connect.facebook.net; "
    . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'";

header('Content-Security-Policy: ' . CSP);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

$blocked = '#^/(\.git|\.htaccess|\.gitignore|docs|tests|scripts|dev|storage|api/lib|api/config\.example\.php|package\.json|README\.md)(/|$)#';
if (preg_match($blocked, $path)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

$pages = [
    '/' => 'index.html',
    '/hvala' => 'hvala.html',
    '/politika-privatnosti' => 'politika-privatnosti.html',
    '/uslovi-koriscenja' => 'uslovi-koriscenja.html',
];

if (isset($pages[$path])) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($root . '/' . $pages[$path]);
    return true;
}

if (preg_match('#^/(index|hvala|politika-privatnosti|uslovi-koriscenja)\.html$#', $path, $m)) {
    $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    $target = $m[1] === 'index' ? '/' : '/' . $m[1];
    header('Location: ' . $target . ($query ? '?' . $query : ''), true, 301);
    return true;
}

if ($path === '/api/submit.php') {
    require $root . '/api/submit.php';
    return true;
}

if (str_ends_with($path, '.php')) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

return false;
```

- [ ] **Step 5: Privremeni `index.html` da bi `/` vratio 200**

`index.html` (zamenjuje se u Task 7):
```html
<!doctype html>
<html lang="sr-Latn"><head><meta charset="utf-8"><title>Nikola Dišić</title></head><body></body></html>
```

- [ ] **Step 6: Pokreni server i testove**

Terminal 1: `npm run dev`
Terminal 2: `bash tests/security.sh`
Expected: sve provere ✓, izlaz 0. Proveri i `cat dev/storage/dry-run.log`: redovi sa `"channel":"mailerlite"` i `"channel":"mail"`, sa „Milica“ čitljivo (UTF-8).

- [ ] **Step 7: Proveri fallback kad MailerLite padne**

U `dev/config.php` postavi `'dry_run_fail_mailerlite' => true`, restartuj `npm run dev`, pa:
```bash
rm -rf dev/storage/ratelimit dev/storage/leads.log
curl -s -X POST http://localhost:8000/api/submit.php -H 'Content-Type: application/json' -H 'Origin: http://localhost:8000' --data "$(grep -o "VALID='.*'" tests/security.sh | sed "s/^VALID='//; s/'$//")"
cat dev/storage/leads.log
```
Expected: odgovor `{"ok":true}`; `leads.log` ima jedan red sa `"email":"milica@example.com"`. Vrati `'dry_run_fail_mailerlite' => false`.

- [ ] **Step 8: Commit**

```bash
git add scripts/dev-router.php tests/security.sh index.html
git commit -m "Lokalni dev router i bezbednosni curl testovi"
```

### Task 7: Landing stranica — HTML i CSS (bez JavaScript-a)

**Files:**
- Modify (zamena celog fajla): `index.html`
- Create: `assets/css/style.css`
- Test: `tests/js/html.test.js`

**Interfaces:**
- Consumes: dev server (Task 6)
- Produces:
  - Svaki CTA je `<button type="button" class="btn btn--primary" data-open-quiz>Prijavi se za saradnju</button>`; ukupno 7 na landingu (6 u sekcijama + sticky)
  - Sticky CTA: `<div class="sticky-cta" data-sticky-cta>`; vidljiv kad ima klasu `is-visible` (dodaje je JS u Task 9)
  - CSS klase koje koriste kasniji taskovi: `.btn`, `.btn--primary`, `.btn--ghost`, `.container`, `.section`, `.section--light`, `.eyebrow`, `.lead`, `.ph` (placeholder slike), `.footer`, `.visually-hidden`
  - CSS tokeni: `--bg`, `--surface`, `--text`, `--muted`, `--accent`, `--accent-text`, `--light-bg`, `--light-text`, `--radius`, `--space`
  - `tests/js/html.test.js` sa funkcijom `pageInvariants(file)` koja proverava svaku postojeću stranicu

- [ ] **Step 1: Napiši test koji pada — `tests/js/html.test.js`**

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';

const root = new URL('../../', import.meta.url);
const read = (file) => readFileSync(new URL(file, root), 'utf8');
const PAGES = ['index.html', 'hvala.html', 'politika-privatnosti.html', 'uslovi-koriscenja.html'];

for (const file of PAGES.filter((f) => existsSync(new URL(f, root)))) {
  test(`${file}: CSP-bezbedan HTML`, () => {
    const html = read(file);
    assert.doesNotMatch(html, /<script(?![^>]*\bsrc=)[^>]*>/i, 'inline <script> nije dozvoljen');
    assert.doesNotMatch(html, /\sstyle\s*=/i, 'style="" atribut nije dozvoljen');
    assert.doesNotMatch(html, /\son[a-z]+\s*=/i, 'inline event handler nije dozvoljen');
    assert.doesNotMatch(html, /<style[\s>]/i, '<style> blok nije dozvoljen');
  });

  test(`${file}: osnovna struktura`, () => {
    const html = read(file);
    assert.match(html, /<html lang="sr-Latn">/);
    assert.match(html, /<meta name="viewport" content="width=device-width, initial-scale=1">/);
    assert.equal((html.match(/<h1[\s>]/g) || []).length, 1, 'tačno jedan <h1>');
    assert.match(html, /href="\/politika-privatnosti"/);
    assert.match(html, /href="\/uslovi-koriscenja"/);
    assert.doesNotMatch(html, /href="[^"]*\.html"/, 'linkovi bez .html');
  });
}

test('index.html: 7 CTA dugmadi za kviz sa istim tekstom', () => {
  const html = read('index.html');
  const ctas = html.match(/<button[^>]*data-open-quiz[^>]*>[^<]*<\/button>/g) || [];
  assert.equal(ctas.length, 7);
  for (const cta of ctas) assert.match(cta, />Prijavi se za saradnju</);
});

test('index.html: sve sekcije iz spec-a postoje redom', () => {
  const html = read('index.html');
  const ids = [...html.matchAll(/<section[^>]*id="([^"]+)"/g)].map((m) => m[1]);
  assert.deepEqual(ids, ['hero', 'rezultati', 'poznato', 'za-koga', 'kako', 'dobijas', 'garancija', 'o-nikoli', 'iskustva', 'pitanja', 'kraj']);
});

test('index.html: obećanje je samo 6 nedelja', () => {
  const html = read('index.html');
  assert.doesNotMatch(html, /nekoliko meseci/i);
  assert.match(html, /6 nedelja do rezultata/);
});
```

- [ ] **Step 2: Pokreni i proveri da pada**

Run: `npm run test:js`
Expected: FAIL — privremeni `index.html` nema viewport, `<h1>`, CTA dugmad ni sekcije.

- [ ] **Step 3: Napiši `index.html`**

Sadržaj koji zavisi od klijenta je označen `{{KLIJENT: …}}`. Tekstovi su polazna verzija koju klijent odobrava.

```html
<!doctype html>
<html lang="sr-Latn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>6 nedelja do rezultata | Nikola Dišić – online trener</title>
  <meta name="description" content="Program za žene koje su probale sve. Personalizovan trening i ishrana bez gladovanja, uz garanciju rezultata. Prijavi se za besplatnu konsultaciju.">
  <meta property="og:title" content="6 nedelja do rezultata | Nikola Dišić">
  <meta property="og:description" content="Program za žene koje su probale sve. Bez gladovanja i bez univerzalnih planova.">
  <meta property="og:image" content="/assets/img/og.jpg">
  <link rel="icon" href="/assets/img/favicon.png">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <main>
    <section class="section hero" id="hero">
      <div class="container hero__grid">
        <div class="hero__copy">
          <p class="eyebrow">Online coaching za žene</p>
          <h1>6 nedelja do rezultata</h1>
          <p class="lead">Program za žene koje su probale sve i ništa nije radilo. Ovaj put bez gladovanja i bez univerzalnih planova treninga i ishrane.</p>
          <ul class="checks">
            <li>Personalizovan plan treninga i ishrane</li>
            <li>Garantovani rezultati ili ti vraćam novac</li>
            <li>Puna podrška tokom celog programa</li>
          </ul>
          <button type="button" class="btn btn--primary" data-open-quiz>Prijavi se za saradnju</button>
          <p class="micro">Traje 60 sekundi · Nikola te lično zove</p>
        </div>
        <div class="hero__media ph ph--portrait" role="img" aria-label="Nikola Dišić">{{KLIJENT: Nikolina fotografija, vertikalna, WebP}}</div>
      </div>
    </section>

    <section class="section" id="rezultati">
      <div class="container">
        <p class="eyebrow">Rezultati klijentkinja</p>
        <h2>Prave klijentkinje, pravi rezultati</h2>
        <div class="results" tabindex="0" aria-label="Transformacije klijentkinja">
          <figure class="result">
            <div class="ph ph--square" role="img" aria-label="Nevena pre i posle">{{KLIJENT: pre/posle – Nevena}}</div>
            <figcaption>
              <strong>Nevena</strong>
              <span class="result__stat">{{KLIJENT: brojka i trajanje, npr. −7 kg za 6 nedelja}}</span>
              <span>Rešena insulinska rezistencija, izgradnja mišića</span>
            </figcaption>
          </figure>
          <figure class="result">
            <div class="ph ph--square" role="img" aria-label="Milica pre i posle">{{KLIJENT: pre/posle – Milica}}</div>
            <figcaption>
              <strong>Milica</strong>
              <span class="result__stat">{{KLIJENT: brojka i trajanje}}</span>
              <span>Rešena insulinska rezistencija, ravan i zategnut struk</span>
            </figcaption>
          </figure>
          <figure class="result">
            <div class="ph ph--square" role="img" aria-label="Emilija pre i posle">{{KLIJENT: pre/posle – Emilija}}</div>
            <figcaption>
              <strong>Emilija</strong>
              <span class="result__stat">{{KLIJENT: brojka i trajanje}}</span>
              <span>Gubitak masti, rekompozicija tela, zdrave navike</span>
            </figcaption>
          </figure>
        </div>
        <button type="button" class="btn btn--primary" data-open-quiz>Prijavi se za saradnju</button>
      </div>
    </section>

    <section class="section section--light" id="poznato">
      <div class="container container--narrow">
        <h2>Da li ti je ovo poznato?</h2>
        <ul class="pains">
          <li>Probala si dijete, izgubila kilograme i sve se vratilo.</li>
          <li>Treniraš, a u ogledalu ne vidiš promenu.</li>
          <li>Insulinska rezistencija ili hormoni ti otežavaju svaki pokušaj.</li>
          <li>Ne znaš šta tačno da jedeš, pa svaki dan improvizuješ.</li>
          <li>Krećeš motivisano, a posle dve nedelje odustaneš.</li>
        </ul>
        <p class="lead">Nije problem u tebi. Problem je u planovima koji nisu napravljeni za tebe.</p>
      </div>
    </section>

    <section class="section" id="za-koga">
      <div class="container">
        <h2>Da li je program za tebe?</h2>
        <div class="fit">
          <div class="fit__col fit__col--yes">
            <h3>Za tebe je ako</h3>
            <ul>
              <li>Želiš da smršaš, oblikuješ telo ili rešiš insulinsku rezistenciju</li>
              <li>Spremna si da 6 nedelja pratiš plan</li>
              <li>Želiš plan prilagođen tvom telu, vremenu i navikama</li>
              <li>Želiš nekoga ko te vodi i drži odgovornom</li>
            </ul>
          </div>
          <div class="fit__col fit__col--no">
            <h3>Nije za tebe ako</h3>
            <ul>
              <li>Tražiš čudotvornu tabletu ili rezultat bez truda</li>
              <li>Nisi spremna da menjaš ishranu</li>
              <li>Želiš samo gotov PDF plan bez praćenja</li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <section class="section section--light" id="kako">
      <div class="container">
        <h2>Kako funkcioniše</h2>
        <ol class="steps">
          <li><span class="steps__num">1</span><h3>Popuniš prijavu</h3><p>Odgovoriš na nekoliko pitanja o cilju i dosadašnjem iskustvu. Traje 60 sekundi.</p></li>
          <li><span class="steps__num">2</span><h3>Nikola te zove</h3><p>Besplatna konsultacija od ~15 minuta. Zajedno vidite da li je program za tebe.</p></li>
          <li><span class="steps__num">3</span><h3>Krećeš sa planom</h3><p>Dobijaš personalizovan plan treninga i ishrane i krećeš uz punu podršku.</p></li>
        </ol>
        <button type="button" class="btn btn--primary" data-open-quiz>Prijavi se za saradnju</button>
      </div>
    </section>

    <section class="section" id="dobijas">
      <div class="container">
        <h2>Šta dobijaš</h2>
        <div class="cards">
          <article class="card"><h3>Personalizovan trening</h3><p>Plan prilagođen tvom nivou, vremenu i opremi, u teretani ili kod kuće.</p></article>
          <article class="card"><h3>Ishrana bez gladovanja</h3><p>Jelovnik prilagođen tvojim navikama i zdravstvenom stanju, bez izbacivanja svega što voliš.</p></article>
          <article class="card"><h3>Podrška</h3><p>{{KLIJENT: kanal i učestalost podrške, npr. WhatsApp svakog dana + nedeljni check-in}}</p></article>
          <article class="card"><h3>Praćenje napretka</h3><p>Redovno merenje i korekcija plana, da bi rezultat bio stalan.</p></article>
        </div>
      </div>
    </section>

    <section class="section section--light" id="garancija">
      <div class="container container--narrow guarantee">
        <p class="eyebrow">Bez rizika</p>
        <h2>Garancija rezultata</h2>
        <p class="lead">Ako ispoštuješ plan, a ne dobiješ rezultat, vraćam ti novac.</p>
        <ul class="checks">
          <li>{{KLIJENT: uslov garancije 1}}</li>
          <li>{{KLIJENT: uslov garancije 2}}</li>
          <li>{{KLIJENT: rok za zahtev}}</li>
        </ul>
        <p class="micro">Pun tekst garancije je u <a href="/uslovi-koriscenja#garancija">Uslovima korišćenja</a>.</p>
        <button type="button" class="btn btn--primary" data-open-quiz>Prijavi se za saradnju</button>
      </div>
    </section>

    <section class="section" id="o-nikoli">
      <div class="container about">
        <div class="ph ph--portrait" role="img" aria-label="Nikola Dišić na treningu">{{KLIJENT: druga Nikolina fotografija}}</div>
        <div>
          <p class="eyebrow">O meni</p>
          <h2>Ja sam Nikola Dišić</h2>
          <p>{{KLIJENT: Nikolina priča, 3–4 rečenice}}</p>
          <ul class="stats">
            <li><strong>{{KLIJENT: broj}}</strong><span>klijentkinja</span></li>
            <li><strong>{{KLIJENT: broj}}</strong><span>godina iskustva</span></li>
            <li><strong>{{KLIJENT: sertifikat}}</strong><span>sertifikovan trener</span></li>
          </ul>
        </div>
      </div>
    </section>

    <section class="section section--light" id="iskustva">
      <div class="container">
        <h2>Šta kažu klijentkinje</h2>
        <div class="cards">
          <blockquote class="card quote"><p>{{KLIJENT: citat 1}}</p><footer>{{KLIJENT: ime 1}}</footer></blockquote>
          <blockquote class="card quote"><p>{{KLIJENT: citat 2}}</p><footer>{{KLIJENT: ime 2}}</footer></blockquote>
          <blockquote class="card quote"><p>{{KLIJENT: citat 3}}</p><footer>{{KLIJENT: ime 3}}</footer></blockquote>
        </div>
        <button type="button" class="btn btn--primary" data-open-quiz>Prijavi se za saradnju</button>
      </div>
    </section>

    <section class="section" id="pitanja">
      <div class="container container--narrow">
        <h2>Česta pitanja</h2>
        <div class="faq-text">
          <details><summary>Da li moram da idem u teretanu?</summary><p>Ne. Plan se pravi prema tome gde treniraš, u teretani ili kod kuće.</p></details>
          <details><summary>Imam insulinsku rezistenciju. Da li je program za mene?</summary><p>Da. Veliki deo klijentkinja je krenuo upravo sa insulinskom rezistencijom. Ishrana i trening se prilagođavaju tvom stanju, a preporučujemo i praćenje lekara.</p></details>
          <details><summary>Koliko vremena dnevno mi treba?</summary><p>{{KLIJENT: tipično trajanje treninga i broj treninga nedeljno}}</p></details>
          <details><summary>Da li moram da gladujem ili merim hranu?</summary><p>Ne gladuješ. {{KLIJENT: da li se hrana meri i kako izgleda jelovnik}}</p></details>
          <details><summary>Šta se dešava na besplatnom pozivu?</summary><p>Nikola te pita o cilju i dosadašnjem iskustvu i objašnjava kako bi izgledala saradnja. Nema obaveze da kreneš.</p></details>
        </div>
      </div>
    </section>

    <section class="section section--light" id="kraj">
      <div class="container container--narrow final">
        <h2>Tvojih 6 nedelja može da počne ove nedelje</h2>
        <p class="lead">Popuni kratku prijavu, a Nikola te zove i zajedno pravite plan.</p>
        <button type="button" class="btn btn--primary" data-open-quiz>Prijavi se za saradnju</button>
      </div>
    </section>
  </main>

  <footer class="footer">
    <div class="container footer__inner">
      <ul class="footer__social">
        <li><a href="{{KLIJENT: Instagram URL}}" rel="noopener" target="_blank">Instagram</a></li>
        <li><a href="{{KLIJENT: TikTok URL}}" rel="noopener" target="_blank">TikTok</a></li>
        <li><a href="{{KLIJENT: YouTube URL}}" rel="noopener" target="_blank">YouTube</a></li>
      </ul>
      <ul class="footer__legal">
        <li><a href="/politika-privatnosti">Politika privatnosti</a></li>
        <li><a href="/uslovi-koriscenja">Uslovi korišćenja</a></li>
      </ul>
      <p>© 2026 Nikola Dišić. Sva prava zadržana.</p>
    </div>
  </footer>

  <div class="sticky-cta" data-sticky-cta>
    <button type="button" class="btn btn--primary" data-open-quiz>Prijavi se za saradnju</button>
  </div>
</body>
</html>
```

- [ ] **Step 4: Napiši `assets/css/style.css`**

Boje i font su polazni predlog (tamno-sportski, sistemski font). Kad stignu materijali klijenta, menjaju se samo tokeni u `:root`.

```css
:root {
  --bg: #0e0e0e;
  --surface: #1a1a1a;
  --text: #f5f3ef;
  --muted: #a8a8a8;
  --accent: #d4ff3a;
  --accent-text: #0e0e0e;
  --light-bg: #f5f3ef;
  --light-text: #141414;
  --light-muted: #5c5c5c;
  --danger: #ff6b6b;
  --radius: 16px;
  --space: clamp(56px, 9vw, 112px);
  --font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

*, *::before, *::after { box-sizing: border-box; }
html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
body { margin: 0; background: var(--bg); color: var(--text); font: 17px/1.6 var(--font); }
img, video { max-width: 100%; display: block; }
h1, h2, h3 { line-height: 1.1; margin: 0 0 .5em; letter-spacing: -.02em; }
h1 { font-size: clamp(2.6rem, 8vw, 4.8rem); font-weight: 900; }
h2 { font-size: clamp(1.9rem, 5vw, 3rem); font-weight: 800; }
h3 { font-size: 1.2rem; font-weight: 700; }
p { margin: 0 0 1em; }
a { color: inherit; }
button { font: inherit; }
:focus-visible { outline: 3px solid var(--accent); outline-offset: 3px; }

.visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
.container { width: min(1120px, 100% - 40px); margin-inline: auto; }
.container--narrow { width: min(760px, 100% - 40px); }
.section { padding-block: var(--space); }
.section--light { background: var(--light-bg); color: var(--light-text); }
.section--light .micro, .section--light .eyebrow { color: var(--light-muted); }
.eyebrow { text-transform: uppercase; letter-spacing: .12em; font-size: .8rem; font-weight: 700; color: var(--muted); margin-bottom: .75em; }
.lead { font-size: clamp(1.1rem, 2.4vw, 1.3rem); }
.micro { font-size: .9rem; color: var(--muted); margin-top: .75em; }

.btn { display: inline-flex; align-items: center; justify-content: center; gap: .5em; min-height: 56px; padding: 0 28px; border-radius: 999px; border: 2px solid transparent; font-weight: 800; font-size: 1.05rem; cursor: pointer; text-decoration: none; transition: transform .15s ease, background-color .15s ease; }
.btn:active { transform: scale(.98); }
.btn--primary { background: var(--accent); color: var(--accent-text); }
.btn--primary:hover { background: #e2ff75; }
.btn--ghost { background: transparent; color: inherit; border-color: currentColor; }
.btn[disabled] { opacity: .5; cursor: not-allowed; }
.section .btn--primary { margin-top: 32px; }

.checks { list-style: none; padding: 0; margin: 0 0 8px; }
.checks li { position: relative; padding-left: 32px; margin-bottom: 10px; }
.checks li::before { content: "✓"; position: absolute; left: 0; top: 0; width: 22px; height: 22px; border-radius: 50%; background: var(--accent); color: var(--accent-text); font-size: .8rem; font-weight: 900; display: grid; place-items: center; margin-top: 3px; }

.ph { display: grid; place-items: center; padding: 16px; text-align: center; font-size: .85rem; color: var(--muted); background: repeating-linear-gradient(45deg, #202020 0 12px, #262626 12px 24px); border-radius: var(--radius); }
.section--light .ph { background: repeating-linear-gradient(45deg, #e6e3dc 0 12px, #ece9e2 12px 24px); color: var(--light-muted); }
.ph--portrait { aspect-ratio: 4 / 5; }
.ph--square { aspect-ratio: 1; }

.hero { padding-top: 40px; }
.hero__grid { display: grid; gap: 32px; align-items: center; }
.hero__media { order: -1; max-height: 56vh; }

.results { display: grid; grid-auto-flow: column; grid-auto-columns: 78%; gap: 16px; overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: 8px; margin-top: 24px; }
.result { margin: 0; scroll-snap-align: start; background: var(--surface); border-radius: var(--radius); overflow: hidden; }
.result figcaption { display: grid; gap: 4px; padding: 16px 18px 20px; color: var(--muted); font-size: .95rem; }
.result strong { color: var(--text); font-size: 1.1rem; }
.result__stat { color: var(--accent); font-weight: 800; }

.pains { list-style: none; padding: 0; margin: 24px 0 32px; }
.pains li { padding: 16px 0 16px 36px; border-bottom: 1px solid #0000001a; position: relative; }
.pains li::before { content: "✕"; position: absolute; left: 0; font-weight: 900; color: var(--danger); }

.fit { display: grid; gap: 16px; margin-top: 24px; }
.fit__col { background: var(--surface); border-radius: var(--radius); padding: 24px; }
.fit__col ul { margin: 0; padding-left: 20px; }
.fit__col li { margin-bottom: 8px; }
.fit__col--yes { border-top: 4px solid var(--accent); }
.fit__col--no { border-top: 4px solid var(--danger); }

.steps { list-style: none; padding: 0; margin: 24px 0 0; display: grid; gap: 16px; counter-reset: none; }
.steps li { background: #fff; border-radius: var(--radius); padding: 24px; }
.steps__num { display: inline-grid; place-items: center; width: 40px; height: 40px; border-radius: 50%; background: var(--light-text); color: var(--light-bg); font-weight: 900; margin-bottom: 12px; }
.steps p { margin: 0; color: var(--light-muted); }

.cards { display: grid; gap: 16px; margin-top: 24px; }
.card { background: var(--surface); border-radius: var(--radius); padding: 24px; margin: 0; }
.card p { margin: 0; color: var(--muted); }
.section--light .card { background: #fff; }
.section--light .card p { color: var(--light-muted); }
.quote p { font-size: 1.05rem; color: inherit; margin-bottom: 12px; }
.quote footer { font-weight: 700; }

.guarantee { text-align: left; }
.about { display: grid; gap: 32px; align-items: center; }
.stats { list-style: none; padding: 0; margin: 24px 0 0; display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.stats li { display: grid; gap: 2px; }
.stats strong { font-size: 1.6rem; color: var(--accent); line-height: 1.1; }
.stats span { font-size: .85rem; color: var(--muted); }

.faq-text details { border-bottom: 1px solid #ffffff1f; }
.faq-text summary { cursor: pointer; list-style: none; padding: 20px 40px 20px 0; font-weight: 700; position: relative; }
.faq-text summary::-webkit-details-marker { display: none; }
.faq-text summary::after { content: "+"; position: absolute; right: 4px; top: 16px; font-size: 1.5rem; }
.faq-text details[open] summary::after { content: "−"; }
.faq-text details p { color: var(--muted); padding-bottom: 20px; margin: 0; }

.final { text-align: center; }

.footer { padding: 40px 0 120px; color: var(--muted); font-size: .9rem; }
.footer__inner { display: grid; gap: 16px; }
.footer ul { list-style: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap; gap: 8px 20px; }
.footer button { background: none; border: 0; color: inherit; padding: 0; text-decoration: underline; cursor: pointer; }
.footer p { margin: 0; }

.sticky-cta { position: fixed; inset: auto 0 0 0; z-index: 20; padding: 12px 20px calc(12px + env(safe-area-inset-bottom)); background: linear-gradient(to top, var(--bg) 60%, transparent); transform: translateY(120%); transition: transform .25s ease; }
.sticky-cta .btn { width: 100%; }
.sticky-cta.is-visible { transform: none; }

@media (min-width: 900px) {
  .hero { padding-top: 72px; }
  .hero__grid { grid-template-columns: 1.1fr .9fr; gap: 64px; }
  .hero__media { order: 0; max-height: none; }
  .results { grid-auto-flow: row; grid-template-columns: repeat(3, 1fr); overflow: visible; }
  .fit, .about { grid-template-columns: 1fr 1fr; }
  .steps, .cards { grid-template-columns: repeat(3, 1fr); }
  #dobijas .cards { grid-template-columns: repeat(4, 1fr); }
  .sticky-cta { display: none; }
  .footer { padding-bottom: 40px; }
}

@media (prefers-reduced-motion: reduce) {
  html { scroll-behavior: auto; }
  *, *::before, *::after { transition: none !important; animation: none !important; }
}
```

- [ ] **Step 5: Pokreni testove i proveri da prolaze**

Run: `npm run test:js`
Expected: svi testovi prolaze (`index.html` testovi + `quiz-json`).

- [ ] **Step 6: Vizuelna provera u browseru**

Run: `npm run dev`, otvori `http://localhost:8000` u širinama 375px i 1280px.
Expected: nema horizontalnog skrola na 375px; transformacije se skroluju horizontalno na mobilnom i stoje u 3 kolone na desktopu; svih 11 sekcija je vidljivo; u konzoli nema CSP grešaka.

- [ ] **Step 7: Commit**

```bash
git add index.html assets/css/style.css tests/js/html.test.js
git commit -m "Landing stranica: struktura, sadržaj i stil"
```

### Task 8: Čista logika kviza (`quiz-state.js`)

**Files:**
- Create: `assets/js/quiz-state.js`
- Test: `tests/js/quiz-state.test.js`

**Interfaces:**
- Consumes: oblik `quiz.json` (Task 1); oblik ulaznog JSON-a za endpoint (Task 2)
- Produces (ES module exports):
  - `UTM_KEYS: string[]`
  - `readUtm(search: string): Record<string,string>` — samo prisutni, neprazni UTM ključevi
  - `createState(): { step: number, answers: Record<string, string|string[]|number> }` → `{ step: 0, answers: {} }`
  - `setAnswer(state, question, value): state` — vraća novi objekat; `single`/`scale` postavlja vrednost; `multi` dodaje ili uklanja vrednost iz niza
  - `isAnswered(state, question): boolean`
  - `totalSteps(quiz): number` → `quiz.questions.length + 1`
  - `emptyContact(): { ime, email, telefon, vreme_poziva, kontakt_kanal, saglasnost }` → telefon `'+381 '`, `saglasnost: false`, ostalo `''`
  - `validateContact(contact): Record<string,string>` — samo ključevi sa greškom; poruke su u `CONTACT_ERRORS`
  - `CONTACT_ERRORS: Record<string,string>`
  - `buildPayload(state, contact, utm, elapsedMs, honeypot): object` — tačan oblik iz Task 2

- [ ] **Step 1: Napiši testove koji padaju — `tests/js/quiz-state.test.js`**

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import {
  readUtm, createState, setAnswer, isAnswered, totalSteps,
  emptyContact, validateContact, buildPayload, CONTACT_ERRORS,
} from '../../assets/js/quiz-state.js';

const quiz = JSON.parse(readFileSync(new URL('../../assets/data/quiz.json', import.meta.url), 'utf8'));
const q = (id) => quiz.questions.find((x) => x.id === id);

const validContact = () => ({
  ime: 'Milica', email: 'milica@example.com', telefon: '064 123 4567',
  vreme_poziva: 'Popodne', kontakt_kanal: 'WhatsApp', saglasnost: true,
});

test('readUtm čita samo UTM ključeve koji imaju vrednost', () => {
  assert.deepEqual(
    readUtm('?utm_source=ig&utm_medium=social&utm_content=link_in_bio&utm_id=97760&utm_campaign='),
    { utm_source: 'ig', utm_medium: 'social', utm_content: 'link_in_bio' },
  );
  assert.deepEqual(readUtm(''), {});
});

test('createState i totalSteps', () => {
  assert.deepEqual(createState(), { step: 0, answers: {} });
  assert.equal(totalSteps(quiz), 7);
});

test('setAnswer za single zamenjuje vrednost i ne menja original', () => {
  const s0 = createState();
  const s1 = setAnswer(s0, q('cilj'), 'Dodati kilograme i oblikovati telo');
  const s2 = setAnswer(s1, q('cilj'), 'Izgubiti kilograme i dodati mišićnu masu');
  assert.deepEqual(s0.answers, {});
  assert.equal(s2.answers.cilj, 'Izgubiti kilograme i dodati mišićnu masu');
});

test('setAnswer za multi dodaje i uklanja', () => {
  let s = createState();
  s = setAnswer(s, q('probala'), 'Dijete');
  s = setAnswer(s, q('probala'), 'Aplikacije');
  assert.deepEqual(s.answers.probala, ['Dijete', 'Aplikacije']);
  s = setAnswer(s, q('probala'), 'Dijete');
  assert.deepEqual(s.answers.probala, ['Aplikacije']);
});

test('setAnswer za scale čuva broj', () => {
  const s = setAnswer(createState(), q('vaznost'), 7);
  assert.equal(s.answers.vaznost, 7);
});

test('isAnswered', () => {
  let s = createState();
  assert.equal(isAnswered(s, q('cilj')), false);
  assert.equal(isAnswered(s, q('probala')), false);
  assert.equal(isAnswered(s, q('vaznost')), false);
  s = setAnswer(s, q('probala'), 'Dijete');
  assert.equal(isAnswered(s, q('probala')), true);
  s = setAnswer(s, q('probala'), 'Dijete');
  assert.equal(isAnswered(s, q('probala')), false, 'prazan niz nije odgovor');
  s = setAnswer(s, q('vaznost'), 1);
  assert.equal(isAnswered(s, q('vaznost')), true);
});

test('emptyContact', () => {
  assert.deepEqual(emptyContact(), {
    ime: '', email: '', telefon: '+381 ', vreme_poziva: '', kontakt_kanal: '', saglasnost: false,
  });
});

test('validateContact: validan kontakt nema grešaka', () => {
  assert.deepEqual(validateContact(validContact()), {});
  assert.deepEqual(validateContact({ ...validContact(), ime: 'Đurđa Čolić', telefon: '+381 64 123 4567' }), {});
});

test('validateContact: prazan kontakt ima sve greške', () => {
  assert.deepEqual(validateContact(emptyContact()), {
    ime: CONTACT_ERRORS.ime,
    email: CONTACT_ERRORS.email,
    telefon: CONTACT_ERRORS.telefon,
    vreme_poziva: CONTACT_ERRORS.vreme_poziva,
    kontakt_kanal: CONTACT_ERRORS.kontakt_kanal,
    saglasnost: CONTACT_ERRORS.saglasnost,
  });
});

test('validateContact: pravila ista kao na serveru', () => {
  const err = (patch) => Object.keys(validateContact({ ...validContact(), ...patch }));
  assert.deepEqual(err({ ime: '<script>' }), ['ime']);
  assert.deepEqual(err({ ime: 'a'.repeat(61) }), ['ime']);
  assert.deepEqual(err({ email: 'milica@' }), ['email']);
  assert.deepEqual(err({ telefon: '12345' }), ['telefon']);
  assert.deepEqual(err({ telefon: '064-123-4567' }), ['telefon']);
  assert.deepEqual(err({ vreme_poziva: 'Noću' }), ['vreme_poziva']);
  assert.deepEqual(err({ saglasnost: 'true' }), ['saglasnost']);
});

test('buildPayload pravi tačan oblik za endpoint', () => {
  let s = createState();
  s = setAnswer(s, q('cilj'), 'Izgubiti kilograme i dodati mišićnu masu');
  s = setAnswer(s, q('vaznost'), 9);
  const payload = buildPayload(s, { ...validContact(), ime: '  Milica ' }, { utm_source: 'ig' }, 42000.7, '');
  assert.deepEqual(payload, {
    answers: { cilj: 'Izgubiti kilograme i dodati mišićnu masu', vaznost: 9 },
    contact: {
      ime: 'Milica', email: 'milica@example.com', telefon: '064 123 4567',
      vreme_poziva: 'Popodne', kontakt_kanal: 'WhatsApp',
    },
    saglasnost: true,
    utm: { utm_source: 'ig' },
    website: '',
    elapsed_ms: 42001,
  });
});
```

- [ ] **Step 2: Pokreni i proveri da pada**

Run: `node --test tests/js/quiz-state.test.js`
Expected: FAIL sa `Cannot find module ... quiz-state.js`.

- [ ] **Step 3: Implementiraj `assets/js/quiz-state.js`**

```js
export const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_content', 'utm_campaign'];

const CALL_TIMES = ['Pre podne', 'Popodne', 'Uveče'];
const CHANNELS = ['Poziv', 'WhatsApp', 'Viber'];

export const CONTACT_ERRORS = {
  ime: 'Upiši svoje ime (samo slova).',
  email: 'Proveri email adresu.',
  telefon: 'Proveri broj telefona.',
  vreme_poziva: 'Izaberi kada da te Nikola pozove.',
  kontakt_kanal: 'Izaberi kako da te kontaktira.',
  saglasnost: 'Potrebna je tvoja saglasnost da bismo obradili prijavu.',
};

export function readUtm(search) {
  const params = new URLSearchParams(search);
  const utm = {};
  for (const key of UTM_KEYS) {
    const value = params.get(key);
    if (value) utm[key] = value;
  }
  return utm;
}

export function createState() {
  return { step: 0, answers: {} };
}

export function setAnswer(state, question, value) {
  const answers = { ...state.answers };
  if (question.type === 'multi') {
    const current = Array.isArray(answers[question.id]) ? answers[question.id] : [];
    answers[question.id] = current.includes(value)
      ? current.filter((item) => item !== value)
      : [...current, value];
  } else {
    answers[question.id] = value;
  }
  return { ...state, answers };
}

export function isAnswered(state, question) {
  const value = state.answers[question.id];
  if (question.type === 'multi') return Array.isArray(value) && value.length > 0;
  if (question.type === 'scale') return Number.isInteger(value);
  return typeof value === 'string' && value !== '';
}

export function totalSteps(quiz) {
  return quiz.questions.length + 1;
}

export function emptyContact() {
  return { ime: '', email: '', telefon: '+381 ', vreme_poziva: '', kontakt_kanal: '', saglasnost: false };
}

export function validateContact(contact) {
  const errors = {};
  const ime = String(contact.ime ?? '').trim();
  if (ime.length < 1 || [...ime].length > 60 || !/^\p{L}[\p{L} '\-]*$/u.test(ime)) {
    errors.ime = CONTACT_ERRORS.ime;
  }
  const email = String(contact.email ?? '').trim();
  if (email.length > 254 || !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
    errors.email = CONTACT_ERRORS.email;
  }
  const telefon = String(contact.telefon ?? '').replace(/\s+/g, '');
  if (!/^\+?[0-9]{8,15}$/.test(telefon)) {
    errors.telefon = CONTACT_ERRORS.telefon;
  }
  if (!CALL_TIMES.includes(contact.vreme_poziva)) {
    errors.vreme_poziva = CONTACT_ERRORS.vreme_poziva;
  }
  if (!CHANNELS.includes(contact.kontakt_kanal)) {
    errors.kontakt_kanal = CONTACT_ERRORS.kontakt_kanal;
  }
  if (contact.saglasnost !== true) {
    errors.saglasnost = CONTACT_ERRORS.saglasnost;
  }
  return errors;
}

export function buildPayload(state, contact, utm, elapsedMs, honeypot) {
  return {
    answers: { ...state.answers },
    contact: {
      ime: String(contact.ime).trim(),
      email: String(contact.email).trim(),
      telefon: String(contact.telefon).trim(),
      vreme_poziva: contact.vreme_poziva,
      kontakt_kanal: contact.kontakt_kanal,
    },
    saglasnost: contact.saglasnost === true,
    utm: { ...utm },
    website: honeypot,
    elapsed_ms: Math.round(elapsedMs),
  };
}
```

Napomena: `CALL_TIMES` i `CHANNELS` moraju biti iste kao `contact` u `quiz.json`. Test `quiz-json.test.js` (Task 1) čuva `quiz.json` stranu, a `validateContact` test ovu.

- [ ] **Step 4: Pokreni i proveri da prolazi**

Run: `npm run test:js`
Expected: svi testovi prolaze.

- [ ] **Step 5: Commit**

```bash
git add assets/js/quiz-state.js tests/js/quiz-state.test.js
git commit -m "Čista logika kviza sa testovima"
```

### Task 9: Kviz overlay (DOM, slanje, sticky CTA)

**Files:**
- Modify: `index.html` (dodaje overlay skeleton i `<script>` pre `</body>`)
- Create: `assets/js/dom.js`, `assets/js/quiz.js`, `assets/js/landing.js`
- Modify: `assets/css/style.css` (dodaje stil kviza na kraj)
- Modify: `tests/js/html.test.js` (dodaje test skeletona)

**Interfaces:**
- Consumes: sve iz `quiz-state.js` (Task 8); `POST /api/submit.php` ugovor (Task 5); `[data-open-quiz]`, `[data-sticky-cta]`, `#hero` (Task 7)
- Produces:
  - `el(tag: string, attrs?: object, text?: string): HTMLElement` iz `dom.js` — `true` atribut = prazan atribut, `false`/`null` = izostavljen, tekst preko `textContent`
  - `initQuiz(): void` iz `quiz.js`
  - Analytics ugovor bez zavisnosti: `document.dispatchEvent(new CustomEvent('nd:track', { detail: { name, params } }))` sa imenima `StartQuiz` (jednom po sesiji), `QuizStep` (`params: { step: 1..7 }`), `Lead` (posle uspešnog slanja). Sluša ga `consent.js` (Task 10).
  - `sessionStorage` ključevi: `nd_quiz_v1` = `{ state, contact, startedAt, started }`, `nd_utm_v1` = UTM objekat
  - Redirect posle uspeha: `/hvala?ime=<encodeURIComponent(ime)>`
  - `body.quiz-open` klasa dok je overlay otvoren
  - `#quiz[data-instagram]` — URL za poruku o grešci

- [ ] **Step 1: Dodaj test koji pada na kraj `tests/js/html.test.js`**

```js
test('index.html: kviz skeleton, honeypot i skripta', () => {
  const html = read('index.html');
  assert.match(html, /<div class="quiz" id="quiz" role="dialog" aria-modal="true" aria-labelledby="quiz-title"[^>]*hidden>/);
  assert.match(html, /<input type="text" id="website" name="website" tabindex="-1" autocomplete="off">/);
  assert.match(html, /data-quiz-body/);
  assert.match(html, /<script type="module" src="\/assets\/js\/landing\.js"><\/script>\s*<\/body>/);
});
```

- [ ] **Step 2: Pokreni i proveri da pada**

Run: `node --test tests/js/html.test.js`
Expected: FAIL u testu „kviz skeleton, honeypot i skripta“.

- [ ] **Step 3: Dodaj skeleton u `index.html` odmah posle `sticky-cta` diva, pre `</body>`**

```html
  <div class="quiz" id="quiz" role="dialog" aria-modal="true" aria-labelledby="quiz-title" data-instagram="{{KLIJENT: Instagram DM URL, npr. https://ig.me/m/korisnicko_ime}}" hidden>
    <div class="quiz__panel">
      <div class="quiz__top">
        <button type="button" class="quiz__icon" data-quiz-back aria-label="Nazad">←</button>
        <div class="quiz__progress" role="progressbar" aria-label="Napredak prijave" aria-valuemin="1" aria-valuemax="7" aria-valuenow="1"><span class="quiz__bar" data-quiz-bar></span></div>
        <button type="button" class="quiz__icon" data-quiz-close aria-label="Zatvori prijavu">✕</button>
      </div>
      <p class="quiz__count" data-quiz-count>Korak 1 od 7</p>
      <div class="quiz__body" data-quiz-body></div>
      <div class="quiz__hp" aria-hidden="true">
        <label for="website">Ne popunjavaj ovo polje</label>
        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
      </div>
    </div>
  </div>

  <script type="module" src="/assets/js/landing.js"></script>
</body>
```

- [ ] **Step 4: Pokreni i proveri da prolazi**

Run: `npm run test:js`
Expected: svi testovi prolaze.

- [ ] **Step 5a: Napravi `assets/js/dom.js`** (deljeni DOM helper; koriste ga `quiz.js` i `faq.js` iz Task 11)

```js
export function el(tag, attrs = {}, text) {
  const node = document.createElement(tag);
  for (const [key, value] of Object.entries(attrs)) {
    if (value === true) node.setAttribute(key, '');
    else if (value !== false && value != null) node.setAttribute(key, String(value));
  }
  if (text != null) node.textContent = text;
  return node;
}
```

- [ ] **Step 5: Implementiraj `assets/js/quiz.js`**

```js
import {
  readUtm, createState, setAnswer, isAnswered, totalSteps,
  emptyContact, validateContact, buildPayload,
} from './quiz-state.js';
import { el } from './dom.js';

const QUIZ_KEY = 'nd_quiz_v1';
const UTM_KEY = 'nd_utm_v1';
const AUTO_ADVANCE_MS = 180;

const session = {
  get(key) {
    try { return JSON.parse(sessionStorage.getItem(key)); } catch { return null; }
  },
  set(key, value) {
    try { sessionStorage.setItem(key, JSON.stringify(value)); } catch { /* privatni režim */ }
  },
  remove(key) {
    try { sessionStorage.removeItem(key); } catch { /* privatni režim */ }
  },
};

function track(name, params = {}) {
  document.dispatchEvent(new CustomEvent('nd:track', { detail: { name, params } }));
}

export function initQuiz() {
  const dialog = document.getElementById('quiz');
  if (!dialog) return;

  const body = dialog.querySelector('[data-quiz-body]');
  const bar = dialog.querySelector('[data-quiz-bar]');
  const progress = dialog.querySelector('[role="progressbar"]');
  const count = dialog.querySelector('[data-quiz-count]');
  const back = dialog.querySelector('[data-quiz-back]');
  const honeypot = dialog.querySelector('#website');

  const utmNow = readUtm(window.location.search);
  if (Object.keys(utmNow).length > 0) session.set(UTM_KEY, utmNow);

  const quizReady = fetch('/assets/data/quiz.json')
    .then((res) => (res.ok ? res.json() : null))
    .catch(() => null);

  let quiz = null;
  let lastFocus = null;
  let saved = session.get(QUIZ_KEY) || {
    state: createState(), contact: emptyContact(), startedAt: null, started: false,
  };

  const persist = () => session.set(QUIZ_KEY, saved);

  async function open(trigger) {
    lastFocus = trigger || document.activeElement;
    dialog.hidden = false;
    document.body.classList.add('quiz-open');
    quiz = quiz || await quizReady;
    if (!quiz) {
      renderFatal();
      return;
    }
    if (!saved.started) {
      saved.started = true;
      saved.startedAt = Date.now();
      track('StartQuiz');
    }
    persist();
    render();
  }

  function close() {
    dialog.hidden = true;
    document.body.classList.remove('quiz-open');
    persist();
    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  }

  function goTo(step) {
    saved.state = { ...saved.state, step };
    persist();
    render();
  }

  function render() {
    const total = totalSteps(quiz);
    const step = saved.state.step;
    count.textContent = `Korak ${step + 1} od ${total}`;
    bar.style.transform = `scaleX(${(step + 1) / total})`;
    progress.setAttribute('aria-valuenow', String(step + 1));
    back.disabled = step === 0;
    body.replaceChildren();

    if (step < quiz.questions.length) renderQuestion(quiz.questions[step]);
    else renderContact();

    track('QuizStep', { step: step + 1 });
    const first = body.querySelector('button, input:not([type="hidden"])');
    if (first) first.focus({ preventScroll: true });
  }

  function renderQuestion(question) {
    body.append(el('h2', { id: 'quiz-title', class: 'quiz__title' }, question.title));
    if (question.hint) body.append(el('p', { class: 'quiz__hint' }, question.hint));

    const group = el('div', { class: `quiz__options quiz__options--${question.type}`, role: 'group', 'aria-labelledby': 'quiz-title' });
    const values = question.type === 'scale'
      ? Array.from({ length: question.max - question.min + 1 }, (_, i) => question.min + i)
      : question.options;

    const next = question.type === 'multi'
      ? el('button', { type: 'button', class: 'btn btn--primary quiz__next', disabled: !isAnswered(saved.state, question) }, 'Dalje')
      : null;

    for (const value of values) {
      const current = saved.state.answers[question.id];
      const pressed = Array.isArray(current) ? current.includes(value) : current === value;
      const option = el('button', { type: 'button', class: 'quiz__opt', 'aria-pressed': String(pressed) }, String(value));
      option.addEventListener('click', () => {
        saved.state = setAnswer(saved.state, question, value);
        persist();
        if (question.type === 'multi') {
          const nowPressed = saved.state.answers[question.id].includes(value);
          option.setAttribute('aria-pressed', String(nowPressed));
          next.disabled = !isAnswered(saved.state, question);
          return;
        }
        group.querySelectorAll('.quiz__opt').forEach((b) => b.setAttribute('aria-pressed', 'false'));
        option.setAttribute('aria-pressed', 'true');
        setTimeout(() => goTo(saved.state.step + 1), AUTO_ADVANCE_MS);
      });
      group.append(option);
    }
    body.append(group);

    if (next) {
      next.addEventListener('click', () => goTo(saved.state.step + 1));
      body.append(next);
    }
  }

  function renderContact() {
    body.append(el('h2', { id: 'quiz-title', class: 'quiz__title' }, 'Skoro gotovo, gde da te Nikola pozove?'));
    const form = el('form', { class: 'quiz__form', novalidate: true });
    const errors = {};

    const setError = (key, message) => {
      errors[key].textContent = message || '';
      const input = form.querySelector(`[name="${key}"]`);
      if (input) input.setAttribute('aria-invalid', message ? 'true' : 'false');
    };

    const errorNode = (key) => {
      errors[key] = el('p', { class: 'field__error', id: `err-${key}`, 'aria-live': 'polite' });
      return errors[key];
    };

    const textField = (key, label, attrs) => {
      const wrap = el('div', { class: 'field' });
      const input = el('input', { id: `f-${key}`, name: key, 'aria-describedby': `err-${key}`, ...attrs });
      input.value = saved.contact[key];
      input.addEventListener('input', () => { saved.contact[key] = input.value; persist(); });
      input.addEventListener('blur', () => setError(key, validateContact(saved.contact)[key]));
      wrap.append(el('label', { for: `f-${key}` }, label), input, errorNode(key));
      return wrap;
    };

    const choiceField = (key, legend, options) => {
      const fieldset = el('fieldset', { class: 'field field--choice', 'aria-describedby': `err-${key}` });
      fieldset.append(el('legend', {}, legend));
      const pills = el('div', { class: 'pills' });
      options.forEach((option, i) => {
        const id = `f-${key}-${i}`;
        const input = el('input', { type: 'radio', id, name: key, value: option, checked: saved.contact[key] === option });
        input.addEventListener('change', () => { saved.contact[key] = option; persist(); setError(key, ''); });
        pills.append(input, el('label', { for: id }, option));
      });
      fieldset.append(pills, errorNode(key));
      return fieldset;
    };

    form.append(
      textField('ime', 'Ime', { type: 'text', autocomplete: 'given-name', maxlength: '60', required: true }),
      textField('email', 'Email', { type: 'email', autocomplete: 'email', inputmode: 'email', required: true }),
      textField('telefon', 'Telefon', { type: 'tel', autocomplete: 'tel', inputmode: 'tel', required: true }),
      choiceField('vreme_poziva', 'Najbolje vreme za poziv', quiz.contact.vreme_poziva),
      choiceField('kontakt_kanal', 'Kontakt preko', quiz.contact.kontakt_kanal),
    );

    const consentWrap = el('div', { class: 'field field--consent' });
    const consent = el('input', { type: 'checkbox', id: 'f-saglasnost', name: 'saglasnost', checked: saved.contact.saglasnost, 'aria-describedby': 'err-saglasnost' });
    consent.addEventListener('change', () => { saved.contact.saglasnost = consent.checked; persist(); setError('saglasnost', ''); });
    const consentLabel = el('label', { for: 'f-saglasnost' });
    consentLabel.append(
      document.createTextNode('Saglasna sam sa '),
      el('a', { href: '/politika-privatnosti', target: '_blank', rel: 'noopener' }, 'Politikom privatnosti'),
      document.createTextNode(' i obradom podataka o zdravlju u svrhu procene saradnje.'),
    );
    consentWrap.append(consent, consentLabel, errorNode('saglasnost'));

    const status = el('div', { class: 'quiz__status', role: 'alert' });
    const submit = el('button', { type: 'submit', class: 'btn btn--primary quiz__submit' }, 'Pošalji prijavu');
    form.append(consentWrap, status, submit);

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      status.replaceChildren();
      const found = validateContact(saved.contact);
      for (const key of Object.keys(errors)) setError(key, found[key]);
      const firstInvalid = Object.keys(found)[0];
      if (firstInvalid) {
        const target = form.querySelector(`[name="${firstInvalid}"]`);
        if (target) target.focus();
        return;
      }

      submit.disabled = true;
      submit.classList.add('is-loading');
      submit.textContent = 'Šaljem…';

      try {
        const payload = buildPayload(
          saved.state, saved.contact, session.get(UTM_KEY) || {},
          Date.now() - (saved.startedAt || Date.now()), honeypot.value,
        );
        const res = await fetch('/api/submit.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.ok !== true) throw new Error(`HTTP ${res.status}`);

        track('Lead');
        const ime = saved.contact.ime.trim();
        session.remove(QUIZ_KEY);
        await new Promise((resolve) => setTimeout(resolve, 300));
        window.location.assign(`/hvala?ime=${encodeURIComponent(ime)}`);
      } catch {
        submit.disabled = false;
        submit.classList.remove('is-loading');
        submit.textContent = 'Pošalji prijavu';
        status.append(
          document.createTextNode('Nešto nije u redu. Pokušaj ponovo ili piši Nikoli na '),
          el('a', { href: dialog.dataset.instagram, target: '_blank', rel: 'noopener' }, 'Instagram'),
          document.createTextNode('.'),
        );
      }
    });

    body.append(form);
  }

  function renderFatal() {
    body.replaceChildren(
      el('h2', { id: 'quiz-title', class: 'quiz__title' }, 'Prijava trenutno ne radi'),
      el('p', {}, 'Osveži stranicu ili piši Nikoli na Instagram.'),
      el('a', { class: 'btn btn--primary', href: dialog.dataset.instagram, target: '_blank', rel: 'noopener' }, 'Otvori Instagram'),
    );
  }

  function trapFocus(event) {
    if (event.key === 'Escape') {
      close();
      return;
    }
    if (event.key !== 'Tab') return;
    const focusables = [...dialog.querySelectorAll('button:not([disabled]), input:not([tabindex="-1"]), a[href]')]
      .filter((node) => node.offsetParent !== null);
    if (focusables.length === 0) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  document.querySelectorAll('[data-open-quiz]').forEach((button) => {
    button.addEventListener('click', () => open(button));
  });
  dialog.querySelector('[data-quiz-close]').addEventListener('click', close);
  back.addEventListener('click', () => { if (saved.state.step > 0) goTo(saved.state.step - 1); });
  dialog.addEventListener('keydown', trapFocus);
}
```

- [ ] **Step 6: Implementiraj `assets/js/landing.js`**

```js
import { initQuiz } from './quiz.js';

initQuiz();

const sticky = document.querySelector('[data-sticky-cta]');
const hero = document.getElementById('hero');
if (sticky && hero && 'IntersectionObserver' in window) {
  new IntersectionObserver(([entry]) => {
    sticky.classList.toggle('is-visible', !entry.isIntersecting);
  }).observe(hero);
}
```

- [ ] **Step 7: Dodaj stil kviza na kraj `assets/css/style.css`**

```css
/* ---------- Kviz ---------- */
body.quiz-open { overflow: hidden; }
body.quiz-open .sticky-cta { display: none; }

.quiz { position: fixed; inset: 0; z-index: 50; background: var(--bg); overflow-y: auto; overscroll-behavior: contain; }
.quiz__panel { min-height: 100%; width: min(640px, 100%); margin-inline: auto; padding: 16px 20px calc(32px + env(safe-area-inset-bottom)); display: flex; flex-direction: column; }
.quiz__top { display: flex; align-items: center; gap: 12px; }
.quiz__icon { width: 44px; height: 44px; border-radius: 50%; border: 0; background: var(--surface); color: var(--text); font-size: 1.1rem; cursor: pointer; }
.quiz__icon[disabled] { visibility: hidden; }
.quiz__progress { flex: 1; height: 6px; background: var(--surface); border-radius: 999px; overflow: hidden; }
.quiz__bar { display: block; height: 100%; background: var(--accent); transform-origin: left; transform: scaleX(0); transition: transform .25s ease; }
.quiz__count { margin: 12px 0 24px; color: var(--muted); font-size: .9rem; }
.quiz__title { font-size: clamp(1.5rem, 5vw, 2.1rem); margin-bottom: .4em; }
.quiz__hint { color: var(--muted); margin-bottom: 20px; }
.quiz__hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }

.quiz__options { display: grid; gap: 10px; margin-top: 16px; }
.quiz__opt { min-height: 60px; padding: 14px 18px; border-radius: 14px; border: 2px solid #2c2c2c; background: var(--surface); color: var(--text); font-weight: 600; text-align: left; cursor: pointer; transition: border-color .15s ease, background-color .15s ease; }
.quiz__opt:hover { border-color: #4a4a4a; }
.quiz__opt[aria-pressed="true"] { border-color: var(--accent); background: #232a0d; }
.quiz__options--scale { grid-template-columns: repeat(5, 1fr); }
.quiz__options--scale .quiz__opt { text-align: center; padding: 0; font-size: 1.15rem; }
.quiz__next, .quiz__submit { width: 100%; margin-top: 24px; }

.quiz__form { display: grid; gap: 18px; margin-top: 8px; }
.field { display: grid; gap: 6px; border: 0; padding: 0; margin: 0; }
.field label, .field legend { font-weight: 700; font-size: .95rem; padding: 0; }
.field input[type="text"], .field input[type="email"], .field input[type="tel"] { min-height: 54px; padding: 0 16px; border-radius: 12px; border: 2px solid #2c2c2c; background: var(--surface); color: var(--text); font: inherit; font-size: 16px; }
.field input:focus { border-color: var(--accent); outline: none; }
.field input[aria-invalid="true"] { border-color: var(--danger); }
.field__error { margin: 0; min-height: 1.2em; color: var(--danger); font-size: .88rem; }
.pills { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
.pills input { position: absolute; opacity: 0; pointer-events: none; }
.pills label { padding: 10px 16px; border-radius: 999px; border: 2px solid #2c2c2c; background: var(--surface); font-weight: 600; cursor: pointer; }
.pills input:checked + label { border-color: var(--accent); background: #232a0d; }
.pills input:focus-visible + label { outline: 3px solid var(--accent); outline-offset: 2px; }
.field--consent { grid-template-columns: 24px 1fr; align-items: start; column-gap: 12px; }
.field--consent input { width: 22px; height: 22px; margin: 2px 0 0; accent-color: var(--accent); }
.field--consent label { font-weight: 400; font-size: .92rem; color: var(--muted); }
.field--consent .field__error { grid-column: 1 / -1; }
.quiz__status { color: var(--danger); font-size: .95rem; }
.quiz__status:empty { display: none; }
.btn.is-loading { opacity: .7; }

@media (min-width: 900px) {
  .quiz { background: rgba(0, 0, 0, .75); display: grid; place-items: center; padding: 32px; }
  .quiz__panel { min-height: auto; background: var(--bg); border-radius: 24px; padding: 28px 32px 36px; }
  .quiz__options--scale { grid-template-columns: repeat(10, 1fr); }
}
```

- [ ] **Step 8: Ručna provera u browseru (dev server, dry-run)**

Run: `npm run dev`, pa otvori `http://localhost:8000/?utm_source=ig&utm_medium=social&utm_content=link_in_bio` u Chrome-u (DevTools → mobilni prikaz 375px), pa redom:

1. Skroluj ispod hero sekcije → sticky CTA se pojavljuje; vrati se gore → nestaje.
2. Klik na CTA → overlay, „Korak 1 od 7“, dugme Nazad sakriveno.
3. Pitanje 1: klik na odgovor → posle ~0,2 s prelazi na korak 2.
4. Pitanje 3: „Dalje“ je onemogućeno dok nije izabran bar jedan odgovor; dva klika na isti odgovor ga poništavaju.
5. Pitanje 5: 10 dugmadi u dva reda po 5.
6. Klik na ✕ na koraku 4, pa ponovo CTA → nastavlja od koraka 4 sa sačuvanim odgovorima. Isto posle Esc.
7. Tab/Shift+Tab ne izlazi iz overlay-a.
8. Korak 7: „Pošalji prijavu“ sa praznom formom → sve greške na srpskom, fokus na polju Ime.
9. Upiši „<b>“ u ime pa izađi iz polja → greška ispod polja.
10. Popuni ispravno, sačekaj bar 5 s od otvaranja kviza, pošalji → dugme „Šaljem…“, zatim preusmeravanje na `/hvala?ime=Milica` (za sada 404 dok ne postoji Task 11).
11. `tail -n 2 dev/storage/dry-run.log` → MailerLite red sadrži `"izvor":"ig / social / link_in_bio"` i sve odgovore; mail red ima naslov sa „Nova prijava: Milica“.
12. Zaustavi dev server, pa pošalji prijavu → poruka greške sa Instagram linkom, odgovori ostaju popunjeni.
13. Konzola: nema CSP grešaka ni JS izuzetaka.

Expected: svih 13 tačaka se ponaša kako je opisano.

- [ ] **Step 9: Pokreni sve testove**

Run: `npm test`
Expected: svi testovi prolaze.

- [ ] **Step 10: Commit**

```bash
git add index.html assets/js/dom.js assets/js/quiz.js assets/js/landing.js assets/css/style.css tests/js/html.test.js
git commit -m "Kviz overlay sa slanjem prijave i sticky CTA"
```

### Task 10: Cookie banner, consent i Meta Pixel

**Files:**
- Create: `assets/js/consent.js`
- Modify: `assets/js/landing.js` (import + init)
- Modify: `index.html` (`data-pixel-id` na `<body>`, banner, dugme u footeru)
- Modify: `assets/css/style.css` (stil banera na kraj)
- Test: `tests/js/consent.test.js`; Modify: `tests/js/html.test.js`

**Interfaces:**
- Consumes: `nd:track` CustomEvent (Task 9)
- Produces:
  - `CONSENT_KEY = 'nd_consent_v1'`, `CONSENT_TTL_MS = 15552000000`
  - `readConsent(storage, now): 'granted' | 'denied' | null` — `storage` ima `getItem`; vrednost u storage-u je `{"value":"granted"|"denied","at":<ms>}`; isteklo, buduće, neispravno ili izuzetak → `null`
  - `writeConsent(storage, value, now): void` — ne baca izuzetak
  - `trackCall(name, params = {}): [method, name, params]` — `'track'` za `PageView` i `Lead`, inače `'trackCustom'`
  - `isValidPixelId(id): boolean` — samo cifre, 10–20 znakova
  - `initConsent(): void` — prikazuje banner ako nema odluke, učitava Pixel posle „Prihvatam“, prosleđuje `nd:track` događaje u `fbq` samo uz pristanak
  - HTML ugovor za svaku stranicu: `<body data-pixel-id="…">`, `<div class="cookie" id="cookie-banner" … hidden>` sa dugmadima `data-consent="denied"` i `data-consent="granted"`, `<button type="button" data-cookie-settings>Podešavanja kolačića</button>` u footeru

- [ ] **Step 1: Napiši testove koji padaju**

`tests/js/consent.test.js`:
```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  CONSENT_KEY, CONSENT_TTL_MS, readConsent, writeConsent, trackCall, isValidPixelId,
} from '../../assets/js/consent.js';

const memoryStorage = (initial = {}) => {
  const data = { ...initial };
  return {
    data,
    getItem: (k) => (k in data ? data[k] : null),
    setItem: (k, v) => { data[k] = String(v); },
  };
};
const throwingStorage = {
  getItem() { throw new Error('blokirano'); },
  setItem() { throw new Error('blokirano'); },
};
const NOW = 1_800_000_000_000;

test('nema odluke → null', () => {
  assert.equal(readConsent(memoryStorage(), NOW), null);
});

test('write pa read vraća odluku', () => {
  const s = memoryStorage();
  writeConsent(s, 'granted', NOW);
  assert.deepEqual(JSON.parse(s.data[CONSENT_KEY]), { value: 'granted', at: NOW });
  assert.equal(readConsent(s, NOW + 1000), 'granted');
  writeConsent(s, 'denied', NOW);
  assert.equal(readConsent(s, NOW), 'denied');
});

test('odluka ističe posle 6 meseci', () => {
  const s = memoryStorage();
  writeConsent(s, 'granted', NOW);
  assert.equal(readConsent(s, NOW + CONSENT_TTL_MS), 'granted');
  assert.equal(readConsent(s, NOW + CONSENT_TTL_MS + 1), null);
});

test('neispravni podaci → null', () => {
  assert.equal(readConsent(memoryStorage({ [CONSENT_KEY]: 'nije json' }), NOW), null);
  assert.equal(readConsent(memoryStorage({ [CONSENT_KEY]: '{"value":"maybe","at":1}' }), NOW), null);
  assert.equal(readConsent(memoryStorage({ [CONSENT_KEY]: '{"value":"granted"}' }), NOW), null);
  assert.equal(readConsent(memoryStorage({ [CONSENT_KEY]: JSON.stringify({ value: 'granted', at: NOW + 60000 }) }), NOW), null);
});

test('storage koji baca izuzetak ne ruši stranicu', () => {
  assert.equal(readConsent(throwingStorage, NOW), null);
  assert.doesNotThrow(() => writeConsent(throwingStorage, 'granted', NOW));
});

test('trackCall bira standardni ili custom događaj', () => {
  assert.deepEqual(trackCall('Lead'), ['track', 'Lead', {}]);
  assert.deepEqual(trackCall('PageView'), ['track', 'PageView', {}]);
  assert.deepEqual(trackCall('StartQuiz'), ['trackCustom', 'StartQuiz', {}]);
  assert.deepEqual(trackCall('QuizStep', { step: 3 }), ['trackCustom', 'QuizStep', { step: 3 }]);
});

test('isValidPixelId', () => {
  assert.equal(isValidPixelId('1234567890123456'), true);
  assert.equal(isValidPixelId('{{KLIJENT: Meta Pixel ID}}'), false);
  assert.equal(isValidPixelId(''), false);
  assert.equal(isValidPixelId(undefined), false);
});
```

Dodaj na kraj `tests/js/html.test.js`:
```js
for (const file of PAGES.filter((f) => existsSync(new URL(f, root)))) {
  test(`${file}: cookie banner i Pixel ID`, () => {
    const html = read(file);
    assert.match(html, /<body data-pixel-id="[^"]+">/);
    assert.match(html, /<div class="cookie" id="cookie-banner"[^>]*hidden>/);
    assert.match(html, /data-consent="denied">Odbijam</);
    assert.match(html, /data-consent="granted">Prihvatam</);
    assert.match(html, /<button type="button" data-cookie-settings>Podešavanja kolačića<\/button>/);
  });
}
```

- [ ] **Step 2: Pokreni i proveri da pada**

Run: `npm run test:js`
Expected: FAIL — `consent.js` ne postoji; `index.html` nema banner.

- [ ] **Step 3: Implementiraj `assets/js/consent.js`**

```js
export const CONSENT_KEY = 'nd_consent_v1';
export const CONSENT_TTL_MS = 15552000000;
const STANDARD_EVENTS = ['PageView', 'Lead'];

export function readConsent(storage, now) {
  try {
    const raw = storage.getItem(CONSENT_KEY);
    if (!raw) return null;
    const data = JSON.parse(raw);
    const validValue = data && (data.value === 'granted' || data.value === 'denied');
    if (!validValue || typeof data.at !== 'number' || data.at > now || now - data.at > CONSENT_TTL_MS) {
      return null;
    }
    return data.value;
  } catch {
    return null;
  }
}

export function writeConsent(storage, value, now) {
  try {
    storage.setItem(CONSENT_KEY, JSON.stringify({ value, at: now }));
  } catch {
    /* blokiran storage: odluka važi samo za ovu stranicu */
  }
}

export function trackCall(name, params = {}) {
  return [STANDARD_EVENTS.includes(name) ? 'track' : 'trackCustom', name, params];
}

export function isValidPixelId(id) {
  return typeof id === 'string' && /^[0-9]{10,20}$/.test(id);
}

function browserStorage() {
  try {
    return window.localStorage;
  } catch {
    return { getItem: () => null, setItem: () => {} };
  }
}

function loadPixel(pixelId) {
  if (window.fbq) return;
  const fbq = function () {
    // eslint-disable-next-line prefer-rest-params
    if (fbq.callMethod) fbq.callMethod.apply(fbq, arguments); else fbq.queue.push(arguments);
  };
  fbq.push = fbq;
  fbq.loaded = true;
  fbq.version = '2.0';
  fbq.queue = [];
  window.fbq = fbq;
  window._fbq = fbq;

  const script = document.createElement('script');
  script.async = true;
  script.src = 'https://connect.facebook.net/en_US/fbevents.js';
  document.head.append(script);

  window.fbq('init', pixelId);
  window.fbq('track', 'PageView');
}

export function initConsent() {
  const banner = document.getElementById('cookie-banner');
  const pixelId = document.body.dataset.pixelId;
  const storage = browserStorage();
  let decision = readConsent(storage, Date.now());

  const apply = () => {
    if (decision === 'granted' && isValidPixelId(pixelId)) {
      loadPixel(pixelId);
    } else if (decision === 'denied' && window.fbq) {
      window.fbq('consent', 'revoke');
    }
  };

  if (banner) {
    banner.hidden = decision !== null;
    banner.querySelectorAll('[data-consent]').forEach((button) => {
      button.addEventListener('click', () => {
        decision = button.dataset.consent === 'granted' ? 'granted' : 'denied';
        writeConsent(storage, decision, Date.now());
        banner.hidden = true;
        apply();
      });
    });
  }

  document.querySelectorAll('[data-cookie-settings]').forEach((button) => {
    button.addEventListener('click', () => {
      if (!banner) return;
      banner.hidden = false;
      const first = banner.querySelector('button');
      if (first) first.focus();
    });
  });

  document.addEventListener('nd:track', (event) => {
    if (decision !== 'granted' || !window.fbq) return;
    const { name, params } = event.detail || {};
    if (typeof name === 'string') window.fbq(...trackCall(name, params || {}));
  });

  apply();
}
```

- [ ] **Step 4: Izmeni `index.html`**

1. Zameni `<body>` sa:
```html
<body data-pixel-id="{{KLIJENT: Meta Pixel ID}}">
```

2. U footeru, u `<ul class="footer__legal">`, dodaj treću stavku:
```html
        <li><button type="button" data-cookie-settings>Podešavanja kolačića</button></li>
```

3. Odmah iznad `<div class="quiz" id="quiz" …>` dodaj:
```html
  <div class="cookie" id="cookie-banner" role="region" aria-label="Kolačići" hidden>
    <p>Koristimo kolačiće (Meta Pixel) da izmerimo koliko su naše objave i reklame korisne. Ništa se ne učitava bez tvog pristanka. Više u <a href="/politika-privatnosti">Politici privatnosti</a>.</p>
    <div class="cookie__actions">
      <button type="button" class="btn btn--ghost" data-consent="denied">Odbijam</button>
      <button type="button" class="btn btn--ghost" data-consent="granted">Prihvatam</button>
    </div>
  </div>
```

- [ ] **Step 5: Zameni ceo `assets/js/landing.js`** (`initConsent()` mora biti pozvan pre `initQuiz()` da bi `nd:track` listener postojao pre prvog događaja)

Konačan `assets/js/landing.js`:
```js
import { initConsent } from './consent.js';
import { initQuiz } from './quiz.js';

initConsent();
initQuiz();

const sticky = document.querySelector('[data-sticky-cta]');
const hero = document.getElementById('hero');
if (sticky && hero && 'IntersectionObserver' in window) {
  new IntersectionObserver(([entry]) => {
    sticky.classList.toggle('is-visible', !entry.isIntersecting);
  }).observe(hero);
}
```

- [ ] **Step 6: Dodaj stil banera na kraj `assets/css/style.css`**

```css
/* ---------- Cookie banner ---------- */
.cookie { position: fixed; z-index: 40; left: 12px; right: 12px; bottom: calc(88px + env(safe-area-inset-bottom)); background: var(--surface); color: var(--text); border: 1px solid #2c2c2c; border-radius: var(--radius); padding: 16px 18px; box-shadow: 0 12px 40px rgba(0, 0, 0, .5); font-size: .92rem; }
.cookie p { margin: 0 0 12px; }
.cookie__actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.cookie .btn { min-height: 46px; font-size: .95rem; padding: 0 12px; }
body.quiz-open .cookie { display: none; }

@media (min-width: 900px) {
  .cookie { left: auto; right: 24px; bottom: 24px; width: 420px; }
}
```

Banner je na mobilnom iznad sticky CTA dugmeta (`bottom: 88px`), pa se ne preklapaju.

- [ ] **Step 7: Pokreni testove**

Run: `npm run test:js`
Expected: svi testovi prolaze.

- [ ] **Step 8: Ručna provera u browseru**

`npm run dev`, Chrome DevTools → Application → Local Storage obriši `nd_consent_v1`, Network filter `facebook`:

1. Učitaj `/` → banner vidljiv, u Network-u nema zahteva ka facebook-u.
2. Klik „Odbijam“ → banner nestaje; reload → banner se ne vraća, i dalje nema facebook zahteva.
3. Footer → „Podešavanja kolačića“ → banner se ponovo pojavljuje.
4. Privremeno promeni `data-pixel-id` u `1234567890123456` (ne commit-uj), obriši `nd_consent_v1`, reload, „Prihvatam“ → zahtev ka `connect.facebook.net/en_US/fbevents.js`; u konzoli nema CSP greške.
5. Otvori kviz → u konzoli `fbq.queue` ili Network sadrži `StartQuiz` i `QuizStep`.
6. Vrati `data-pixel-id="{{KLIJENT: Meta Pixel ID}}"`.

Expected: sve tačke kao opisano.

- [ ] **Step 9: Commit**

```bash
git add assets/js/consent.js assets/js/landing.js index.html assets/css/style.css tests/js/consent.test.js tests/js/html.test.js
git commit -m "Cookie banner sa pristankom i Meta Pixel događaji"
```

### Task 11: Hvala stranica sa video FAQ

**Files:**
- Create: `hvala.html`, `assets/js/faq.js`, `assets/js/hvala-page.js`, `assets/nikola-disic.vcf`
- Modify: `assets/css/style.css` (stil na kraj)
- Test: `tests/js/faq.test.js`; Modify: `tests/js/html.test.js`

**Interfaces:**
- Consumes: `initConsent()` (Task 10); `el()` iz `assets/js/dom.js` (Task 9); CSS klase iz Task 7; redirect `/hvala?ime=…` (Task 9)
- Produces:
  - `greetingText(search: string): string` → `"Hvala, Milica!"` ili `"Hvala!"` (ime trim, 1–60 znakova, ista regex kao server)
  - `initFaq(): void` — radi nad `[data-faq]`; stavke su `.faq-item[data-video][data-poster][data-vtt]`; stavka sa praznim `data-video` se uklanja; ako nijedna ne ostane, `[data-faq-section]` dobija `hidden`
  - Konvencija imena video fajlova: `assets/video/NN-slug.mp4`, `NN-slug.jpg`, `NN-slug.vtt` (NN = 01–08)

- [ ] **Step 1: Napiši testove koji padaju**

`tests/js/faq.test.js`:
```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { greetingText } from '../../assets/js/faq.js';

test('pozdrav sa imenom', () => {
  assert.equal(greetingText('?ime=Milica'), 'Hvala, Milica!');
  assert.equal(greetingText('?ime=%C4%90ur%C4%91a'), 'Hvala, Đurđa!');
  assert.equal(greetingText('?ime=%20Ana-Marija%20'), 'Hvala, Ana-Marija!');
});

test('bez imena ili sa neispravnim imenom je neutralan pozdrav', () => {
  assert.equal(greetingText(''), 'Hvala!');
  assert.equal(greetingText('?ime='), 'Hvala!');
  assert.equal(greetingText('?ime=%3Cscript%3E'), 'Hvala!');
  assert.equal(greetingText(`?ime=${'a'.repeat(61)}`), 'Hvala!');
});
```

Dodaj na kraj `tests/js/html.test.js`:
```js
test('hvala.html: noindex, pozdrav, koraci, vCard, 8 FAQ stavki i skripta', () => {
  const html = read('hvala.html');
  assert.match(html, /<meta name="robots" content="noindex">/);
  assert.match(html, /<h1 data-greeting>Hvala!<\/h1>/);
  assert.match(html, /href="\/assets\/nikola-disic\.vcf" download/);
  assert.equal((html.match(/class="faq-item"/g) || []).length, 8);
  assert.equal((html.match(/data-video="[^"]*"/g) || []).length, 8);
  assert.match(html, /<section[^>]*data-faq-section/);
  assert.match(html, /<script type="module" src="\/assets\/js\/hvala-page\.js"><\/script>\s*<\/body>/);
});
```

- [ ] **Step 2: Pokreni i proveri da pada**

Run: `npm run test:js`
Expected: FAIL — `faq.js` i `hvala.html` ne postoje.

- [ ] **Step 3: Implementiraj `assets/js/faq.js`**

```js
import { el } from './dom.js';

export function greetingText(search) {
  const ime = (new URLSearchParams(search).get('ime') || '').trim();
  const valid = ime.length > 0 && [...ime].length <= 60 && /^\p{L}[\p{L} '\-]*$/u.test(ime);
  return valid ? `Hvala, ${ime}!` : 'Hvala!';
}

export function initFaq() {
  const list = document.querySelector('[data-faq]');
  const section = document.querySelector('[data-faq-section]');
  if (!list) return;

  list.querySelectorAll('.faq-item').forEach((item) => {
    if (!item.dataset.video) item.remove();
  });
  const items = [...list.querySelectorAll('.faq-item')];
  if (items.length === 0) {
    if (section) section.hidden = true;
    return;
  }

  const partsOf = (item) => ({
    button: item.querySelector('.faq-q'),
    panel: item.querySelector('.faq-panel'),
  });

  function buildPlayer(item, index) {
    const { panel } = partsOf(item);
    const frame = el('div', { class: 'faq-player' });
    const video = el('video', {
      class: 'faq-video', controls: true, playsinline: true, preload: 'none', poster: item.dataset.poster || null,
    });
    video.append(el('source', { src: item.dataset.video, type: 'video/mp4' }));
    if (item.dataset.vtt) {
      video.append(el('track', { kind: 'captions', srclang: 'sr', label: 'Srpski', src: item.dataset.vtt }));
    }

    const playButton = el('button', { type: 'button', class: 'faq-play', 'aria-label': 'Pusti odgovor', hidden: true }, '▶');
    playButton.addEventListener('click', () => video.play());
    video.addEventListener('play', () => { playButton.hidden = true; });

    frame.append(video, playButton);
    panel.append(frame);

    const nextItem = items[index + 1];
    if (nextItem) {
      const next = el('button', { type: 'button', class: 'btn btn--ghost faq-next', hidden: true }, 'Sledeće pitanje →');
      next.addEventListener('click', () => {
        open(nextItem);
        nextItem.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
      video.addEventListener('ended', () => { next.hidden = false; });
      panel.append(next);
    }
    return { video, playButton };
  }

  const players = new Map();

  function close(item) {
    const { button, panel } = partsOf(item);
    button.setAttribute('aria-expanded', 'false');
    panel.hidden = true;
    const player = players.get(item);
    if (player) player.video.pause();
  }

  function open(item) {
    items.filter((other) => other !== item).forEach(close);
    const { button, panel } = partsOf(item);
    button.setAttribute('aria-expanded', 'true');
    panel.hidden = false;

    if (!players.has(item)) players.set(item, buildPlayer(item, items.indexOf(item)));
    const { video, playButton } = players.get(item);
    const attempt = video.play();
    if (attempt && typeof attempt.catch === 'function') {
      attempt.catch(() => { playButton.hidden = false; });
    }
  }

  items.forEach((item) => {
    const { button } = partsOf(item);
    button.addEventListener('click', () => {
      if (button.getAttribute('aria-expanded') === 'true') close(item);
      else open(item);
    });
  });
}
```

- [ ] **Step 4: Implementiraj `assets/js/hvala-page.js`**

```js
import { initConsent } from './consent.js';
import { greetingText, initFaq } from './faq.js';

initConsent();

const greeting = document.querySelector('[data-greeting]');
if (greeting) greeting.textContent = greetingText(window.location.search);

initFaq();
```

- [ ] **Step 5: Napravi `assets/nikola-disic.vcf`**

```
BEGIN:VCARD
VERSION:3.0
N:Dišić;Nikola;;;
FN:Nikola Dišić – Trener
TEL;TYPE=CELL:{{KLIJENT: Nikolin broj telefona u formatu +381…}}
END:VCARD
```

- [ ] **Step 6: Napiši `hvala.html`**

Svih 8 stavki ima prazan `data-video` dok snimci ne stignu, pa je FAQ sekcija sakrivena. Kad stigne snimak, popune se `data-video`, `data-poster` i `data-vtt` (Task 12).

```html
<!doctype html>
<html lang="sr-Latn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Prijava je stigla | Nikola Dišić</title>
  <link rel="icon" href="/assets/img/favicon.png">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body data-pixel-id="{{KLIJENT: Meta Pixel ID}}">
  <main>
    <section class="section thanks" id="potvrda">
      <div class="container container--narrow">
        <p class="thanks__badge" aria-hidden="true">✓</p>
        <h1 data-greeting>Hvala!</h1>
        <p class="lead">Tvoja prijava je stigla. Evo šta sledi:</p>
        <ol class="next-steps">
          <li><strong>Nikola te zove u roku od 24h</strong> (radnim danima), u terminu koji si izabrala.</li>
          <li><strong>Poziv traje oko 15 minuta</strong> i potpuno je besplatan.</li>
          <li><strong>Sačuvaj Nikolin broj</strong> da bi znala ko te zove.</li>
        </ol>
        <a class="btn btn--primary" href="/assets/nikola-disic.vcf" download>Sačuvaj Nikolin broj</a>
      </div>
    </section>

    <section class="section section--light" id="odgovori" data-faq-section>
      <div class="container container--narrow">
        <p class="eyebrow">Dok čekaš poziv</p>
        <h2>Nikola ti odgovara na najčešća pitanja</h2>
        <p>Klikni na pitanje i odgovor kreće odmah.</p>
        <div class="faq-video-list" data-faq>
          <div class="faq-item" data-video="" data-poster="" data-vtt="">
            <h3><button type="button" class="faq-q" id="faq-b-1" aria-expanded="false" aria-controls="faq-p-1">Koliko košta saradnja i zašto vredi?</button></h3>
            <div class="faq-panel" id="faq-p-1" role="region" aria-labelledby="faq-b-1" hidden></div>
          </div>
          <div class="faq-item" data-video="" data-poster="" data-vtt="">
            <h3><button type="button" class="faq-q" id="faq-b-2" aria-expanded="false" aria-controls="faq-p-2">Probala sam sve, zašto bi ovo bilo drugačije?</button></h3>
            <div class="faq-panel" id="faq-p-2" role="region" aria-labelledby="faq-b-2" hidden></div>
          </div>
          <div class="faq-item" data-video="" data-poster="" data-vtt="">
            <h3><button type="button" class="faq-q" id="faq-b-3" aria-expanded="false" aria-controls="faq-p-3">Nemam vremena, koliko mi dnevno treba?</button></h3>
            <div class="faq-panel" id="faq-p-3" role="region" aria-labelledby="faq-b-3" hidden></div>
          </div>
          <div class="faq-item" data-video="" data-poster="" data-vtt="">
            <h3><button type="button" class="faq-q" id="faq-b-4" aria-expanded="false" aria-controls="faq-p-4">Imam insulinsku rezistenciju ili hormonske probleme, da li je program za mene?</button></h3>
            <div class="faq-panel" id="faq-p-4" role="region" aria-labelledby="faq-b-4" hidden></div>
          </div>
          <div class="faq-item" data-video="" data-poster="" data-vtt="">
            <h3><button type="button" class="faq-q" id="faq-b-5" aria-expanded="false" aria-controls="faq-p-5">Da li moram da idem u teretanu ili može kod kuće?</button></h3>
            <div class="faq-panel" id="faq-p-5" role="region" aria-labelledby="faq-b-5" hidden></div>
          </div>
          <div class="faq-item" data-video="" data-poster="" data-vtt="">
            <h3><button type="button" class="faq-q" id="faq-b-6" aria-expanded="false" aria-controls="faq-p-6">Da li moram da merim hranu i da se odričem omiljenih stvari?</button></h3>
            <div class="faq-panel" id="faq-p-6" role="region" aria-labelledby="faq-b-6" hidden></div>
          </div>
          <div class="faq-item" data-video="" data-poster="" data-vtt="">
            <h3><button type="button" class="faq-q" id="faq-b-7" aria-expanded="false" aria-controls="faq-p-7">Kako tačno funkcioniše garancija?</button></h3>
            <div class="faq-panel" id="faq-p-7" role="region" aria-labelledby="faq-b-7" hidden></div>
          </div>
          <div class="faq-item" data-video="" data-poster="" data-vtt="">
            <h3><button type="button" class="faq-q" id="faq-b-8" aria-expanded="false" aria-controls="faq-p-8">Kako izgleda saradnja online i šta se dešava posle 6 nedelja?</button></h3>
            <div class="faq-panel" id="faq-p-8" role="region" aria-labelledby="faq-b-8" hidden></div>
          </div>
        </div>
      </div>
    </section>

    <section class="section" id="podsetnik">
      <div class="container">
        <h2>Ovo je moguće i za tebe</h2>
        <div class="results">
          <figure class="result">
            <div class="ph ph--square" role="img" aria-label="Nevena pre i posle">{{KLIJENT: pre/posle – Nevena}}</div>
            <figcaption><strong>Nevena</strong><span class="result__stat">{{KLIJENT: brojka i trajanje}}</span></figcaption>
          </figure>
          <figure class="result">
            <div class="ph ph--square" role="img" aria-label="Milica pre i posle">{{KLIJENT: pre/posle – Milica}}</div>
            <figcaption><strong>Milica</strong><span class="result__stat">{{KLIJENT: brojka i trajanje}}</span></figcaption>
          </figure>
          <figure class="result">
            <div class="ph ph--square" role="img" aria-label="Emilija pre i posle">{{KLIJENT: pre/posle – Emilija}}</div>
            <figcaption><strong>Emilija</strong><span class="result__stat">{{KLIJENT: brojka i trajanje}}</span></figcaption>
          </figure>
        </div>
      </div>
    </section>
  </main>

  <footer class="footer">
    <div class="container footer__inner">
      <ul class="footer__social">
        <li><a href="{{KLIJENT: Instagram URL}}" rel="noopener" target="_blank">Instagram</a></li>
        <li><a href="{{KLIJENT: TikTok URL}}" rel="noopener" target="_blank">TikTok</a></li>
        <li><a href="{{KLIJENT: YouTube URL}}" rel="noopener" target="_blank">YouTube</a></li>
      </ul>
      <ul class="footer__legal">
        <li><a href="/politika-privatnosti">Politika privatnosti</a></li>
        <li><a href="/uslovi-koriscenja">Uslovi korišćenja</a></li>
        <li><button type="button" data-cookie-settings>Podešavanja kolačića</button></li>
      </ul>
      <p>© 2026 Nikola Dišić. Sva prava zadržana.</p>
    </div>
  </footer>

  <div class="cookie" id="cookie-banner" role="region" aria-label="Kolačići" hidden>
    <p>Koristimo kolačiće (Meta Pixel) da izmerimo koliko su naše objave i reklame korisne. Ništa se ne učitava bez tvog pristanka. Više u <a href="/politika-privatnosti">Politici privatnosti</a>.</p>
    <div class="cookie__actions">
      <button type="button" class="btn btn--ghost" data-consent="denied">Odbijam</button>
      <button type="button" class="btn btn--ghost" data-consent="granted">Prihvatam</button>
    </div>
  </div>

  <script type="module" src="/assets/js/hvala-page.js"></script>
</body>
</html>
```

- [ ] **Step 7: Dodaj stil na kraj `assets/css/style.css`**

```css
/* ---------- Hvala stranica ---------- */
.thanks { padding-top: 56px; }
.thanks__badge { width: 64px; height: 64px; border-radius: 50%; background: var(--accent); color: var(--accent-text); display: grid; place-items: center; font-size: 2rem; font-weight: 900; margin: 0 0 20px; }
.next-steps { padding-left: 22px; margin: 0 0 8px; }
.next-steps li { margin-bottom: 10px; }
.thanks .btn { margin-top: 20px; }

.faq-video-list { margin-top: 24px; border-top: 1px solid #0000001a; }
.faq-item { border-bottom: 1px solid #0000001a; }
.faq-item h3 { margin: 0; font-size: 1.05rem; }
.faq-q { width: 100%; text-align: left; background: none; border: 0; color: inherit; padding: 20px 44px 20px 0; font-weight: 700; cursor: pointer; position: relative; }
.faq-q::after { content: "▶"; position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 30px; height: 30px; border-radius: 50%; background: var(--light-text); color: var(--light-bg); font-size: .7rem; display: grid; place-items: center; }
.faq-q[aria-expanded="true"]::after { content: "−"; font-size: 1.1rem; }
.faq-panel { padding-bottom: 24px; }
.faq-player { position: relative; width: min(360px, 100%); margin-inline: auto; }
.faq-video { width: 100%; aspect-ratio: 9 / 16; background: #000; border-radius: var(--radius); object-fit: cover; }
.faq-play { position: absolute; inset: 0; margin: auto; width: 84px; height: 84px; border-radius: 50%; border: 0; background: var(--accent); color: var(--accent-text); font-size: 2rem; cursor: pointer; }
.faq-next { display: flex; margin: 16px auto 0; }
```

- [ ] **Step 8: Pokreni testove**

Run: `npm run test:js`
Expected: svi testovi prolaze, uključujući invarijante za `hvala.html`.

- [ ] **Step 9: Ručna provera sa test videom**

```bash
mkdir -p dev/video
ffmpeg -y -f lavfi -i testsrc=size=720x1280:rate=30 -f lavfi -i sine=frequency=440 -t 6 -c:v libx264 -pix_fmt yuv420p -c:a aac -shortest dev/video/test.mp4
cp dev/video/test.mp4 assets/video/01-test.mp4
cp dev/video/test.mp4 assets/video/02-test.mp4
```

Privremeno (ne commit-uj) postavi `data-video="/assets/video/01-test.mp4"` na prvu i `data-video="/assets/video/02-test.mp4"` na drugu stavku u `hvala.html`. `npm run dev`, otvori `http://localhost:8000/hvala?ime=Milica` na 375px:

1. Naslov „Hvala, Milica!“; `/hvala?ime=<script>` → „Hvala!“; `/hvala` → „Hvala!“.
2. Vidljive su samo 2 FAQ stavke.
3. Klik na prvo pitanje → video kreće sa zvukom, bez fullscreen-a.
4. Klik na drugo pitanje → prvo se zatvara i pauzira, drugo kreće.
5. Kad prvi video završi → „Sledeće pitanje →“ otvara drugo; posle drugog (poslednjeg) nema dugmeta.
6. Ponovni klik na otvoreno pitanje → zatvara ga i pauzira.
7. „Sačuvaj Nikolin broj“ preuzima `.vcf`.
8. Vrati sve `data-video=""` i obriši `assets/video/01-test.mp4` i `02-test.mp4` → FAQ sekcija nije vidljiva.
9. Prođi ceo tok sa landinga: prijava → `/hvala?ime=…` radi.

Expected: sve tačke kao opisano, bez CSP grešaka u konzoli.

- [ ] **Step 10: Commit**

```bash
git add hvala.html assets/js/faq.js assets/js/hvala-page.js assets/nikola-disic.vcf assets/css/style.css tests/js/faq.test.js tests/js/html.test.js
git commit -m "Hvala stranica sa video FAQ i vCard kontaktom"
```

### Task 12: Obrada FAQ snimaka (ffmpeg + titlovi)

**Files:**
- Create: `scripts/encode-faq.sh`, `scripts/cyr2lat.js`
- Test: `tests/js/cyr2lat.test.js`, `tests/encode.sh`

**Interfaces:**
- Consumes: konvencija `assets/video/NN-slug.{mp4,jpg,vtt}` (Task 11)
- Produces:
  - `bash scripts/encode-faq.sh <ulaz> <NN-slug>` → `assets/video/<NN-slug>.mp4` (H.264 High, 720×1280, 30 fps, AAC mono 128k, loudnorm, `+faststart`), `.jpg` poster (kadar na 1 s), `.vtt` ako je dostupan `whisper-cli` i model (`WHISPER_MODEL`, podrazumevano `~/.whisper/ggml-medium.bin`)
  - `cyr2lat(text: string): string` iz `scripts/cyr2lat.js`; CLI: `node scripts/cyr2lat.js <fajl>` prepisuje fajl u latinicu

- [ ] **Step 1: Napiši testove koji padaju**

`tests/js/cyr2lat.test.js`:
```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { cyr2lat } from '../../scripts/cyr2lat.js';

test('prevodi ćirilicu u latinicu uključujući digrafe', () => {
  assert.equal(cyr2lat('Здраво, ја сам Никола'), 'Zdravo, ja sam Nikola');
  assert.equal(cyr2lat('Љиља Његош Џонић ђак ћуфта жаба шума чај'), 'Ljilja Njegoš Džonić đak ćufta žaba šuma čaj');
  assert.equal(cyr2lat('ЉУБАВ ЊИВА ЏЕП'), 'LJUBAV NJIVA DŽEP');
});

test('ne dira latinicu, brojeve i VTT oznake', () => {
  const vtt = 'WEBVTT\n\n00:00:01.000 --> 00:00:03.500\nIshrana bez gladovanja, 6 nedelja.';
  assert.equal(cyr2lat(vtt), vtt);
});
```

`tests/encode.sh`:
```bash
#!/usr/bin/env bash
set -euo pipefail
WORK=$(mktemp -d)
trap 'rm -rf "$WORK" assets/video/99-test.*' EXIT

ffmpeg -loglevel error -y -f lavfi -i testsrc=size=1920x1080:rate=25 -f lavfi -i sine=frequency=440 -t 4 \
  -c:v libx264 -pix_fmt yuv420p -c:a aac -shortest "$WORK/ulaz.mp4"

if bash scripts/encode-faq.sh "$WORK/ulaz.mp4" 99-test 2>/dev/null; then
  echo "✗ slug 99 treba da bude odbijen"; exit 1
fi

# Privremeno dozvoli 99 kroz env za test
FAQ_ALLOW_TEST_SLUG=1 bash scripts/encode-faq.sh "$WORK/ulaz.mp4" 99-test

MP4=assets/video/99-test.mp4
[[ -f "$MP4" && -f assets/video/99-test.jpg ]] || { echo "✗ nedostaje mp4 ili jpg"; exit 1; }
DIMS=$(ffprobe -v error -select_streams v:0 -show_entries stream=codec_name,width,height -of csv=p=0 "$MP4")
[[ "$DIMS" == "h264,720,1280" ]] || { echo "✗ video: $DIMS"; exit 1; }
AUDIO=$(ffprobe -v error -select_streams a:0 -show_entries stream=codec_name,channels -of csv=p=0 "$MP4")
[[ "$AUDIO" == "aac,1" ]] || { echo "✗ audio: $AUDIO"; exit 1; }
head -c 64 "$MP4" | grep -q ftyp || { echo "✗ nije mp4"; exit 1; }
MOOV=$(grep -abo moov "$MP4" | head -1 | cut -d: -f1)
MDAT=$(grep -abo mdat "$MP4" | head -1 | cut -d: -f1)
(( MOOV < MDAT )) || { echo "✗ faststart: moov ($MOOV) posle mdat ($MDAT)"; exit 1; }
echo "✓ encode-faq.sh radi"
```

- [ ] **Step 2: Pokreni i proveri da pada**

Run: `node --test tests/js/cyr2lat.test.js; bash tests/encode.sh`
Expected: FAIL — `cyr2lat.js` i `encode-faq.sh` ne postoje.

- [ ] **Step 3: Implementiraj `scripts/cyr2lat.js`**

```js
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const MAP = {
  А: 'A', Б: 'B', В: 'V', Г: 'G', Д: 'D', Ђ: 'Đ', Е: 'E', Ж: 'Ž', З: 'Z', И: 'I', Ј: 'J', К: 'K',
  Л: 'L', Љ: 'Lj', М: 'M', Н: 'N', Њ: 'Nj', О: 'O', П: 'P', Р: 'R', С: 'S', Т: 'T', Ћ: 'Ć', У: 'U',
  Ф: 'F', Х: 'H', Ц: 'C', Ч: 'Č', Џ: 'Dž', Ш: 'Š',
  а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', ђ: 'đ', е: 'e', ж: 'ž', з: 'z', и: 'i', ј: 'j', к: 'k',
  л: 'l', љ: 'lj', м: 'm', н: 'n', њ: 'nj', о: 'o', п: 'p', р: 'r', с: 's', т: 't', ћ: 'ć', у: 'u',
  ф: 'f', х: 'h', ц: 'c', ч: 'č', џ: 'dž', ш: 'š',
};
const DIGRAPHS = { Љ: 'LJ', Њ: 'NJ', Џ: 'DŽ' };

export function cyr2lat(text) {
  const chars = [...text];
  return chars.map((ch, i) => {
    if (DIGRAPHS[ch]) {
      const neighbor = chars[i + 1] ?? chars[i - 1] ?? '';
      const neighborUpper = /\p{Lu}/u.test(neighbor);
      return neighborUpper ? DIGRAPHS[ch] : MAP[ch];
    }
    return MAP[ch] ?? ch;
  }).join('');
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const file = process.argv[2];
  if (!file) {
    console.error('Upotreba: node scripts/cyr2lat.js <fajl>');
    process.exit(1);
  }
  writeFileSync(file, cyr2lat(readFileSync(file, 'utf8')));
}
```

- [ ] **Step 4: Implementiraj `scripts/encode-faq.sh`**

```bash
#!/usr/bin/env bash
# Obrada vertikalnog snimka sa telefona za FAQ na /hvala.
# Upotreba: bash scripts/encode-faq.sh <ulazni-video> <NN-slug>   npr. 01-cena
set -euo pipefail

if [[ $# -ne 2 ]]; then
  echo "Upotreba: bash scripts/encode-faq.sh <ulazni-video> <NN-slug>  (npr. 01-cena)"
  exit 1
fi

IN="$1"
SLUG="$2"
SLUG_RE='^0[1-8]-[a-z0-9-]+$'
[[ "${FAQ_ALLOW_TEST_SLUG:-}" == "1" ]] && SLUG_RE='^(0[1-8]|99)-[a-z0-9-]+$'

if [[ ! "$SLUG" =~ $SLUG_RE ]]; then
  echo "Slug mora biti oblika 01-cena … 08-posle-6-nedelja"
  exit 1
fi
if [[ ! -f "$IN" ]]; then
  echo "Ulazni fajl ne postoji: $IN"
  exit 1
fi

OUT_DIR="assets/video"
MP4="$OUT_DIR/$SLUG.mp4"
JPG="$OUT_DIR/$SLUG.jpg"
mkdir -p "$OUT_DIR"

echo "→ Video: $MP4"
ffmpeg -loglevel error -stats -y -i "$IN" \
  -vf "scale=720:1280:force_original_aspect_ratio=increase,crop=720:1280,fps=30" \
  -c:v libx264 -profile:v high -preset slow -crf 26 -maxrate 2500k -bufsize 5000k -pix_fmt yuv420p \
  -af "loudnorm=I=-16:TP=-1.5:LRA=11" -c:a aac -b:a 128k -ac 1 \
  -movflags +faststart "$MP4"

echo "→ Poster: $JPG"
ffmpeg -loglevel error -y -ss 1 -i "$MP4" -frames:v 1 -q:v 3 "$JPG"

BYTES=$(wc -c < "$MP4" | tr -d ' ')
MB=$(( BYTES / 1024 / 1024 ))
echo "→ Veličina: ${MB} MB"
if (( BYTES > 15 * 1024 * 1024 )); then
  echo "⚠ Veće od 15 MB. Skrati snimak ili povećaj -crf (npr. 28)."
fi

MODEL="${WHISPER_MODEL:-$HOME/.whisper/ggml-medium.bin}"
if command -v whisper-cli >/dev/null 2>&1 && [[ -f "$MODEL" ]]; then
  echo "→ Titlovi: $OUT_DIR/$SLUG.vtt"
  WAV=$(mktemp -t faq).wav
  ffmpeg -loglevel error -y -i "$MP4" -ar 16000 -ac 1 -c:a pcm_s16le "$WAV"
  whisper-cli -m "$MODEL" -l sr -f "$WAV" -ovtt -of "$OUT_DIR/$SLUG" >/dev/null
  rm -f "$WAV"
  node scripts/cyr2lat.js "$OUT_DIR/$SLUG.vtt"
  echo "  Proveri tekst u $OUT_DIR/$SLUG.vtt pre commit-a."
else
  echo "→ Titlovi preskočeni. Za automatske titlove:"
  echo "  brew install whisper-cpp"
  echo "  mkdir -p ~/.whisper && curl -L -o ~/.whisper/ggml-medium.bin https://huggingface.co/ggerganov/whisper.cpp/resolve/main/ggml-medium.bin"
fi

echo "✓ Gotovo. U hvala.html postavi:"
echo "  data-video=\"/$MP4\" data-poster=\"/$JPG\" data-vtt=\"/$OUT_DIR/$SLUG.vtt\""
```

- [ ] **Step 5: Pokreni testove i proveri da prolaze**

Run: `npm run test:js && bash tests/encode.sh`
Expected: svi JS testovi prolaze; `✓ encode-faq.sh radi`.

- [ ] **Step 6: Commit**

```bash
git add scripts/encode-faq.sh scripts/cyr2lat.js tests/js/cyr2lat.test.js tests/encode.sh
git commit -m "Skripta za obradu FAQ snimaka i latinične titlove"
```

### Task 13: Politika privatnosti i Uslovi korišćenja

**Files:**
- Create: `politika-privatnosti.html`, `uslovi-koriscenja.html`, `assets/js/legal.js`
- Modify: `assets/css/style.css` (stil na kraj)
- Modify: `tests/js/html.test.js`

**Interfaces:**
- Consumes: `initConsent()` (Task 10); footer i banner markup (Task 10/11); link `/uslovi-koriscenja#garancija` sa landinga (Task 7)
- Produces: pravne stranice sa sidrima `#garancija` (uslovi) i `#kolacici` (politika); `.legal` CSS klasa

⚠️ Tekst je osnova usklađena sa spec-om; pre objave ga pregleda pravnik (spec, sekcija 9).

- [ ] **Step 1: Dodaj test koji pada na kraj `tests/js/html.test.js`**

```js
test('politika-privatnosti.html: obavezni delovi', () => {
  const html = read('politika-privatnosti.html');
  for (const needle of ['Rukovalac', 'posebne vrste podataka', 'izričit', 'MailerLite', 'Hostinger', 'Meta',
    '12 meseci', '30 dana', 'id="kolacici"', 'Poverenik', 'povučeš pristanak']) {
    assert.ok(html.includes(needle), `nedostaje: ${needle}`);
  }
  assert.match(html, /<script type="module" src="\/assets\/js\/legal\.js"><\/script>\s*<\/body>/);
});

test('uslovi-koriscenja.html: obavezni delovi', () => {
  const html = read('uslovi-koriscenja.html');
  for (const needle of ['id="garancija"', 'ne zamenjuje', 'individualni', 'Autorska prava', 'odgovornosti',
    'Republike Srbije']) {
    assert.ok(html.includes(needle), `nedostaje: ${needle}`);
  }
  assert.match(html, /<script type="module" src="\/assets\/js\/legal\.js"><\/script>\s*<\/body>/);
});
```

- [ ] **Step 2: Pokreni i proveri da pada**

Run: `npm run test:js`
Expected: FAIL — fajlovi ne postoje.

- [ ] **Step 3: Napravi `assets/js/legal.js`**

```js
import { initConsent } from './consent.js';

initConsent();
```

- [ ] **Step 4: Napiši `politika-privatnosti.html`**

```html
<!doctype html>
<html lang="sr-Latn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Politika privatnosti | Nikola Dišić</title>
  <link rel="icon" href="/assets/img/favicon.png">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body data-pixel-id="{{KLIJENT: Meta Pixel ID}}">
  <main class="section">
    <article class="container container--narrow legal">
      <p><a href="/">← Nazad na početnu</a></p>
      <h1>Politika privatnosti</h1>
      <p class="micro">Poslednja izmena: {{KLIJENT: datum objave}}</p>

      <p>Ova politika objašnjava koje podatke prikupljamo kada popuniš prijavu na ovom sajtu, zašto ih prikupljamo, koliko ih čuvamo i koja prava imaš. Primenjuju se Zakon o zaštiti podataka o ličnosti Republike Srbije („ZZPL“) i, za korisnike iz Evropske unije, Opšta uredba o zaštiti podataka (GDPR).</p>

      <h2>1. Rukovalac</h2>
      <p>Rukovalac podacima je {{KLIJENT: pravni status i puno ime, npr. „Nikola Dišić PR, [naziv radnje]“}}, {{KLIJENT: adresa}}, PIB {{KLIJENT: PIB ili obrisati}}, email: {{KLIJENT: kontakt email}}.</p>

      <h2>2. Koje podatke prikupljamo</h2>
      <ul>
        <li><strong>Kontakt podaci:</strong> ime, email adresa, broj telefona, željeno vreme i način kontakta.</li>
        <li><strong>Odgovori iz prijave:</strong> tvoj cilj, koliko dugo pokušavaš, šta si probala, prepreke, koliko ti je važno da kreneš i spremnost na ulaganje.</li>
        <li><strong>Podaci o zdravlju:</strong> ako navedeš zdravstveni cilj ili prepreku (npr. insulinsku rezistenciju ili hormonske probleme). Ovo su posebne vrste podataka o ličnosti.</li>
        <li><strong>Izvor posete:</strong> UTM oznake iz linka kojim si došla (npr. Instagram).</li>
        <li><strong>Tehnički podaci:</strong> IP adresa, koja se kratkotrajno koristi za zaštitu od zloupotrebe forme.</li>
        <li><strong>Kolačići:</strong> samo ako ih prihvatiš, pogledaj odeljak 7.</li>
      </ul>

      <h2>3. Svrha i pravni osnov</h2>
      <ul>
        <li><strong>Procena saradnje i poziv:</strong> na osnovu tvog pristanka datog u prijavi.</li>
        <li><strong>Podaci o zdravlju:</strong> isključivo na osnovu tvog izričitog pristanka (član 17. ZZPL, član 9. GDPR), da bi Nikola procenio da li je program odgovarajući za tebe.</li>
        <li><strong>Email obaveštenja u vezi sa prijavom i programom:</strong> na osnovu pristanka; od emailova možeš da se odjaviš linkom u svakom emailu.</li>
        <li><strong>Zaštita sajta od zloupotrebe:</strong> na osnovu legitimnog interesa.</li>
        <li><strong>Merenje uspešnosti objava i reklama (Meta Pixel):</strong> samo na osnovu pristanka datog u baneru za kolačiće.</li>
      </ul>

      <h2>4. Ko obrađuje podatke u naše ime</h2>
      <ul>
        <li><strong>MailerLite</strong> (UAB MailerLite, Litvanija, EU): čuvanje kontakata i slanje emailova.</li>
        <li><strong>Hostinger</strong> (Hostinger International Ltd., Kipar, EU): hosting sajta i email obaveštenja.</li>
        <li><strong>Meta</strong> (Meta Platforms Ireland Ltd., Irska, EU): Meta Pixel, samo uz tvoj pristanak.</li>
      </ul>
      <p>Ovi pružaoci usluga mogu podatke prenositi van Srbije i EU (npr. u SAD) uz odgovarajuće mere zaštite, kao što su standardne ugovorne klauzule. Podatke ne prodajemo i ne ustupamo drugima u marketinške svrhe.</p>

      <h2>5. Koliko čuvamo podatke</h2>
      <ul>
        <li>Podatke iz prijave, ako ne dođe do saradnje: <strong>12 meseci</strong> od prijave, nakon čega ih brišemo.</li>
        <li>Rezervnu kopiju prijave na serveru (koristi se samo ako email servis privremeno ne radi): najviše <strong>30 dana</strong>.</li>
        <li>Podatke o IP adresi za zaštitu od zloupotrebe: najviše 1 sat.</li>
        <li>Ako započneš saradnju, podaci se čuvaju tokom saradnje i onoliko koliko zakon nalaže.</li>
      </ul>

      <h2>6. Tvoja prava</h2>
      <p>Imaš pravo da zatražiš pristup svojim podacima, njihovu ispravku ili brisanje, ograničenje obrade i prenos podataka, kao i da uložiš prigovor na obradu. U svakom trenutku možeš da povučeš pristanak, bez uticaja na zakonitost obrade pre povlačenja. Zahtev pošalji na {{KLIJENT: kontakt email}}; odgovaramo u roku od 30 dana.</p>
      <p>Imaš pravo da podneseš pritužbu Povereniku za informacije od javnog značaja i zaštitu podataka o ličnosti (Bulevar kralja Aleksandra 15, 11120 Beograd, <a href="https://www.poverenik.rs" rel="noopener" target="_blank">www.poverenik.rs</a>). Korisnici iz EU mogu se obratiti i nadzornom organu u svojoj državi.</p>

      <h2 id="kolacici">7. Kolačići i Meta Pixel</h2>
      <p>Sajt koristi neophodno lokalno skladište pregledača da zapamti tvoju odluku o kolačićima (6 meseci) i odgovore iz prijave dok je ne pošalješ (do zatvaranja taba).</p>
      <p>Uz tvoj pristanak koristimo Meta Pixel, koji Meti šalje podatke o poseti (npr. da je stranica otvorena, da je prijava započeta ili poslata) radi merenja i unapređenja objava i reklama. Pixel se ne učitava dok ne klikneš „Prihvatam“. Odluku možeš da promeniš bilo kada preko linka „Podešavanja kolačića“ u dnu stranice.</p>

      <h2>8. Maloletna lica</h2>
      <p>Prijava je namenjena punoletnim licima. Ne prikupljamo svesno podatke osoba mlađih od 18 godina.</p>

      <h2>9. Izmene politike</h2>
      <p>O značajnim izmenama obaveštavamo na ovoj stranici, uz datum poslednje izmene na vrhu.</p>
    </article>
  </main>

  <footer class="footer">
    <div class="container footer__inner">
      <ul class="footer__legal">
        <li><a href="/politika-privatnosti">Politika privatnosti</a></li>
        <li><a href="/uslovi-koriscenja">Uslovi korišćenja</a></li>
        <li><button type="button" data-cookie-settings>Podešavanja kolačića</button></li>
      </ul>
      <p>© 2026 Nikola Dišić. Sva prava zadržana.</p>
    </div>
  </footer>

  <div class="cookie" id="cookie-banner" role="region" aria-label="Kolačići" hidden>
    <p>Koristimo kolačiće (Meta Pixel) da izmerimo koliko su naše objave i reklame korisne. Ništa se ne učitava bez tvog pristanka. Više u <a href="/politika-privatnosti">Politici privatnosti</a>.</p>
    <div class="cookie__actions">
      <button type="button" class="btn btn--ghost" data-consent="denied">Odbijam</button>
      <button type="button" class="btn btn--ghost" data-consent="granted">Prihvatam</button>
    </div>
  </div>

  <script type="module" src="/assets/js/legal.js"></script>
</body>
</html>
```

- [ ] **Step 5: Napiši `uslovi-koriscenja.html`**

```html
<!doctype html>
<html lang="sr-Latn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Uslovi korišćenja | Nikola Dišić</title>
  <link rel="icon" href="/assets/img/favicon.png">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body data-pixel-id="{{KLIJENT: Meta Pixel ID}}">
  <main class="section">
    <article class="container container--narrow legal">
      <p><a href="/">← Nazad na početnu</a></p>
      <h1>Uslovi korišćenja</h1>
      <p class="micro">Poslednja izmena: {{KLIJENT: datum objave}}</p>

      <h2>1. O usluzi</h2>
      <p>Ovaj sajt vodi {{KLIJENT: pravni status i puno ime}} („Pružalac“). Pružalac nudi uslugu online coachinga: personalizovan plan treninga i ishrane, praćenje napretka i podršku tokom programa „6 nedelja do rezultata“. Popunjavanjem prijave ne nastaje ugovor niti obaveza plaćanja; prijava služi da Pružalac proceni da li je program odgovarajući i da te kontaktira.</p>

      <h2>2. Zaključenje saradnje i plaćanje</h2>
      <p>Cena, trajanje i način plaćanja dogovaraju se nakon besplatne konsultacije i potvrđuju pisanim putem (email ili poruka) pre početka programa. {{KLIJENT: način plaćanja i rok, npr. uplata na račun pre početka}}.</p>

      <h2>3. Zdravlje i medicinski disklejmer</h2>
      <p>Program ne zamenjuje savet, dijagnozu ni lečenje od strane lekara ili drugog zdravstvenog radnika. Ako imaš zdravstveno stanje (npr. insulinsku rezistenciju, hormonski poremećaj, trudnoću, povredu ili hroničnu bolest), pre početka programa konsultuj se sa lekarom. Obavezna si da Pružaoca obavestiš o zdravstvenim stanjima koja mogu uticati na trening i ishranu. Treniraš i menjaš ishranu na sopstvenu odgovornost.</p>

      <h2>4. Rezultati</h2>
      <p>Rezultati prikazani na sajtu su stvarni rezultati klijentkinja, ali su individualni i zavise od početnog stanja, zdravlja, doslednosti i drugih faktora. Oni ne predstavljaju obećanje istog ishoda za svaku osobu, osim u okviru garancije opisane u odeljku 5.</p>

      <h2 id="garancija">5. Garancija povrata novca</h2>
      <p>Ako ispoštuješ plan, a ne postigneš rezultat, Pružalac vraća uplaćeni iznos pod sledećim uslovima:</p>
      <ul>
        <li>{{KLIJENT: uslov garancije 1, npr. redovno slanje nedeljnih check-in-ova}}</li>
        <li>{{KLIJENT: uslov garancije 2, npr. pridržavanje plana treninga i ishrane}}</li>
        <li>{{KLIJENT: definicija rezultata, npr. merljiva promena obima struka ili telesne mase}}</li>
        <li>{{KLIJENT: rok i način podnošenja zahteva, npr. email najkasnije 7 dana po završetku programa}}</li>
      </ul>
      <p>Povraćaj se vrši na isti način na koji je izvršena uplata, u roku od {{KLIJENT: broj}} dana od prihvatanja zahteva. Garancija ne umanjuje prava potrošača propisana Zakonom o zaštiti potrošača.</p>

      <h2>6. Autorska prava</h2>
      <p>Planovi treninga i ishrane, video materijali, tekstovi i ostali sadržaji su autorsko delo Pružaoca. Namenjeni su isključivo tvojoj ličnoj upotrebi i ne smeju se deliti, prodavati ni objavljivati bez pisane saglasnosti.</p>

      <h2>7. Ograničenje odgovornosti</h2>
      <p>U meri u kojoj to zakon dozvoljava, Pružalac ne odgovara za povrede ili štetu nastalu nepridržavanjem uputstava, prećutkivanjem zdravstvenih stanja ili nepravilnim izvođenjem vežbi. Pružalac ne odgovara za privremenu nedostupnost sajta.</p>

      <h2>8. Privatnost</h2>
      <p>Obrada podataka o ličnosti opisana je u <a href="/politika-privatnosti">Politici privatnosti</a>.</p>

      <h2>9. Merodavno pravo i sporovi</h2>
      <p>Na ove uslove primenjuje se pravo Republike Srbije. Sporove nastojimo da rešimo dogovorom; u suprotnom je nadležan stvarno nadležni sud u {{KLIJENT: grad}}, ne dirajući prava potrošača na nadležnost suda prema mestu prebivališta.</p>

      <h2>10. Izmene uslova</h2>
      <p>Pružalac može izmeniti ove uslove objavom na ovoj stranici. Za već zaključenu saradnju važe uslovi koji su važili u trenutku njenog zaključenja.</p>
    </article>
  </main>

  <footer class="footer">
    <div class="container footer__inner">
      <ul class="footer__legal">
        <li><a href="/politika-privatnosti">Politika privatnosti</a></li>
        <li><a href="/uslovi-koriscenja">Uslovi korišćenja</a></li>
        <li><button type="button" data-cookie-settings>Podešavanja kolačića</button></li>
      </ul>
      <p>© 2026 Nikola Dišić. Sva prava zadržana.</p>
    </div>
  </footer>

  <div class="cookie" id="cookie-banner" role="region" aria-label="Kolačići" hidden>
    <p>Koristimo kolačiće (Meta Pixel) da izmerimo koliko su naše objave i reklame korisne. Ništa se ne učitava bez tvog pristanka. Više u <a href="/politika-privatnosti">Politici privatnosti</a>.</p>
    <div class="cookie__actions">
      <button type="button" class="btn btn--ghost" data-consent="denied">Odbijam</button>
      <button type="button" class="btn btn--ghost" data-consent="granted">Prihvatam</button>
    </div>
  </div>

  <script type="module" src="/assets/js/legal.js"></script>
</body>
</html>
```

- [ ] **Step 6: Dodaj stil na kraj `assets/css/style.css`**

```css
/* ---------- Pravne stranice ---------- */
.legal { font-size: 1rem; }
.legal h1 { font-size: clamp(2rem, 6vw, 3rem); }
.legal h2 { font-size: 1.3rem; margin-top: 2em; scroll-margin-top: 24px; }
.legal ul { padding-left: 20px; }
.legal li { margin-bottom: 8px; }
.legal p, .legal li { color: #d6d3cd; }
.legal a { color: var(--accent); }
```

- [ ] **Step 7: Pokreni testove**

Run: `npm run test:js`
Expected: svi testovi prolaze (uključujući opšte invarijante za obe nove stranice).

- [ ] **Step 8: Ručna provera**

`npm run dev`: `/politika-privatnosti` i `/uslovi-koriscenja` se otvaraju; link „Uslovima korišćenja“ iz sekcije garancije na landingu skroluje na naslov „5. Garancija povrata novca“; link u čekboksu kviza otvara politiku u novom tabu i kviz ostaje otvoren; banner radi i na pravnim stranicama.

- [ ] **Step 9: Commit**

```bash
git add politika-privatnosti.html uslovi-koriscenja.html assets/js/legal.js assets/css/style.css tests/js/html.test.js
git commit -m "Politika privatnosti i Uslovi korišćenja"
```

### Task 14: `.htaccess` i provera pred objavu

**Files:**
- Create: `.htaccess`, `scripts/prelaunch-check.sh`
- Test: `tests/js/server-config.test.js`

**Interfaces:**
- Consumes: CSP i blokade iz `scripts/dev-router.php` (Task 6); `{{KLIJENT: …}}` markeri (Taskovi 7–13); `data-video/poster/vtt` (Task 11)
- Produces:
  - Produkciono ponašanje identično dev routeru: HTTPS redirect, čisti URL-ovi, 301 sa `*.html`, 403 za repo fajlove i sve PHP osim `api/submit.php`, sigurnosni headeri, keš
  - `bash scripts/prelaunch-check.sh` → izlaz 0 samo kad nema markera, placeholder slika, nedostajućih fajlova i kad testovi prolaze

- [ ] **Step 1: Napiši test koji pada — `tests/js/server-config.test.js`**

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (file) => readFileSync(new URL(`../../${file}`, import.meta.url), 'utf8');
const CSP = "default-src 'self'; script-src 'self' https://connect.facebook.net; img-src 'self' data: https://www.facebook.com; connect-src 'self' https://www.facebook.com https://connect.facebook.net; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'";

test('.htaccess i dev router imaju identičan CSP', () => {
  assert.ok(read('.htaccess').includes(`Header always set Content-Security-Policy "${CSP}"`));
  const router = read('scripts/dev-router.php').replace(/"\s*\.\s*"/g, '');
  assert.ok(router.includes(CSP), 'dev-router.php CSP se razlikuje');
});

test('.htaccess ima obavezne headere, blokade i čiste URL-ove', () => {
  const h = read('.htaccess');
  for (const needle of [
    'Options -Indexes',
    'Strict-Transport-Security "max-age=31536000; includeSubDomains"',
    'X-Content-Type-Options "nosniff"',
    'Referrer-Policy "strict-origin-when-cross-origin"',
    'Permissions-Policy "camera=(), microphone=(), geolocation=()"',
    'RewriteRule ^(?:\\.git|docs|tests|scripts|dev|storage)(?:/|$) - [F,L]',
    'RewriteRule ^api/submit\\.php$ - [L]',
    'RewriteRule \\.php$ - [F,L]',
    'RewriteRule ^(hvala|politika-privatnosti|uslovi-koriscenja)/?$ $1.html [L]',
    'AddType text/vtt .vtt',
  ]) {
    assert.ok(h.includes(needle), `nedostaje: ${needle}`);
  }
});
```

- [ ] **Step 2: Pokreni i proveri da pada**

Run: `node --test tests/js/server-config.test.js`
Expected: FAIL sa `ENOENT` za `.htaccess`.

- [ ] **Step 3: Napiši `.htaccess`**

```apache
Options -Indexes
DirectoryIndex index.html
AddDefaultCharset utf-8
ServerSignature Off

AddType text/vtt .vtt
AddType text/vcard .vcf
AddType application/javascript .js
AddType image/webp .webp

<IfModule mod_rewrite.c>
  RewriteEngine On

  # HTTPS
  RewriteCond %{HTTPS} !=on
  RewriteCond %{HTTP:X-Forwarded-Proto} !https
  RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

  # Repo fajlovi i PHP biblioteke nisu javni
  RewriteRule ^(?:\.git|docs|tests|scripts|dev|storage)(?:/|$) - [F,L]
  RewriteRule ^(?:package\.json|README\.md|\.gitignore|\.htaccess)$ - [F,L]
  RewriteRule ^api/submit\.php$ - [L]
  RewriteRule \.php$ - [F,L]
  RewriteRule ^api/ - [F,L]

  # *.html → čist URL (samo direktni zahtevi; query string se čuva)
  RewriteCond %{THE_REQUEST} \s/+(hvala|politika-privatnosti|uslovi-koriscenja)\.html[\s?]
  RewriteRule ^ /%1 [R=301,L]
  RewriteCond %{THE_REQUEST} \s/+index\.html[\s?]
  RewriteRule ^ / [R=301,L]

  # Čist URL → fajl
  RewriteRule ^(hvala|politika-privatnosti|uslovi-koriscenja)/?$ $1.html [L]
</IfModule>

<IfModule mod_headers.c>
  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
  Header always set X-Content-Type-Options "nosniff"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
  Header always set X-Frame-Options "DENY"
  Header always set Content-Security-Policy "default-src 'self'; script-src 'self' https://connect.facebook.net; img-src 'self' data: https://www.facebook.com; connect-src 'self' https://www.facebook.com https://connect.facebook.net; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'"
</IfModule>

<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresDefault "access plus 0 seconds"
  ExpiresByType text/css "access plus 1 hour"
  ExpiresByType application/javascript "access plus 1 hour"
  ExpiresByType application/json "access plus 0 seconds"
  ExpiresByType image/webp "access plus 30 days"
  ExpiresByType image/jpeg "access plus 30 days"
  ExpiresByType image/png "access plus 30 days"
  ExpiresByType video/mp4 "access plus 30 days"
  ExpiresByType text/vtt "access plus 30 days"
</IfModule>

<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json text/vtt image/svg+xml
</IfModule>
```

CSS i JS imaju keš od 1 sata jer nema build koraka sa heširanim imenima; izmena stiže korisnicima najkasnije za sat vremena.

- [ ] **Step 4: Pokreni i proveri da prolazi**

Run: `npm run test:js`
Expected: svi testovi prolaze.

- [ ] **Step 5: Napiši `scripts/prelaunch-check.sh`**

```bash
#!/usr/bin/env bash
# Pokreni pre objave i pre promene linka na Instagramu. Izlaz 0 = spremno.
set -uo pipefail
FAILED=0
PAGES=(index.html hvala.html politika-privatnosti.html uslovi-koriscenja.html)

MARKERS=$(grep -rn '{{KLIJENT' "${PAGES[@]}" assets 2>/dev/null || true)
if [[ -n "$MARKERS" ]]; then
  echo "✗ Nedostaje sadržaj klijenta ($(wc -l <<<"$MARKERS" | tr -d ' ') mesta):"
  sed 's/^/    /' <<<"$MARKERS"
  FAILED=1
else
  echo "✓ Nema {{KLIJENT}} markera"
fi

if grep -q 'class="ph' "${PAGES[@]}"; then
  echo "✗ Placeholder slike (.ph) još postoje — zameni ih pravim <img> WebP slikama"
  FAILED=1
else
  echo "✓ Nema placeholder slika"
fi

for file in assets/img/favicon.png assets/img/og.jpg assets/nikola-disic.vcf; do
  if [[ ! -f "$file" ]]; then echo "✗ Nedostaje $file"; FAILED=1; fi
done

READY=0
while IFS= read -r path; do
  [[ -z "$path" ]] && continue
  if [[ -f "${path#/}" ]]; then
    [[ "$path" == *.mp4 ]] && READY=$((READY + 1))
  else
    echo "✗ hvala.html referencira fajl koji ne postoji: $path"
    FAILED=1
  fi
done < <(grep -o 'data-\(video\|poster\|vtt\)="/[^"]*"' hvala.html | sed 's/^[^"]*"//; s/"$//')
echo "ℹ FAQ videa spremno: $READY od 8 (sekcija je sakrivena dok je 0)"

if ls assets/video 2>/dev/null | grep -q -- '-test\.'; then
  echo "✗ Test video fajlovi su u assets/video"
  FAILED=1
fi

if npm test >/dev/null 2>&1; then
  echo "✓ Testovi prolaze"
else
  echo "✗ Testovi padaju (npm test)"
  FAILED=1
fi

exit $FAILED
```

- [ ] **Step 6: Proveri da skripta hvata trenutno stanje**

Run: `bash scripts/prelaunch-check.sh; echo "izlaz: $?"`
Expected: lista `{{KLIJENT}}` markera, poruka o placeholder slikama, nedostaju `favicon.png` i `og.jpg`, `FAQ videa spremno: 0 od 8`, `✓ Testovi prolaze`, `izlaz: 1`.

- [ ] **Step 7: Commit**

```bash
git add .htaccess scripts/prelaunch-check.sh tests/js/server-config.test.js
git commit -m "Apache konfiguracija i provera pred objavu"
```

---

### Task 15: Deploy na Hostinger i provera na produkciji

Ovaj task je operativni (nalozi, paneli, pravi podaci). Radi ga osoba sa pristupom GitHubu, Hostingeru, MailerLite-u i Meta Business nalogu. Svaki korak koji objavljuje ili menja nalog klijenta se prethodno potvrđuje sa vlasnikom naloga.

**Files:**
- Na serveru (van repoa): `domains/<domen>/config.php`, `domains/<domen>/storage/`

**Interfaces:**
- Consumes: ceo repo (Taskovi 1–14), `api/config.example.php` (Task 5), `bash scripts/prelaunch-check.sh` (Task 14)
- Produces: sajt uživo na `https://<domen>`, Instagram bio link ka njemu

- [ ] **Step 1: Ubaci sadržaj klijenta i prođi proveru**

Zameni sve `{{KLIJENT: …}}` markere pravim sadržajem, `.ph` placeholdere pravim `<img src="/assets/img/….webp" alt="…" width="…" height="…" loading="lazy">` slikama (hero slika bez `loading="lazy"`), dodaj `assets/img/favicon.png` (512×512) i `assets/img/og.jpg` (1200×630).

Run: `bash scripts/prelaunch-check.sh`
Expected: izlaz 0 (broj FAQ videa može biti 0).

- [ ] **Step 2: GitHub repo**

```bash
gh repo create nikola-disic-sajt --private --source=. --remote=origin
git push -u origin main
```
Expected: privatni repo sa svim commit-ovima; `dev/` i `storage/` nisu u repou (`git ls-files | grep -E '^(dev|storage)/'` ne vraća ništa).

- [ ] **Step 3: MailerLite priprema**

1. Subscribers → Fields → napravi polja (tačni ključevi): `cilj`, `koliko_dugo`, `probala`, `prepreke`, `spremnost`, `vreme_poziva`, `kontakt_kanal`, `izvor` (Text) i `vaznost` (Number). Proveri da plan dozvoljava 9 custom polja.
2. Subscribers → Groups → napravi „Prijave – sajt“ i „Nurture“; zapiši ID-jeve.
3. Integrations → API → novi token; zapiši ga samo u `config.php`.

- [ ] **Step 4: Hostinger priprema**

1. hPanel → Websites → domen → PHP Configuration → PHP 8.2 ili novije; ekstenzije `curl` i `mbstring` uključene.
2. hPanel → Security → SSL aktivan.
3. hPanel → Emails → napravi `prijave@<domen>` (SPF i DKIM uključeni) da obaveštenja ne idu u spam.
4. File Manager: `public_html` mora biti prazan pre prvog Git deploy-a (sačuvaj i ukloni podrazumevani `default.php`/`index.html`).
5. File Manager: u `domains/<domen>/` (folder koji sadrži `public_html`) napravi `storage/` (prava 700) i `config.php` (prava 600) po šablonu `api/config.example.php` sa pravim vrednostima: API token, ID-jevi grupa, `mail_to`, `mail_from_address` = `prijave@<domen>`, `allowed_origins` = `['https://<domen>', 'https://www.<domen>']`, `'dry_run' => false`.

- [ ] **Step 5: Hostinger Git auto-deploy**

1. hPanel → Websites → domen → Advanced → Git → Create repository: SSH URL repoa, grana `main`, direktorijum prazan (= `public_html`).
2. Kopiraj SSH ključ koji prikaže Hostinger → GitHub repo → Settings → Deploy keys → Add (samo čitanje).
3. Klikni Deploy; zatim uključi Auto Deployment i kopiraj webhook URL → GitHub → Settings → Webhooks → Add webhook (Content type `application/json`, samo push događaj).
4. Napravi mali commit (npr. razmak u README), push → proveri u hPanel-u da je deploy pokrenut automatski.

Expected: `https://<domen>` prikazuje landing.

- [ ] **Step 6: Provera servera**

```bash
D=https://<domen>
curl -sI http://<domen>/ | head -1                              # 301
curl -s -o /dev/null -w '%{http_code}\n' $D/                     # 200
curl -sI $D/ | grep -iE 'content-security-policy|strict-transport|x-content-type|referrer-policy|permissions-policy'
for p in /.git/config /api/lib/handler.php /api/config.example.php /docs/ /tests/security.sh /package.json /README.md; do
  echo "$p $(curl -s -o /dev/null -w '%{http_code}' $D$p)"      # 403 ili 404
done
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' "$D/hvala.html?ime=Test"   # 301 → /hvala?ime=Test
curl -s -o /dev/null -w '%{http_code}\n' $D/hvala                # 200
curl -s -o /dev/null -w '%{http_code}\n' $D/api/submit.php       # 405
curl -s -o /dev/null -w '%{http_code}\n' -X POST $D/api/submit.php -H 'Content-Type: application/json' -H 'Origin: https://evil.example' --data '{}'   # 403
```
Expected: vrednosti iz komentara; svih 5 headera prisutno. Proveri i https://securityheaders.com za domen → ocena A ili bolja.

- [ ] **Step 7: End-to-end prijave na produkciji**

1. Sa iPhone Safari-ja: `https://<domen>/?utm_source=ig&utm_medium=social&utm_content=link_in_bio` → kviz → prijava sa svojim emailom → `/hvala?ime=…`.
2. MailerLite: kontakt postoji, svih 11 polja popunjeno, `izvor` = `ig / social / link_in_bio`, grupa „Prijave – sajt“.
3. Email obaveštenje stiže na `mail_to` (proveri i spam), `tel:` i WhatsApp linkovi rade na telefonu.
4. Sa Android Chrome-a: prijava sa odgovorom „Trenutno nisam u mogućnosti“ → kontakt je u obe grupe.
5. Fallback: u `config.php` privremeno pokvari `mailerlite_api_key` → nova prijava i dalje vodi na `/hvala`, email stiže, `storage/leads.log` ima red. Vrati ključ.
6. Rate limit: 6 brzih POST zahteva sa `-H 'Origin: https://<domen>'` i validnim telom → šesti vraća 429. Obriši `storage/ratelimit/`.
7. Obriši test kontakte iz MailerLite-a i `storage/leads.log`.

- [ ] **Step 8: Performanse, pristupačnost, Pixel**

1. Chrome DevTools → Lighthouse → Mobile → landing: Performance ≥ 90, Accessibility ≥ 90.
2. DevTools → Network (Disable cache) → landing bez videa: ukupno preneto < 1 MB.
3. VoiceOver na iPhone-u: prođi kviz do kraja; pitanja, odgovori i greške se čitaju.
4. Meta Pixel Helper (Chrome ekstenzija): pre pristanka nema Pixela; posle „Prihvatam“ `PageView`; otvaranje kviza `StartQuiz` i `QuizStep`; slanje `Lead`. Meta Events Manager → Test events potvrđuje iste događaje.

- [ ] **Step 9: Pravni pregled i čuvanje podataka**

1. Pravnik pregleda `politika-privatnosti.html` i `uslovi-koriscenja.html`; izmene se commit-uju i deploy-uju.
2. Postavi podsetnik u kalendaru na svaka 3 meseca: u MailerLite-u izbrisati kontakte iz grupe „Prijave – sajt“ starije od 12 meseci koji nisu postali klijenti (rok iz Politike privatnosti).

- [ ] **Step 10: Puštanje**

1. Instagram bio link → `https://<domen>/?utm_source=ig&utm_medium=social&utm_content=link_in_bio`.
2. Stara MailerLite stranica ostaje objavljena još 14 dana (stari linkovi u objavama), zatim se unpublish-uje.
3. Prve 3 prijave posle puštanja proveri ručno u MailerLite-u i inbox-u.

- [ ] **Step 11: Dodavanje FAQ videa kad budu snimljeni**

Za svaki snimak: `bash scripts/encode-faq.sh <snimak> NN-slug`, proveri `.vtt`, popuni `data-video`, `data-poster`, `data-vtt` u `hvala.html`, `bash scripts/prelaunch-check.sh`, commit i push (auto-deploy). Proveri na telefonu da odgovor kreće na klik.
