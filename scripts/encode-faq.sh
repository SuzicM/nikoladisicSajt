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
  WAV_DIR=$(mktemp -d -t faq)
  trap 'rm -rf "$WAV_DIR"' EXIT
  WAV="$WAV_DIR/audio.wav"
  ffmpeg -loglevel error -y -i "$MP4" -ar 16000 -ac 1 -c:a pcm_s16le "$WAV"
  whisper-cli -m "$MODEL" -l sr -f "$WAV" -ovtt -of "$OUT_DIR/$SLUG" >/dev/null
  node scripts/cyr2lat.js "$OUT_DIR/$SLUG.vtt"
  echo "  Proveri tekst u $OUT_DIR/$SLUG.vtt pre commit-a."
else
  echo "→ Titlovi preskočeni. Za automatske titlove:"
  echo "  brew install whisper-cpp"
  echo "  mkdir -p ~/.whisper && curl -L -o ~/.whisper/ggml-medium.bin https://huggingface.co/ggerganov/whisper.cpp/resolve/main/ggml-medium.bin"
fi

echo "✓ Gotovo. U hvala.html postavi:"
echo "  data-video=\"/$MP4\" data-poster=\"/$JPG\" data-vtt=\"/$OUT_DIR/$SLUG.vtt\""
