import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (file) => readFileSync(new URL(`../../${file}`, import.meta.url), 'utf8');
const CSP = "default-src 'self'; script-src 'self' https://connect.facebook.net; img-src 'self' data: https://www.facebook.com; connect-src 'self' https://www.facebook.com https://connect.facebook.net; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'";

test('.htaccess i dev router imaju identičan CSP', () => {
  assert.ok(read('.htaccess').includes(`Header always set Content-Security-Policy "${CSP}"`));
  const router = read('scripts/dev-router.php').replace(/"\s*\.\s*"/g, '');
  assert.ok(router.includes(CSP), 'dev-router.php CSP se razlikuje');
});

test('.htaccess ima obavezne headere, blokade i čiste URL-ove', () => {
  const h = read('.htaccess');
  for (const needle of [
    'Options -Indexes',
    'Strict-Transport-Security "max-age=31536000; includeSubDomains"',
    'X-Content-Type-Options "nosniff"',
    'Referrer-Policy "strict-origin-when-cross-origin"',
    'Permissions-Policy "camera=(), microphone=(), geolocation=()"',
    'RewriteRule ^(?:\\.git|docs|tests|scripts|dev|storage)(?:/|$) - [F,L]',
    'RewriteRule ^api/submit\\.php$ - [L]',
    'RewriteRule \\.php$ - [F,L]',
    'RewriteRule ^(hvala|politika-privatnosti|uslovi-koriscenja)/?$ $1.html [L]',
    'AddType text/vtt .vtt',
  ]) {
    assert.ok(h.includes(needle), `nedostaje: ${needle}`);
  }
});
