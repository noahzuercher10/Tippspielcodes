const Tippspiel = (() => {
  const MODE_KEY = 'tippspiel.mode';

  function getMode()  { return localStorage.getItem(MODE_KEY) || 'points'; }
  function setMode(m) { localStorage.setItem(MODE_KEY, m); }

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
