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

test('digraf na kraju reči velikim slovima i samostalni digraf', () => {
  assert.equal(cyr2lat('КОЊ ЈЕ ЛЕП'), 'KONJ JE LEP');
  assert.equal(cyr2lat('ЏЕП.'), 'DŽEP.');
  assert.equal(cyr2lat('БОЉ!'), 'BOLJ!');
  assert.equal(cyr2lat('Коњ је леп'), 'Konj je lep');
  assert.equal(cyr2lat('Њ'), 'Nj');
});
