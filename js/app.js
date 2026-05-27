/**
 * ============================================================
 * Tippspiel - Globale JS-Helfer
 * ------------------------------------------------------------
 * Wird im Header (vor allen Page-Scripts) geladen, damit das
 * Objekt `Tippspiel` ueberall verfuegbar ist.
 *
 * Exportiert:
 *   Tippspiel.getMode()       - aktueller Spielmodus ("points" | "money")
 *   Tippspiel.setMode(m)      - Spielmodus aendern
 *   Tippspiel.get(url)        - GET-Request -> JSON
 *   Tippspiel.post(url, body) - POST-Request -> JSON
 *   Tippspiel.toast(msg, type)- Mini-Benachrichtigung unten rechts
 * ============================================================
 */
const Tippspiel = (() => {

  // LocalStorage-Schluessel fuer den aktuellen Modus
  // (so bleibt die Wahl auch nach Neuladen erhalten)
  const MODE_KEY = 'tippspiel.mode';

  /** Liest den aktuellen Modus aus dem LocalStorage. Default "points". */
  function getMode()  { return localStorage.getItem(MODE_KEY) || 'points'; }
  /** Schreibt den Modus in den LocalStorage. */
  function setMode(m) { localStorage.setItem(MODE_KEY, m); }

  /**
   * Kleiner Fetch-Wrapper:
   *  - sendet Cookies mit (Session)
   *  - setzt JSON-Content-Type
   *  - parst die Antwort als JSON
   *  - wirft eine aussagekraeftige Exception bei HTTP-Fehlern
   */
  async function api(url, opts = {}) {
    const r = await fetch(url, {
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', ...(opts.headers || {}) },
      ...opts
    });
    // Antwort als JSON parsen; bei nicht-JSON-Antwort {} statt Exception
    const data = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(data.error || ('HTTP ' + r.status));
    return data;
  }

  /** Convenience-Wrapper fuer GET. */
  const get  = (u)       => api(u);
  /** Convenience-Wrapper fuer POST mit JSON-Body. */
  const post = (u, body) => api(u, { method: 'POST', body: JSON.stringify(body) });

  /**
   * Toast-Benachrichtigung unten rechts.
   *
   * @param {string} msg   Anzuzeigender Text
   * @param {string} type  'info' (blau) | 'ok' (gruen) | 'error' (rot)
   */
  function toast(msg, type = 'info') {
    const t = document.createElement('div');
    t.textContent = msg;
    // Inline-Style: Toast soll auch ohne weitere CSS-Datei funktionieren
    t.style.cssText = `
      position: fixed; right: 20px; bottom: 20px; z-index: 9999;
      background: ${type==='error'?'#ef4d4d':type==='ok'?'#19c37d':'#4f8cff'};
      color: white; padding: 10px 16px; border-radius: 8px;
      box-shadow: 0 6px 18px rgba(0,0,0,.4); font-size: 14px;
      max-width: 320px;`;
    document.body.appendChild(t);
    // Nach 3.5 s automatisch entfernen
    setTimeout(() => t.remove(), 3500);
  }

  /**
   * Initialisiert das Modus-Dropdown im Header (Punkte / Geld).
   * Bei Aenderung wird das custom Event "mode-changed" auf document
   * abgefeuert - die Pages reagieren darauf.
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

  // Modus-Switch beim Laden initialisieren
  document.addEventListener('DOMContentLoaded', initModeSwitch);

  // Public-API zurueckgeben (alles andere bleibt privat)
  return { getMode, setMode, get, post, toast };
})();
