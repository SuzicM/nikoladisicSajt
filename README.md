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
Proveravaj povremeno storage/leads.log: tu završavaju prijave kada MailerLite ne radi ili kada ih zadrži rate limit.

## Dodavanje FAQ videa

```bash
bash scripts/encode-faq.sh ~/Downloads/snimak.mov 01-cena
```

Zatim u `hvala.html` na odgovarajućoj `.faq-item` popuni
`data-video="/assets/video/01-cena.mp4" data-poster="/assets/video/01-cena.jpg" data-vtt="/assets/video/01-cena.vtt"`,
proveri tekst u `.vtt` fajlu i commit-uj.
