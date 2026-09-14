import { initConsent } from './consent.js';
import { initQuiz } from './quiz.js';

initConsent();
initQuiz();

const sticky = document.querySelector('[data-sticky-cta]');
const hero = document.getElementById('hero');
if (sticky && hero && 'IntersectionObserver' in window) {
  new IntersectionObserver(([entry]) => {
    sticky.classList.toggle('is-visible', !entry.isIntersecting);
  }).observe(hero);
}
