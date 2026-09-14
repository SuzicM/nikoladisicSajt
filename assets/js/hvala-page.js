import { initConsent } from './consent.js';
import { greetingFor, initFaq } from './faq.js';

let ime = null;
try {
  ime = JSON.parse(sessionStorage.getItem('nd_ime_v1'));
} catch {
  ime = null;
}
if (window.location.search) {
  history.replaceState(null, '', window.location.pathname);
}

initConsent();

const greeting = document.querySelector('[data-greeting]');
if (greeting) greeting.textContent = greetingFor(ime);

initFaq();
