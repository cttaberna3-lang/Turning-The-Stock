/* Injected at the top of a saved board while it is shown offline (see MainActivity.showSnapshot). */
(function () {
  if (window.__stacksOffline) return;
  window.__stacksOffline = true;
  var SAVED_AT = __SAVED_AT__;

  // Nothing can be sent without internet: stop background requests from erroring.
  window.fetch = function () { return Promise.reject(new TypeError('Offline')); };

  var msgEl = null, btnEl = null, barEl = null, resetTimer = null, online = false;

  function savedText() {
    try {
      return new Date(SAVED_AT).toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    } catch (e) { return ''; }
  }
  function baseText() {
    return online ? 'Back online. Tap Refresh to update the board.'
                  : 'Offline \u2014 showing the board saved ' + savedText();
  }
  function say(text) {
    if (!msgEl) return;
    msgEl.textContent = text;
    clearTimeout(resetTimer);
    resetTimer = setTimeout(function () { msgEl.textContent = baseText(); }, 2800);
  }

  // Forms need the server, so block them politely instead of showing a browser error page.
  document.addEventListener('submit', function (e) {
    e.preventDefault(); e.stopImmediatePropagation();
    say('You are offline. Connect to the internet to save changes.');
  }, true);

  window.__stacksOnline = function () {
    online = true;
    if (barEl) barEl.style.borderColor = 'rgba(63,92,78,.8)';
    if (btnEl) btnEl.textContent = 'Refresh';
    if (msgEl) msgEl.textContent = baseText();
  };

  function build() {
    var st = document.createElement('style');
    st.textContent =
      '#stk-off{position:fixed;left:12px;right:12px;bottom:calc(12px + env(safe-area-inset-bottom,0px));z-index:2147483647;' +
      'display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:4px;background:var(--paper-card,#f8f3e4);' +
      'border:1.5px solid var(--rust,#a1492a);color:var(--ink,#2b2a24);font:600 12px/1.35 system-ui,-apple-system,sans-serif;' +
      'box-shadow:4px 4px 0 rgba(43,42,36,.15);animation:stk-bar .25s ease both}' +
      '@keyframes stk-bar{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}' +
      '#stk-off span{flex:1}' +
      '#stk-off button{border:1.5px solid var(--ink,#2b2a24);border-radius:2px;padding:7px 14px;background:var(--ink,#2b2a24);color:var(--paper-card,#f8f3e4);font:700 11px system-ui,sans-serif;text-transform:uppercase;letter-spacing:.03em}' +
      'body{padding-bottom:78px !important}';
    document.head.appendChild(st);
    barEl = document.createElement('div'); barEl.id = 'stk-off';
    msgEl = document.createElement('span'); msgEl.textContent = baseText();
    btnEl = document.createElement('button'); btnEl.type = 'button'; btnEl.textContent = 'Retry';
    btnEl.addEventListener('click', function () { try { window.AndroidOffline.retry(); } catch (e) {} });
    barEl.appendChild(document.createTextNode('\uD83D\uDCF4'));
    barEl.appendChild(msgEl); barEl.appendChild(btnEl);
    document.body.appendChild(barEl);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', build); else build();
})();
