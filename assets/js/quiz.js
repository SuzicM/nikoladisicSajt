import {
  readUtm, createState, setAnswer, isAnswered, totalSteps,
  emptyContact, validateContact, buildPayload,
} from './quiz-state.js';
import { el } from './dom.js';

const QUIZ_KEY = 'nd_quiz_v1';
const UTM_KEY = 'nd_utm_v1';
const AUTO_ADVANCE_MS = 180;

const session = {
  get(key) {
    try { return JSON.parse(sessionStorage.getItem(key)); } catch { return null; }
  },
  set(key, value) {
    try { sessionStorage.setItem(key, JSON.stringify(value)); } catch { /* privatni režim */ }
  },
  remove(key) {
    try { sessionStorage.removeItem(key); } catch { /* privatni režim */ }
  },
};

function track(name, params = {}) {
  document.dispatchEvent(new CustomEvent('nd:track', { detail: { name, params } }));
}

export function initQuiz() {
  const dialog = document.getElementById('quiz');
  if (!dialog) return;

  const body = dialog.querySelector('[data-quiz-body]');
  const bar = dialog.querySelector('[data-quiz-bar]');
  const progress = dialog.querySelector('[role="progressbar"]');
  const count = dialog.querySelector('[data-quiz-count]');
  const back = dialog.querySelector('[data-quiz-back]');
  const honeypot = dialog.querySelector('#website');

  const utmNow = readUtm(window.location.search);
  if (Object.keys(utmNow).length > 0) session.set(UTM_KEY, utmNow);

  const quizReady = fetch('/assets/data/quiz.json')
    .then((res) => (res.ok ? res.json() : null))
    .catch(() => null);

  let quiz = null;
  let lastFocus = null;
  let saved = session.get(QUIZ_KEY) || {
    state: createState(), contact: emptyContact(), startedAt: null, started: false,
  };

  const persist = () => session.set(QUIZ_KEY, saved);

  async function open(trigger) {
    lastFocus = trigger || document.activeElement;
    dialog.hidden = false;
    document.body.classList.add('quiz-open');
    quiz = quiz || await quizReady;
    if (!quiz) {
      renderFatal();
      return;
    }
    if (!saved.started) {
      saved.started = true;
      saved.startedAt = Date.now();
      track('StartQuiz');
    }
    persist();
    render();
  }

  function close() {
    dialog.hidden = true;
    document.body.classList.remove('quiz-open');
    persist();
    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  }

  function goTo(step) {
    const last = totalSteps(quiz) - 1;
    step = Math.max(0, Math.min(step, last));
    saved.state = { ...saved.state, step };
    persist();
    render();
  }

  function render() {
    const total = totalSteps(quiz);
    const step = saved.state.step;
    count.textContent = `Korak ${step + 1} od ${total}`;
    bar.style.transform = `scaleX(${(step + 1) / total})`;
    progress.setAttribute('aria-valuenow', String(step + 1));
    back.disabled = step === 0;
    body.replaceChildren();

    if (step < quiz.questions.length) renderQuestion(quiz.questions[step]);
    else renderContact();

    track('QuizStep', { step: step + 1 });
    const first = body.querySelector('button, input:not([type="hidden"])');
    if (first) first.focus({ preventScroll: true });
  }

  function renderQuestion(question) {
    body.append(el('h2', { id: 'quiz-title', class: 'quiz__title' }, question.title));
    if (question.hint) body.append(el('p', { class: 'quiz__hint' }, question.hint));

    const group = el('div', { class: `quiz__options quiz__options--${question.type}`, role: 'group', 'aria-labelledby': 'quiz-title' });
    const values = question.type === 'scale'
      ? Array.from({ length: question.max - question.min + 1 }, (_, i) => question.min + i)
      : question.options;

    const next = question.type === 'multi'
      ? el('button', { type: 'button', class: 'btn btn--primary quiz__next', disabled: !isAnswered(saved.state, question) }, 'Dalje')
      : null;

    let advancing = false;

    for (const value of values) {
      const current = saved.state.answers[question.id];
      const pressed = Array.isArray(current) ? current.includes(value) : current === value;
      const option = el('button', { type: 'button', class: 'quiz__opt', 'aria-pressed': String(pressed) }, String(value));
      option.addEventListener('click', () => {
        if (question.type !== 'multi' && advancing) return;
        saved.state = setAnswer(saved.state, question, value);
        persist();
        if (question.type === 'multi') {
          const nowPressed = saved.state.answers[question.id].includes(value);
          option.setAttribute('aria-pressed', String(nowPressed));
          next.disabled = !isAnswered(saved.state, question);
          return;
        }
        group.querySelectorAll('.quiz__opt').forEach((b) => b.setAttribute('aria-pressed', 'false'));
        option.setAttribute('aria-pressed', 'true');
        advancing = true;
        const from = saved.state.step;
        setTimeout(() => { if (saved.state.step === from) goTo(from + 1); }, AUTO_ADVANCE_MS);
      });
      group.append(option);
    }
    body.append(group);

    if (next) {
      next.addEventListener('click', () => {
        if (next.disabled) return;
        next.disabled = true;
        goTo(saved.state.step + 1);
      });
      body.append(next);
    }
  }

  function renderContact() {
    body.append(el('h2', { id: 'quiz-title', class: 'quiz__title' }, 'Skoro gotovo, gde da te Nikola pozove?'));
    const form = el('form', { class: 'quiz__form', novalidate: true });
    const errors = {};

    const setError = (key, message) => {
      errors[key].textContent = message || '';
      const input = form.querySelector(`[name="${key}"]`);
      if (input) input.setAttribute('aria-invalid', message ? 'true' : 'false');
    };

    const errorNode = (key) => {
      errors[key] = el('p', { class: 'field__error', id: `err-${key}`, 'aria-live': 'polite' });
      return errors[key];
    };

    const textField = (key, label, attrs) => {
      const wrap = el('div', { class: 'field' });
      const input = el('input', { id: `f-${key}`, name: key, 'aria-describedby': `err-${key}`, ...attrs });
      input.value = saved.contact[key];
      input.addEventListener('input', () => { saved.contact[key] = input.value; persist(); });
      input.addEventListener('blur', () => setError(key, validateContact(saved.contact)[key]));
      wrap.append(el('label', { for: `f-${key}` }, label), input, errorNode(key));
      return wrap;
    };

    const choiceField = (key, legend, options) => {
      const fieldset = el('fieldset', { class: 'field field--choice', 'aria-describedby': `err-${key}` });
      fieldset.append(el('legend', {}, legend));
      const pills = el('div', { class: 'pills' });
      options.forEach((option, i) => {
        const id = `f-${key}-${i}`;
        const input = el('input', { type: 'radio', id, name: key, value: option, checked: saved.contact[key] === option });
        input.addEventListener('change', () => { saved.contact[key] = option; persist(); setError(key, ''); });
        pills.append(input, el('label', { for: id }, option));
      });
      fieldset.append(pills, errorNode(key));
      return fieldset;
    };

    form.append(
      textField('ime', 'Ime', { type: 'text', autocomplete: 'given-name', maxlength: '60', required: true }),
      textField('email', 'Email', { type: 'email', autocomplete: 'email', inputmode: 'email', required: true }),
      textField('telefon', 'Telefon', { type: 'tel', autocomplete: 'tel', inputmode: 'tel', required: true }),
      choiceField('vreme_poziva', 'Najbolje vreme za poziv', quiz.contact.vreme_poziva),
      choiceField('kontakt_kanal', 'Kontakt preko', quiz.contact.kontakt_kanal),
    );

    const consentWrap = el('div', { class: 'field field--consent' });
    const consent = el('input', { type: 'checkbox', id: 'f-saglasnost', name: 'saglasnost', checked: saved.contact.saglasnost, 'aria-describedby': 'err-saglasnost' });
    consent.addEventListener('change', () => { saved.contact.saglasnost = consent.checked; persist(); setError('saglasnost', ''); });
    const consentLabel = el('label', { for: 'f-saglasnost' });
    consentLabel.append(
      document.createTextNode('Saglasna sam sa '),
      el('a', { href: '/politika-privatnosti', target: '_blank', rel: 'noopener' }, 'Politikom privatnosti'),
      document.createTextNode(' i obradom podataka o zdravlju u svrhu procene saradnje.'),
    );
    consentWrap.append(consent, consentLabel, errorNode('saglasnost'));

    const status = el('div', { class: 'quiz__status', role: 'alert' });
    const submit = el('button', { type: 'submit', class: 'btn btn--primary quiz__submit' }, 'Pošalji prijavu');
    form.append(consentWrap, status, submit);

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      status.replaceChildren();
      const found = validateContact(saved.contact);
      for (const key of Object.keys(errors)) setError(key, found[key]);
      const firstInvalid = Object.keys(found)[0];
      if (firstInvalid) {
        const target = form.querySelector(`[name="${firstInvalid}"]`);
        if (target) target.focus();
        return;
      }

      submit.disabled = true;
      submit.classList.add('is-loading');
      submit.textContent = 'Šaljem…';

      try {
        const payload = buildPayload(
          saved.state, saved.contact, session.get(UTM_KEY) || {},
          Date.now() - (saved.startedAt || Date.now()), honeypot.value,
        );
        const res = await fetch('/api/submit.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.ok !== true) throw new Error(`HTTP ${res.status}`);

        track('Lead');
        const ime = saved.contact.ime.trim();
        session.remove(QUIZ_KEY);
        session.set('nd_ime_v1', ime);
        await new Promise((resolve) => setTimeout(resolve, 300));
        window.location.assign('/hvala');
      } catch {
        submit.disabled = false;
        submit.classList.remove('is-loading');
        submit.textContent = 'Pošalji prijavu';
        status.append(
          document.createTextNode('Nešto nije u redu. Pokušaj ponovo ili piši Nikoli na '),
          el('a', { href: dialog.dataset.instagram, target: '_blank', rel: 'noopener' }, 'Instagram'),
          document.createTextNode('.'),
        );
      }
    });

    body.append(form);
  }

  function renderFatal() {
    body.replaceChildren(
      el('h2', { id: 'quiz-title', class: 'quiz__title' }, 'Prijava trenutno ne radi'),
      el('p', {}, 'Osveži stranicu ili piši Nikoli na Instagram.'),
      el('a', { class: 'btn btn--primary', href: dialog.dataset.instagram, target: '_blank', rel: 'noopener' }, 'Otvori Instagram'),
    );
  }

  function trapFocus(event) {
    if (event.key === 'Escape') {
      close();
      return;
    }
    if (event.key !== 'Tab') return;
    const focusables = [...dialog.querySelectorAll('button:not([disabled]), input:not([tabindex="-1"]), a[href]')]
      .filter((node) => node.offsetParent !== null);
    if (focusables.length === 0) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  document.querySelectorAll('[data-open-quiz]').forEach((button) => {
    button.addEventListener('click', () => open(button));
  });
  dialog.querySelector('[data-quiz-close]').addEventListener('click', close);
  back.addEventListener('click', () => { if (saved.state.step > 0) goTo(saved.state.step - 1); });
  dialog.addEventListener('keydown', trapFocus);
}
