/**
 * MT5 web-terminal broker URLs — HF Markets only (EA Matrix parity).
 */
import { HF_MARKETS_SERVERS } from '@/constants/servers';

export const DEFAULT_MT5_BROKER = 'hfmarketssa-live1';

export const MT5_BROKER_URLS: Record<string, string> = Object.fromEntries(
  Object.entries(HF_MARKETS_SERVERS).map(([key, cfg]) => [key, cfg.url])
);

/** Picker list: one entry per terminal URL. */
export const MT5_BROKERS = (() => {
  const seenUrls = new Set<string>();
  const list: string[] = [];
  for (const key of Object.keys(MT5_BROKER_URLS)) {
    const url = MT5_BROKER_URLS[key];
    if (seenUrls.has(url)) continue;
    seenUrls.add(url);
    list.push(key);
  }
  return list;
})();

export function normalizeMt5ServerKey(server: string): string {
  const trimmed = (server || '').trim();
  if (!trimmed) return '';
  if (MT5_BROKER_URLS[trimmed]) return trimmed;
  const lower = trimmed.toLowerCase();
  for (const key of Object.keys(MT5_BROKER_URLS)) {
    if (key.toLowerCase() === lower) return key;
  }
  return trimmed;
}

/** Force English UI so automation that matches English labels/titles stays stable worldwide. */
export const MT5_ENGLISH_ACCEPT_LANGUAGE = 'en-US,en;q=0.9';

export const MT5_ENGLISH_WEBVIEW_HEADERS: Record<string, string> = {
  'Accept-Language': MT5_ENGLISH_ACCEPT_LANGUAGE,
};

/**
 * Append / overwrite lang=en on any MT5 terminal URL.
 * XM already ships ?lang=en; other brokers default to the device locale.
 */
export function ensureMt5EnglishTerminalUrl(rawUrl: string): string {
  const input = String(rawUrl || '').trim();
  if (!input) return input;
  try {
    const u = new URL(input);
    u.searchParams.set('lang', 'en');
    return u.toString();
  } catch {
    if (/[?&]lang=/i.test(input)) {
      return input.replace(/([?&]lang=)[^&]*/i, '$1en');
    }
    const sep = input.includes('?') ? '&' : '?';
    return `${input}${sep}lang=en`;
  }
}

/**
 * Early JS lock: html lang, storage keys, and navigator.language.
 * Must run before the terminal reads locale (injectedJavaScriptBeforeContentLoaded).
 */
export const MT5_ENGLISH_LOCK_JS = `(function(){
  try {
    if (document && document.documentElement) {
      document.documentElement.setAttribute('lang', 'en');
      document.documentElement.setAttribute('xml:lang', 'en');
    }
  } catch (e0) {}
  try {
    var storeKeys = ['lang','language','locale','i18n','uiLang','ui-language','mt5-lang','terminal-lang','webterminal-lang','WebTerminalLanguage'];
    for (var i = 0; i < storeKeys.length; i++) {
      try { localStorage.setItem(storeKeys[i], 'en'); } catch (e1) {}
      try { sessionStorage.setItem(storeKeys[i], 'en'); } catch (e2) {}
    }
  } catch (e3) {}
  try {
    Object.defineProperty(Navigator.prototype, 'language', { get: function(){ return 'en-US'; }, configurable: true });
    Object.defineProperty(Navigator.prototype, 'languages', { get: function(){ return ['en-US','en']; }, configurable: true });
  } catch (e4) {
    try {
      Object.defineProperty(navigator, 'language', { get: function(){ return 'en-US'; }, configurable: true });
      Object.defineProperty(navigator, 'languages', { get: function(){ return ['en-US','en']; }, configurable: true });
    } catch (e5) {}
  }
})();`;

export function getMt5EnglishLockJs(): string {
  return `${MT5_ENGLISH_LOCK_JS}\ntrue;`;
}

export function applyEnglishHtmlLang(html: string): string {
  const src = String(html || '');
  if (/<html[^>]*\blang\s*=/i.test(src)) {
    return src.replace(/<html([^>]*?)\blang\s*=\s*(["']).*?\2/i, '<html$1lang="en"');
  }
  return src.replace(/<html\b/i, '<html lang="en"');
}

export function resolveMt5TerminalUrl(server: string): string {
  const key = normalizeMt5ServerKey(server);
  const raw = MT5_BROKER_URLS[key] || MT5_BROKER_URLS[DEFAULT_MT5_BROKER];
  return ensureMt5EnglishTerminalUrl(raw);
}

/** Origin used by /terminal/* asset proxy (no path). */
export function resolveMt5BrokerBaseUrl(server: string): string {
  try {
    const terminal = resolveMt5TerminalUrl(server);
    const u = new URL(terminal);
    return `${u.protocol}//${u.host}`;
  } catch {
    return 'https://live-webterminal.hfm-sa.com:1951';
  }
}

/**
 * RCG (and similar) serve an incomplete TLS chain — browsers trust them via
 * AIA, but Bun/Node fetch fails with UNABLE_TO_VERIFY_LEAF_SIGNATURE.
 * Android System WebView often cannot AIA-fetch intermediates either, so the
 * terminal never becomes interactive when loaded directly.
 */
export function mt5HostNeedsInsecureTls(hostname: string): boolean {
  const host = (hostname || '').toLowerCase();
  return host === 'webtrader.rcgmarkets.com' || host.endsWith('.rcgmarkets.com');
}

/** True when native Android must load this broker via the VPS MT5 HTML proxy. */
export function mt5ServerNeedsNativeWebViewProxy(server: string): boolean {
  try {
    const hostname = new URL(resolveMt5TerminalUrl(server || DEFAULT_MT5_BROKER)).hostname;
    return mt5HostNeedsInsecureTls(hostname);
  } catch {
    return false;
  }
}

/** Absolute Render MT5 proxy URL for Android WebView (RCG TLS). Web stays relative on Render. */
export function resolveMt5NativeProxyWebViewUrl(proxyPath: string): string {
  if (proxyPath.startsWith('http://') || proxyPath.startsWith('https://')) {
    return proxyPath;
  }
  const path = proxyPath.startsWith('/') ? proxyPath : `/${proxyPath}`;
  // Env-only — must not import api-base-url (react-native) — server.ts imports mt5-brokers.
  const base = (
    process.env.EXPO_PUBLIC_MT5_PROXY_BASE_URL || 'https://nextrade-app.onrender.com'
  ).replace(/\/$/, '');
  return `${base}${path}`;
}

export function isMt5ProxyWebViewUrl(url: string): boolean {
  const u = url || '';
  return u.includes('/api/mt5-proxy') || u.includes('/api/mt5-trading-proxy');
}

/** @deprecated No proxy-blocked brokers remain; kept for call-site compatibility. */
export function isMt5ProxyBlockedBroker(_server: string): boolean {
  return false;
}

/** Web dev (localhost) has no /api proxy — forward via Metro to local server.ts (port 3000). */
export function resolveMt5ApiProxyUrl(relativePath: string, platformOs: string): string {
  if (platformOs !== 'web' || typeof window === 'undefined') {
    return relativePath;
  }
  const { hostname } = window.location;
  const isLocalDev =
    hostname === 'localhost' ||
    hostname === '127.0.0.1' ||
    hostname.endsWith('.local') ||
    /^\d+\.\d+\.\d+\.\d+$/.test(hostname);
  // Same-origin relative path; Metro dev server proxies /api + /terminal → localhost:3000
  if (isLocalDev) {
    return relativePath.startsWith('/') ? relativePath : `/${relativePath}`;
  }
  return relativePath.startsWith('/') ? relativePath : `/${relativePath}`;
}

/**
 * Link/trade WebView URL:
 * - web → relative same-origin proxy (Render / local) — do not change
 * - Android + incomplete-TLS brokers (RCG) → absolute VPS proxy
 * - other native → direct broker terminal (e.g. Razor, like EA Trade)
 */
export function resolveMt5LinkWebViewUrl(
  server: string,
  platformOs: string,
  proxyPath: string
): string {
  if (platformOs === 'web') {
    return proxyPath;
  }
  if (platformOs === 'android' && mt5ServerNeedsNativeWebViewProxy(server)) {
    return resolveMt5NativeProxyWebViewUrl(proxyPath);
  }
  return resolveMt5TerminalUrl(server);
}

/** Delay before injecting auth script after WebView load. */
export function getMt5ShellReadyDelayMs(_server: string, isAndroid: boolean): number {
  return isAndroid ? 4800 : 3200;
}

export function getMt5InnerAuthKickMs(_server: string, isAndroid: boolean): number {
  return isAndroid ? 1200 : 450;
}

export function getMt5InnerAuthFallbackMs(_server: string, isAndroid: boolean): number {
  return isAndroid ? 5600 : 3200;
}

/** Injected before page load in native MT5 WebViews. */
export function getMt5WebViewBootstrapJs(preserveSession = false): string {
  if (preserveSession) {
    // Proxy HTML already carries server-injected auth/trade scripts — do not wipe storage.
    return `
${MT5_ENGLISH_LOCK_JS}
(function(){
  try {
    window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'webview_ready' }));
  } catch(e) {}
})();
true;
`;
  }
  return `
(function(){
  try { localStorage.clear(); } catch(e) {}
  try { sessionStorage.clear(); } catch(e) {}
  try {
    if (typeof indexedDB !== 'undefined' && indexedDB.databases) {
      indexedDB.databases().then(function(dbs){
        dbs.forEach(function(db){ if (db.name) try { indexedDB.deleteDatabase(db.name); } catch(e2) {} });
      });
    }
  } catch(e) {}
  try {
    if (typeof document !== 'undefined' && document.cookie) {
      document.cookie.split(';').forEach(function(c){
        var eq = c.indexOf('=');
        var name = eq > -1 ? c.substr(0, eq) : c;
        document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
      });
    }
  } catch(e) {}
})();
${MT5_ENGLISH_LOCK_JS}
(function(){
  try {
    window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'webview_ready' }));
  } catch(e) {}
})();
true;
`;
}

/** Polls terminal DOM; posts page_ready_for_script when connect sheet or session appears. */
export function getMt5LinkShellProbeMaxWaitMs(_server: string): number {
  return 12000;
}

export function getMt5LinkShellProbeJs(generation: number, maxWaitMs: number): string {
  return `
(function(){
  var gen = ${generation};
  var maxWait = ${maxWaitMs};
  var start = Date.now();
  var fired = false;
  function fire() {
    if (fired) return;
    fired = true;
    try {
      window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'page_ready_for_script', gen: gen }));
    } catch(e) {}
  }
  function isShellReady() {
    try {
      var bt = document.body ? (document.body.innerText || document.body.textContent || '') : '';
      if (bt.indexOf('Connect to account') >= 0) return true;
      if (bt.indexOf('Enter Login') >= 0 && bt.indexOf('Password') >= 0) return true;
      if (bt.indexOf('Search symbol') >= 0) return true;
      if (/\\bEquity\\b/i.test(bt) && /\\bBalance\\b/i.test(bt)) return true;
      var login = document.querySelector('input[placeholder*="login" i], input[name="login"], input[name="Login"]');
      var pwd = document.querySelector('input[type="password"], input[placeholder*="password" i], input[name="password"]');
      if (login && pwd) {
        var lr = login.getBoundingClientRect();
        var pr = pwd.getBoundingClientRect();
        if (lr.width > 6 && lr.height > 6 && pr.width > 6 && pr.height > 6) return true;
      }
    } catch(e) {}
    return false;
  }
  function poll() {
    if (isShellReady() || Date.now() - start >= maxWait) {
      fire();
      return;
    }
    setTimeout(poll, 450);
  }
  function kick() {
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
      setTimeout(poll, 350);
    } else {
      document.addEventListener('DOMContentLoaded', function(){ setTimeout(poll, 350); }, { once: true });
      setTimeout(poll, 1200);
    }
  }
  kick();
})();true;`;
}

/** JS snippet inlined in MT5 link/trade auth scripts (broker account drawer detection). */
export const MT5_BROKER_SHEET_MARKERS_JS = `
function pageHasBrokerAccountsSheet(bt) {
  return bt.indexOf('Trading accounts') >= 0 ||
    bt.indexOf('Razor Markets') >= 0 ||
    bt.indexOf('RCG Markets') >= 0;
}
function overlayHasBrokerAccountsText(txt) {
  return txt.indexOf('Trading accounts') >= 0 ||
    txt.indexOf('Razor Markets') >= 0 ||
    txt.indexOf('RCG Markets') >= 0;
}
`;

/** Shared login/password field discovery. */
export const MT5_FORM_INPUT_HELPERS_JS = `
function mt5WalkDocs(scan) {
  try {
    if (scan(document)) return true;
  } catch (e0) {}
  var iframes = document.querySelectorAll('iframe');
  for (var fi = 0; fi < iframes.length; fi++) {
    try {
      var idoc = iframes[fi].contentDocument;
      if (idoc && scan(idoc)) return true;
    } catch (eIf) {}
  }
  return false;
}
function mt5QueryInDocs(selector) {
  var found = null;
  mt5WalkDocs(function(doc) {
    var el = doc.querySelector(selector);
    if (el) { found = el; return true; }
    return false;
  });
  return found;
}
function mt5QueryAllInDocs(selector) {
  var out = [];
  mt5WalkDocs(function(doc) {
    var list = doc.querySelectorAll(selector);
    for (var i = 0; i < list.length; i++) out.push(list[i]);
    return false;
  });
  return out;
}
function findMt5LoginInput() {
  var selectors = [
    'input[name="login"]',
    'input[name="Login"]',
    'input[placeholder*="Enter Login" i]',
    'input[placeholder*="login" i]',
    'input[type="number"]',
    'input#login'
  ];
  for (var si = 0; si < selectors.length; si++) {
    var el = mt5QueryInDocs(selectors[si]);
    if (el && mt5InputVisible(el)) return el;
  }
  var all = mt5QueryAllInDocs('input');
  for (var i = 0; i < all.length; i++) {
    var inp = all[i];
    var ph = ((inp.getAttribute && inp.getAttribute('placeholder')) || '').toLowerCase();
    var ty = ((inp.type || '') + '').toLowerCase();
    if (ty === 'password') continue;
    if (ph.indexOf('login') >= 0 || (ty === 'number' && ph.indexOf('password') < 0)) {
      if (mt5InputVisible(inp)) return inp;
    }
  }
  return mt5QueryInDocs('input[placeholder*="Enter Login" i]') ||
    mt5QueryInDocs('input[placeholder*="login" i]') ||
    mt5QueryInDocs('input[name="login"]');
}
function findMt5PasswordInput() {
  var selectors = [
    'input[name="password"]',
    'input[type="password"]',
    'input[placeholder*="Enter Password" i]',
    'input[placeholder*="password" i]',
    'input#password'
  ];
  for (var si = 0; si < selectors.length; si++) {
    var el = mt5QueryInDocs(selectors[si]);
    if (el && mt5InputVisible(el)) return el;
  }
  var all = mt5QueryAllInDocs('input');
  for (var i = 0; i < all.length; i++) {
    var inp = all[i];
    var ph = ((inp.getAttribute && inp.getAttribute('placeholder')) || '').toLowerCase();
    var ty = ((inp.type || '') + '').toLowerCase();
    if (ty === 'password' || ph.indexOf('password') >= 0) {
      if (mt5InputVisible(inp)) return inp;
    }
  }
  return mt5QueryInDocs('input[type="password"]') ||
    mt5QueryInDocs('input[placeholder*="Enter Password" i]');
}
function mt5InputVisible(el) {
  if (!el) return false;
  try {
    var st = window.getComputedStyle(el);
    if (st.display === 'none' || st.visibility === 'hidden' || parseFloat(st.opacity || '1') < 0.05) return false;
    var r = el.getBoundingClientRect();
    return r.width > 6 && r.height > 6;
  } catch (e) { return true; }
}
function connectSheetUiVisible() {
  try {
    var loginIn = findMt5LoginInput();
    var pwdIn = findMt5PasswordInput();
    if (mt5InputVisible(loginIn) && mt5InputVisible(pwdIn)) return true;
    var bt = '';
    mt5WalkDocs(function(doc) {
      if (doc.body) bt += (doc.body.innerText || doc.body.textContent || '') + '\\n';
      return false;
    });
    if (bt.indexOf('Connect to account') < 0) return false;
    return pageHasBrokerAccountsSheet(bt) ||
      bt.indexOf('Enter Login') >= 0 ||
      bt.indexOf('Enter Password') >= 0;
  } catch (e) { return false; }
}
function mt5LoginFormReady() {
  return mt5InputVisible(findMt5LoginInput()) && mt5InputVisible(findMt5PasswordInput());
}
function isConnectToAccountSheetOpen() {
  try {
    if (!connectSheetUiVisible()) return false;
    return mt5LoginFormReady();
  } catch (e) { return false; }
}
function mt5SetInputValue(el, val) {
  if (!el || val == null || val === '') return;
  try {
    el.focus();
    try { el.click(); } catch (eC) {}
    var nativeSetter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
    nativeSetter = nativeSetter && nativeSetter.set;
    el.value = '';
    el.dispatchEvent(new Event('input', { bubbles: true }));
    if (nativeSetter) nativeSetter.call(el, String(val));
    else el.value = String(val);
    try {
      el.dispatchEvent(new InputEvent('input', { bubbles: true, cancelable: true, inputType: 'insertText', data: String(val) }));
    } catch (eIn) {
      el.dispatchEvent(new Event('input', { bubbles: true }));
    }
    el.dispatchEvent(new Event('change', { bubbles: true }));
    el.dispatchEvent(new Event('blur', { bubbles: true }));
  } catch (e) {}
}
`;

/** Wait for terminal shell — proceed as soon as login form or session is visible. */
export function getMt5TerminalReadyWaitJs(shellWaitMs = 8000): string {
  return `
async function waitPastCloudflare(sendMessage, sleep, isTerminalSessionVisible) {
  sendMessage('step_update', 'Loading broker terminal...');
  var deadline = Date.now() + ${shellWaitMs};
  while (Date.now() < deadline) {
    if (isTerminalSessionVisible() || mt5LoginFormReady() || connectSheetUiVisible()) {
      sendMessage('step_update', connectSheetUiVisible() ? 'Connect form ready' : 'Terminal ready');
      return true;
    }
    await sleep(800);
  }
  await sleep(1500);
  if (isTerminalSessionVisible() || mt5LoginFormReady() || connectSheetUiVisible()) {
    sendMessage('step_update', connectSheetUiVisible() ? 'Connect form ready' : 'Terminal ready');
    return true;
  }
  sendMessage('authentication_failed', 'Terminal did not load in time — try again');
  return false;
}
`;
}

export const MT5_TERMINAL_READY_WAIT_JS = getMt5TerminalReadyWaitJs(8000);
