import { initConsent } from './consent.js';
import { greetingText, initFaq } from './faq.js';

initConsent();

const greeting = document.querySelector('[data-greeting]');
if (greeting) greeting.textContent = greetingText(window.location.search);

initFaq();
