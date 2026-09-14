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
