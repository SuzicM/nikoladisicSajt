import { initQuiz } from './quiz.js';

initQuiz();

const sticky = document.querySelector('[data-sticky-cta]');
const hero = document.getElementById('hero');
if (sticky && hero && 'IntersectionObserver' in window) {
  new IntersectionObserver(([entry]) => {
    sticky.classList.toggle('is-visible', !entry.isIntersecting);
  }).observe(hero);
}
