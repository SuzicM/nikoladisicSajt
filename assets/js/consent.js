export const CONSENT_KEY = 'nd_consent_v1';
export const CONSENT_TTL_MS = 15552000000;
const STANDARD_EVENTS = ['PageView', 'Lead'];

export function readConsent(storage, now) {
  try {
    const raw = storage.getItem(CONSENT_KEY);
    if (!raw) return null;
    const data = JSON.parse(raw);
    const validValue = data && (data.value === 'granted' || data.value === 'denied');
    if (!validValue || typeof data.at !== 'number' || data.at > now || now - data.at > CONSENT_TTL_MS) {
      return null;
    }
    return data.value;
  } catch {
    return null;
  }
}

export function writeConsent(storage, value, now) {
  try {
    storage.setItem(CONSENT_KEY, JSON.stringify({ value, at: now }));
  } catch {
    /* blokiran storage: odluka važi samo za ovu stranicu */
  }
}

export function trackCall(name, params = {}) {
  return [STANDARD_EVENTS.includes(name) ? 'track' : 'trackCustom', name, params];
}

export function isValidPixelId(id) {
  return typeof id === 'string' && /^[0-9]{10,20}$/.test(id);
}

export function pixelBootCalls(pixelId) {
  return [
    ['set', 'autoConfig', false, pixelId],
    ['init', pixelId],
    ['track', 'PageView'],
  ];
}

function browserStorage() {
  try {
    return window.localStorage;
  } catch {
    return { getItem: () => null, setItem: () => {} };
  }
}

function loadPixel(pixelId) {
  if (window.fbq) return;
  const fbq = function () {
    // eslint-disable-next-line prefer-rest-params
    if (fbq.callMethod) fbq.callMethod.apply(fbq, arguments); else fbq.queue.push(arguments);
  };
  fbq.push = fbq;
  fbq.loaded = true;
  fbq.version = '2.0';
  fbq.queue = [];
  window.fbq = fbq;
  window._fbq = fbq;

  const script = document.createElement('script');
  script.async = true;
  script.src = 'https://connect.facebook.net/en_US/fbevents.js';
  document.head.append(script);

  for (const call of pixelBootCalls(pixelId)) window.fbq(...call);
}

export function initConsent() {
  const banner = document.getElementById('cookie-banner');
  const pixelId = document.body.dataset.pixelId;
  const storage = browserStorage();
  let decision = readConsent(storage, Date.now());

  const apply = () => {
    if (decision === 'granted' && isValidPixelId(pixelId)) {
      loadPixel(pixelId);
    } else if (decision === 'denied' && window.fbq) {
      window.fbq('consent', 'revoke');
    }
  };

  if (banner) {
    banner.hidden = decision !== null;
    banner.querySelectorAll('[data-consent]').forEach((button) => {
      button.addEventListener('click', () => {
        decision = button.dataset.consent === 'granted' ? 'granted' : 'denied';
        writeConsent(storage, decision, Date.now());
        banner.hidden = true;
        apply();
      });
    });
  }

  document.querySelectorAll('[data-cookie-settings]').forEach((button) => {
    button.addEventListener('click', () => {
      if (!banner) return;
      banner.hidden = false;
      const first = banner.querySelector('button');
      if (first) first.focus();
    });
  });

  document.addEventListener('nd:track', (event) => {
    if (decision !== 'granted' || !window.fbq) return;
    const { name, params } = event.detail || {};
    if (typeof name === 'string') window.fbq(...trackCall(name, params || {}));
  });

  apply();
}
