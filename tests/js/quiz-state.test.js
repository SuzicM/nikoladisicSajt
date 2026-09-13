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
