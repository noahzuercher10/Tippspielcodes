/**
 * app.js – globales Tippspiel-Namespace-Objekt
 *
 * Wird auf jeder Seite geladen (via header.php).
 * Stellt bereit:
 *   Tippspiel.get(url)          – GET-Request → Promise<data>
 *   Tippspiel.post(url, body)   – POST-Request → Promise<data>
 *   Tippspiel.toast(msg, type)  – temporäre Benachrichtigung (ok | error | info)
 *   Tippspiel.getMode()         – aktueller Modus aus localStorage ('points' | 'money')
 *   Tippspiel.setMode(m)        – Modus speichern
 *
 * Der Modus-Schalter im Header löst beim Ändern das Custom-Event
 * 'mode-changed' auf document aus, damit sport.js und andere Seiten
 * reagieren können.
 */
const Tippspiel = (() => {
  /** localStorage-Key für den gewählten Spielmodus */
  const MODE_KEY = 'tippspiel.mode';

  /** Gibt den aktuellen Spielmodus zurück ('points' | 'money'). */
  function getMode()  { return localStorage.getItem(MODE_KEY) || 'points'; }
  /** Speichert den Spielmodus in localStorage. */
  function setMode(m) { localStorage.setItem(MODE_KEY, m); }

  /**
   * Zentraler HTTP-Helper. Wirft einen Error wenn der Server
   * einen Fehler-Status zurückgibt oder data.error gesetzt ist.
   * @param {string} url
   * @param {RequestInit} opts
   * @returns {Promise<any>}
   */
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

  /** GET-Shortcut. */
  const get  = (u)       => api(u);
  /** POST-Shortcut mit JSON-Body. */
  const post = (u, body) => api(u, { method: 'POST', body: JSON.stringify(body) });

  /**
   * Zeigt eine temporäre Toast-Benachrichtigung unten rechts.
   * @param {string} msg   Anzeigetext
   * @param {'ok'|'error'|'info'} type  Farbe der Benachrichtigung
   */
  function toast(msg, type = 'info') {
    const t = document.createElement('div');
    t.textContent = msg;
    const colors = { error: '#dc2626', ok: '#059669', info: '#2563eb' };
    t.style.cssText = `
      position:fixed;right:20px;bottom:20px;z-index:9999;
      background:${colors[type] || colors.info};
      color:#fff;padding:10px 16px;border-radius:8px;
      box-shadow:0 4px 14px rgba(0,0,0,.2);font-size:14px;max-width:320px;`;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
  }

  /**
   * Verbindet den Modus-Select im Header mit localStorage und feuert
   * bei Änderung das 'mode-changed'-Event auf document.
   */
  function initModeSwitch() {
    const sel = document.getElementById('mode');
    if (!sel) return;
    sel.value = getMode();
    sel.addEventListener('change', () => {
      setMode(sel.value);
      document.dispatchEvent(new CustomEvent('mode-changed', { detail: sel.value }));
    });
  }

  document.addEventListener('DOMContentLoaded', initModeSwitch);

  return { getMode, setMode, get, post, toast };
})();
