export const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_content', 'utm_campaign'];

const CALL_TIMES = ['Pre podne', 'Popodne', 'Uveče'];
const CHANNELS = ['Poziv', 'WhatsApp', 'Viber'];
const EMAIL_RE = /^[A-Za-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[A-Za-z0-9!#$%&'*+/=?^_`{|}~-]+)*@(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,}$/;

export const CONTACT_ERRORS = {
  ime: 'Upiši svoje ime (samo slova).',
  email: 'Proveri email adresu.',
  telefon: 'Proveri broj telefona.',
  vreme_poziva: 'Izaberi kada da te Nikola pozove.',
  kontakt_kanal: 'Izaberi kako da te kontaktira.',
  saglasnost: 'Potrebna je tvoja saglasnost da bismo obradili prijavu.',
};

export function readUtm(search) {
  const params = new URLSearchParams(search);
  const utm = {};
  for (const key of UTM_KEYS) {
    const value = params.get(key);
    if (value) utm[key] = value;
  }
  return utm;
}

export function createState() {
  return { step: 0, answers: {} };
}

export function setAnswer(state, question, value) {
  const answers = { ...state.answers };
  if (question.type === 'multi') {
    const current = Array.isArray(answers[question.id]) ? answers[question.id] : [];
    answers[question.id] = current.includes(value)
      ? current.filter((item) => item !== value)
      : [...current, value];
  } else {
    answers[question.id] = value;
  }
  return { ...state, answers };
}

export function isAnswered(state, question) {
  const value = state.answers[question.id];
  if (question.type === 'multi') return Array.isArray(value) && value.length > 0;
  if (question.type === 'scale') return Number.isInteger(value);
  return typeof value === 'string' && value !== '';
}

export function totalSteps(quiz) {
  return quiz.questions.length + 1;
}

export function emptyContact() {
  return { ime: '', email: '', telefon: '+381 ', vreme_poziva: '', kontakt_kanal: '', saglasnost: false };
}

export function validateContact(contact) {
  const errors = {};
  const ime = String(contact.ime ?? '').trim();
  if (ime.length < 1 || [...ime].length > 60 || !/^\p{L}[\p{L} '\-]*$/u.test(ime)) {
    errors.ime = CONTACT_ERRORS.ime;
  }
  const email = String(contact.email ?? '').trim();
  const [localPart] = email.split('@');
  if (email.length > 254 || (localPart?.length ?? 0) > 64 || !EMAIL_RE.test(email)) {
    errors.email = CONTACT_ERRORS.email;
  }
  const telefon = String(contact.telefon ?? '').replace(/\s+/g, '');
  if (!/^\+?[0-9]{8,15}$/.test(telefon)) {
    errors.telefon = CONTACT_ERRORS.telefon;
  }
  if (!CALL_TIMES.includes(contact.vreme_poziva)) {
    errors.vreme_poziva = CONTACT_ERRORS.vreme_poziva;
  }
  if (!CHANNELS.includes(contact.kontakt_kanal)) {
    errors.kontakt_kanal = CONTACT_ERRORS.kontakt_kanal;
  }
  if (contact.saglasnost !== true) {
    errors.saglasnost = CONTACT_ERRORS.saglasnost;
  }
  return errors;
}

export function buildPayload(state, contact, utm, elapsedMs, honeypot) {
  return {
    answers: { ...state.answers },
    contact: {
      ime: String(contact.ime).trim(),
      email: String(contact.email).trim(),
      telefon: String(contact.telefon).trim(),
      vreme_poziva: contact.vreme_poziva,
      kontakt_kanal: contact.kontakt_kanal,
    },
    saglasnost: contact.saglasnost === true,
    utm: { ...utm },
    website: honeypot,
    elapsed_ms: Math.round(elapsedMs),
  };
}
