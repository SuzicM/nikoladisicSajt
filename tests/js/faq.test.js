import { test } from 'node:test';
import assert from 'node:assert/strict';
import { greetingText } from '../../assets/js/faq.js';

test('pozdrav sa imenom', () => {
  assert.equal(greetingText('?ime=Milica'), 'Hvala, Milica!');
  assert.equal(greetingText('?ime=%C4%90ur%C4%91a'), 'Hvala, Đurđa!');
  assert.equal(greetingText('?ime=%20Ana-Marija%20'), 'Hvala, Ana-Marija!');
});

test('bez imena ili sa neispravnim imenom je neutralan pozdrav', () => {
  assert.equal(greetingText(''), 'Hvala!');
  assert.equal(greetingText('?ime='), 'Hvala!');
  assert.equal(greetingText('?ime=%3Cscript%3E'), 'Hvala!');
  assert.equal(greetingText(`?ime=${'a'.repeat(61)}`), 'Hvala!');
});
