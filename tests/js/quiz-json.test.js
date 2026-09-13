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
