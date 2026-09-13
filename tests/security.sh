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
