export function el(tag, attrs = {}, text) {
  const node = document.createElement(tag);
  for (const [key, value] of Object.entries(attrs)) {
    if (value === true) node.setAttribute(key, '');
    else if (value !== false && value != null) node.setAttribute(key, String(value));
  }
  if (text != null) node.textContent = text;
  return node;
}
