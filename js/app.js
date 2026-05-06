/**
 * Globale JS-Helpers fuers Tippspiel.
 */

const Tippspiel = (() => {

  /** Gewuenschten Modus aus dem Header-Dropdown im LocalStorage speichern. */
  const MODE_KEY = 'tippspiel.mode';

  function getMode()        { return localStorage.getItem(MODE_KEY) || 'points'; }
  function setMode(m)       { localStorage.setItem(MODE_KEY, m); }

  /** kleine Fetch-Helper */
  async function api(url, opts = {}) {
    const r = await fetch(url, {
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', ...(opts.headers || {}) },
      ...opts
    });
    const data = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(data.error || ('HTTP ' + r.status));
    return data;
  }
  const get  = (u)       => api(u);
  const post = (u, body) => api(u, { method: 'POST', body: JSON.stringify(body) });

  /** Toast-Notification */
  function toast(msg, type = 'info') {
    const t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = `
      position: fixed; right: 20px; bottom: 20px; z-index: 9999;
      background: ${type==='error'?'#ef4d4d':type==='ok'?'#19c37d':'#4f8cff'};
      color: white; padding: 10px 16px; border-radius: 8px;
      box-shadow: 0 6px 18px rgba(0,0,0,.4); font-size: 14px;
      max-width: 320px;`;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
  }

  /** Mode-Dropdown im Header initialisieren. */
  function initModeSwitch() {
    const sel = document.getElementById('mode');
    if (!sel) return;
    sel.value = getMode();
    sel.addEventListener('change', () => {
      setMode(sel.value);
      // Pages reagieren via custom event
      document.dispatchEvent(new CustomEvent('mode-changed', { detail: sel.value }));
    });
  }

  document.addEventListener('DOMContentLoaded', initModeSwitch);

  return { getMode, setMode, get, post, toast };
})();
