# Nikola Dišić – sajt za prijave (lead funnel) – dizajn

**Datum:** 2026-09-13
**Status:** odobren dizajn, čeka pregled spec-a
**Zamenjuje:** MailerLite subscribe page `nikola-disic-wnalvh.subscribepage.io`

## 1. Cilj

Sajt pretvara saobraćaj sa Instagrama (link in bio) u **kvalifikovane prijave za saradnju** i odgovara na najčešće prigovore **pre** prodajnog poziva. Plaćanja na sajtu nema. Nikola lično zove svaki lead.

**Kriterijumi uspeha**
- Svaka prijava stiže u MailerLite sa svim odgovorima iz kviza i Nikola dobija email obaveštenje.
- Nikola iz naslova email-a vidi prioritet leada (spremnost + važnost).
- Posle prijave lead vidi video-odgovore na prigovore.
- Sajt ima Politiku privatnosti, Uslove korišćenja i cookie banner za Meta Pixel.
- Lighthouse mobilni ≥ 90, landing bez videa < 1 MB.

## 2. Problemi postojećeg sajta koje rešavamo

1. CTA obećava „Zakaži besplatnu konsultaciju“, a forma je prijava na newsletter.
2. Stranica posle prijave je generička i na engleskom, bez sledećeg koraka.
3. Nema kvalifikacije (budžet, spremnost, telefon).
4. Nikola se ne pojavljuje (fotografija, priča, autoritet).
5. Kontradikcija „6 nedelja“ i „za nekoliko meseci“.
6. Rezultati bez brojki, trajanja i citata.
7. Garancija bez uslova.
8. Nema Politike privatnosti ni Uslova korišćenja, iako se prikupljaju zdravstveni podaci.
9. Nema sekcija „Kako funkcioniše“, „Za koga je / nije“ ni FAQ.

## 3. Tok korisnika

```
Instagram bio → / (landing)
                  ↓ CTA "Prijavi se za saradnju"
               Kviz (fullscreen overlay na landingu)
                  6 kvalifikacionih pitanja → lični podaci + saglasnost
                  ↓ POST JSON
               /api/submit.php → MailerLite API (kontakt, custom polja, grupe)
                               → email Nikoli
                               → (fallback) log fajl ako MailerLite ne odgovori
                  ↓ uspeh
               /hvala?ime=… (potvrda, sledeći koraci, video FAQ)
                  ↓
               Nikola zove lead u roku od 24h (radnim danima)
```

## 4. Tehnički pristup

Čist HTML/CSS/JavaScript bez build koraka i bez zavisnosti, plus jedan PHP endpoint. Hosting na Hostingeru, kod na GitHubu (privatan repo), auto-deploy preko Hostinger Git integracije sa grane `main`.

### Struktura repoa

```
/index.html                  landing + kviz overlay
/hvala.html                  potvrda + video FAQ
/politika-privatnosti.html
/uslovi-koriscenja.html
/.htaccess                   HTTPS, čisti URL-ovi, headeri, keš, zaštita /api
/assets/css/style.css
/assets/js/quiz.js           kviz: tok, validacija, sessionStorage, slanje
/assets/js/faq.js            accordion + autoplay
/assets/js/consent.js        cookie banner + uslovno učitavanje Meta Pixela
/assets/img/                 transformacije i fotografije (WebP)
/assets/video/               FAQ MP4, posteri (JPG/WebP), titlovi (VTT)
/api/submit.php              validacija, MailerLite, email, rate limit, log
/api/config.example.php      šablon konfiguracije (u Gitu)
```

Van `public_html` (nije u Gitu, postavlja se ručno jednom):
```
/config.php                  MailerLite API ključ, ID-jevi grupa, email adrese, dozvoljeni Origin
/storage/leads.log           fallback log prijava (redovi stariji od 30 dana brišu se pri svakom upisu)
/storage/ratelimit/          brojači po IP adresi (fajlovi stariji od 1h brišu se pri svakom zahtevu)
```

Čisti URL-ovi: `/hvala`, `/politika-privatnosti`, `/uslovi-koriscenja` (bez `.html`).

## 5. Landing stranica (`/`)

Mobile-first, bez navigacije i spoljnih linkova osim footera. Na mobilnom je sticky CTA dugme pri dnu ekrana. Vizuelni stil: tamno, sportski, čisto (crna / off-white + jedna akcentna boja iz Nikolinog Instagrama). Konačne boje i font se biraju kad stignu materijali.

| # | Sekcija | Sadržaj |
|---|---|---|
| 1 | Hero | Naslov „6 nedelja do rezultata“, podnaslov za žene koje su probale sve, Nikolina fotografija ili kratak nemi video, CTA, mikrotekst „Traje 60 sekundi · Nikola te lično zove“ |
| 2 | Traka rezultata | 3–4 pre/posle u horizontalnom slideru: ime, brojka, trajanje |
| 3 | Da li ti je poznato? | 4–5 bol-tačaka |
| 4 | Za koga je / nije | Dve kolone ✓ / ✗ |
| 5 | Kako funkcioniše | Prijava → Nikola zove (besplatna konsultacija) → personalizovan plan |
| 6 | Šta dobijaš | Trening, ishrana bez gladovanja, podrška (kanal i učestalost), praćenje napretka |
| 7 | Garancija | Blok sa jasnim uslovima, identičnim onima u Uslovima korišćenja |
| 8 | O Nikoli | Fotografija, priča, iskustvo, sertifikati, broj klijenata |
| 9 | Testimonijali | Citati ili screenshot poruka, opciono kratki video |
| 10 | Kratak FAQ | 4–5 tekstualnih pitanja (accordion, bez videa) |
| 11 | Završni CTA | Ponovljeno obećanje + dugme; ograničenje mesta samo ako je istinito |
| — | Footer | © Nikola Dišić, Politika privatnosti, Uslovi korišćenja, IG / TikTok / YouTube |

CTA dugme („Prijavi se za saradnju“) se nalazi posle sekcija 1, 2, 5, 7, 9 i 11 i uvek otvara kviz.

„6 nedelja“ je jedini vremenski okvir obećanja. Kod transformacija piše stvarno trajanje.

## 6. Kviz

### Ponašanje
- Fullscreen overlay na mobilnom, centrirana kartica na desktopu.
- Jedno pitanje po ekranu, traka napretka („Korak N od 7“), dugme Nazad.
- Pitanja sa jednim odgovorom (1, 2, 5, 6) posle klika automatski prelaze dalje.
- Pitanja sa više odgovora (3, 4) imaju dugme „Dalje“, aktivno kad je izabran bar jedan odgovor.
- Zatvaranje (X / Esc) čuva odgovore u `sessionStorage`; ponovno otvaranje nastavlja od istog koraka.
- Skala 1–10 na mobilnom: dva reda po 5 dugmadi.
- Pristupačnost: navigacija tastaturom, focus trap, ARIA oznake, `prefers-reduced-motion`.

### Pitanja (ponuđeni odgovori su i server-side whitelist)

1. **Koji ti je glavni cilj?** (jedan)
   `Izgubiti kilograme i dodati mišićnu masu` · `Dodati kilograme i oblikovati telo` · `Rešavanje zdravstvenih problema (insulinska rezistencija, hormoni…)`
2. **Koliko dugo pokušavaš da dođeš do tog cilja?** (jedan)
   `Tek počinjem` · `Do 1 godine` · `1–3 godine` · `Duže od 3 godine`
3. **Šta si do sada probala?** (više)
   `Dijete` · `Teretanu sama` · `Grupne treninge` · `Drugog trenera` · `Aplikacije` · `Ništa od navedenog`
4. **Šta te je najviše sprečavalo?** (više)
   `Nedostatak vremena` · `Ne znam šta da jedem` · `Gubim motivaciju` · `Zdravstveni problemi` · `Nemam plan`
5. **Koliko ti je važno da ovo rešiš u narednih 6 nedelja?** (jedan, celi broj 1–10)
6. **Da li si spremna da uložiš u saradnju sa trenerom ako vidiš da je program za tebe?** (jedan)
   `Da, spremna sam` · `Želim prvo da čujem detalje` · `Trenutno nisam u mogućnosti`
7. **Lični podaci** – naslov „Skoro gotovo, gde da te Nikola pozove?“
   - Ime (obavezno, `autocomplete="given-name"`)
   - Email (obavezno, `type="email"`)
   - Telefon (obavezno, `type="tel"`, prefiks `+381` unapred popunjen i izmenljiv)
   - Najbolje vreme za poziv (obavezno): `Pre podne` · `Popodne` · `Uveče`
   - Kontakt preko (obavezno): `Poziv` · `WhatsApp` · `Viber`
   - Čekboks (obavezan): „Saglasna sam sa [Politikom privatnosti] i obradom podataka o zdravlju u svrhu procene saradnje.“ Link se otvara u novom tabu.
   - Dugme: „Pošalji prijavu“

### Validacija i greške (frontend)
- Greška se prikazuje ispod polja posle napuštanja polja (blur), na srpskom.
- Tokom slanja dugme je zaključano, sa spinnerom.
- Neuspeh (mreža, 4xx ili 5xx): odgovori ostaju, poruka „Nešto nije u redu. Pokušaj ponovo ili piši Nikoli na Instagram“ + link ka Instagram DM-u.
- Uspeh: redirect na `/hvala?ime=<ime>`, uz Pixel događaj `Lead` ako je dat pristanak.
- UTM parametri (`utm_source`, `utm_medium`, `utm_content`, `utm_campaign`) se čitaju sa landing URL-a, čuvaju u `sessionStorage` i šalju uz prijavu.

## 7. Backend: `/api/submit.php`

### Redosled obrade
1. Samo `POST`, `Content-Type: application/json`, telo ≤ 10 KB. Inače 405/415/413.
2. `Origin` mora biti domen sajta. Inače 403.
3. Honeypot polje popunjeno ili vreme od otvaranja kviza < 5 s → odgovor 200 bez obrade (tiho odbijanje).
4. Rate limit: najviše 5 prijava po IP adresi na sat. Inače 429.
5. Validacija (inače 422, bez detalja o tome koje pravilo je palo):
   - pitanja 1, 2, 6, vreme poziva, kanal: tačno jedan odgovor sa whitelist-e
   - pitanja 3, 4: niz od 1+ jedinstvenih vrednosti sa whitelist-e
   - pitanje 5: ceo broj 1–10
   - ime: 1–60 karaktera, Unicode slova, razmak, crtica, apostrof
   - email: `filter_var(FILTER_VALIDATE_EMAIL)`, ≤ 254 karaktera
   - telefon: posle uklanjanja razmaka `^\+?[0-9]{8,15}$`
   - saglasnost: `true`
   - UTM vrednosti: ≤ 100 karaktera, samo `[A-Za-z0-9_\-.]`, ostalo se odbacuje
6. MailerLite API: upsert kontakta (email kao ključ), popunjavanje polja, dodavanje u grupu „Prijave – sajt“, a za odgovor `Trenutno nisam u mogućnosti` i u grupu „Nurture“.
7. Email Nikoli (uvek, i kada MailerLite ne uspe).
8. Ako MailerLite ne uspe: upis JSON reda u `storage/leads.log` i greška u PHP error log. Korisnik i dalje dobija uspeh ako je email Nikoli poslat.
9. Ako ne uspeju ni MailerLite ni email: upis u log i odgovor 500.

### MailerLite polja

| Polje | Tip | Izvor |
|---|---|---|
| `name` | ugrađeno | ime |
| `email` | ugrađeno | email |
| `phone` | ugrađeno | telefon |
| `cilj` | tekst | pitanje 1 |
| `koliko_dugo` | tekst | pitanje 2 |
| `probala` | tekst | pitanje 3, spojeno sa „, “ |
| `prepreke` | tekst | pitanje 4, spojeno sa „, “ |
| `vaznost` | broj | pitanje 5 |
| `spremnost` | tekst | pitanje 6 |
| `vreme_poziva` | tekst | lični podaci |
| `kontakt_kanal` | tekst | lični podaci |
| `izvor` | tekst | `utm_source / utm_medium / utm_content / utm_campaign` |

Custom polja i grupe se ručno kreiraju u MailerLite nalogu pre puštanja. Treba proveriti da li besplatni plan ima ograničenje broja custom polja (potrebno je 9).

### Email Nikoli
- `From` i `To`: fiksne adrese iz `config.php`.
- `Reply-To`: email leada, tek posle validacije i uklanjanja `\r` i `\n`.
- Naslov: `🔥 Nova prijava: <ime> (spremnost: <odgovor 6>, važnost <5>/10)`
- Telo (HTML, sve vrednosti kroz `htmlspecialchars`): svi odgovori, `tel:` link, `https://wa.me/<broj>` link, UTM izvor, vreme prijave.

## 8. Stranica posle prijave (`/hvala`)

- `<meta name="robots" content="noindex">`
- Redosled:
  1. Potvrda „Hvala, <ime>! Tvoja prijava je stigla ✓“. Ime iz `?ime=` se ubacuje preko `textContent`, najviše 60 karaktera; bez parametra piše „Hvala!“.
  2. Šta sledi: poziv u roku od 24h radnim danima u izabranom terminu · poziv traje ~15 min i besplatan je · dugme „Sačuvaj broj“ (statički `.vcf` fajl „Nikola Dišić – Trener“).
  3. „Dok čekaš poziv, Nikola ti odgovara na najčešća pitanja“: video FAQ accordion.
  4. 2–3 transformacije.
  5. Footer.

### Video FAQ
- Otvoreno je samo jedno pitanje; otvaranje drugog zatvara i pauzira prethodno.
- Klik na pitanje otvara panel i pokreće `video.play()` sa zvukom (klik je korisnička interakcija).
- Ako `play()` bude odbijen: prikazuje se veliko ▶ dugme.
- Zatvaranje panela pauzira video.
- Na kraju videa: dugme „Sledeće pitanje →“ (nema ga posle poslednjeg).
- `<video playsinline preload="none" poster="…">`, format 9:16, na desktopu najviše ~360px širine.
- Titlovi: VTT `<track>`, isključeni po podrazumevanoj vrednosti, uključuju se u playeru.
- Pitanja su definisana u HTML-u. Pitanje bez video fajla se ne renderuje, pa stranica može uživo i pre snimanja.

### Pitanja za snimanje (redosled na stranici)
1. Koliko košta saradnja i zašto vredi?
2. Probala sam sve, zašto bi ovo bilo drugačije?
3. Nemam vremena, koliko mi dnevno treba?
4. Imam insulinsku rezistenciju / hormonske probleme, da li je program za mene?
5. Da li moram da idem u teretanu ili može kod kuće?
6. Da li moram da merim hranu i da se odričem omiljenih stvari?
7. Kako tačno funkcioniše garancija?
8. Kako izgleda saradnja online i šta se dešava posle 6 nedelja?

### Snimanje i obrada
- Snimanje: telefon, vertikalno, 30–90 s, prirodno svetlo spreda, bubica, prva rečenica je direktan odgovor, kraj „Pričamo detaljnije na pozivu“.
- Obrada: `ffmpeg` → H.264, 720×1280, AAC, `-movflags +faststart`, cilj 5–15 MB po videu. Poster je kadar sa ~1 s. VTT titlovi se generišu pa ručno proveravaju.

## 9. Pravne stranice

Tekstovi se pišu kao osnova po ZZPL-u i GDPR-u. **Pre objave ih pregleda pravnik.**

### Politika privatnosti
Rukovalac (pravni status Nikole, adresa, PIB ako postoji, kontakt email) · kategorije podataka i svrha · podaci o zdravlju kao posebna vrsta podataka, obrada na osnovu izričitog pristanka · obrađivači: MailerLite, Hostinger, Meta (Pixel, samo uz pristanak) · prenos podataka van Srbije/EU · rokovi čuvanja: 12 meseci za leadove bez saradnje, 30 dana za fallback log · kolačići · prava lica i povlačenje pristanka · pravo na pritužbu Povereniku za informacije od javnog značaja i zaštitu podataka o ličnosti.

### Uslovi korišćenja
Opis usluge (online coaching) · medicinski disklejmer · rezultati su individualni · uslovi garancije povrata novca (identični bloku na landingu) · autorska prava na planove i sadržaj · ograničenje odgovornosti · merodavno pravo Republike Srbije.

## 10. Meta Pixel i cookie banner

- Pixel se **ne učitava** dok korisnik ne klikne „Prihvatam“.
- Banner: kratak tekst, dugmad „Prihvatam“ i „Odbijam“ jednake vizuelne težine, link ka Politici privatnosti. Izbor se čuva u `localStorage` na 6 meseci.
- Link „Podešavanja kolačića“ u footeru ponovo otvara banner.
- Događaji: `PageView` (sve stranice), `StartQuiz` (custom, otvaranje kviza), `QuizStep` (custom, parametar `step`), `Lead` (uspešno slanje).
- Banner se na mobilnom ne preklapa sa sticky CTA dugmetom.
- Pixel ID nije tajan: upisuje se kao `data-pixel-id` atribut na `<body>` svake stranice, a `consent.js` ga čita odatle.

## 11. Bezbednost

- **XSS:** korisnički unos se na frontendu ubacuje samo preko `textContent`; u email-u sve prolazi kroz `htmlspecialchars`.
- **CSP:** `default-src 'self'`; `script-src 'self' https://connect.facebook.net`; `img-src 'self' data: https://www.facebook.com`; `connect-src 'self' https://www.facebook.com`; `frame-ancestors 'none'`; bez inline skripti.
- **Headeri:** HSTS, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`.
- **Server-side whitelist validacija** svih polja (sekcija 7).
- **Email header injection:** fiksni `From`/`To`, sanitizovan `Reply-To`.
- **Botovi:** honeypot, minimalno vreme, rate limit po IP adresi, provera `Origin`. Cloudflare Turnstile se dodaje samo ako spam prođe.
- **Konfiguracija i podaci** van `public_html`; `.htaccess` dozvoljava samo `/api/submit.php` u `/api/`; listanje direktorijuma isključeno; PHP greške se ne prikazuju.
- MailerLite greške se ne vraćaju korisniku.
- Nema baze podataka ni paketa trećih strana.

## 12. Deploy

1. Privatan GitHub repo, `main` = produkcija.
2. Hostinger → Git → povezivanje repoa, auto-deploy na push (webhook).
3. Jednokratno na serveru: `config.php` i `storage/` iznad `public_html`, sa pravima 600/700.
4. Jednokratno u MailerLite-u: 9 custom polja, grupe „Prijave – sajt“ i „Nurture“, API ključ.
5. Instagram bio link se menja na novi domen tek posle testiranja na produkciji.

## 13. Testiranje

- **Uređaji:** iPhone Safari, Android Chrome, desktop Chrome i Firefox (kviz, autoplay, sticky CTA, cookie banner).
- **End-to-end:** prijava stiže u MailerLite test grupu sa svim poljima i email stiže Nikoli; simuliran pad MailerLite-a (pogrešan ključ) i dalje šalje email i upisuje log.
- **Bezbednosna curl skripta** (`tests/security.sh`) proverava da su odbijeni: `<script>` u imenu (422), predugačka polja, nepostojeći odgovori, pogrešan `Origin`, `GET` zahtev, popunjen honeypot, 6. zahtev sa iste IP adrese u sat vremena.
- **Headeri:** securityheaders.com ocena A ili bolje.
- **Performanse:** Lighthouse mobilni ≥ 90; landing bez videa < 1 MB.
- **Pristupačnost:** ceo kviz radi samo tastaturom; screen reader čita pitanja i greške.
- **Pixel:** Meta Pixel Helper potvrđuje da pre pristanka nema zahteva, a posle pristanka da se događaji šalju.

## 14. Ulazi od klijenta

Implementacija počinje bez njih, uz neutralan demo sadržaj; pravi sadržaj se ubacuje pre objave.

- Transformacije: pre/posle fotografije, ime (uz pristanak klijentkinje), brojka, trajanje
- Nikolina fotografija, priča, iskustvo, sertifikati, broj klijenata
- Testimonijali (tekst ili screenshot poruka)
- Tačni uslovi garancije
- Šta tačno ulazi u „podršku“ (kanal i učestalost)
- Linkovi ka Instagramu, TikToku i YouTube-u; Instagram DM link
- Broj telefona za vCard
- Pravni status Nikole (preduzetnik ili fizičko lice), adresa, PIB, kontakt email
- Domen
- MailerLite API ključ, Meta Pixel ID, email adresa za obaveštenja
- 8 snimljenih FAQ videa
- Akcentna boja i font (ili odobrenje predloga)

## 15. Van obima

- Online plaćanje
- Kalendar za zakazivanje
- Blog i dodatne stranice
- Email sekvence u MailerLite-u (samo grupe se pripremaju)
- CMS ili admin panel
