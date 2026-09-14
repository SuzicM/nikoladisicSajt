import { test } from 'node:test';
import assert from 'node:assert/strict';
import { greetingFor } from '../../assets/js/faq.js';

test('pozdrav sa imenom', () => {
  assert.equal(greetingFor('Milica'), 'Hvala, Milica!');
  assert.equal(greetingFor('Đurđa'), 'Hvala, Đurđa!');
  assert.equal(greetingFor(' Ana-Marija '), 'Hvala, Ana-Marija!');
});

test('bez imena ili sa neispravnim imenom je neutralan pozdrav', () => {
  assert.equal(greetingFor(null), 'Hvala!');
  assert.equal(greetingFor(''), 'Hvala!');
  assert.equal(greetingFor('<script>'), 'Hvala!');
  assert.equal(greetingFor('a'.repeat(61)), 'Hvala!');
  assert.equal(greetingFor(42), 'Hvala!');
});
