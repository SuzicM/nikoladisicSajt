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

test('index.html: kviz skeleton, honeypot i skripta', () => {
  const html = read('index.html');
  assert.match(html, /<div class="quiz" id="quiz" role="dialog" aria-modal="true" aria-labelledby="quiz-title"[^>]*hidden>/);
  assert.match(html, /<input type="text" id="website" name="website" tabindex="-1" autocomplete="off">/);
  assert.match(html, /data-quiz-body/);
  assert.match(html, /<script type="module" src="\/assets\/js\/landing\.js"><\/script>\s*<\/body>/);
});
