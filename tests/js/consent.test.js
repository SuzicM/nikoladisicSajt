import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  CONSENT_KEY, CONSENT_TTL_MS, readConsent, writeConsent, trackCall, isValidPixelId,
} from '../../assets/js/consent.js';

const memoryStorage = (initial = {}) => {
  const data = { ...initial };
  return {
    data,
    getItem: (k) => (k in data ? data[k] : null),
    setItem: (k, v) => { data[k] = String(v); },
  };
};
const throwingStorage = {
  getItem() { throw new Error('blokirano'); },
  setItem() { throw new Error('blokirano'); },
};
const NOW = 1_800_000_000_000;

test('nema odluke → null', () => {
  assert.equal(readConsent(memoryStorage(), NOW), null);
});

test('write pa read vraća odluku', () => {
  const s = memoryStorage();
  writeConsent(s, 'granted', NOW);
  assert.deepEqual(JSON.parse(s.data[CONSENT_KEY]), { value: 'granted', at: NOW });
  assert.equal(readConsent(s, NOW + 1000), 'granted');
  writeConsent(s, 'denied', NOW);
  assert.equal(readConsent(s, NOW), 'denied');
});

test('odluka ističe posle 6 meseci', () => {
  const s = memoryStorage();
  writeConsent(s, 'granted', NOW);
  assert.equal(readConsent(s, NOW + CONSENT_TTL_MS), 'granted');
  assert.equal(readConsent(s, NOW + CONSENT_TTL_MS + 1), null);
});

test('neispravni podaci → null', () => {
  assert.equal(readConsent(memoryStorage({ [CONSENT_KEY]: 'nije json' }), NOW), null);
  assert.equal(readConsent(memoryStorage({ [CONSENT_KEY]: '{"value":"maybe","at":1}' }), NOW), null);
  assert.equal(readConsent(memoryStorage({ [CONSENT_KEY]: '{"value":"granted"}' }), NOW), null);
  assert.equal(readConsent(memoryStorage({ [CONSENT_KEY]: JSON.stringify({ value: 'granted', at: NOW + 60000 }) }), NOW), null);
});

test('storage koji baca izuzetak ne ruši stranicu', () => {
  assert.equal(readConsent(throwingStorage, NOW), null);
  assert.doesNotThrow(() => writeConsent(throwingStorage, 'granted', NOW));
});

test('trackCall bira standardni ili custom događaj', () => {
  assert.deepEqual(trackCall('Lead'), ['track', 'Lead', {}]);
  assert.deepEqual(trackCall('PageView'), ['track', 'PageView', {}]);
  assert.deepEqual(trackCall('StartQuiz'), ['trackCustom', 'StartQuiz', {}]);
  assert.deepEqual(trackCall('QuizStep', { step: 3 }), ['trackCustom', 'QuizStep', { step: 3 }]);
});

test('isValidPixelId', () => {
  assert.equal(isValidPixelId('1234567890123456'), true);
  assert.equal(isValidPixelId('{{KLIJENT: Meta Pixel ID}}'), false);
  assert.equal(isValidPixelId(''), false);
  assert.equal(isValidPixelId(undefined), false);
});
