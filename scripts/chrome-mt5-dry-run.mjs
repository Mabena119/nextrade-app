#!/usr/bin/env bun
/**
 * EA Trade–style Chrome CDP dry-run for MT5 trading-proxy.
 *
 * Opens Google Chrome with remote debugging, loads local (or remote) trading
 * proxy with dryRun=1, and streams console / postMessage-style logs until:
 *   - dry_run_ok / Order dialog ready  → PASS
 *   - Order dialog not ready / auth fail → FAIL
 *
 * Env:
 *   MT5_LOGIN MT5_PASSWORD MT5_BROKER MT5_SYMBOL
 *   PROXY_BASE   default http://localhost:3000
 *   DRY_RUN      default 1 (set 0 to actually click Buy — careful)
 *   TIMEOUT_MS   default 180000
 *
 * Example:
 *   MT5_LOGIN=55038840 MT5_PASSWORD='…' MT5_BROKER=hfmarketssa-live2 \
 *     bun scripts/chrome-mt5-dry-run.mjs
 */
import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const CHROME =
  process.env.CHROME_PATH ||
  '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PROXY_BASE = (process.env.PROXY_BASE || 'http://127.0.0.1:3000').replace(/\/$/, '');
const LOGIN = process.env.MT5_LOGIN || '';
const PASSWORD = process.env.MT5_PASSWORD || '';
const BROKER = process.env.MT5_BROKER || 'hfmarketssa-live2';
const SYMBOL = process.env.MT5_SYMBOL || 'US30.F';
const ACTION = process.env.MT5_ACTION || 'buy';
const VOLUME = process.env.MT5_VOLUME || '0.01';
const SL = process.env.MT5_SL || '51450';
const TP = process.env.MT5_TP || '51650';
const DRY_RUN = (process.env.DRY_RUN ?? '1') !== '0';
const TIMEOUT_MS = Number(process.env.TIMEOUT_MS || 180000);
const DEBUG_PORT = Number(process.env.CDP_PORT || 9229);

if (!LOGIN || !PASSWORD) {
  console.error('Set MT5_LOGIN and MT5_PASSWORD');
  process.exit(2);
}

const proxyUrl =
  `${PROXY_BASE}/api/mt5-trading-proxy` +
  `?broker=${encodeURIComponent(BROKER)}` +
  `&login=${encodeURIComponent(LOGIN)}` +
  `&password=${encodeURIComponent(PASSWORD)}` +
  `&symbol=${encodeURIComponent(SYMBOL)}` +
  `&action=${encodeURIComponent(ACTION)}` +
  `&sl=${encodeURIComponent(SL)}` +
  `&tp=${encodeURIComponent(TP)}` +
  `&volume=${encodeURIComponent(VOLUME)}` +
  `&numberOfTrades=1` +
  `&robotName=${encodeURIComponent('ChromeDryRun')}` +
  (DRY_RUN ? '&dryRun=1' : '');

const profileDir = mkdtempSync(join(tmpdir(), 'nextrade-chrome-dry-'));
const logPath = join(profileDir, 'events.jsonl');
const events = [];

function logEvent(kind, text) {
  const line = { t: new Date().toISOString(), kind, text: String(text || '') };
  events.push(line);
  const short = line.text.length > 240 ? line.text.slice(0, 240) + '…' : line.text;
  console.log(`[${kind}] ${short}`);
}

function classify(text) {
  const s = String(text || '');
  if (/dry_run_ok|Dry run — form filled|Order dialog ready \(volume/i.test(s)) return 'pass';
  if (/Order dialog not ready after waiting/i.test(s)) return 'fail_dialog';
  if (/authentication_failed|Login field not found|Password field not found/i.test(s)) return 'fail_auth';
  if (/Trade button not found|Order dialog trigger not found/i.test(s)) return 'fail_ui';
  if (/all_trades_completed|Dry run — will open form/i.test(s)) return 'progress';
  if (/authentication_success|MT5 session verified/i.test(s)) return 'auth_ok';
  if (/Chart opened|Symbol .* prefix match|Configured to execute|Executing trade/i.test(s)) return 'progress';
  return null;
}

async function waitForCdp(port, attempts = 40) {
  for (let i = 0; i < attempts; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${port}/json/version`);
      if (res.ok) return await res.json();
    } catch {
      /* retry */
    }
    await Bun.sleep(250);
  }
  throw new Error(`Chrome CDP not ready on :${port}`);
}

async function cdpSend(ws, id, method, params = {}) {
  ws.send(JSON.stringify({ id, method, params }));
  return new Promise((resolve, reject) => {
    const onMsg = (raw) => {
      let msg;
      try {
        msg = JSON.parse(typeof raw === 'string' ? raw : raw.toString());
      } catch {
        return;
      }
      if (msg.id === id) {
        ws.removeEventListener('message', onMsg);
        if (msg.error) reject(new Error(JSON.stringify(msg.error)));
        else resolve(msg.result);
      }
    };
    ws.addEventListener('message', onMsg);
  });
}

let chromeProc = null;
let outcome = 'timeout';
let detail = '';

try {
  // Quick health check
  const health = await fetch(`${PROXY_BASE}/api/mt5-trading-proxy?broker=${encodeURIComponent(BROKER)}&symbol=${encodeURIComponent(SYMBOL)}&dryRun=1`).catch(
    (e) => {
      throw new Error(`Proxy not reachable at ${PROXY_BASE}: ${e.message}`);
    }
  );
  if (!health.ok) throw new Error(`Proxy HTTP ${health.status}`);
  const html = await health.text();
  if (!html.includes('isDryRun = true') && DRY_RUN) {
    console.warn('WARNING: proxy HTML missing isDryRun=true — is local server restarted?');
  }
  if (html.includes('volumeInput && commentInput && tradeButton')) {
    console.warn('WARNING: brittle commentInput dialog check still present in HTML');
  }
  if (!html.includes('Order dialog ready (volume + trade action)')) {
    console.warn('WARNING: resilient dialog-ready message missing from HTML');
  }

  logEvent('info', `Launching Chrome (CDP :${DEBUG_PORT})`);
  chromeProc = spawn(
    CHROME,
    [
      `--remote-debugging-port=${DEBUG_PORT}`,
      `--user-data-dir=${profileDir}`,
      '--no-first-run',
      '--no-default-browser-check',
      '--disable-background-networking',
      '--disable-features=TranslateUI',
      '--window-size=1400,900',
      'about:blank',
    ],
    { stdio: ['ignore', 'ignore', 'pipe'] }
  );
  chromeProc.stderr.on('data', (buf) => {
    const t = buf.toString();
    if (/ERROR|FATAL/i.test(t)) logEvent('chrome_err', t.trim().slice(0, 200));
  });

  await waitForCdp(DEBUG_PORT);
  logEvent('info', 'CDP ready');

  // Attach to the blank page, enable console, THEN navigate so we catch all MT5 logs
  const targetsRes = await fetch(`http://127.0.0.1:${DEBUG_PORT}/json/list`);
  const targets = await targetsRes.json();
  const page = targets.find((t) => t.type === 'page');
  if (!page?.webSocketDebuggerUrl) throw new Error('No page target');

  let nextId = 1;
  const pageWs = new WebSocket(page.webSocketDebuggerUrl);
  await new Promise((resolve, reject) => {
    pageWs.addEventListener('open', resolve);
    pageWs.addEventListener('error', reject);
  });
  const pageSend = (method, params) => cdpSend(pageWs, nextId++, method, params);

  await pageSend('Runtime.enable');
  await pageSend('Console.enable');
  await pageSend('Log.enable');
  await pageSend('Page.enable');

  pageWs.addEventListener('message', (raw) => {
    let msg;
    try {
      msg = JSON.parse(typeof raw === 'string' ? raw : raw.toString());
    } catch {
      return;
    }
    if (!msg.method) return;

    let text = '';
    if (msg.method === 'Runtime.consoleAPICalled') {
      const args = msg.params?.args || [];
      text = args
        .map((a) => a.value ?? a.description ?? '')
        .filter(Boolean)
        .join(' ');
    } else if (msg.method === 'Log.entryAdded') {
      text = msg.params?.entry?.text || '';
    } else if (msg.method === 'Runtime.exceptionThrown') {
      text = msg.params?.exceptionDetails?.text || 'exception';
      logEvent('exception', text);
    }
    if (!text) return;
    if (
      !/\[MT5 Trading\]|step_update|dry_run|Order dialog|authentication|Chart |Symbol |Trade |Executing|DRY RUN|Message sent|WebSocket|Market Watch|Login |Password /i.test(
        text
      )
    ) {
      return;
    }
    logEvent('console', text);
    const c = classify(text);
    if (c === 'pass') {
      outcome = 'pass';
      detail = text;
    } else if (c && c.startsWith('fail') && outcome === 'timeout') {
      outcome = c;
      detail = text;
    } else if (c === 'auth_ok') {
      logEvent('info', 'Auth OK');
    }
  });

  logEvent('info', `Navigate → ${proxyUrl.replace(PASSWORD, '***')}`);
  await pageSend('Page.navigate', { url: proxyUrl });

  // Poll until pass/fail/timeout
  const deadline = Date.now() + TIMEOUT_MS;
  while (Date.now() < deadline && outcome === 'timeout') {
    try {
      const ev = await pageSend('Runtime.evaluate', {
        expression: `(function(){
          var t = '';
          try { t = (document.body && (document.body.innerText||'')) || ''; } catch(e) {}
          return JSON.stringify({
            hasBuy: /\\bBuy\\b/i.test(t),
            hasSell: /\\bSell\\b/i.test(t),
            hasEquity: /Equity/i.test(t),
            hasVolume: !!document.querySelector('input[inputmode="decimal"]'),
            tradeBtn: !!document.querySelector('button[class*="trade-button"]'),
            title: document.title
          });
        })()`,
        returnByValue: true,
      });
      const snap = JSON.parse(ev?.result?.value || '{}');
      if (snap.hasEquity || snap.hasVolume || snap.tradeBtn) {
        logEvent('dom', JSON.stringify(snap));
      }
    } catch {
      /* page navigating */
    }
    await Bun.sleep(2000);
  }

  writeFileSync(logPath, events.map((e) => JSON.stringify(e)).join('\n') + '\n');
  console.log('\n=== RESULT ===');
  console.log('outcome:', outcome);
  console.log('detail:', detail || '(none)');
  console.log('events:', logPath);
  console.log('dryRun:', DRY_RUN);

  pageWs.close();
  process.exitCode = outcome === 'pass' ? 0 : 1;
} catch (err) {
  console.error('FATAL:', err?.message || err);
  process.exitCode = 2;
} finally {
  try {
    chromeProc?.kill('SIGTERM');
  } catch {
    /* ignore */
  }
  // Keep profile briefly for debugging if FAIL
  if (outcome === 'pass') {
    try {
      rmSync(profileDir, { recursive: true, force: true });
    } catch {
      /* ignore */
    }
  } else {
    console.log('Chrome profile kept at', profileDir);
  }
}
