import { el } from './dom.js';

export function greetingFor(name) {
  const ime = typeof name === 'string' ? name.trim() : '';
  const valid = ime.length > 0 && [...ime].length <= 60 && /^\p{L}[\p{L} '\-]*$/u.test(ime);
  return valid ? `Hvala, ${ime}!` : 'Hvala!';
}

export function initFaq() {
  const list = document.querySelector('[data-faq]');
  const section = document.querySelector('[data-faq-section]');
  if (!list) return;

  list.querySelectorAll('.faq-item').forEach((item) => {
    if (!item.dataset.video) item.remove();
  });
  const items = [...list.querySelectorAll('.faq-item')];
  if (items.length === 0) {
    if (section) section.hidden = true;
    return;
  }

  const partsOf = (item) => ({
    button: item.querySelector('.faq-q'),
    panel: item.querySelector('.faq-panel'),
  });

  function buildPlayer(item, index) {
    const { panel } = partsOf(item);
    const frame = el('div', { class: 'faq-player' });
    const video = el('video', {
      class: 'faq-video', controls: true, playsinline: true, preload: 'none', poster: item.dataset.poster || null,
    });
    video.append(el('source', { src: item.dataset.video, type: 'video/mp4' }));
    if (item.dataset.vtt) {
      video.append(el('track', { kind: 'captions', srclang: 'sr', label: 'Srpski', src: item.dataset.vtt }));
    }

    const playButton = el('button', { type: 'button', class: 'faq-play', 'aria-label': 'Pusti odgovor', hidden: true }, '▶');
    playButton.addEventListener('click', () => video.play());
    video.addEventListener('play', () => { playButton.hidden = true; });

    frame.append(video, playButton);
    panel.append(frame);

    const nextItem = items[index + 1];
    if (nextItem) {
      const next = el('button', { type: 'button', class: 'btn btn--ghost faq-next', hidden: true }, 'Sledeće pitanje →');
      next.addEventListener('click', () => {
        open(nextItem);
        nextItem.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
      video.addEventListener('ended', () => { next.hidden = false; });
      panel.append(next);
    }
    return { video, playButton };
  }

  const players = new Map();

  function close(item) {
    const { button, panel } = partsOf(item);
    button.setAttribute('aria-expanded', 'false');
    panel.hidden = true;
    const player = players.get(item);
    if (player) player.video.pause();
  }

  function open(item) {
    items.filter((other) => other !== item).forEach(close);
    const { button, panel } = partsOf(item);
    button.setAttribute('aria-expanded', 'true');
    panel.hidden = false;

    if (!players.has(item)) players.set(item, buildPlayer(item, items.indexOf(item)));
    const { video, playButton } = players.get(item);
    const attempt = video.play();
    if (attempt && typeof attempt.catch === 'function') {
      attempt.catch(() => { playButton.hidden = false; });
    }
  }

  items.forEach((item) => {
    const { button } = partsOf(item);
    button.addEventListener('click', () => {
      if (button.getAttribute('aria-expanded') === 'true') close(item);
      else open(item);
    });
  });
}
