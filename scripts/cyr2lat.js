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
  const isLetter = (c) => c !== undefined && /\p{L}/u.test(c);
  const isUpper = (c) => c !== undefined && /\p{Lu}/u.test(c);

  return chars.map((ch, i) => {
    if (DIGRAPHS[ch]) {
      const next = chars[i + 1];
      const prev = chars[i - 1];
      const upper = isLetter(next) ? isUpper(next) : isUpper(prev);
      return upper ? DIGRAPHS[ch] : MAP[ch];
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
