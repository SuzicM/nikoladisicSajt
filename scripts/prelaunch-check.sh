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
