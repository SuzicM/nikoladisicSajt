import { test } from 'node:test';
import assert from 'node:assert/strict';
import { cyr2lat } from '../../scripts/cyr2lat.js';

test('prevodi ćirilicu u latinicu uključujući digrafe', () => {
  assert.equal(cyr2lat('Здраво, ја сам Никола'), 'Zdravo, ja sam Nikola');
  assert.equal(cyr2lat('Љиља Његош Џонић ђак ћуфта жаба шума чај'), 'Ljilja Njegoš Džonić đak ćufta žaba šuma čaj');
  assert.equal(cyr2lat('ЉУБАВ ЊИВА ЏЕП'), 'LJUBAV NJIVA DŽEP');
});

test('ne dira latinicu, brojeve i VTT oznake', () => {
  const vtt = 'WEBVTT\n\n00:00:01.000 --> 00:00:03.500\nIshrana bez gladovanja, 6 nedelja.';
  assert.equal(cyr2lat(vtt), vtt);
});
