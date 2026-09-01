// Simple Bun server to serve static web export and handle API routes
// - Serves files from ./dist
// - Routes API calls to optimized database connection pool

import path from 'path';
import { createPool } from 'mysql2/promise';
import {
  addSubscription,
  removeSubscription,
  loadSubscriptions,
  setOnSubscriptionRemoved,
  getVapidPublicKey,
  isPushConfigured,
} from './services/push-service';
import { startWebPushSignalsPolling, pollWebPushSignalsNow } from './services/web-push-signals-polling';
import { normalizeMt5ServerKey, resolveMt5TerminalUrl, mt5HostNeedsInsecureTls, resolveMt5BrokerBaseUrl, DEFAULT_MT5_BROKER, MT5_BROKER_URLS, ensureMt5EnglishTerminalUrl, MT5_ENGLISH_ACCEPT_LANGUAGE, MT5_ENGLISH_LOCK_JS, applyEnglishHtmlLang } from './utils/mt5-brokers';
import { patchMt5InlineAuthScript } from './utils/mt5-server-auth-script-patch';
import { resolveDbConfig } from './config/database';
// Declare Bun global for TypeScript linting in non-Bun tooling contexts
// eslint-disable-next-line @typescript-eslint/no-unused-vars
declare const Bun: any;

const DIST_DIR = path.join(process.cwd(), 'dist');
const PORT = Number(process.env.PORT || 3000);
/** VPS Bun API — used when Render cannot reach cPanel MySQL (port 3306 closed). */
const DEFAULT_VPS_API = 'https://nextradeai.io';
const NEXTRADE_SITE_ORIGIN = DEFAULT_VPS_API;
/** When set (Render), proxy /api/* to VPS — same-origin web app without remote MySQL. */
const API_UPSTREAM = (
  process.env.API_UPSTREAM_URL ||
  (process.env.RENDER ? DEFAULT_VPS_API : '')
).replace(/\/$/, '');

/**
 * Fetch MT5 terminal / assets. Some brokers (RCG) omit intermediate certs so
 * strict TLS fails in Bun/Node while browsers still load fine.
 */
async function fetchMt5Remote(targetUrl: string, init: RequestInit = {}): Promise<Response> {
  let hostname = '';
  try {
    hostname = new URL(targetUrl).hostname;
  } catch {
    /* ignore */
  }
  const headers = {
    ...((init.headers as Record<string, string> | undefined) || {}),
    'Accept-Language': MT5_ENGLISH_ACCEPT_LANGUAGE,
  };
  const nextInit = { ...init, headers };
  if (mt5HostNeedsInsecureTls(hostname)) {
    return fetch(targetUrl, {
      ...nextInit,
      // Bun: allow incomplete chain for known broker hosts only
      tls: { rejectUnauthorized: false },
    } as RequestInit);
  }
  return fetch(targetUrl, nextInit);
}

function injectEnglishLockIntoHtml(html: string): string {
  let next = applyEnglishHtmlLang(html);
  const tag = `<script>${MT5_ENGLISH_LOCK_JS}</script>`;
  if (next.includes('</head>')) {
    return next.replace('</head>', `${tag}</head>`);
  }
  if (next.includes('<head>')) {
    return next.replace('<head>', `<head>${tag}`);
  }
  return tag + next;
}

/** Lowercase slug → broker origin, derived from MT5_BROKER_URLS (+ short aliases). */
function buildMt5BrokerBaseUrlMap(): Record<string, string> {
  const map: Record<string, string> = {};
  for (const [key, terminalUrl] of Object.entries(MT5_BROKER_URLS)) {
    try {
      const u = new URL(terminalUrl);
      const base = `${u.protocol}//${u.host}`;
      const slug = key.toLowerCase().replace(/\s+/g, '-');
      map[slug] = base;
    } catch {
      /* skip bad URL */
    }
  }
  // Short aliases used by ?broker= query / legacy keys
  map['rcg'] = map['rcgmarkets-real'] || 'https://webtrader.rcgmarkets.com';
  map['rcgmarkets'] = map['rcg']!;
  map['rcgmarkets-live'] = map['rcg']!;
  map['razormarkets'] = map['razormarkets-live'] || 'https://webtrader.razormarkets.co.za';
  map['atfx'] = map['atfxafrica-live'] || 'https://webtrading.atfxafrica.com';
  map['atfxafrica'] = map['atfx']!;
  map['space'] = map['spacemarkets-live'] || 'https://webtrader.spacemarkets.io';
  map['spacemarkets'] = map['space']!;
  map['trade245'] = map['trade245-live'] || 'https://webtrader.trade245.com';
  map['trade-245'] = map['trade245']!;
  map['accumarkets'] = map['accumarkets-live'] || 'https://webterminal.accumarkets.co.za';
  map['rockwest'] = map['rockwest-server'] || 'https://webtrader.rock-west.com';
  map['rock-west'] = map['rockwest']!;
  map['maonoglobalmarkets'] = map['maonoglobalmarkets-live'] || 'https://web.maonoglobalmarkets.com';
  map['rocketx'] = map['rocketx-live'] || 'https://webtrader.rocketx.io:1950';
  map['profinwealth'] = map['profinwealth-live'] || 'https://mt5.profinwealth.com';
  map['pxbttrading'] = map['pxbttrading-1'] || 'https://mt5.primexbt.com';
  map['jpmarkets'] = map['jpmarkets-live'] || 'https://web.jpmarkets.co.za';
  map['vaultmarkets'] = map['vaultmarkets-live'] || 'https://web.vaultmarkets.trade';
  return map;
}

const MT5_BROKER_BASE_URL_MAP = buildMt5BrokerBaseUrlMap();
const DEFAULT_MT5_BROKER_BASE_URL = resolveMt5BrokerBaseUrl(DEFAULT_MT5_BROKER);
/**
 * Routes that must run on this host (never relay to VPS).
 * MT5 terminal proxies rewrite HTML/asset origins to the request host — if Render
 * upstreams them to auraai-vps.com, the web WebView on onrender.com gets a broken
 * cross-origin terminal (EA Trade keeps mt5-proxy on the same app host).
 */
const LOCAL_API_PREFIXES = [
  '/api/analyze-chart',
  '/api/mt5-trade-sizing',
  '/api/mt5-proxy',
  '/api/mt5-trading-proxy',
  '/api/terminal-proxy',
  '/api/validate-mt5',
  '/api/brand-asset',
  // Gmail SMTP must run on Render — cPanel smtpmailgidonly hijacks VPS outbound 587/465
  '/api/send-email',
];

/** Hosts where we force https://hostname for MT5 HTML rewrites (same-origin as WebView). */
function isMt5ProxyPublicHost(hostname: string): boolean {
  return (
    hostname.includes('onrender.com') ||
    hostname.includes('nextradeai.io') ||
    hostname.includes('auraai-vps.com') ||
    hostname === 'localhost' ||
    hostname === '127.0.0.1'
  );
}

/** Prefer the browser-facing host when behind Render/Cloudflare (X-Forwarded-Host). */
function resolveMt5ProxyOrigin(request: Request, url: URL): string {
  const forwarded = (request.headers.get('x-forwarded-host') || '')
    .split(',')[0]
    ?.trim();
  const hostHeader = (request.headers.get('host') || '').trim();
  const hostname = (forwarded || hostHeader || url.hostname).split(':')[0];
  const forwardedProto = (request.headers.get('x-forwarded-proto') || '')
    .split(',')[0]
    ?.trim()
    .toLowerCase();
  const useHttps =
    forwardedProto === 'https' ||
    url.protocol === 'https:' ||
    isMt5ProxyPublicHost(hostname);
  if (useHttps && hostname) {
    return `https://${hostname}`;
  }
  return url.origin;
}

function shouldHandleApiLocally(pathname: string): boolean {
  return LOCAL_API_PREFIXES.some((p) => pathname === p || pathname.startsWith(p + '/'));
}

/**
 * EA Trade serves API + PWA on one host and uses open CORS (`*`) on API responses.
 * Reflect the browser Origin when present; otherwise `*`.
 * Never substitute a mismatched allowlist entry — that breaks iOS Safari/PWA polling.
 */
function corsHeaders(request: Request): Record<string, string> {
  const origin = (request.headers.get('Origin') || '').trim();
  const allowed = origin || '*';
  return {
    'Access-Control-Allow-Origin': allowed,
    'Access-Control-Allow-Methods': 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
    'Access-Control-Allow-Headers':
      'Content-Type, Authorization, x-auraai-email-secret, x-requested-with',
    'Access-Control-Max-Age': '86400',
    ...(allowed !== '*' ? { Vary: 'Origin' } : {}),
  };
}

function withCors(request: Request, response: Response): Response {
  const headers = new Headers(response.headers);
  for (const [key, value] of Object.entries(corsHeaders(request))) {
    headers.set(key, value);
  }
  return new Response(response.body, {
    status: response.status,
    statusText: response.statusText,
    headers,
  });
}

/** Server-side relay to VPS API (Render → auraai-vps.com). No browser CORS involved. */
async function proxyApiToUpstream(request: Request): Promise<Response> {
  const url = new URL(request.url);
  const upstreamBase = API_UPSTREAM.replace(/\/$/, '');
  const target = `${upstreamBase}${url.pathname}${url.search}`;

  const headers = new Headers(request.headers);
  headers.delete('host');
  headers.delete('connection');
  if (/^https?:\/\/\d{1,3}(?:\.\d{1,3}){3}(?::\d+)?$/i.test(upstreamBase)) {
    headers.set('host', 'nextradeai.io');
  }

  const init: RequestInit = {
    method: request.method,
    headers,
    redirect: 'manual',
  };

  if (request.method !== 'GET' && request.method !== 'HEAD') {
    init.body = await request.arrayBuffer();
  }

  const upstream = await fetch(target, init);
  const outHeaders = new Headers(upstream.headers);
  outHeaders.delete('transfer-encoding');

  return new Response(upstream.body, {
    status: upstream.status,
    statusText: upstream.statusText,
    headers: outHeaders,
  });
}

const { host: DB_HOST, user: DB_USER, password: DB_PASSWORD, database: DB_NAME, port: DB_PORT } =
  resolveDbConfig();

// Optimized connection pool configuration for scaling AND CPU efficiency
const POOL_CONFIG = {
  connectionLimit: Number(process.env.DB_CONNECTION_LIMIT || 20),
  maxIdle: Number(process.env.DB_MAX_IDLE || 10),
  idleTimeout: Number(process.env.DB_IDLE_TIMEOUT || 60000),
  queueLimit: Number(process.env.DB_QUEUE_LIMIT || 50),
  enableKeepAlive: true,
  keepAliveInitialDelay: 10000,
  waitForConnections: true,
  connectTimeout: Number(process.env.DB_CONNECT_TIMEOUT || 20000),
  acquireTimeout: Number(process.env.DB_ACQUIRE_TIMEOUT || 20000),
  timeout: Number(process.env.DB_QUERY_TIMEOUT || 30000),

  // CPU-efficient settings
  decimalNumbers: true,
  bigNumberStrings: false,
  supportBigNumbers: true,
  dateStrings: false,
  typeCast: true,
  multipleStatements: false,
  rowsAsArray: false,
};

// Create optimized database connection pool
const pool = createPool({
  host: DB_HOST,
  user: DB_USER,
  password: DB_PASSWORD,
  database: DB_NAME,
  port: DB_PORT,
  ...POOL_CONFIG,
});

console.log('✅ Database connection pool initialized:', {
  host: DB_HOST,
  database: DB_NAME,
  connectionLimit: POOL_CONFIG.connectionLimit,
});

async function verifyDatabaseConnection(): Promise<void> {
  if (API_UPSTREAM) {
    console.log(`ℹ️ API upstream proxy enabled → ${API_UPSTREAM}/api/* (skipping local DB ping)`);
    return;
  }
  try {
    const conn = await pool.getConnection();
    await conn.ping();
    conn.release();
    console.log(`✅ Database reachable at ${DB_HOST}:${DB_PORT}/${DB_NAME}`);
  } catch (error) {
    console.error(`❌ Database connection failed (${DB_HOST}:${DB_PORT}):`, error);
  }
}

void verifyDatabaseConnection();

function getPool() {
  return pool;
}

// Graceful shutdown
async function shutdownServer() {
  console.log('🔄 Shutting down server...');
  try {
    await pool.end();
    console.log('✅ Database connections closed');
    process.exit(0);
  } catch (error) {
    console.error('❌ Error during shutdown:', error);
    process.exit(1);
  }
}

process.on('SIGTERM', shutdownServer);
process.on('SIGINT', shutdownServer);

async function serveStatic(request: Request): Promise<Response> {
  try {
    const url = new URL(request.url);
    let filePath = url.pathname;

    // Prevent path traversal
    if (filePath.includes('..')) {
      return new Response('Not Found', { status: 404 });
    }

    // Default to index.html
    if (filePath === '/' || filePath === '') {
      filePath = '/index.html';
    }

    const absolutePath = path.join(DIST_DIR, filePath);
    const file = Bun.file(absolutePath);
    if (await file.exists()) {
      // Set proper MIME type based on file extension
      const ext = path.extname(filePath).toLowerCase();
      let contentType = 'application/octet-stream';

      switch (ext) {
        case '.html':
          contentType = 'text/html; charset=utf-8';
          break;
        case '.css':
          contentType = 'text/css; charset=utf-8';
          break;
        case '.js':
          contentType = 'application/javascript; charset=utf-8';
          break;
        case '.json':
          contentType = 'application/json; charset=utf-8';
          break;
        case '.png':
          contentType = 'image/png';
          break;
        case '.jpg':
        case '.jpeg':
          contentType = 'image/jpeg';
          break;
        case '.gif':
          contentType = 'image/gif';
          break;
        case '.svg':
          contentType = 'image/svg+xml';
          break;
        case '.ico':
          contentType = 'image/x-icon';
          break;
        case '.woff':
          contentType = 'font/woff';
          break;
        case '.woff2':
          contentType = 'font/woff2';
          break;
        case '.ttf':
          contentType = 'font/ttf';
          break;
        case '.eot':
          contentType = 'application/vnd.ms-fontobject';
          break;
      }

      // Service worker must not be cached so updates propagate (critical for iOS PWA)
      const isServiceWorker = filePath === '/sw.js';
      const cacheControl = ext === '.html'
        ? 'no-cache, no-store, must-revalidate'
        : isServiceWorker
          ? 'no-cache, no-store, must-revalidate'
          : 'public, max-age=31536000';

      return new Response(file, {
        headers: {
          'Content-Type': contentType,
          'Cache-Control': cacheControl,
        },
      });
    }

    // SPA fallback
    const indexFile = Bun.file(path.join(DIST_DIR, 'index.html'));
    if (await indexFile.exists()) {
      return new Response(indexFile, {
        headers: {
          'Content-Type': 'text/html; charset=utf-8',
        },
      });
    }

    return new Response('Not Found', { status: 404 });
  } catch (error) {
    console.error('Static serve error:', error);
    return new Response('Internal Server Error', { status: 500 });
  }
}

async function handleApi(request: Request): Promise<Response> {
  const url = new URL(request.url);
  const { pathname } = url;

  try {
    if (pathname === '/api/validate-mt5') {
      const route = await import('./app/api/validate-mt5/route.ts');
      if (request.method === 'GET' && typeof route.GET === 'function') {
        return route.GET(request) as Promise<Response>;
      }
      if (request.method === 'OPTIONS' && typeof route.OPTIONS === 'function') {
        return route.OPTIONS() as Promise<Response>;
      }
      return new Response('Method Not Allowed', { status: 405 });
    }

    if (pathname === '/api/check-email') {
      const route = await import('./app/api/check-email/route.ts');
      if (request.method === 'POST' && typeof route.POST === 'function') {
        return route.POST(request) as Promise<Response>;
      }
      if (request.method === 'GET' && typeof route.GET === 'function') {
        return route.GET() as Promise<Response>;
      }
      return new Response('Method Not Allowed', { status: 405 });
    }
    // Add auth-license routing
    if (pathname === '/api/auth-license') {
      const route = await import('./app/api/auth-license/route.ts');
      if (request.method === 'POST' && typeof route.POST === 'function') {
        return route.POST(request) as Promise<Response>;
      }
      if (request.method === 'GET' && typeof route.GET === 'function') {
        return route.GET() as Promise<Response>;
      }
      return new Response('Method Not Allowed', { status: 405 });
    }

    // Add symbols routing
    if (pathname === '/api/symbols') {
      const route = await import('./app/api/symbols/route.ts');
      if (request.method === 'GET' && typeof route.GET === 'function') {
        return route.GET(request) as Promise<Response>;
      }
      return new Response('Method Not Allowed', { status: 405 });
    }

    // Add AI chart analysis routing
    if (pathname === '/api/analyze-chart') {
      const route = await import('./app/api/analyze-chart/route.ts');
      if (request.method === 'POST' && typeof route.POST === 'function') {
        return route.POST(request) as Promise<Response>;
      }
      if (request.method === 'GET' && typeof route.GET === 'function') {
        return route.GET() as Promise<Response>;
      }
      return new Response('Method Not Allowed', { status: 405 });
    }

    if (pathname === '/api/mt5-trade-sizing') {
      const route = await import('./app/api/mt5-trade-sizing/route.ts');
      if (request.method === 'POST' && typeof route.POST === 'function') {
        return route.POST(request) as Promise<Response>;
      }
      if (request.method === 'GET' && typeof route.GET === 'function') {
        return route.GET() as Promise<Response>;
      }
      return new Response('Method Not Allowed', { status: 405 });
    }

    if (pathname === '/api/scanner-status') {
      const route = await import('./app/api/scanner-status/route.ts');
      if (request.method === 'GET' && typeof route.GET === 'function') {
        return route.GET(request) as Promise<Response>;
      }
      if (request.method === 'POST' && typeof route.POST === 'function') {
        return route.POST(request) as Promise<Response>;
      }
      return new Response('Method Not Allowed', { status: 405 });
    }

    if (pathname === '/api/ozow-checkout') {
      const route = await import('./app/api/ozow-checkout/route.ts');
      if (request.method === 'POST' && typeof route.POST === 'function') {
        return route.POST(request) as Promise<Response>;
      }
      return new Response('Method Not Allowed', { status: 405 });
    }

    if (pathname === '/api/send-email') {
      const route = await import('./app/api/send-email/route.ts');
      if (request.method === 'POST' && typeof route.POST === 'function') {
        return route.POST(request) as Promise<Response>;
      }
      if (request.method === 'GET' && typeof route.GET === 'function') {
        return route.GET() as Promise<Response>;
      }
      return new Response('Method Not Allowed', { status: 405 });
    }

    // Add terminal-proxy routing
    if (pathname === '/api/terminal-proxy') {
      const route = await import('./app/api/terminal-proxy.ts');
      if (request.method === 'GET' && typeof route.default === 'function') {
        // Convert Bun Request to Express-like request/response
        const expressReq = {
          method: request.method,
          query: Object.fromEntries(new URL(request.url).searchParams),
          url: request.url
        } as any;

        const expressRes = {
          status: (code: number) => ({
            json: (data: any) => new Response(JSON.stringify(data), {
              status: code,
              headers: { 'Content-Type': 'application/json' }
            }),
            send: (data: string) => new Response(data, {
              status: code,
              headers: { 'Content-Type': 'text/html; charset=utf-8' }
            })
          }),
          setHeader: (name: string, value: string) => { }
        } as any;

        return route.default(expressReq, expressRes);
      }
      return new Response('Method Not Allowed', { status: 405 });
    }

    /**
     * Same-origin brand/upload proxy for iOS PWA / web (EA Trade pattern: avoid
     * cross-origin fetch to auraai-vps.com/admin/uploads which has no CORS headers).
     * Usage: /api/brand-asset?path=6a4f….png  OR  /api/brand-asset?url=https://auraai-vps.com/admin/uploads/…
     */
    if (pathname === '/api/brand-asset') {
      if (request.method === 'OPTIONS') {
        return new Response(null, { status: 204, headers: corsHeaders(request) });
      }
      if (request.method !== 'GET' && request.method !== 'HEAD') {
        return new Response('Method Not Allowed', { status: 405 });
      }
      try {
        const rawPath = (url.searchParams.get('path') || '').replace(/^\/+/, '');
        const rawUrl = (url.searchParams.get('url') || '').trim();
        let target: string | null = null;
        if (rawUrl) {
          const u = new URL(rawUrl);
          if (
            (u.hostname === 'nextradeai.io' ||
              u.hostname === 'www.nextradeai.io' ||
              u.hostname === 'auraai-vps.com' ||
              u.hostname === 'www.auraai-vps.com') &&
            u.pathname.startsWith('/admin/uploads/')
          ) {
            target = u.toString();
          }
        } else if (rawPath && !rawPath.includes('..') && /^[A-Za-z0-9._/\-]+$/.test(rawPath)) {
          target = `${NEXTRADE_SITE_ORIGIN}/admin/uploads/${rawPath}`;
        }
        if (!target) {
          return new Response('Bad request', { status: 400 });
        }
        const upstream = await fetch(target, {
          headers: {
            'User-Agent':
              'Mozilla/5.0 (compatible; AuraAIBrandProxy/1.0)',
            Referer: `${NEXTRADE_SITE_ORIGIN}/`,
            Accept: '*/*',
          },
        });
        if (!upstream.ok) {
          return new Response(`Upstream ${upstream.status}`, { status: upstream.status });
        }
        const headers = new Headers();
        const ct = upstream.headers.get('content-type') || 'application/octet-stream';
        headers.set('Content-Type', ct);
        headers.set('Cache-Control', 'public, max-age=3600');
        headers.set('Access-Control-Allow-Origin', '*');
        const buf = await upstream.arrayBuffer();
        return new Response(buf, { status: 200, headers });
      } catch (e) {
        console.error('[brand-asset] proxy error:', e);
        return new Response('Proxy failed', { status: 502 });
      }
    }


    // Database API endpoints
    // Get EA ID from license key
    if (pathname === '/api/get-ea-from-license') {
      if (request.method === 'GET') {
        const licenseKey = url.searchParams.get('licenseKey');
        if (!licenseKey) {
          return new Response(JSON.stringify({ error: 'License key required' }), {
            status: 400,
            headers: { 'Content-Type': 'application/json' },
          });
        }

        let conn = null;
        try {
          const pool = getPool();
          conn = await pool.getConnection();

          const [rows] = await conn.execute(
            'SELECT ea FROM licences WHERE k_ey = ? LIMIT 1',
            [licenseKey]
          );

          const result = rows as any[];
          const eaId = result.length > 0 ? result[0].ea : null;

          // Return in format expected by client: { id: eaId } or { eaId: eaId } for compatibility
          return new Response(JSON.stringify({
            id: eaId,
            eaId: eaId  // Also include eaId for backward compatibility
          }), {
            headers: { 'Content-Type': 'application/json' },
          });
        } catch (error) {
          console.error('❌ Database error in get-ea-from-license:', error);
          return new Response(JSON.stringify({ error: 'Database error' }), {
            status: 500,
            headers: { 'Content-Type': 'application/json' },
          });
        } finally {
          if (conn) {
            try {
              conn.release();
            } catch (releaseError) {
              console.error('❌ Failed to release connection:', releaseError);
            }
          }
        }
      }
      return new Response('Method Not Allowed', { status: 405 });
    }

    // MT5 Proxy endpoint - fetches MT5 terminal and injects authentication script
    if (pathname === '/api/mt5-proxy') {
      if (request.method === 'GET') {
        const broker = normalizeMt5ServerKey(url.searchParams.get('broker') || '');
        const server = broker;
        const terminalUrl = ensureMt5EnglishTerminalUrl(
          url.searchParams.get('url') || resolveMt5TerminalUrl(broker)
        );
        const login = url.searchParams.get('login');
        const password = url.searchParams.get('password');

        if (!terminalUrl) {
          return new Response('Missing terminal URL', { status: 400 });
        }

        try {
          // Fetch the MT5 terminal page
          const response = await fetchMt5Remote(terminalUrl, {
            headers: {
              'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
              'Accept-Language': MT5_ENGLISH_ACCEPT_LANGUAGE,
            },
          });

          if (!response.ok) {
            return new Response(`Failed to fetch terminal: ${response.statusText}`, { status: response.status });
          }

          let html = await response.text();
          html = injectEnglishLockIntoHtml(html);

          // Get base URL for fixing relative URLs
          const baseUrlObj = new URL(terminalUrl);
          const baseUrl = `${baseUrlObj.protocol}//${baseUrlObj.host}`;
          const wsBaseUrl = baseUrl.replace('http://', 'ws://').replace('https://', 'wss://');
          // Root-based terminals (e.g. Profinwealth) serve from domain root, not /terminal/
          const isRootTerminal = !terminalUrl.replace(/\/$/, '').endsWith('/terminal');

          // Fix relative URLs in HTML (for assets, scripts, stylesheets)
          // Replace relative URLs with proxy URLs so they go through our proxy
          // Same-origin as the WebView host (Render or VPS) — never rewrite to the other.
          const proxyOrigin = resolveMt5ProxyOrigin(request, url);

          // For terminal assets, route through proxy to avoid CORS issues
          // Root terminals: route ALL /path through proxy; standard: route /terminal/path
          html = html.replace(/href="\/([^"]+)"/g, (match, path) => {
            if (path.startsWith('terminal/')) {
              return `href="${proxyOrigin}/terminal/${path.replace('terminal/', '')}?broker=${encodeURIComponent(broker)}"`;
            }
            if (isRootTerminal) return `href="${proxyOrigin}/terminal/${path}?broker=${encodeURIComponent(broker)}"`;
            return `href="${baseUrl}/${path}"`;
          });
          html = html.replace(/src="\/([^"]+)"/g, (match, path) => {
            if (path.startsWith('terminal/')) {
              return `src="${proxyOrigin}/terminal/${path.replace('terminal/', '')}?broker=${encodeURIComponent(broker)}"`;
            }
            if (isRootTerminal) return `src="${proxyOrigin}/terminal/${path}?broker=${encodeURIComponent(broker)}"`;
            return `src="${baseUrl}/${path}"`;
          });
          html = html.replace(/url\("\/\/([^"]+)"\)/g, (match, path) => {
            if (path.startsWith('terminal/')) {
              // Route through proxy for CSS url() references
              return `url("${proxyOrigin}/terminal/${path.replace('terminal/', '')}?broker=${encodeURIComponent(broker)}")`;
            }
            return `url("${baseUrl}/${path}")`;
          });
          html = html.replace(/url\('\/\/([^']+)'\)/g, (match, path) => {
            if (path.startsWith('terminal/')) {
              // Route through proxy for CSS url() references
              return `url('${proxyOrigin}/terminal/${path.replace('terminal/', '')}?broker=${encodeURIComponent(broker)}')`;
            }
            return `url('${baseUrl}/${path}')`;
          });

          // Also fix any absolute broker URLs in the HTML to use proxy
          html = html.replace(new RegExp(`${baseUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}/terminal/([^"'>\\s]+)`, 'g'), (match, assetPath) => {
            return `${proxyOrigin}/terminal/${assetPath}?broker=${encodeURIComponent(broker)}`;
          });

          // Fix WebSocket URLs - replace proxy domain with broker domain
          const proxyDomain = url.origin; // e.g., https://auraai-vps.com
          const proxyHost = proxyDomain.replace(/https?:\/\//, '').replace(/\./g, '\\.');

          // Replace WebSocket URLs pointing to proxy with broker's WebSocket URL
          html = html.replace(new RegExp(`wss?://${proxyHost}/terminal/ws`, 'gi'), `${wsBaseUrl}/terminal/ws`);
          html = html.replace(new RegExp(`wss?://${proxyHost}/terminal/`, 'gi'), `${wsBaseUrl}/terminal/`);

          // Fix dynamically constructed WebSocket URLs
          // Replace window.location.origin/hostname with broker's base URL in WebSocket contexts
          html = html.replace(
            /(new\s+WebSocket\s*\(\s*['"`])(wss?:\/\/)(window\.location\.(origin|hostname)|location\.(origin|hostname))(['"`])/g,
            `$1${wsBaseUrl}/terminal/ws$6`
          );

          // Inject WebSocket override script - run first (match trading-proxy: broker + /terminal paths)
          const proxyHostPlain = url.hostname;
          const wsOverrideScript = `
            (function() {
              const originalWebSocket = window.WebSocket;
              const brokerWsUrl = '${wsBaseUrl}/terminal/ws';
              const proxyHost = '${proxyHostPlain}';
              const brokerHost = '${baseUrlObj.host}';
              window.WebSocket = function(url, protocols) {
                if (url && typeof url === 'string') {
                  const isToProxy = url.includes(proxyHost) || (url.includes('/terminal') && !url.includes(brokerHost));
                  if (isToProxy) {
                    return new originalWebSocket(brokerWsUrl, protocols);
                  }
                }
                return new originalWebSocket(url, protocols);
              };
              Object.setPrototypeOf(window.WebSocket, originalWebSocket);
              window.WebSocket.prototype = originalWebSocket.prototype;
              window.WebSocket.CONNECTING = originalWebSocket.CONNECTING;
              window.WebSocket.OPEN = originalWebSocket.OPEN;
              window.WebSocket.CLOSING = originalWebSocket.CLOSING;
              window.WebSocket.CLOSED = originalWebSocket.CLOSED;
            })();
          `;

          // Inject WebSocket override script before auth script
          if (html.includes('</head>')) {
            html = html.replace('</head>', `<script>${wsOverrideScript}</script></head>`);
          } else if (html.includes('<head>')) {
            html = html.replace('<head>', `<head><script>${wsOverrideScript}</script>`);
          } else {
            html = `<script>${wsOverrideScript}</script>` + html;
          }

          // Escape credentials for safe injection (same as Android)
          const escapeValue = (value: string) => {
            return (value || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\n/g, '\\n').replace(/\r/g, '\\r');
          };

          const loginValue = escapeValue(login || '');
          const passwordValue = escapeValue(password || '');
          const serverValue = escapeValue(server || '');

          // Generate authentication script - EXACT COPY from Android getMT5Script()
          // This script will be injected into the HTML and executed automatically when page loads
          const authScript = `
            (function() {
              ${MT5_ENGLISH_LOCK_JS}
              console.log('[MT5 Auth] Script injected and executing...');
              
              const sendMessage = (type, message, extras) => {
                try {
                  var payload = { type: type, message: message };
                  if (extras && typeof extras === 'object') {
                    for (var key in extras) {
                      if (Object.prototype.hasOwnProperty.call(extras, key) && extras[key] != null) {
                        payload[key] = extras[key];
                      }
                    }
                  }
                  var messageData = JSON.stringify(payload);
                  if (window.parent && window.parent !== window) {
                    window.parent.postMessage(messageData, '*');
                  }
                  if (window.ReactNativeWebView) {
                    window.ReactNativeWebView.postMessage(messageData);
                  }
                  console.log('[MT5 Auth] Message sent:', type, message);
                } catch(e) {
                  console.error('[MT5 Auth] Error sending message:', e);
                }
              };

              function collectPageTextDeep() {
                var parts = [];
                function walk(d) {
                  try {
                    if (!d) return;
                    if (d.body && d.body.innerText) parts.push(d.body.innerText);
                    var ifr = d.querySelectorAll('iframe');
                    for (var ii = 0; ii < ifr.length; ii++) {
                      try {
                        var ind = ifr[ii].contentDocument;
                        if (ind) walk(ind);
                      } catch (eIf) {}
                    }
                  } catch (eW) {}
                }
                walk(document);
                return parts.join('\\n');
              }

              function normalizeAmountToken(raw) {
                if (!raw) return null;
                var s = String(raw).replace(/[\\s\\u00a0\\u202f\\u2007\\u2009]+/g, '').replace(/'/g, '');
                if (s.indexOf('.') >= 0) {
                  s = s.replace(/,/g, '');
                } else if (s.indexOf(',') > 0 && s.indexOf(',') === s.lastIndexOf(',')) {
                  var sp = s.split(',');
                  if (sp.length === 2 && sp[1].length <= 2 && /^\\d+$/.test(sp[1])) {
                    s = sp[0].replace(/\\./g, '') + '.' + sp[1];
                  } else {
                    s = s.replace(/,/g, '');
                  }
                } else {
                  s = s.replace(/,/g, '');
                }
                return s || null;
              }

              function scrapeTerminalAccountStats() {
                var equity = null;
                var balance = null;
                var floatingProfit = null;
                try {
                  var raw = collectPageTextDeep();
                  var txt = raw || ((document.body && document.body.innerText) ? document.body.innerText : '');
                  txt = txt.replace(/[\\u00a0\\u202f\\u2007\\u2009]/g, ' ');
                  var lineEq = txt.match(/(?:^|[\\n\\r])\\s*Equity\\s*[:\\s]+([\\d][\\d\\s,']*\\.?\\d*)/im);
                  if (lineEq) equity = normalizeAmountToken(lineEq[1]);
                  var lineBal = txt.match(/(?:^|[\\n\\r])\\s*Balance\\s*[:\\s]+([\\d][\\d\\s,']*\\.?\\d*)/im);
                  if (lineBal) balance = normalizeAmountToken(lineBal[1]);
                  if (!equity || !balance) {
                    var compact = txt.replace(/[\\n\\r]+/g, ' ');
                    if (!equity) {
                      var e2 = compact.match(/Equity[:\\s]+([\\d][\\d\\s,']*\\.?\\d*)/i);
                      if (e2) equity = normalizeAmountToken(e2[1]);
                    }
                    if (!balance) {
                      var b2 = compact.match(/Balance[:\\s]+([\\d][\\d\\s,']*\\.?\\d*)/i);
                      if (b2) balance = normalizeAmountToken(b2[1]);
                    }
                  }
                  if (!equity) {
                    var e3 = txt.match(/\\bEquity\\b[^\\d\\n]{0,56}([\\d][\\d\\s,\\.']*)/im);
                    if (e3) equity = normalizeAmountToken(e3[1]);
                  }
                  if (!balance) {
                    var b3 = txt.match(/\\bBalance\\b[^\\d\\n]{0,56}([\\d][\\d\\s,\\.']*)/im);
                    if (b3) balance = normalizeAmountToken(b3[1]);
                  }
                  var cfp = txt.replace(/[\\n\\r\\t]+/g, ' ').replace(/\\s+/g, ' ');
                  var g1 = cfp.match(/(?:Floating|Unrealized)\\s*(?:P\\/?L|Profit)?\\s*[:#]?\\s*([-+]?[\\d][\\d\\s,']*\\.?\\d*)/i);
                  if (g1) floatingProfit = normalizeAmountToken(g1[1]);
                  if (floatingProfit == null) {
                    var g2 = cfp.match(/\\bP\\s*\\/?\\s*L\\s*[:#]?\\s*([-+]?[\\d][\\d\\s,']*\\.?\\d*)/i);
                    if (g2) floatingProfit = normalizeAmountToken(g2[1]);
                  }
                } catch (err) {}
                return { equity: equity, balance: balance, floatingProfit: floatingProfit };
              }

              function findMT5SearchField() {
                var q = [
                  'input[placeholder*="Search symbol" i]',
                  'input[placeholder*="symbol" i]',
                  'input[placeholder*="Search" i]',
                  'input[aria-label*="Search" i]',
                  'input[type="search"]'
                ];
                for (var qi = 0; qi < q.length; qi++) {
                  var el = document.querySelector(q[qi]);
                  if (!el || !el.offsetParent) continue;
                  if (q[qi].indexOf('type="search"') >= 0) {
                    var ph = ((el.getAttribute && el.getAttribute('placeholder')) || '').toLowerCase();
                    var nm = ((el.name || '') + '').toLowerCase();
                    if (ph.indexOf('login') >= 0 || ph.indexOf('password') >= 0 || nm === 'login' || nm === 'password') continue;
                  }
                  return el;
                }
                return null;
              }

              sendMessage('mt5_loaded', 'MT5 terminal loaded successfully');
              console.log('[MT5 Auth] Script initialized, waiting for page load...');
              
              const sleep = (ms) => new Promise(r => setTimeout(r, ms));
              
              // Store credentials
              const loginCredential = '${loginValue}';
              const passwordCredential = '${passwordValue}';
              const serverCredential = '${escapeValue(server || '')}';

              function isTerminalSessionVisible() {
                try {
                  var sb = document.querySelector('input[placeholder*="Search symbol" i]') ||
                           document.querySelector('input[placeholder*="Search" i]') ||
                           document.querySelector('input[type="search"]');
                  if (sb && sb.offsetParent) return true;
                  var txt = (document.body && document.body.innerText) ? document.body.innerText : '';
                  if (/\\bEquity\\b/i.test(txt) && /\\bBalance\\b/i.test(txt)) return true;
                  if (/\\bBid\\b/i.test(txt) && /\\bAsk\\b/i.test(txt)) return true;
                  var list = document.querySelectorAll('canvas');
                  for (var ci = 0; ci < list.length; ci++) {
                    var c = list[ci];
                    if ((c.width || 0) * (c.height || 0) >= 50000) return true;
                  }
                } catch (e) {}
                return false;
              }

              function isConnectModalVisible() {
                try {
                  var bt = (document.body && document.body.innerText) ? document.body.innerText : '';
                  if (bt.indexOf('Connect to account') < 0) return false;
                  var pwd = document.querySelector('input[type="password"]');
                  if (!pwd || !pwd.offsetParent) return false;
                  var rr = pwd.getBoundingClientRect();
                  return rr.width > 0 && rr.height > 0;
                } catch (e) { return false; }
              }

              function isPasswordInModalOverlay() {
                try {
                  var pwd = document.querySelector('input[type="password"]');
                  if (!pwd || !pwd.offsetParent) return false;
                  var rr = pwd.getBoundingClientRect();
                  if (rr.width < 8 || rr.height < 8) return false;
                  var node = pwd;
                  for (var d = 0; d < 28 && node; d++) {
                    var cls = String(node.className || '');
                    var z = parseInt(window.getComputedStyle(node).zIndex, 10) || 0;
                    var tag = (node.tagName || '').toUpperCase();
                    if (tag === 'DIALOG' || cls.indexOf('dialog') >= 0 || cls.indexOf('modal') >= 0 || cls.indexOf('popup') >= 0 || cls.indexOf('overlay') >= 0 || cls.indexOf('backdrop') >= 0 || cls.indexOf('sheet') >= 0 || node.getAttribute('aria-modal') === 'true' || z > 45) {
                      return true;
                    }
                    node = node.parentElement;
                  }
                } catch (e2) {}
                return false;
              }

              function isTradingAccountsSheetVisible() {
                try {
                  if (!isTerminalSessionVisible()) return false;
                  var bt = (document.body && document.body.innerText) ? document.body.innerText : '';
                  var hasTitle = bt.indexOf('Trading accounts') >= 0 || bt.indexOf('Trading account') >= 0 ||
                    (bt.indexOf('Razor Markets') >= 0 && (bt.indexOf('Connect to account') >= 0 || bt.indexOf('Remove') >= 0));
                  if (!hasTitle) return false;
                  if (bt.indexOf('Connect to account') < 0 && bt.indexOf('Remove') < 0) return false;
                  return true;
                } catch (e) { return false; }
              }

              function findTradingAccountsOverlayRoot() {
                try {
                  var candidates = document.querySelectorAll('div, section, aside, [role="dialog"], dialog');
                  var best = null;
                  var minArea = 1e12;
                  for (var i = 0; i < Math.min(candidates.length, 450); i++) {
                    var el = candidates[i];
                    if (!el.offsetParent) continue;
                    var txt = (el.innerText || '').trim();
                    if (txt.length < 40 || txt.length > 2500) continue;
                    if (txt.indexOf('Trading accounts') < 0 && txt.indexOf('Razor Markets') < 0) continue;
                    if (txt.indexOf('Connect to account') < 0 && txt.indexOf('Remove') < 0) continue;
                    var r = el.getBoundingClientRect();
                    var area = r.width * r.height;
                    if (r.width > 100 && r.height > 90 && area >= 12000 && area < minArea) {
                      minArea = area;
                      best = el;
                    }
                  }
                  if (best) return best;
                  var btns = document.querySelectorAll('button, [role="button"]');
                  for (var b = 0; b < Math.min(btns.length, 120); b++) {
                    var t = ((btns[b].innerText || btns[b].textContent || '') + '').trim().toLowerCase();
                    if (t.indexOf('connect') >= 0 && t.indexOf('account') >= 0) {
                      var node = btns[b];
                      for (var d = 0; d < 22 && node; d++) {
                        var inner = (node.innerText || '').trim();
                        if (inner.indexOf('Trading accounts') >= 0 || inner.indexOf('Razor Markets') >= 0) return node;
                        node = node.parentElement;
                      }
                    }
                  }
                } catch (e2) {}
                return null;
              }

              function hideTradingAccountsOverlayIfPresent() {
                try {
                  if (!isTradingAccountsSheetVisible()) return false;
                  var root = findTradingAccountsOverlayRoot();
                  if (root) {
                    root.style.display = 'none';
                    root.style.visibility = 'hidden';
                    root.style.pointerEvents = 'none';
                    return true;
                  }
                  var all = document.querySelectorAll('div, section, aside, [role="dialog"]');
                  for (var ai = 0; ai < Math.min(all.length, 350); ai++) {
                    var ae = all[ai];
                    if (!ae.offsetParent) continue;
                    var atxt = (ae.innerText || '').trim();
                    if (atxt.length > 4000 || atxt.length < 35) continue;
                    if ((atxt.indexOf('Trading accounts') >= 0 || atxt.indexOf('Razor Markets') >= 0) && atxt.indexOf('Connect to account') >= 0) {
                      var ar = ae.getBoundingClientRect();
                      if (ar.width > 120 && ar.height > 80) {
                        ae.style.display = 'none';
                        ae.style.visibility = 'hidden';
                        ae.style.pointerEvents = 'none';
                        return true;
                      }
                    }
                  }
                } catch (e3) {}
                return false;
              }

              function isAnyLoginModalBlocking() {
                if (isConnectModalVisible()) return true;
                if (isTradingAccountsSheetVisible()) return true;
                if (isTerminalSessionVisible() && isPasswordInModalOverlay()) return true;
                return false;
              }

              function findPasswordModalOverlayRoot() {
                try {
                  var pwd = document.querySelector('input[type="password"]');
                  if (!pwd || !pwd.offsetParent) return null;
                  var node = pwd;
                  for (var d = 0; d < 28 && node; d++) {
                    var cls = String(node.className || '');
                    var txt = (node.innerText || '').trim();
                    var z = parseInt(window.getComputedStyle(node).zIndex, 10) || 0;
                    var tag = (node.tagName || '').toUpperCase();
                    if (txt.indexOf('Connect to account') >= 0) return node;
                    if (txt.indexOf('Server') >= 0 && txt.indexOf('Password') >= 0 && txt.length < 500) return node;
                    if (tag === 'DIALOG' || cls.indexOf('dialog') >= 0 || cls.indexOf('modal') >= 0 || cls.indexOf('popup') >= 0 || cls.indexOf('overlay') >= 0 || cls.indexOf('backdrop') >= 0 || cls.indexOf('sheet') >= 0 || node.getAttribute('aria-modal') === 'true' || z > 50) {
                      return node;
                    }
                    node = node.parentElement;
                  }
                } catch (e2) {}
                return null;
              }

              function setInputValueForOverlay(el, val) {
                if (!el || val == null || val === '') return;
                try {
                  el.focus();
                  el.value = '';
                  el.dispatchEvent(new Event('input', { bubbles: true }));
                  el.dispatchEvent(new Event('change', { bubbles: true }));
                  var nativeSetter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value') && Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
                  if (nativeSetter) nativeSetter.call(el, val);
                  else el.value = val;
                  el.dispatchEvent(new Event('input', { bubbles: true }));
                  el.dispatchEvent(new Event('change', { bubbles: true }));
                  el.dispatchEvent(new Event('blur', { bubbles: true }));
                } catch (e) {}
              }

              const dismissLoginOverlay = async function() {
                var pw = passwordCredential;
                try {
                  hideTradingAccountsOverlayIfPresent();
                } catch (eT) {}
                try {
                  if (pw && isAnyLoginModalBlocking()) {
                    var pwdIn = document.querySelector('input[type="password"]');
                    if (pwdIn && (!pwdIn.value || String(pwdIn.value).trim() === '')) {
                      setInputValueForOverlay(pwdIn, pw);
                      await new Promise(function(r) { setTimeout(r, 400); });
                      var btns0 = document.querySelectorAll('button');
                      for (var b0 = 0; b0 < btns0.length; b0++) {
                        var t0 = ((btns0[b0].innerText || btns0[b0].textContent || '') + '').trim().toLowerCase();
                        if (t0.indexOf('connect') >= 0 && t0.indexOf('account') >= 0) {
                          btns0[b0].click();
                          sendMessage('step_update', 'Login modal: submitted password (Connect to account)');
                          await new Promise(function(r) { setTimeout(r, 2200); });
                          break;
                        }
                      }
                    }
                  }
                } catch (e0) {}
                try {
                  document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', keyCode: 27, bubbles: true, cancelable: true }));
                  await new Promise(function(r) { setTimeout(r, 120); });
                  document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', keyCode: 27, bubbles: true, cancelable: true }));
                } catch (e) {}
                await new Promise(function(r) { setTimeout(r, 200); });
                try {
                  var root = findPasswordModalOverlayRoot();
                  if (root) {
                    root.style.display = 'none';
                    root.style.visibility = 'hidden';
                    root.style.pointerEvents = 'none';
                    sendMessage('step_update', 'Hid login modal overlay (password form root)');
                  } else if (isAnyLoginModalBlocking()) {
                    var all = document.querySelectorAll('div, section, [role="dialog"], dialog');
                    for (var ai = 0; ai < Math.min(all.length, 250); ai++) {
                      var ae = all[ai];
                      if (!ae.offsetParent) continue;
                      var atxt = (ae.innerText || '').trim();
                      if (atxt.length > 500) continue;
                      if (atxt.indexOf('Connect to account') >= 0 || (atxt.indexOf('Server') >= 0 && atxt.indexOf('Password') >= 0 && atxt.indexOf('Login') >= 0)) {
                        var ar = ae.getBoundingClientRect();
                        if (ar.width > 160 && ar.height > 100) {
                          ae.style.display = 'none';
                          ae.style.visibility = 'hidden';
                          ae.style.pointerEvents = 'none';
                          sendMessage('step_update', 'Hid login modal (text match)');
                          break;
                        }
                      }
                    }
                  }
                } catch (e3) {}
                try {
                  if (isTerminalSessionVisible() && isPasswordInModalOverlay()) {
                    var root2 = findPasswordModalOverlayRoot();
                    if (root2) {
                      root2.style.display = 'none';
                      root2.style.visibility = 'hidden';
                      root2.style.pointerEvents = 'none';
                      sendMessage('step_update', 'Removed second login layer so terminal stays visible');
                    }
                  }
                } catch (e5) {}
                try {
                  var pwd = document.querySelector('input[type="password"]');
                  var sb = document.querySelector('input[placeholder*="Search symbol" i]') ||
                           document.querySelector('input[placeholder*="Search" i]') ||
                           document.querySelector('input[type="search"]');
                  if (pwd && pwd.offsetParent && sb && sb.offsetParent) {
                    var node = pwd;
                    for (var d = 0; d < 18 && node; d++) {
                      node = node.parentElement;
                      if (!node) break;
                      var cls = String(node.className || '');
                      var z = parseInt(window.getComputedStyle(node).zIndex, 10) || 0;
                      if (node.tagName === 'DIALOG' || cls.indexOf('dialog') >= 0 || cls.indexOf('modal') >= 0 || cls.indexOf('popup') >= 0 || cls.indexOf('overlay') >= 0 || cls.indexOf('backdrop') >= 0 || z > 40) {
                        node.style.display = 'none';
                        node.style.visibility = 'hidden';
                        node.style.pointerEvents = 'none';
                        sendMessage('step_update', 'Dismissed login layer blocking chart');
                        break;
                      }
                    }
                  }
                } catch (e4) {}
                try {
                  hideTradingAccountsOverlayIfPresent();
                } catch (eT2) {}
              };
              
              const authenticateMT5 = async () => {
                try {
                  console.log('[MT5 Auth] Starting authentication process...');
                  sendMessage('step_update', 'Initializing MT5 Account...');
                  await sleep(5500);
                  console.log('[MT5 Auth] Initial wait complete, checking for existing connections...');
                  
                  // Check for disclaimer and accept if present
                  const disclaimer = document.querySelector('#disclaimer');
                  if (disclaimer) {
                    const acceptButton = document.querySelector('.accept-button');
                    if (acceptButton) {
                      acceptButton.click();
                      sendMessage('step_update', 'Accepting disclaimer...');
                      await sleep(2000);
                    }
                  }
                  
                  // Remove existing connection - find Remove button (works across different broker terminals)
                  const findAndClickRemove = () => {
                    const allClickables = document.querySelectorAll('button, a, [role="button"], .button');
                    for (const el of allClickables) {
                      const text = (el.textContent || '').trim().toLowerCase();
                      const isRed = el.className && (el.className.includes('red') || el.style.color === 'red');
                      if (text === 'remove' || text.includes('remove') || text === 'disconnect' || (isRed && text.includes('remove'))) {
                        return el;
                      }
                    }
                    return null;
                  };
                  for (let attempt = 0; attempt < 3; attempt++) {
                    const removeBtn = findAndClickRemove();
                    if (removeBtn) {
                      sendMessage('step_update', 'Removing existing connection...');
                      removeBtn.click();
                      await sleep(4500);
                    } else break;
                  }
                  
                  // Wait for form to be ready
                  await sleep(2000);
                  
                  // Fill login credentials with enhanced field detection
                  const loginField = document.querySelector('input[name="login"]') || 
                                    document.querySelector('input[type="text"][placeholder*="login" i]') ||
                                    document.querySelector('input[type="number"]') ||
                                    document.querySelector('input#login');
                  
                  const passwordField = document.querySelector('input[name="password"]') || 
                                       document.querySelector('input[type="password"]') ||
                                       document.querySelector('input#password');
                  
                  // Fill login field
                  if (loginField && loginCredential) {
                    console.log('[MT5 Auth] Found login field, filling credentials...');
                    loginField.focus();
                    loginField.value = '';
                    loginField.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                    loginField.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    
                    setTimeout(() => {
                    loginField.focus();
                      loginField.value = loginCredential;
                    loginField.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                    loginField.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                      console.log('[MT5 Auth] Login field filled');
                    sendMessage('step_update', 'Login filled');
                    }, 100);
                  } else {
                    console.error('[MT5 Auth] Login field not found! Available inputs:', document.querySelectorAll('input').length);
                    sendMessage('authentication_failed', 'Login field not found');
                    return;
                  }
                  
                  // Fill password field
                  if (passwordField && passwordCredential) {
                    setTimeout(() => {
                    passwordField.focus();
                      passwordField.value = '';
                    passwordField.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                    passwordField.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    
                      setTimeout(() => {
                    passwordField.focus();
                        passwordField.value = passwordCredential;
                    passwordField.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                    passwordField.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    sendMessage('step_update', 'Password filled');
                      }, 100);
                    }, 300);
                  } else {
                    sendMessage('authentication_failed', 'Password field not found');
                    return;
                  }
                  
                  // Wait for fields to be filled
                  await sleep(2000);
                  
                  // Click login button
                  sendMessage('step_update', 'Connecting to Server...');
                  console.log('[MT5 Auth] Looking for login button...');
                  const loginButton = document.querySelector('.button.svelte-1wrky82.active') ||
                                     document.querySelector('button[type="submit"]') ||
                                     document.querySelector('.button.active') ||
                                     Array.from(document.querySelectorAll('button')).find(btn => 
                                       btn.textContent.trim().toLowerCase().includes('login') ||
                                       btn.textContent.trim().toLowerCase().includes('connect')
                                     );
                  
                  if (loginButton) {
                    console.log('[MT5 Auth] Found login button, clicking...');
                    loginButton.click();
                    console.log('[MT5 Auth] Login button clicked, waiting for connection...');
                    await sleep(8000);
                    for (var ov = 0; ov < 6; ov++) {
                      await dismissLoginOverlay();
                      await sleep(600);
                    }
                  } else {
                    console.error('[MT5 Auth] Login button not found! Available buttons:', document.querySelectorAll('button').length);
                    sendMessage('authentication_failed', 'Login button not found');
                    return;
                  }
                  
                  sendMessage('step_update', 'Verifying authentication...');
                  await sleep(3000);
                  for (var ov2 = 0; ov2 < 6; ov2++) {
                    await dismissLoginOverlay();
                    if (!isAnyLoginModalBlocking()) break;
                    await sleep(450);
                  }

                  function tryFinishWithScrapedStats(successMsg) {
                    var st = scrapeTerminalAccountStats();
                    if (st.equity && st.balance) {
                      sendMessage('authentication_success', successMsg, { equity: st.equity, balance: st.balance });
                      return true;
                    }
                    return false;
                  }

                  for (var poll = 0; poll < 14; poll++) {
                    if (tryFinishWithScrapedStats('MT5 Login Successful - Balance and equity')) return;
                    await dismissLoginOverlay();
                    await sleep(900);
                  }

                  var searchField = findMT5SearchField();
                  if (searchField) {
                    await dismissLoginOverlay();
                    await sleep(2000);
                    if (tryFinishWithScrapedStats('MT5 Login Successful - Terminal ready')) return;
                    var statsOk = scrapeTerminalAccountStats();
                    sendMessage('authentication_success', 'MT5 Login Successful - Search bar detected', { equity: statsOk.equity, balance: statsOk.balance });
                    return;
                  }

                  await sleep(3000);
                  await dismissLoginOverlay();
                  for (var poll2 = 0; poll2 < 8; poll2++) {
                    if (tryFinishWithScrapedStats('MT5 Login Successful - Balance and equity')) return;
                    await dismissLoginOverlay();
                    await sleep(700);
                  }

                  var searchFieldRetry = findMT5SearchField();
                  if (searchFieldRetry) {
                    await dismissLoginOverlay();
                    if (tryFinishWithScrapedStats('MT5 Login Successful - Terminal ready')) return;
                    var statsRetry = scrapeTerminalAccountStats();
                    sendMessage('authentication_success', 'MT5 Login Successful - Search bar detected', { equity: statsRetry.equity, balance: statsRetry.balance });
                    return;
                  }

                  if (tryFinishWithScrapedStats('MT5 Login Successful - Balance and equity')) return;

                  var low = ((document.body && document.body.innerText) ? document.body.innerText : '').toLowerCase();
                  if (
                    low.indexOf('invalid login') >= 0 ||
                    low.indexOf('invalid password') >= 0 ||
                    low.indexOf('wrong password') >= 0 ||
                    low.indexOf('wrong login') >= 0 ||
                    low.indexOf('incorrect password') >= 0 ||
                    low.indexOf('incorrect login') >= 0
                  ) {
                    sendMessage('authentication_failed', 'Authentication failed - Invalid login or password');
                  } else {
                    sendMessage('authentication_failed', 'Could not verify MT5 session. If the chart is visible, wait a few seconds and try Link Account again.');
                  }
                  
                } catch(e) {
                  sendMessage('authentication_failed', 'Error during authentication: ' + e.message);
                }
              };
               
              // Start authentication after page loads
              setTimeout(authenticateMT5, 3000);
            })();
        `;

          // Inject script before closing body tag (EXACTLY like Android)
          const patchedAuthScript = patchMt5InlineAuthScript(authScript);
          if (html.includes('</body>')) {
            html = html.replace('</body>', `<script>${patchedAuthScript}</script></body>`);
            console.log('✅ MT5 authentication script injected before </body> tag');
          } else if (html.includes('</html>')) {
            html = html.replace('</html>', `<script>${patchedAuthScript}</script></html>`);
            console.log('✅ MT5 authentication script injected before </html> tag');
          } else {
            html += `<script>${patchedAuthScript}</script>`;
            console.log('✅ MT5 authentication script appended to HTML');
          }

          // Verify script was injected
          if (html.includes('authenticateMT5')) {
            console.log('✅ Script injection verified - authenticateMT5 function found in HTML');
          } else {
            console.error('❌ Script injection failed - authenticateMT5 function not found in HTML');
          }

          // Return modified HTML with CORS headers
          return new Response(html, {
            headers: {
              'Content-Type': 'text/html; charset=utf-8',
              'Content-Language': 'en',
              'Access-Control-Allow-Origin': '*',
              'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
              'Access-Control-Allow-Headers': 'Content-Type',
              'X-Frame-Options': 'SAMEORIGIN',
            },
          });
        } catch (error) {
          console.error('MT5 Proxy error:', error);
          return new Response(`Proxy error: ${error.message}`, { status: 500 });
        }
      }
      return new Response('Method Not Allowed', { status: 405 });
    }

    // MT5 Trading Proxy endpoint - fetches MT5 terminal and injects trading script (EXACTLY like Android)
    if (pathname === '/api/mt5-trading-proxy') {
      if (request.method === 'GET') {
        const broker = normalizeMt5ServerKey(url.searchParams.get('broker') || '');
        const terminalUrl = ensureMt5EnglishTerminalUrl(
          url.searchParams.get('url') || resolveMt5TerminalUrl(broker)
        );
        const login = url.searchParams.get('login');
        const password = url.searchParams.get('password');
        const symbol = url.searchParams.get('symbol') || '';
        const action = url.searchParams.get('action') || '';
        const sl = url.searchParams.get('sl') || '';
        const tp = url.searchParams.get('tp') || '';
        const volume = url.searchParams.get('volume') || '0.01';
        const robotName = url.searchParams.get('robotName') || 'NexTradeAI';
        const numberOfTrades = url.searchParams.get('numberOfTrades') || '1';
        const chartWarmup = url.searchParams.get('chartWarmup') === '1';

        if (!terminalUrl) {
          return new Response('Missing terminal URL', { status: 400 });
        }

        try {
          // Fetch the MT5 terminal page (same as auth proxy)
          const response = await fetchMt5Remote(terminalUrl, {
            headers: {
              'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
              'Accept-Language': MT5_ENGLISH_ACCEPT_LANGUAGE,
            },
          });

          if (!response.ok) {
            return new Response(`Failed to fetch terminal: ${response.statusText}`, { status: response.status });
          }

          let html = await response.text();
          html = injectEnglishLockIntoHtml(html);

          // Get base URL and fix URLs (same as auth proxy)
          const baseUrlObj = new URL(terminalUrl);
          const baseUrl = `${baseUrlObj.protocol}//${baseUrlObj.host}`;
          const wsBaseUrl = baseUrl.replace('http://', 'ws://').replace('https://', 'wss://');
          const isRootTerminal = !terminalUrl.replace(/\/$/, '').endsWith('/terminal');

          const proxyOrigin = resolveMt5ProxyOrigin(request, url);

          // Fix relative URLs - root terminals: route ALL paths through proxy
          html = html.replace(/href="\/([^"]+)"/g, (match, path) => {
            if (path.startsWith('terminal/')) {
              return `href="${proxyOrigin}/terminal/${path.replace('terminal/', '')}?broker=${encodeURIComponent(broker)}"`;
            }
            if (isRootTerminal) return `href="${proxyOrigin}/terminal/${path}?broker=${encodeURIComponent(broker)}"`;
            return `href="${baseUrl}/${path}"`;
          });
          html = html.replace(/src="\/([^"]+)"/g, (match, path) => {
            if (path.startsWith('terminal/')) {
              return `src="${proxyOrigin}/terminal/${path.replace('terminal/', '')}?broker=${encodeURIComponent(broker)}"`;
            }
            if (isRootTerminal) return `src="${proxyOrigin}/terminal/${path}?broker=${encodeURIComponent(broker)}"`;
            return `src="${baseUrl}/${path}"`;
          });
          html = html.replace(/url\("\/\/([^"]+)"\)/g, (match, path) => {
            if (path.startsWith('terminal/')) {
              return `url("${proxyOrigin}/terminal/${path.replace('terminal/', '')}?broker=${encodeURIComponent(broker)}")`;
            }
            return `url("${baseUrl}/${path}")`;
          });
          html = html.replace(/url\('\/\/([^']+)'\)/g, (match, path) => {
            if (path.startsWith('terminal/')) {
              return `url('${proxyOrigin}/terminal/${path.replace('terminal/', '')}?broker=${encodeURIComponent(broker)}')`;
            }
            return `url('${baseUrl}/${path}')`;
          });

          html = html.replace(new RegExp(`${baseUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}/terminal/([^"'>\\s]+)`, 'g'), (match, assetPath) => {
            return `${proxyOrigin}/terminal/${assetPath}?broker=${encodeURIComponent(broker)}`;
          });

          // Fix WebSocket URLs
          const proxyDomain = url.origin;
          const proxyHost = proxyDomain.replace(/https?:\/\//, '').replace(/\./g, '\\.');

          html = html.replace(new RegExp(`wss?://${proxyHost}/terminal/ws`, 'gi'), `${wsBaseUrl}/terminal/ws`);
          html = html.replace(new RegExp(`wss?://${proxyHost}/terminal/`, 'gi'), `${wsBaseUrl}/terminal/`);

          html = html.replace(
            /(new\s+WebSocket\s*\(\s*['"`])(wss?:\/\/)(window\.location\.(origin|hostname)|location\.(origin|hostname))(['"`])/g,
            `$1${wsBaseUrl}/terminal/ws$6`
          );

          // Inject WebSocket override script - run first to catch terminal's WebSocket URLs (including /terminal without /ws)
          const proxyHostPlain = url.hostname;
          const wsOverrideScript = `
            (function() {
              const originalWebSocket = window.WebSocket;
              const brokerWsUrl = '${wsBaseUrl}/terminal/ws';
              const proxyHost = '${proxyHostPlain}';
              const brokerHost = '${baseUrlObj.host}';
              window.WebSocket = function(url, protocols) {
                if (url && typeof url === 'string') {
                  const isToProxy = url.includes(proxyHost) || (url.includes('/terminal') && !url.includes(brokerHost));
                  if (isToProxy) {
                    return new originalWebSocket(brokerWsUrl, protocols);
                  }
                }
                return new originalWebSocket(url, protocols);
              };
              Object.setPrototypeOf(window.WebSocket, originalWebSocket);
              window.WebSocket.prototype = originalWebSocket.prototype;
              window.WebSocket.CONNECTING = originalWebSocket.CONNECTING;
              window.WebSocket.OPEN = originalWebSocket.OPEN;
              window.WebSocket.CLOSING = originalWebSocket.CLOSING;
              window.WebSocket.CLOSED = originalWebSocket.CLOSED;
            })();
          `;

          if (html.includes('</head>')) {
            html = html.replace('</head>', `<script>${wsOverrideScript}</script></head>`);
          } else if (html.includes('<head>')) {
            html = html.replace('<head>', `<head><script>${wsOverrideScript}</script>`);
          } else {
            html = `<script>${wsOverrideScript}</script>` + html;
          }

          // Escape values for safe injection
          const escapeValue = (value: string) => {
            return (value || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\n/g, '\\n').replace(/\r/g, '\\r');
          };

          const loginValue = escapeValue(login || '');
          const passwordValue = escapeValue(password || '');
          const symbolValue = escapeValue(symbol || '');
          const actionValue = escapeValue(action || '');
          const slValue = escapeValue(sl || '');
          const tpValue = escapeValue(tp || '');
          const volumeValue = escapeValue(volume || '0.01');
          const orderCommentForMt5 = `${(robotName || 'NexTradeAI').trim()} - AURA AI`;
          const robotNameValue = escapeValue(orderCommentForMt5);
          const numberOfTradesValue = escapeValue(numberOfTrades || '1');
          const isChartWarmupJs = chartWarmup ? 'true' : 'false';

          // Generate trading script - EXACT COPY from Android mt5-signal-webview.tsx generateMT5AuthScript()
          // This includes authentication + trading logic - MUST BE IDENTICAL TO ANDROID VERSION
          const tradingScript = `
            (function() {
              ${MT5_ENGLISH_LOCK_JS}
              console.log('[MT5 Trading] Script injected and executing...');
              
              const sendMessage = (type, message, extras) => {
                try {
                  if (type === 'chart_screenshot' && window.__eaChartScreenshotSent) {
                    return;
                  }
                  var payload = { type: type, message: message };
                  if (extras && typeof extras === 'object') {
                    for (var ek in extras) {
                      if (Object.prototype.hasOwnProperty.call(extras, ek) && extras[ek] != null) {
                        payload[ek] = extras[ek];
                      }
                    }
                  }
                  if (type === 'chart_screenshot') {
                    window.__eaChartScreenshotSent = true;
                  }
                  var messageData = JSON.stringify(payload);
                  if (window.ReactNativeWebView) {
                    window.ReactNativeWebView.postMessage(messageData);
                  } else if (window.parent && window.parent !== window) {
                    window.parent.postMessage(messageData, '*');
                  }
                  console.log('[MT5 Trading] Message sent:', type, message);
                } catch(e) {
                  console.error('[MT5 Trading] Error sending message:', e);
                }
              };

              function scrapeTerminalAccountStats() {
                var equity = null;
                var balance = null;
                var fpOut = null;
                try {
                  var txt = (document.body && document.body.innerText) ? document.body.innerText : '';
                  var lineEq = txt.match(/(?:^|[\\n\\r])\\s*Equity\\s*[:\\s]+([\\d][\\d\\s,]*\\.?\\d*)/im);
                  if (lineEq) equity = lineEq[1].replace(/\\s/g, '').replace(/,/g, '');
                  var lineBal = txt.match(/(?:^|[\\n\\r])\\s*Balance\\s*[:\\s]+([\\d][\\d\\s,]*\\.?\\d*)/im);
                  if (lineBal) balance = lineBal[1].replace(/\\s/g, '').replace(/,/g, '');
                  if (!equity || !balance) {
                    var compact = txt.replace(/[\\n\\r]+/g, ' ');
                    if (!equity) {
                      var e2 = compact.match(/Equity[:\\s]+([\\d][\\d\\s,]*\\.?\\d*)/i);
                      if (e2) equity = e2[1].replace(/\\s/g, '').replace(/,/g, '');
                    }
                    if (!balance) {
                      var b2 = compact.match(/Balance[:\\s]+([\\d][\\d\\s,]*\\.?\\d*)/i);
                      if (b2) balance = b2[1].replace(/\\s/g, '').replace(/,/g, '');
                    }
                  }
                  fpOut = null;
                  var cfx = txt.replace(/[\\n\\r\\t]+/g, ' ').replace(/\\s+/g, ' ');
                  var gf1 = cfx.match(/(?:Floating|Unrealized)\\s*(?:P\\/?L|Profit)?\\s*[:#]?\\s*([-+]?[\\d][\\d\\s,]*\\.?\\d*)/i);
                  if (gf1) fpOut = gf1[1].replace(/\\s/g, '').replace(/,/g, '');
                  if (fpOut == null) {
                    var gf2 = cfx.match(/\\bP\\s*\\/?\\s*L\\s*[:#]?\\s*([-+]?[\\d][\\d\\s,]*\\.?\\d*)/i);
                    if (gf2) fpOut = gf2[1].replace(/\\s/g, '').replace(/,/g, '');
                  }
                } catch (err) {}
                return { equity: equity, balance: balance, floatingProfit: fpOut };
              }

              sendMessage('mt5_loaded', 'MT5 terminal loaded successfully');
              console.log('[MT5 Trading] Script initialized, waiting for page load...');
              
              const sleep = (ms) => new Promise(r => setTimeout(r, ms));
              const isChartWarmup = ${isChartWarmupJs};

              // Prevent page reloads and navigation
              window.addEventListener('beforeunload', function(e) {
                e.preventDefault();
                e.returnValue = '';
                return '';
              });
              
              document.addEventListener('keydown', function(e) {
                if ((e.key === 'F5' || (e.ctrlKey && e.key === 'r') || (e.ctrlKey && e.key === 'R'))) {
                  e.preventDefault();
                  return false;
                }
              });
              
              const originalReload = window.location.reload;
              window.location.reload = function() {
                console.log('Page reload prevented');
                return false;
              };
              
              // Override console methods to suppress warnings
              const originalWarn = console.warn;
              const originalError = console.error;
              const originalLog = console.log;
              
              function shouldSuppress(message) {
                return message.includes('interactive-widget') || 
                       message.includes('viewport') ||
                       message.includes('Viewport argument key') ||
                       message.includes('AES-CBC') ||
                       message.includes('AES-CTR') ||
                       message.includes('AES-GCM') ||
                       message.includes('chosen-ciphertext') ||
                       message.includes('authentication by default') ||
                       message.includes('not recognized and ignored');
              }
              
              console.warn = function(...args) {
                const message = args.join(' ');
                if (shouldSuppress(message)) return;
                originalWarn.apply(console, args);
              };
              
              console.error = function(...args) {
                const message = args.join(' ');
                if (shouldSuppress(message)) return;
                originalError.apply(console, args);
              };
              
              console.log = function(...args) {
                const message = args.join(' ');
                if (shouldSuppress(message)) return;
                originalLog.apply(console, args);
              };

              // Override WebSocket to redirect to broker (proxy /terminal or /terminal/ws -> broker /terminal/ws)
              const originalWebSocket = window.WebSocket;
              const brokerWsUrl = '${wsBaseUrl}/terminal/ws';
              const proxyHostPlain = '${url.hostname}';
              window.WebSocket = function(url, protocols) {
                console.log('WebSocket connection attempt to:', url);
                const isToProxy = url && typeof url === 'string' && (url.includes(proxyHostPlain) || (url.includes('/terminal') && !url.includes('${baseUrlObj.host}')));
                if (isToProxy) {
                  console.log('Redirecting WebSocket to broker:', brokerWsUrl);
                  return new originalWebSocket(brokerWsUrl, protocols);
                }
                return new originalWebSocket(url, protocols);
              };
              
              Object.setPrototypeOf(window.WebSocket, originalWebSocket);
              Object.defineProperty(window.WebSocket, 'prototype', {
                value: originalWebSocket.prototype,
                writable: false
              });

              const loginCredential = '${loginValue}';
              const passwordCredential = '${passwordValue}';
              const serverCredential = '${escapeValue(broker || '')}';

              function isTerminalSessionVisible() {
                try {
                  const sb = document.querySelector('input[placeholder*="Search symbol" i]') ||
                           document.querySelector('input[placeholder*="Search" i]') ||
                           document.querySelector('input[type="search"]');
                  if (sb && sb.offsetParent) return true;
                  const txt = (document.body && document.body.innerText) ? document.body.innerText : '';
                  if (/\bEquity\b/i.test(txt) && /\bBalance\b/i.test(txt)) return true;
                  if (/\bBid\b/i.test(txt) && /\bAsk\b/i.test(txt)) return true;
                  const list = document.querySelectorAll('canvas');
                  for (let ci = 0; ci < list.length; ci++) {
                    const c = list[ci];
                    if ((c.width || 0) * (c.height || 0) >= 50000) return true;
                  }
                } catch (e) {}
                return false;
              }

              function isConnectModalVisible() {
                try {
                  const bt = (document.body && document.body.innerText) ? document.body.innerText : '';
                  if (bt.indexOf('Connect to account') < 0) return false;
                  const pwd = document.querySelector('input[type="password"]');
                  if (!pwd || !pwd.offsetParent) return false;
                  const rr = pwd.getBoundingClientRect();
                  return rr.width > 0 && rr.height > 0;
                } catch (e) { return false; }
              }

              function isPasswordInModalOverlay() {
                try {
                  const pwd = document.querySelector('input[type="password"]');
                  if (!pwd || !pwd.offsetParent) return false;
                  const rr = pwd.getBoundingClientRect();
                  if (rr.width < 8 || rr.height < 8) return false;
                  let node = pwd;
                  for (let d = 0; d < 28 && node; d++) {
                    const cls = String(node.className || '');
                    const z = parseInt(window.getComputedStyle(node).zIndex, 10) || 0;
                    const tag = (node.tagName || '').toUpperCase();
                    if (tag === 'DIALOG' || cls.indexOf('dialog') >= 0 || cls.indexOf('modal') >= 0 || cls.indexOf('popup') >= 0 || cls.indexOf('overlay') >= 0 || cls.indexOf('backdrop') >= 0 || cls.indexOf('sheet') >= 0 || node.getAttribute('aria-modal') === 'true' || z > 45) {
                      return true;
                    }
                    node = node.parentElement;
                  }
                } catch (e2) {}
                return false;
              }

              function isTradingAccountsSheetVisible() {
                try {
                  if (!isTerminalSessionVisible()) return false;
                  const bt = (document.body && document.body.innerText) ? document.body.innerText : '';
                  const hasTitle = bt.indexOf('Trading accounts') >= 0 || bt.indexOf('Trading account') >= 0 ||
                    (bt.indexOf('Razor Markets') >= 0 && (bt.indexOf('Connect to account') >= 0 || bt.indexOf('Remove') >= 0));
                  if (!hasTitle) return false;
                  if (bt.indexOf('Connect to account') < 0 && bt.indexOf('Remove') < 0) return false;
                  return true;
                } catch (e) { return false; }
              }

              function findTradingAccountsOverlayRoot() {
                try {
                  const candidates = document.querySelectorAll('div, section, aside, [role="dialog"], dialog');
                  let best = null;
                  let minArea = 1e12;
                  for (let i = 0; i < Math.min(candidates.length, 450); i++) {
                    const el = candidates[i];
                    if (!el.offsetParent) continue;
                    const txt = (el.innerText || '').trim();
                    if (txt.length < 40 || txt.length > 2500) continue;
                    if (txt.indexOf('Trading accounts') < 0 && txt.indexOf('Razor Markets') < 0) continue;
                    if (txt.indexOf('Connect to account') < 0 && txt.indexOf('Remove') < 0) continue;
                    const r = el.getBoundingClientRect();
                    const area = r.width * r.height;
                    if (r.width > 100 && r.height > 90 && area >= 12000 && area < minArea) {
                      minArea = area;
                      best = el;
                    }
                  }
                  if (best) return best;
                  const btns = document.querySelectorAll('button, [role="button"]');
                  for (let b = 0; b < Math.min(btns.length, 120); b++) {
                    const t = ((btns[b].innerText || btns[b].textContent || '') + '').trim().toLowerCase();
                    if (t.indexOf('connect') >= 0 && t.indexOf('account') >= 0) {
                      let node = btns[b];
                      for (let d = 0; d < 22 && node; d++) {
                        const inner = (node.innerText || '').trim();
                        if (inner.indexOf('Trading accounts') >= 0 || inner.indexOf('Razor Markets') >= 0) return node;
                        node = node.parentElement;
                      }
                    }
                  }
                } catch (e2) {}
                return null;
              }

              function hideTradingAccountsOverlayIfPresent() {
                try {
                  if (!isTradingAccountsSheetVisible()) return false;
                  const root = findTradingAccountsOverlayRoot();
                  if (root) {
                    root.style.display = 'none';
                    root.style.visibility = 'hidden';
                    root.style.pointerEvents = 'none';
                    return true;
                  }
                  const all = document.querySelectorAll('div, section, aside, [role="dialog"]');
                  for (let ai = 0; ai < Math.min(all.length, 350); ai++) {
                    const ae = all[ai];
                    if (!ae.offsetParent) continue;
                    const atxt = (ae.innerText || '').trim();
                    if (atxt.length > 4000 || atxt.length < 35) continue;
                    if ((atxt.indexOf('Trading accounts') >= 0 || atxt.indexOf('Razor Markets') >= 0) && atxt.indexOf('Connect to account') >= 0) {
                      const ar = ae.getBoundingClientRect();
                      if (ar.width > 120 && ar.height > 80) {
                        ae.style.display = 'none';
                        ae.style.visibility = 'hidden';
                        ae.style.pointerEvents = 'none';
                        return true;
                      }
                    }
                  }
                } catch (e3) {}
                return false;
              }

              function isAnyLoginModalBlocking() {
                if (isConnectModalVisible()) return true;
                if (isTradingAccountsSheetVisible()) return true;
                if (isTerminalSessionVisible() && isPasswordInModalOverlay()) return true;
                return false;
              }

              function findPasswordModalOverlayRoot() {
                try {
                  const pwd = document.querySelector('input[type="password"]');
                  if (!pwd || !pwd.offsetParent) return null;
                  let node = pwd;
                  for (let d = 0; d < 28 && node; d++) {
                    const cls = String(node.className || '');
                    const txt = (node.innerText || '').trim();
                    const z = parseInt(window.getComputedStyle(node).zIndex, 10) || 0;
                    const tag = (node.tagName || '').toUpperCase();
                    if (txt.indexOf('Connect to account') >= 0) return node;
                    if (txt.indexOf('Server') >= 0 && txt.indexOf('Password') >= 0 && txt.length < 500) return node;
                    if (tag === 'DIALOG' || cls.indexOf('dialog') >= 0 || cls.indexOf('modal') >= 0 || cls.indexOf('popup') >= 0 || cls.indexOf('overlay') >= 0 || cls.indexOf('backdrop') >= 0 || cls.indexOf('sheet') >= 0 || node.getAttribute('aria-modal') === 'true' || z > 50) {
                      return node;
                    }
                    node = node.parentElement;
                  }
                } catch (e2) {}
                return null;
              }

              function setInputValueForOverlay(el, val) {
                if (!el || val == null || val === '') return;
                try {
                  el.focus();
                  el.value = '';
                  el.dispatchEvent(new Event('input', { bubbles: true }));
                  el.dispatchEvent(new Event('change', { bubbles: true }));
                  const desc = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
                  const nativeSetter = desc && desc.set;
                  if (nativeSetter) nativeSetter.call(el, val);
                  else el.value = val;
                  el.dispatchEvent(new Event('input', { bubbles: true }));
                  el.dispatchEvent(new Event('change', { bubbles: true }));
                  el.dispatchEvent(new Event('blur', { bubbles: true }));
                } catch (e) {}
              }

              function visitAllFramesDeep(visitor) {
                function walk(d) {
                  if (!d) return;
                  try {
                    visitor(d);
                    var iframes = d.querySelectorAll('iframe');
                    for (var i = 0; i < iframes.length; i++) {
                      try {
                        var ind = iframes[i].contentDocument;
                        if (ind) walk(ind);
                      } catch (e) {}
                    }
                  } catch (e2) {}
                }
                walk(document);
              }

              async function acceptDisclaimersAndConfirmDeep() {
                var maxPasses = 4;
                for (var pass = 0; pass < maxPasses; pass++) {
                  var changed = false;
                  visitAllFramesDeep(function(d) {
                    try {
                      var disc = d.querySelector('#disclaimer');
                      if (disc && disc.offsetParent) {
                        var ab = d.querySelector('.accept-button');
                        if (ab) {
                          ab.click();
                          changed = true;
                          sendMessage('step_update', 'Accepted broker disclaimer');
                        }
                      }
                    } catch (e) {}
                  });
                  visitAllFramesDeep(function(d) {
                    try {
                      var txt = (d.body && d.body.innerText) ? d.body.innerText : '';
                      var low = txt.toLowerCase();
                      if (low.indexOf('one click') < 0 && low.indexOf('one-click') < 0) return;
                      if (low.indexOf('disclaimer') < 0 && low.indexOf('terms and conditions') < 0) return;
                      var boxes = d.querySelectorAll('input[type="checkbox"]');
                      var hit = false;
                      for (var i = 0; i < boxes.length; i++) {
                        var cb = boxes[i];
                        if (!cb.offsetParent || cb.checked) continue;
                        var labTxt = '';
                        if (cb.labels && cb.labels.length) labTxt = (cb.labels[0].innerText || '') + '';
                        try {
                          var wrapLab = cb.closest('label');
                          if (wrapLab) labTxt += ' ' + (wrapLab.innerText || '');
                        } catch (eL) {}
                        var labLow = (labTxt + '').toLowerCase();
                        if (labLow.indexOf('accept') >= 0 || labLow.indexOf('terms') >= 0 || labLow.indexOf('condition') >= 0) {
                          cb.click();
                          hit = true;
                          changed = true;
                          sendMessage('step_update', 'Accepted One Click Trading checkbox');
                          break;
                        }
                      }
                      if (!hit) {
                        for (var j = 0; j < boxes.length; j++) {
                          var c2 = boxes[j];
                          if (c2.offsetParent && !c2.checked) {
                            c2.click();
                            changed = true;
                            sendMessage('step_update', 'Accepted terms checkbox');
                            break;
                          }
                        }
                      }
                    } catch (e2) {}
                  });
                  visitAllFramesDeep(function(d) {
                    try {
                      var ttxt = (d.body && d.body.innerText) ? d.body.innerText : '';
                      if (!/one click|disclaimer|terms/i.test(ttxt)) return;
                      var btns = d.querySelectorAll('button, [role="button"], a');
                      for (var k = 0; k < btns.length; k++) {
                        var el = btns[k];
                        if (!el.offsetParent) continue;
                        var t = ((el.innerText || el.textContent || '') + '').trim().toLowerCase();
                        if (
                          t === 'ok' ||
                          t === 'accept' ||
                          t === 'continue' ||
                          t.indexOf('i agree') >= 0 ||
                          t.indexOf('i accept') >= 0 ||
                          (t.indexOf('confirm') >= 0 && t.length < 24)
                        ) {
                          el.click();
                          changed = true;
                          sendMessage('step_update', 'Confirmed disclaimer dialog');
                          break;
                        }
                      }
                    } catch (e3) {}
                  });
                  if (!changed) break;
                  await new Promise(function(r) { setTimeout(r, 500); });
                }
              }

              const dismissLoginOverlay = async () => {
                try {
                  hideTradingAccountsOverlayIfPresent();
                } catch (eT) {}
                try {
                  if (passwordCredential && isAnyLoginModalBlocking()) {
                    const pwdIn = document.querySelector('input[type="password"]');
                    if (pwdIn && (!pwdIn.value || String(pwdIn.value).trim() === '')) {
                      setInputValueForOverlay(pwdIn, passwordCredential);
                      await sleep(400);
                      const btns0 = document.querySelectorAll('button');
                      for (let b0 = 0; b0 < btns0.length; b0++) {
                        const t0 = ((btns0[b0].innerText || btns0[b0].textContent || '') + '').trim().toLowerCase();
                        if (t0.indexOf('connect') >= 0 && t0.indexOf('account') >= 0) {
                          btns0[b0].click();
                          sendMessage('step_update', 'Login modal: submitted password (Connect to account)');
                          await sleep(2200);
                          break;
                        }
                      }
                    }
                  }
                } catch (e0) {}
                try {
                  document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', keyCode: 27, bubbles: true, cancelable: true }));
                  await sleep(120);
                  document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', keyCode: 27, bubbles: true, cancelable: true }));
                } catch (e) {}
                await sleep(200);
                try {
                  const root = findPasswordModalOverlayRoot();
                  if (root) {
                    root.style.display = 'none';
                    root.style.visibility = 'hidden';
                    root.style.pointerEvents = 'none';
                    sendMessage('step_update', 'Hid login modal overlay (password form root)');
                  } else if (isAnyLoginModalBlocking()) {
                    const all = document.querySelectorAll('div, section, [role="dialog"], dialog');
                    for (let ai = 0; ai < Math.min(all.length, 250); ai++) {
                      const ae = all[ai];
                      if (!ae.offsetParent) continue;
                      const atxt = (ae.innerText || '').trim();
                      if (atxt.length > 500) continue;
                      if (atxt.indexOf('Connect to account') >= 0 || (atxt.indexOf('Server') >= 0 && atxt.indexOf('Password') >= 0 && atxt.indexOf('Login') >= 0)) {
                        const ar = ae.getBoundingClientRect();
                        if (ar.width > 160 && ar.height > 100) {
                          ae.style.display = 'none';
                          ae.style.visibility = 'hidden';
                          ae.style.pointerEvents = 'none';
                          sendMessage('step_update', 'Hid login modal (text match)');
                          break;
                        }
                      }
                    }
                  }
                } catch (e3) {}
                try {
                  if (isTerminalSessionVisible() && isPasswordInModalOverlay()) {
                    const root2 = findPasswordModalOverlayRoot();
                    if (root2) {
                      root2.style.display = 'none';
                      root2.style.visibility = 'hidden';
                      root2.style.pointerEvents = 'none';
                      sendMessage('step_update', 'Removed second login layer so terminal stays visible');
                    }
                  }
                } catch (e5) {}
                try {
                  const pwd = document.querySelector('input[type="password"]');
                  const sb = document.querySelector('input[placeholder*="Search symbol" i]') ||
                           document.querySelector('input[placeholder*="Search" i]') ||
                           document.querySelector('input[type="search"]');
                  if (pwd && pwd.offsetParent && sb && sb.offsetParent) {
                    let node = pwd;
                    for (let d = 0; d < 18 && node; d++) {
                      node = node.parentElement;
                      if (!node) break;
                      const cls = String(node.className || '');
                      const z = parseInt(window.getComputedStyle(node).zIndex, 10) || 0;
                      if (node.tagName === 'DIALOG' || cls.indexOf('dialog') >= 0 || cls.indexOf('modal') >= 0 || cls.indexOf('popup') >= 0 || cls.indexOf('overlay') >= 0 || cls.indexOf('backdrop') >= 0 || z > 40) {
                        node.style.display = 'none';
                        node.style.visibility = 'hidden';
                        node.style.pointerEvents = 'none';
                        sendMessage('step_update', 'Dismissed login layer blocking chart');
                        break;
                      }
                    }
                  }
                } catch (e4) {}
                try {
                  hideTradingAccountsOverlayIfPresent();
                } catch (eT2) {}
              };

              function getAllCanvasesDeep() {
                const out = [];
                function walk(d) {
                  if (!d) return;
                  try {
                    const list = d.querySelectorAll('canvas');
                    for (let i = 0; i < list.length; i++) out.push(list[i]);
                    const iframes = d.querySelectorAll('iframe');
                    for (let j = 0; j < iframes.length; j++) {
                      try {
                        const ind = iframes[j].contentDocument;
                        if (ind) walk(ind);
                      } catch (e) {}
                    }
                  } catch (e2) {}
                }
                walk(document);
                return out;
              }

              function eaQuerySelectorAllDeep(cssSelector) {
                const collected = [];
                function walkSearch(d) {
                  if (!d) return;
                  try {
                    const list = d.querySelectorAll(cssSelector);
                    for (let i = 0; i < list.length; i++) collected.push(list[i]);
                    const fr = d.querySelectorAll('iframe');
                    for (let i = 0; i < fr.length; i++) {
                      try {
                        const inner = fr[i].contentDocument;
                        if (inner) walkSearch(inner);
                      } catch (eF) {}
                    }
                  } catch (eW) {}
                }
                walkSearch(document);
                return collected;
              }

              function eaPickVisibleSearchInputDeep() {
                const combinedSel =
                  'input[placeholder*="Search symbol" i], input[placeholder*="Search" i], input[type="search"], label.search input, .search input';
                const arr = eaQuerySelectorAllDeep(combinedSel);
                for (let k = 0; k < arr.length; k++) {
                  const inp = arr[k];
                  if (!inp || inp.offsetParent === null) continue;
                  const ph = ((inp.getAttribute && inp.getAttribute('placeholder')) || '').toLowerCase();
                  const nm = ((inp.name || '') + '').toLowerCase();
                  const ty = ((inp.type || '') + '').toLowerCase();
                  if (ty === 'password' || ty === 'hidden') continue;
                  if (ph.indexOf('login') >= 0 || ph.indexOf('password') >= 0 || nm === 'login' || nm === 'password')
                    continue;
                  return inp;
                }
                const allInputs = eaQuerySelectorAllDeep('input');
                for (let j = 0; j < allInputs.length; j++) {
                  const inp = allInputs[j];
                  if (!inp || inp.offsetParent === null) continue;
                  const ty = ((inp.type || '') + '').toLowerCase();
                  const ph = ((inp.getAttribute && inp.getAttribute('placeholder')) || '').toLowerCase();
                  const nm = ((inp.name || '') + '').toLowerCase();
                  if (ty === 'password' || ty === 'hidden' || ty === 'checkbox' || ty === 'radio' || ty === 'submit' || ty === 'number') continue;
                  if (ph.indexOf('login') >= 0 || ph.indexOf('password') >= 0 || ph.indexOf('server') >= 0) continue;
                  if (nm === 'login' || nm === 'password' || nm === 'server') continue;
                  return inp;
                }
                return null;
              }

              function eaExpandMarketWatchPanel() {
                const nodes = document.querySelectorAll('div.icon-button, button, [role="button"]');
                for (let i = 0; i < nodes.length; i++) {
                  const el = nodes[i];
                  if (!el || el.offsetParent === null) continue;
                  const title = ((el.getAttribute && (el.getAttribute('title') || el.getAttribute('aria-label'))) || '').toLowerCase();
                  if (!title) continue;
                  const isWatch = title.indexOf('market watch') >= 0 || title.indexOf('ctrl + m') >= 0 || title.indexOf('ctrl+m') >= 0;
                  if (isWatch && title.indexOf('hide') < 0) {
                    try { el.click(); } catch (eClick) {}
                    return true;
                  }
                }
                return false;
              }

              function canvasHasWebGLContext(canvas) {
                try {
                  if (!canvas || !canvas.getContext) return false;
                  const gl =
                    canvas.getContext('webgl2', { stencil: false }) ||
                    canvas.getContext('webgl', { stencil: false }) ||
                    canvas.getContext('experimental-webgl');
                  return !!gl;
                } catch (e) {
                  return false;
                }
              }

              function collectRankedCanvasCandidates() {
                const canvases = getAllCanvasesDeep();
                const ranked = [];
                for (let i = 0; i < canvases.length; i++) {
                  const c = canvases[i];
                  const rect = c.getBoundingClientRect();
                  if (rect.bottom < -35 || rect.top > window.innerHeight + 50) continue;
                  if (rect.width < 80 || rect.height < 58) continue;
                  const rectArea = rect.width * rect.height;
                  const internal = (c.width || 0) * (c.height || 0);
                  let score = internal > 5000 ? Math.min(rectArea, internal) : rectArea;
                  try {
                    if (canvasHasWebGLContext(c)) score *= 1.5;
                  } catch (e) {}
                  if (score > 0) ranked.push({ canvas: c, score });
                }
                ranked.sort((a, b) => b.score - a.score);
                return ranked;
              }

              const waitForChartReady = async (maxMs) => {
                const deadline = Date.now() + maxMs;
                const tick = 450;
                function isLikelyLoginScreen() {
                  try {
                    if (isAnyLoginModalBlocking()) return true;
                    const hasChart = hasChartCanvas();
                    const hasBidAsk = hasBidAskRibbon();
                    const sb = document.querySelector('input[placeholder*="Search symbol" i]') ||
                             document.querySelector('input[placeholder*="Search" i]') ||
                             document.querySelector('input[type="search"]');
                    const hasSb = sb && sb.offsetParent !== null;
                    if (hasSb && (hasChart || hasBidAsk)) {
                      return false;
                    }
                    const pwd = document.querySelector('input[type="password"]');
                    if (!pwd || pwd.offsetParent === null) return false;
                    const btns = document.querySelectorAll('button');
                    for (let j = 0; j < btns.length; j++) {
                      const t = ((btns[j].innerText || btns[j].textContent || '') + '').trim().toLowerCase();
                      if (t.indexOf('connect') >= 0 && (t.indexOf('account') >= 0 || t === 'connect')) {
                        return btns[j].offsetParent !== null;
                      }
                    }
                  } catch (e) {}
                  return false;
                }
                function hasChartCanvas() {
                  try {
                    function maxArea(d) {
                      if (!d) return 0;
                      let best = 0;
                      try {
                        const list = d.querySelectorAll('canvas');
                        for (let i = 0; i < list.length; i++) {
                          const c = list[i];
                          const area = (c.width || 0) * (c.height || 0);
                          if (area > best) best = area;
                        }
                        const iframes = d.querySelectorAll('iframe');
                        for (let j = 0; j < iframes.length; j++) {
                          try {
                            const ind = iframes[j].contentDocument;
                            if (ind) {
                              const sub = maxArea(ind);
                              if (sub > best) best = sub;
                            }
                          } catch (e) {}
                        }
                      } catch (e2) {}
                      return best;
                    }
                    return maxArea(document) >= 60000;
                  } catch (e3) { return false; }
                }
                function hasBidAskRibbon() {
                  try {
                    function concatText(d) {
                      if (!d || !d.body) return '';
                      let t = '';
                      try {
                        t += (d.body.innerText || '') + '\\n';
                        const iframes = d.querySelectorAll('iframe');
                        for (let i = 0; i < iframes.length; i++) {
                          try {
                            const ind = iframes[i].contentDocument;
                            if (ind) t += concatText(ind);
                          } catch (e) {}
                        }
                      } catch (e2) {}
                      return t;
                    }
                    const txt = concatText(document);
                    return /\\bBid\\b/i.test(txt) && /\\bAsk\\b/i.test(txt);
                  } catch (e3) { return false; }
                }
                let canvasReadySince = 0;
                while (Date.now() < deadline) {
                  await acceptDisclaimersAndConfirmDeep();
                  await dismissLoginOverlay();
                  const onLogin = isLikelyLoginScreen();
                  const chartOk = hasChartCanvas();
                  const bidAskOk = hasBidAskRibbon();
                  if (!onLogin && chartOk && bidAskOk) {
                    sendMessage('step_update', 'Chart ready for export');
                    return true;
                  }
                  if (!onLogin && chartOk) {
                    if (!canvasReadySince) canvasReadySince = Date.now();
                    if (Date.now() - canvasReadySince >= 8000) {
                      sendMessage('step_update', 'Chart ready for export');
                      return true;
                    }
                    sendMessage('step_update', 'Chart canvas ready — waiting for quotes...');
                  } else {
                    canvasReadySince = 0;
                  }
                  await sleep(tick);
                }
                if (!isLikelyLoginScreen() && hasChartCanvas()) {
                  sendMessage('step_update', 'Chart ready for export');
                  return true;
                }
                return false;
              };

              function findSaveChartAsImageButton() {
                let found = null;
                function titleLooksLikeSave(title) {
                  const t = String(title || '').toLowerCase();
                  if (!t) return false;
                  if (t.indexOf('ctrl + s') >= 0 || t.indexOf('ctrl+s') >= 0) return true;
                  if (t.indexOf('save chart') >= 0) return true;
                  if (t.indexOf('save') >= 0 && (t.indexOf('image') >= 0 || t.indexOf('png') >= 0 || t.indexOf('jpg') >= 0)) return true;
                  return false;
                }
                function searchDoc(d) {
                  if (!d || found) return;
                  try {
                    const exact = d.querySelector(
                      'div.icon-button.svelte-1iwf8ix[title="Save Chart as Image (Ctrl + S)"]'
                    );
                    if (exact && exact.offsetParent !== null) {
                      found = exact;
                      return;
                    }
                    const all = d.querySelectorAll('div.icon-button, button, [role="button"], [title], [aria-label]');
                    for (let bi = 0; bi < all.length; bi++) {
                      const title = (all[bi].getAttribute('title') || all[bi].getAttribute('aria-label') || '');
                      if (titleLooksLikeSave(title) && all[bi].offsetParent !== null) {
                        found = all[bi];
                        return;
                      }
                    }
                    const iframes = d.querySelectorAll('iframe');
                    for (let j = 0; j < iframes.length; j++) {
                      try {
                        const ind = iframes[j].contentDocument;
                        if (ind) searchDoc(ind);
                      } catch (e) {}
                    }
                  } catch (e) {}
                }
                searchDoc(document);
                return found;
              }

              function captureLargestChartCanvasAsPng() {
                try {
                  const ranked = collectRankedCanvasCandidates();
                  for (let i = 0; i < ranked.length; i++) {
                    try {
                      const src = ranked[i].canvas;
                      const maxW = 900;
                      let out = src;
                      if (src.width > maxW) {
                        const scale = maxW / src.width;
                        const tmp = document.createElement('canvas');
                        tmp.width = Math.max(1, Math.round(src.width * scale));
                        tmp.height = Math.max(1, Math.round(src.height * scale));
                        const ctx = tmp.getContext('2d');
                        if (ctx) {
                          ctx.drawImage(src, 0, 0, tmp.width, tmp.height);
                          out = tmp;
                        }
                      }
                      const url = out.toDataURL('image/jpeg', 0.72);
                      if (url && url.indexOf('data:image') === 0 && url.length > 800) {
                        return url.split(',')[1];
                      }
                    } catch (eC) {}
                  }
                } catch (e0) {}
                return null;
              }

              const origHtmlAnchorClick = HTMLAnchorElement.prototype.click;
              let chartExportAnchorBlockInstalled = false;
              function installChartExportAnchorBlock() {
                if (chartExportAnchorBlockInstalled) return;
                chartExportAnchorBlockInstalled = true;
                HTMLAnchorElement.prototype.click = function() {
                  try {
                    const href = String(this.href || '');
                    const tw = window.top;
                    if (
                      tw &&
                      tw.__eaChartWarmupCapture &&
                      tw.__eaGotChartBlob &&
                      href.indexOf('blob:') === 0 &&
                      this.getAttribute('download') !== null
                    ) {
                      return;
                    }
                  } catch (eA) {}
                  return origHtmlAnchorClick.apply(this, arguments);
                };
              }
              function uninstallChartExportAnchorBlock() {
                if (!chartExportAnchorBlockInstalled) return;
                chartExportAnchorBlockInstalled = false;
                try {
                  HTMLAnchorElement.prototype.click = origHtmlAnchorClick;
                } catch (eU) {}
              }

              function installExportImageBlobHook() {
                let bestBlob = null;
                const createdEntries = [];
                const restoreList = [];
                const patchedWins = [];

                function considerBlob(blob) {
                  if (!blob || blob.size < 400) return;
                  try {
                    const t = (blob.type || '').toLowerCase();
                    const isImage = t.indexOf('image/') === 0;
                    const untypedLarge = (!t || t === '') && blob.size >= 800;
                    const octetOk = t === 'application/octet-stream' && blob.size >= 1200;
                    if (!isImage && !untypedLarge && !octetOk) return;
                    if (!bestBlob || blob.size > bestBlob.size) bestBlob = blob;
                    try {
                      const tw = window.top;
                      if (tw) tw.__eaGotChartBlob = true;
                    } catch (eFlag) {}
                  } catch (e0) {}
                }

                function ensurePatch(win) {
                  if (!win || !win.URL) return;
                  for (let p = 0; p < patchedWins.length; p++) {
                    if (patchedWins[p] === win) return;
                  }
                  patchedWins.push(win);
                  const origCreate = win.URL.createObjectURL.bind(win.URL);
                  win.URL.createObjectURL = function(blob) {
                    const url = origCreate(blob);
                    try {
                      createdEntries.push({ w: win, url: url });
                      considerBlob(blob);
                    } catch (e1) {}
                    return url;
                  };
                  restoreList.push(() => {
                    try {
                      win.URL.createObjectURL = origCreate;
                    } catch (e2) {}
                  });
                }

                function walkInstall(doc) {
                  if (!doc) return;
                  try {
                    ensurePatch(doc.defaultView);
                    const iframes = doc.querySelectorAll('iframe');
                    for (let fi = 0; fi < iframes.length; fi++) {
                      try {
                        const ind = iframes[fi].contentDocument;
                        if (ind) walkInstall(ind);
                      } catch (e3) {}
                    }
                  } catch (e4) {}
                }
                walkInstall(document);

                return {
                  takeBestBlob() {
                    return bestBlob;
                  },
                  cleanup() {
                    for (let ui = 0; ui < createdEntries.length; ui++) {
                      try {
                        createdEntries[ui].w.URL.revokeObjectURL(createdEntries[ui].url);
                      } catch (eR) {}
                    }
                    createdEntries.length = 0;
                    for (let ri = 0; ri < restoreList.length; ri++) {
                      restoreList[ri]();
                    }
                    restoreList.length = 0;
                    patchedWins.length = 0;
                  },
                };
              }

              function blobToBase64(blob) {
                return new Promise((resolve, reject) => {
                  try {
                    const r = new FileReader();
                    r.onloadend = () => {
                      const result = r.result;
                      if (typeof result === 'string' && result.indexOf(',') >= 0) {
                        resolve(result.split(',')[1]);
                      } else {
                        reject(new Error('read failed'));
                      }
                    };
                    r.onerror = () => reject(new Error('read failed'));
                    r.readAsDataURL(blob);
                  } catch (e3) {
                    reject(e3);
                  }
                });
              }

              async function waitForChartExportBlob(hook, minBytes, timeoutMs) {
                const deadline = Date.now() + timeoutMs;
                while (Date.now() < deadline) {
                  const b = hook.takeBestBlob();
                  if (b && b.size >= minBytes) return b;
                  await sleep(80);
                }
                const last = hook.takeBestBlob();
                if (last && last.size >= Math.min(minBytes, 800)) return last;
                return null;
              }

              async function focusChartForExport() {
                try {
                  const ranked = collectRankedCanvasCandidates();
                  const chartElement = ranked.length > 0 ? ranked[0].canvas : null;
                  if (chartElement) {
                    sendMessage('step_update', 'Focusing on chart...');
                    try {
                      chartElement.scrollIntoView({ block: 'center', inline: 'nearest' });
                    } catch (e0) {}
                    if (chartElement.focus) chartElement.focus();
                    chartElement.click();
                    await sleep(450);
                    sendMessage('step_update', 'Chart focused');
                    return;
                  }
                  const chartContainer =
                    document.querySelector('[class*="chart-container"]') ||
                    document.querySelector('[class*="trading-chart"]') ||
                    document.querySelector('div[class*="chart"]');
                  if (chartContainer) {
                    sendMessage('step_update', 'Focusing on chart...');
                    if (chartContainer.focus) chartContainer.focus();
                    chartContainer.click();
                    await sleep(450);
                    sendMessage('step_update', 'Chart container focused');
                  }
                } catch (e4) {}
              }

              async function prepareChartForExport() {
                try {
                  const ranked = collectRankedCanvasCandidates();
                  if (ranked.length > 0) {
                    ranked[0].canvas.scrollIntoView({ block: 'center', inline: 'nearest' });
                  }
                } catch (e) {}
                await new Promise((r) => {
                  requestAnimationFrame(() => {
                    requestAnimationFrame(r);
                  });
                });
                await sleep(450);
              }

              const captureChartWarmupForAi = async () => {
                if (isChartWarmup) {
                  await ensureSearchClosedAndMainChartReadyForWarmup();
                }
                await acceptDisclaimersAndConfirmDeep();
                await dismissLoginOverlay();
                window.__eaChartScreenshotSent = false;
                window.__eaLastChartCanvas = null;
                await prepareChartForExport();
                await focusChartForExport();
                for (let preCap = 0; preCap < 10; preCap++) {
                  await acceptDisclaimersAndConfirmDeep();
                  await dismissLoginOverlay();
                  if (!isAnyLoginModalBlocking()) break;
                  await sleep(450);
                }
                await prepareChartForExport();
                await focusChartForExport();
                sendMessage(
                  'step_update',
                  'Analysing chart'
                );
                let hook = null;
                try {
                  try {
                    const tw = window.top;
                    if (tw) {
                      tw.__eaChartWarmupCapture = true;
                      tw.__eaGotChartBlob = false;
                    }
                  } catch (eCap) {}
                  installChartExportAnchorBlock();
                  hook = installExportImageBlobHook();
                  let blob = null;
                  for (let saveTry = 0; saveTry < 3 && !blob; saveTry++) {
                    const saveBtn = findSaveChartAsImageButton();
                    if (!saveBtn) {
                      sendMessage('step_update', 'Save Chart as Image not found — retrying (' + (saveTry + 1) + '/3)');
                      await prepareChartForExport();
                      await focusChartForExport();
                      await sleep(700);
                      continue;
                    }
                    const clicked = typeof mouseClick === 'function' ? mouseClick(saveBtn) : false;
                    if (!clicked) saveBtn.click();
                    blob = await waitForChartExportBlob(hook, 1200, saveTry === 0 ? 18000 : 12000);
                  }
                  if (!blob) {
                    sendMessage('step_update', 'Toolbar export failed — capturing chart canvas');
                    const canvasB64 = captureLargestChartCanvasAsPng();
                    if (canvasB64 && canvasB64.length > 80) {
                      sendMessage('chart_screenshot', 'snapshot', { image: canvasB64, mimeType: 'image/jpeg' });
                      return;
                    }
                    sendMessage(
                      'chart_warmup_capture_failed',
                      'Chart image export timed out or image was too small — ensure the chart is focused and try again'
                    );
                    return;
                  }
                  try {
                    const b64 = await blobToBase64(blob);
                    if (!b64 || b64.length < 80) {
                      const canvasB64 = captureLargestChartCanvasAsPng();
                      if (canvasB64 && canvasB64.length > 80) {
                        sendMessage('chart_screenshot', 'snapshot', { image: canvasB64, mimeType: 'image/jpeg' });
                        return;
                      }
                      sendMessage('chart_warmup_capture_failed', 'Could not read exported chart image');
                      return;
                    }
                    const _mt = blob.type && String(blob.type).toLowerCase();
                    const mime =
                      _mt && _mt.indexOf('image/') === 0 ? blob.type : 'image/png';
                    sendMessage('chart_screenshot', 'snapshot', { image: b64, mimeType: mime });
                  } catch (e5) {
                    const canvasB64 = captureLargestChartCanvasAsPng();
                    if (canvasB64 && canvasB64.length > 80) {
                      sendMessage('chart_screenshot', 'snapshot', { image: canvasB64, mimeType: 'image/jpeg' });
                      return;
                    }
                    sendMessage(
                      'chart_warmup_capture_failed',
                      e5 && e5.message ? e5.message : 'Could not read exported chart image'
                    );
                  }
                } finally {
                  if (hook) hook.cleanup();
                  try {
                    const tw2 = window.top;
                    if (tw2) {
                      tw2.__eaChartWarmupCapture = false;
                      tw2.__eaGotChartBlob = false;
                    }
                  } catch (eCap2) {}
                  uninstallChartExportAnchorBlock();
                }
              };

              // Optimized authentication function matching Android robustness
              const runPostAuthTradeFlow = async function() {
                await acceptDisclaimersAndConfirmDeep();
                await dismissLoginOverlay();
                var _eqSC = scrapeTerminalAccountStats();
                sendMessage('authentication_success', 'MT5 session verified', { equity: _eqSC.equity, balance: _eqSC.balance });
                var symbolFound = await searchForSymbol('${symbolValue}');
                if (!symbolFound) {
                  sendMessage('chart_warmup_capture_failed', 'Quote Set Not found ${symbolValue}');
                  return;
                }
                await openChart('${symbolValue}');
                if (isChartWarmup) {
                  await acceptDisclaimersAndConfirmDeep();
                  await dismissLoginOverlay();
                  sendMessage('step_update', 'Waiting for chart (login must complete)...');
                  const chartReadyOk = await waitForChartReady(120000);
                  if (!chartReadyOk) {
                    sendMessage('chart_warmup_capture_failed', 'Chart not ready in time — still on login or chart not visible');
                    return;
                  }
                  var _eqCW = scrapeTerminalAccountStats();
                  if (_eqCW.equity || _eqCW.balance) {
                    sendMessage('equity_snapshot', 'Account updated', { equity: _eqCW.equity, balance: _eqCW.balance });
                  }
                  await captureChartWarmupForAi();
                  return;
                }
                await executeMultipleTrades();
              };

              const authenticateMT5 = async () => {
                try {
                  console.log('[MT5 Trading] Starting authentication process...');
                  sendMessage('step_update', 'Initializing MT5 Account...');

                  // Already logged in — skip portal login fill
                  if (isTerminalSessionVisible() && !isAnyLoginModalBlocking()) {
                    sendMessage('step_update', 'Session already active — continuing...');
                    await runPostAuthTradeFlow();
                    return;
                  }
                  
                  // Wait for page to be ready instead of fixed delay
                  let retries = 0;
                  while (retries < 10) {
                    const form = document.querySelector('.form');
                    const loginField = document.querySelector('input[name="login"]');
                    if (form || loginField) break;
                    if (isTerminalSessionVisible() && !isAnyLoginModalBlocking()) break;
                    await sleep(300);
                    retries++;
                  }

                  if (isTerminalSessionVisible() && !isAnyLoginModalBlocking()) {
                    sendMessage('step_update', 'Session already active — continuing...');
                    await runPostAuthTradeFlow();
                    return;
                  }
                  
                  // Check for disclaimer and accept if present
                  const disclaimer = document.querySelector('#disclaimer');
                  if (disclaimer) {
                    const acceptButton = document.querySelector('.accept-button');
                    if (acceptButton) {
                      acceptButton.click();
                      sendMessage('step_update', 'Accepting disclaimer...');
                      await sleep(500);
                    }
                  }
                  
                  // Remove existing connection - find Remove button (works across different broker terminals)
                  const findAndClickRemove = () => {
                    const allClickables = document.querySelectorAll('button, a, [role="button"], .button');
                    for (const el of allClickables) {
                      const text = (el.textContent || '').trim().toLowerCase();
                      const isRed = el.className && (el.className.includes('red') || el.style.color === 'red');
                      if (text === 'remove' || text.includes('remove') || text === 'disconnect' || (isRed && text.includes('remove'))) {
                        return el;
                      }
                    }
                    return null;
                  };
                  for (let attempt = 0; attempt < 3; attempt++) {
                    const removeBtn = findAndClickRemove();
                    if (removeBtn) {
                      sendMessage('step_update', 'Removing existing connection...');
                      removeBtn.click();
                      await sleep(4500);
                    } else break;
                  }
                  
                  await sleep(2000);
                  
                  // Fill login credentials - use native setter for Svelte/React-controlled inputs
                  const loginField = document.querySelector('input[name="login"]') || 
                                    document.querySelector('input[type="text"][placeholder*="login" i]') ||
                                    document.querySelector('input[type="number"]') ||
                                    document.querySelector('input#login');
                  
                  const passwordField = document.querySelector('input[name="password"]') || 
                                       document.querySelector('input[type="password"]') ||
                                       document.querySelector('input#password');
                  
                  if (!loginField || !passwordField) {
                    if (isTerminalSessionVisible() && !isAnyLoginModalBlocking()) {
                      sendMessage('step_update', 'Session already active — continuing...');
                      await runPostAuthTradeFlow();
                      return;
                    }
                    sendMessage('authentication_failed', 'Login form not found');
                    return;
                  }
                  if (!loginCredential) {
                    sendMessage('authentication_failed', 'Login not configured - connect MT5 in MetaTrader tab');
                    return;
                  }
                  if (!passwordCredential) {
                    sendMessage('authentication_failed', 'Password not configured - connect MT5 in MetaTrader tab');
                    return;
                  }
                  
                  const setInputValue = (el, val) => {
                    el.focus();
                    el.value = '';
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    const nativeSetter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;
                    if (nativeSetter) nativeSetter.call(el, val);
                    else el.value = val;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    el.dispatchEvent(new Event('blur', { bubbles: true }));
                  };
                  setInputValue(loginField, loginCredential);
                  sendMessage('step_update', 'Login filled');
                  await sleep(300);
                  setInputValue(passwordField, passwordCredential);
                  sendMessage('step_update', 'Password filled');
                  if (serverCredential) {
                    var serverField = document.querySelector('input[name="server"]') ||
                      document.getElementById('server') ||
                      document.querySelector('input[placeholder*="server" i]');
                    if (serverField) {
                      setInputValue(serverField, serverCredential);
                      sendMessage('step_update', 'Server filled');
                    }
                  }
                  await sleep(1500);
                  
                  sendMessage('step_update', 'Connecting to Server...');
                  const loginButton = document.querySelector('.button.svelte-1wrky82.active') ||
                                   document.querySelector('button[type="submit"]') ||
                                   document.querySelector('.button.active') ||
                                   Array.from(document.querySelectorAll('button')).find(btn => 
                                     btn.textContent.trim().toLowerCase().includes('login') ||
                                     btn.textContent.trim().toLowerCase().includes('connect')
                                   );
                  
                  if (loginButton) {
                    loginButton.click();
                    sendMessage('step_update', 'Connecting...');
                    let loginRetries = 0;
                    while (loginRetries < 35) {
                      const pageText = (document.body?.innerText || '').toLowerCase();
                      if (pageText.includes('invalid login') || pageText.includes('invalid password') || 
                          pageText.includes('wrong password') || pageText.includes('wrong login') ||
                          pageText.includes('incorrect password') || pageText.includes('incorrect login')) {
                        sendMessage('authentication_failed', 'Invalid login or password - verify credentials in MetaTrader tab');
                        return;
                      }
                      const loginForm = document.querySelector('input[name="login"]');
                      const searchBar = document.querySelector('input[placeholder*="Search symbol" i]') ||
                                       document.querySelector('input[placeholder*="Search" i]') ||
                                       document.querySelector('input[type="search"]') ||
                                       document.querySelector('.search input');
                      if (!loginForm && searchBar && searchBar.offsetParent !== null) {
                        break;
                      }
                      if (isTerminalSessionVisible() && !isAnyLoginModalBlocking()) break;
                      await sleep(500);
                      loginRetries++;
                    }
                  } else {
                    if (isTerminalSessionVisible() && !isAnyLoginModalBlocking()) {
                      sendMessage('step_update', 'Session already active — continuing...');
                      await runPostAuthTradeFlow();
                      return;
                    }
                    sendMessage('authentication_failed', 'Login button not found');
                    return;
                  }
                  
                  sendMessage('step_update', 'Verifying authentication...');
                  await sleep(1000);
                  await acceptDisclaimersAndConfirmDeep();
                  await dismissLoginOverlay();
                  
                  sendMessage('step_update', 'Checking Market Watch panel...');
                  
                  const searchFieldCheck = document.querySelector('input[placeholder*="Search symbol" i]') ||
                                          document.querySelector('input[placeholder*="Search" i]') ||
                                          document.querySelector('input[type="search"]');
                  
                  if (!searchFieldCheck || searchFieldCheck.offsetParent === null) {
                    sendMessage('step_update', 'Expanding Market Watch panel...');
                    
                    const marketWatchButton = document.querySelector('div.icon-button.svelte-1iwf8ix[title="Show Market Watch (Ctrl + M)"]') ||
                                             document.querySelector('div.icon-button[title*="Show Market Watch" i]') ||
                                             document.querySelector('div.icon-button[title*="Market Watch" i]') ||
                                             Array.from(document.querySelectorAll('div.icon-button')).find(btn => 
                                               btn.getAttribute('title') && btn.getAttribute('title').includes('Market Watch')
                                             );
                    
                    if (marketWatchButton) {
                      const buttonTitle = marketWatchButton.getAttribute('title') || '';
                      if (buttonTitle.toLowerCase().includes('show')) {
                        marketWatchButton.click();
                        sendMessage('step_update', 'Market Watch button clicked, waiting for panel to expand...');
                        await sleep(2000);
                      } else {
                        sendMessage('step_update', 'Market Watch already visible');
                      }
                    }
                  } else {
                    sendMessage('step_update', 'Market Watch already visible');
                  }
                  
                  await sleep(1000);
                  const searchField = document.querySelector('input[placeholder*="Search symbol" i]') ||
                                     document.querySelector('input[placeholder*="Search" i]') ||
                                     document.querySelector('input[type="search"]');
                  
                  if (searchField && searchField.offsetParent !== null) {
                    await runPostAuthTradeFlow();
                    return;
                  }
                  
                  await sleep(3000);
                  const searchFieldRetry = document.querySelector('input[placeholder*="Search symbol" i]') ||
                                          document.querySelector('input[placeholder*="Search" i]') ||
                                          document.querySelector('input[type="search"]');
                  
                  if (searchFieldRetry && searchFieldRetry.offsetParent !== null) {
                    await runPostAuthTradeFlow();
                    return;
                  }

                  if (isTerminalSessionVisible() && !isAnyLoginModalBlocking()) {
                    sendMessage('step_update', 'Session detected without search bar — continuing...');
                    await runPostAuthTradeFlow();
                    return;
                  }
                  
                  const errText = (document.body?.innerText || '').toLowerCase();
                  if (errText.includes('invalid') || errText.includes('wrong') || errText.includes('incorrect')) {
                    sendMessage('authentication_failed', 'Invalid login or password - verify credentials in MetaTrader tab');
                  } else {
                    sendMessage('authentication_failed', 'Authentication failed - could not reach terminal. Check broker connection.');
                  }
                  
                } catch(e) {
                  sendMessage('authentication_failed', 'Error during authentication: ' + e.message);
                }
              };

              const closeSearchPanelAfterSymbolSelect = async () => {
                try {
                  sendMessage('step_update', 'Closing search panel for a wider chart...');
                  try {
                    const sf =
                      document.querySelector('input[placeholder*="Search symbol" i]') ||
                      document.querySelector('input[placeholder*="Search" i]');
                    if (sf) sf.blur();
                  } catch (e) {}
                  document.dispatchEvent(
                    new KeyboardEvent('keydown', { key: 'Escape', keyCode: 27, bubbles: true, cancelable: true })
                  );
                  await sleep(220);
                  document.dispatchEvent(
                    new KeyboardEvent('keydown', { key: 'Escape', keyCode: 27, bubbles: true, cancelable: true })
                  );
                  await sleep(300);
                  const hideMw =
                    document.querySelector('div.icon-button.svelte-1iwf8ix[title="Hide Market Watch (Ctrl + M)"]') ||
                    Array.from(document.querySelectorAll('div.icon-button.svelte-1iwf8ix')).find(btn => {
                      const t = (btn.getAttribute('title') || '').toLowerCase();
                      return t.includes('hide') && t.includes('market watch');
                    });
                  if (hideMw) {
                    hideMw.click();
                    await sleep(650);
                  }
                  const sf2 =
                    document.querySelector('input[placeholder*="Search symbol" i]') ||
                    document.querySelector('input[placeholder*="Search" i]');
                  if (sf2) {
                    sf2.value = '';
                    sf2.dispatchEvent(new Event('input', { bubbles: true }));
                    sf2.blur();
                  }
                  document.dispatchEvent(
                    new KeyboardEvent('keydown', { key: 'Escape', keyCode: 27, bubbles: true, cancelable: true })
                  );
                  await sleep(400);
                } catch (e) {}
              };

              /**
               * Before chart AI snapshot (same as RN mt5-signal-webview): dismiss search / Market Watch, then wait
               * until the main WebGL chart fills the viewport enough for Save Chart as Image.
               */
              async function ensureSearchClosedAndMainChartReadyForWarmup() {
                try {
                  sendMessage('step_update', 'Closing search and expanding chart before capture...');
                  await acceptDisclaimersAndConfirmDeep();
                  await dismissLoginOverlay();
                  await closeSearchPanelAfterSymbolSelect();
                  await sleep(550);
                  await dismissLoginOverlay();
                  await closeSearchPanelAfterSymbolSelect();
                  await sleep(420);
                  const vp = Math.max(1, window.innerWidth || 800) * Math.max(1, window.innerHeight || 600);
                  const minCanvasRectArea = Math.max(65000, vp * 0.13);
                  const minInternalArea = 36000;
                  const deadline = Date.now() + 24000;
                  let n = 0;
                  while (Date.now() < deadline) {
                    n++;
                    const ranked = collectRankedCanvasCandidates();
                    if (ranked.length > 0) {
                      const c = ranked[0].canvas;
                      const rect = c.getBoundingClientRect();
                      const area = rect.width * rect.height;
                      const internal = (c.width || 0) * (c.height || 0);
                      if (area >= minCanvasRectArea && internal >= minInternalArea) {
                        sendMessage(
                          'step_update',
                          'Chart ready (~' + Math.round(area / 1000) + 'k px²) — locking focus...'
                        );
                        try {
                          c.scrollIntoView({ block: 'center', inline: 'nearest' });
                        } catch (e0) {}
                        const cx = rect.left + rect.width / 2;
                        const cy = rect.top + rect.height / 2;
                        try {
                          c.dispatchEvent(
                            new MouseEvent('mousedown', {
                              bubbles: true,
                              cancelable: true,
                              clientX: cx,
                              clientY: cy,
                              view: window
                            })
                          );
                          c.dispatchEvent(
                            new MouseEvent('mouseup', {
                              bubbles: true,
                              cancelable: true,
                              clientX: cx,
                              clientY: cy,
                              view: window
                            })
                          );
                          c.dispatchEvent(
                            new MouseEvent('click', {
                              bubbles: true,
                              cancelable: true,
                              clientX: cx,
                              clientY: cy,
                              view: window
                            })
                          );
                        } catch (e1) {}
                        if (c.focus) c.focus();
                        await sleep(700);
                        return;
                      }
                    }
                    sendMessage('step_update', 'Waiting for chart to open and expand (' + n + ')...');
                    await dismissLoginOverlay();
                    await prepareChartForExport();
                    await focusChartForExport();
                    await closeSearchPanelAfterSymbolSelect();
                    await sleep(Math.min(900, 420 + n * 45));
                  }
                  sendMessage('step_update', 'Chart size check incomplete — proceeding with best-effort capture');
                } catch (eEns) {}
              }

              function eaMouseClickCenter(element) {
                try {
                  const rect = element.getBoundingClientRect();
                  const x = rect.left + rect.width / 2;
                  const y = rect.top + rect.height / 2;
                  element.dispatchEvent(
                    new MouseEvent('mousedown', {
                      bubbles: true,
                      cancelable: true,
                      view: window,
                      button: 0,
                      buttons: 1,
                      clientX: x,
                      clientY: y,
                    })
                  );
                  element.dispatchEvent(
                    new MouseEvent('mouseup', {
                      bubbles: true,
                      cancelable: true,
                      view: window,
                      button: 0,
                      buttons: 0,
                      clientX: x,
                      clientY: y,
                    })
                  );
                  element.dispatchEvent(
                    new MouseEvent('click', {
                      bubbles: true,
                      cancelable: true,
                      view: window,
                      button: 0,
                      buttons: 0,
                      clientX: x,
                      clientY: y,
                    })
                  );
                  return true;
                } catch (e) {
                  return false;
                }
              }

              function eaSetInputValueForSearch(el, val) {
                const v = val == null ? '' : String(val);
                try {
                  el.focus();
                  const desc = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
                  const nativeSetter = desc && desc.set;
                  el.value = '';
                  el.dispatchEvent(new Event('input', { bubbles: true }));
                  if (typeof InputEvent !== 'undefined') {
                    try {
                      el.dispatchEvent(
                        new InputEvent('input', {
                          bubbles: true,
                          cancelable: true,
                          inputType: 'deleteContentBackward',
                          data: null,
                        })
                      );
                    } catch (eI0) {}
                  }
                  if (nativeSetter) nativeSetter.call(el, v);
                  else el.value = v;
                  if (typeof InputEvent !== 'undefined') {
                    try {
                      el.dispatchEvent(
                        new InputEvent('input', {
                          bubbles: true,
                          cancelable: true,
                          inputType: 'insertFromPaste',
                          data: v,
                        })
                      );
                    } catch (eI1) {}
                  }
                  el.dispatchEvent(new Event('change', { bubbles: true }));
                  el.dispatchEvent(
                    new KeyboardEvent('keyup', {
                      key: 'Enter',
                      code: 'Enter',
                      keyCode: 13,
                      bubbles: true,
                    })
                  );
                } catch (eSv) {
                  try {
                    el.value = v;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                  } catch (eS2) {}
                }
              }

              function eaActivateSearchResultRow(row) {
                try {
                  row.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                } catch (eA0) {}
                try {
                  row.click();
                } catch (eA1) {}
                eaMouseClickCenter(row);
                try {
                  const rr = row.getBoundingClientRect();
                  row.dispatchEvent(
                    new MouseEvent('dblclick', {
                      bubbles: true,
                      cancelable: true,
                      view: window,
                      button: 0,
                      buttons: 0,
                      clientX: rr.left + rr.width / 2,
                      clientY: rr.top + rr.height / 2,
                    })
                  );
                } catch (eA2) {}
              }

              function eaExtractTickerFromRow(cellText) {
                const raw = String(cellText || '').trim();
                if (!raw) return '';
                let line = raw.split(/[\\r\\n]+/)[0].trim();
                const slash = line.indexOf('/');
                if (slash >= 0) line = line.substring(0, slash).trim();
                if (line.length > 40) return '';
                const token = (line.split(/\\s+/)[0] || '').trim();
                if (token.length < 3 || token.length > 24) return '';
                return token;
              }
              function eaNormalizeSymbolKey(s) {
                return String(s || '').replace(/\\s/g, '').toUpperCase();
              }
              function eaAlnumSymbol(s) {
                return eaNormalizeSymbolKey(s).replace(/[^A-Z0-9]/g, '');
              }
              function eaIsPrefixSymbolMatch(wanted, candidate) {
                const wNorm = eaNormalizeSymbolKey(wanted);
                const cNorm = eaNormalizeSymbolKey(candidate);
                if (!wNorm || !cNorm) return false;
                if (wNorm === cNorm) return true;
                if (cNorm.indexOf(wNorm + '.') === 0 || cNorm.indexOf(wNorm + '_') === 0 || cNorm.indexOf(wNorm + '#') === 0) {
                  return true;
                }
                if (wNorm.indexOf(cNorm + '.') === 0 || wNorm.indexOf(cNorm + '_') === 0 || wNorm.indexOf(cNorm + '#') === 0) {
                  return true;
                }
                const w = eaAlnumSymbol(wanted);
                const c = eaAlnumSymbol(candidate);
                if (!w || !c || w.length < 3 || c.length < 3) return false;
                if (c === w) return true;
                const brokerSuffix = /^(M|I|C|S|PRO|RAW|ECN|STP|MIC|TRD|CNC)$/i;
                if (c.indexOf(w) === 0) {
                  return brokerSuffix.test(c.substring(w.length));
                }
                if (w.indexOf(c) === 0) {
                  return brokerSuffix.test(w.substring(c.length));
                }
                return false;
              }
              function eaSymbolMatchRank(wanted, ticker) {
                if (!eaIsPrefixSymbolMatch(wanted, ticker)) return null;
                const w = eaAlnumSymbol(wanted);
                const c = eaAlnumSymbol(ticker);
                if (c === w) return 0;
                return 10 + Math.abs(c.length - w.length);
              }
              function eaRowTextMatchesInstrument(cellText, wanted) {
                const ticker = eaExtractTickerFromRow(cellText);
                if (!ticker) return false;
                return eaIsPrefixSymbolMatch(wanted, ticker);
              }
              function eaConsiderSearchCandidate(el, cellText, wanted, best) {
                const ticker = eaExtractTickerFromRow(cellText);
                if (!ticker) return best;
                const rank = eaSymbolMatchRank(wanted, ticker);
                if (rank == null) return best;
                if (!best || rank < best.score) return { el: el, score: rank, ticker: ticker };
                return best;
              }

              function eaBuildSymbolSearchQueries(symbolName) {
                const s = String(symbolName || '').trim();
                const out = [];
                function pushUnique(v) {
                  const t = String(v || '').trim();
                  if (!t) return;
                  if (t.replace(/[^A-Za-z0-9]/g, '').length < 4 && t.length < 4) return;
                  for (let i = 0; i < out.length; i++) {
                    if (out[i].toLowerCase() === t.toLowerCase()) return;
                  }
                  out.push(t);
                }
                pushUnique(s);
                pushUnique(s.replace(/\\s+/g, ' '));
                const dottedRoot = s.split(/[.#_]/)[0];
                if (dottedRoot) pushUnique(dottedRoot.trim());
                const alnum = eaAlnumSymbol(s);
                if (alnum) pushUnique(alnum);
                const stripped = alnum.replace(/(MIC|TRD|CNC|PRO|RAW|ECN|STP|[MICS])$/i, '');
                if (stripped.length >= 4) pushUnique(stripped);
                const m = s.match(/^(.+?)\\s+index$/i);
                if (m) pushUnique(m[1].trim());
                const parts = s.split(/\\s+/).filter((p) => p.length > 0);
                if (parts.length >= 3) pushUnique(parts.slice(0, -1).join(' '));
                if (parts.length >= 2) pushUnique(parts.slice(0, 2).join(' '));
                if (parts.length >= 1 && parts[0].length >= 4) pushUnique(parts[0]);
                return out;
              }

              async function eaSelectSearchResultToOpenChart(symbolName, searchField) {
                const deadlineMs = Date.now() + 24000;
                while (Date.now() < deadlineMs) {
                  const seen = new WeakSet();
                  let best = null;
                  let mwRows = [];
                  try {
                    mwRows = eaQuerySelectorAllDeep('div.row.svelte-1m8pzlu');
                  } catch (eMr) {
                    mwRows = [];
                  }
                  for (let mwi = 0; mwi < mwRows.length; mwi++) {
                    const mrow = mwRows[mwi];
                    if (!mrow || !mrow.offsetParent || seen.has(mrow)) continue;
                    const mbtn =
                      (mrow.querySelector &&
                        (mrow.querySelector('button.item.svelte-fad8m4') || mrow.querySelector('button.item'))) ||
                      null;
                    if (!mbtn || !mbtn.offsetParent) continue;
                    const mtx = (mrow.innerText || mrow.textContent || '').trim();
                    if (!mtx || mtx.length > 160) continue;
                    seen.add(mrow);
                    seen.add(mbtn);
                    best = eaConsiderSearchCandidate(mbtn, mtx, symbolName, best);
                  }
                  const selectorList = [
                    'div.row.svelte-1m8pzlu button.item.svelte-fad8m4',
                    'div.row.svelte-1m8pzlu button.item',
                    '.name.svelte-19bwscl .symbol.svelte-19bwscl',
                    '.symbol.svelte-19bwscl',
                    '[class*="name"] [class*="symbol"]',
                    '[role="option"]',
                    '[role="listbox"] [role="option"]',
                    'table tbody tr td',
                    'div[class*="watch"] td',
                    'div[class*="symbol"]',
                  ];
                  for (let si = 0; si < selectorList.length; si++) {
                    let nodes = [];
                    try {
                      nodes = eaQuerySelectorAllDeep(selectorList[si]);
                    } catch (e0) {
                      nodes = [];
                    }
                    for (let ni = 0; ni < nodes.length; ni++) {
                      const el = nodes[ni];
                      if (!el || !el.offsetParent || seen.has(el)) continue;
                      seen.add(el);
                      const rowForText =
                        el.closest && el.closest('div.row.svelte-1m8pzlu')
                          ? el.closest('div.row.svelte-1m8pzlu')
                          : null;
                      let t = (el.innerText || el.textContent || '').trim();
                      if ((!t || t.length < 3) && rowForText) {
                        t = (rowForText.innerText || rowForText.textContent || '').trim();
                      }
                      if (!t || t.length > 80) continue;
                      const rowBtn =
                        el.tagName === 'BUTTON' && el.classList && el.classList.contains('item')
                          ? el
                          : rowForText &&
                            (rowForText.querySelector('button.item.svelte-fad8m4') ||
                              rowForText.querySelector('button.item'));
                      const row =
                        rowBtn ||
                        (el.closest('[role="option"]') ||
                          el.closest('tr') ||
                          el.closest('li') ||
                          el.closest('div[class*="row"]') ||
                          el);
                      best = eaConsiderSearchCandidate(row, t, symbolName, best);
                    }
                  }
                  if (best) {
                    eaActivateSearchResultRow(best.el);
                    sendMessage(
                      'symbol_selected',
                      'Symbol ' + symbolName + ' — prefix match ' + best.ticker
                    );
                    await sleep(2400);
                    return true;
                  }
                  await sleep(380);
                }
                sendMessage('error', 'Quote Set Not found ' + symbolName);
                return false;
              }

              // Search for symbol function - STRICTLY SEQUENTIAL Step 2
              const searchForSymbol = async (symbolName) => {
                try {
                  sendMessage('step_update', 'Step 2: Searching for symbol ' + symbolName + '...');
                  let searchField = null;
                  for (let openTry = 0; openTry < 4 && (!searchField || searchField.offsetParent === null); openTry++) {
                    searchField = eaPickVisibleSearchInputDeep();
                    if (searchField && searchField.offsetParent !== null) break;
                    sendMessage('step_update', 'Opening Market Watch for search (' + (openTry + 1) + '/4)...');
                    eaExpandMarketWatchPanel();
                    await sleep(1400 + openTry * 400);
                    searchField = eaPickVisibleSearchInputDeep();
                  }

                  if (!searchField || searchField.offsetParent === null) {
                    sendMessage('error', 'Search field not found or not visible after expanding');
                    return false;
                  }

                  sendMessage('step_update', 'Search bar found — resolving symbol (prefix match only)...');
                  const queries = eaBuildSymbolSearchQueries(symbolName);
                  let symbolSelected = false;
                  for (let round = 0; round < 3 && !symbolSelected; round++) {
                    if (round > 0) {
                      sendMessage('step_update', 'Retrying symbol search round ' + (round + 1) + '/3');
                      eaExpandMarketWatchPanel();
                      await sleep(900);
                      searchField = eaPickVisibleSearchInputDeep() || searchField;
                    }
                    for (let qi = 0; qi < queries.length; qi++) {
                      sendMessage(
                        'step_update',
                        'Search try ' + (qi + 1) + '/' + queries.length + ': "' + queries[qi] + '"'
                      );
                      eaSetInputValueForSearch(searchField, queries[qi]);
                      await sleep(900 + qi * 220 + round * 250);
                      sendMessage('symbol_search', 'Symbol query: ' + queries[qi]);
                      symbolSelected = await eaSelectSearchResultToOpenChart(symbolName, searchField);
                      if (symbolSelected) break;
                      await sleep(500);
                    }
                  }

                  if (symbolSelected) {
                    await acceptDisclaimersAndConfirmDeep();
                    await dismissLoginOverlay();
                    await sleep(700);
                    await acceptDisclaimersAndConfirmDeep();
                    await dismissLoginOverlay();
                    await closeSearchPanelAfterSymbolSelect();
                    return true;
                  }
                  sendMessage('error', 'Quote Set Not found ' + symbolName);
                  await closeSearchPanelAfterSymbolSelect();
                  return false;
                } catch(e) {
                  sendMessage('error', 'Error searching for symbol: ' + e.message);
                  return false;
                }
              };

              // Open chart function - STRICTLY SEQUENTIAL Step 3
              const openChart = async (symbolName) => {
                try {
                  sendMessage('step_update', 'Step 3: Opening chart for ' + symbolName + '...');
                  
                  await sleep(2000);
                  
                  let chartElement = null;
                  let retries = 0;
                  while (retries < 5) {
                    chartElement = document.querySelector('[class*="chart"]') ||
                                  document.querySelector('canvas') ||
                                  document.querySelector('[id*="chart"]') ||
                                  document.querySelector('[class*="Chart"]');
                    
                    if (chartElement) {
                      sendMessage('step_update', 'Chart opened for ' + symbolName);
                      break;
                    }
                    await sleep(500);
                    retries++;
                  }
                  
                  await sleep(1000);
                  
                  if (chartElement) {
                    sendMessage('step_update', 'Focusing on chart...');
                    chartElement.focus();
                    chartElement.click();
                    await sleep(500);
                    sendMessage('step_update', 'Chart focused');
                  } else {
                    const chartContainer = document.querySelector('[class*="chart-container"]') ||
                                          document.querySelector('[class*="trading-chart"]') ||
                                          document.querySelector('div[class*="chart"]');
                    if (chartContainer) {
                      chartContainer.focus();
                      chartContainer.click();
                      await sleep(500);
                      sendMessage('step_update', 'Chart container focused');
                    }
                  }

                  await acceptDisclaimersAndConfirmDeep();
                  await dismissLoginOverlay();
                  await sleep(450);
                  await acceptDisclaimersAndConfirmDeep();
                  await dismissLoginOverlay();

                  const rankedOpen = collectRankedCanvasCandidates();
                  if (rankedOpen.length > 0) {
                    const cc = rankedOpen[0].canvas;
                    try {
                      const rcc = cc.getBoundingClientRect();
                      cc.scrollIntoView({ block: 'center', inline: 'nearest' });
                      cc.dispatchEvent(
                        new MouseEvent('click', {
                          bubbles: true,
                          cancelable: true,
                          clientX: rcc.left + rcc.width / 2,
                          clientY: rcc.top + rcc.height / 2,
                          view: window
                        })
                      );
                    } catch (eoc) {}
                  }
                  sendMessage('step_update', 'Closing search panel after chart open...');
                  await closeSearchPanelAfterSymbolSelect();
                  await sleep(600);
                } catch(e) {
                  sendMessage('error', 'Error opening chart: ' + e.message);
                }
              };

              // Helper function to simulate mouse click
              const mouseClick = (element) => {
                try {
                  const rect = element.getBoundingClientRect();
                  const x = rect.left + rect.width / 2;
                  const y = rect.top + rect.height / 2;
                  
                  const mousedownEvent = new MouseEvent('mousedown', {
                    bubbles: true,
                    cancelable: true,
                    view: window,
                    button: 0,
                    buttons: 1,
                    clientX: x,
                    clientY: y,
                    screenX: x,
                    screenY: y
                  });
                  element.dispatchEvent(mousedownEvent);
                  
                  const mouseupEvent = new MouseEvent('mouseup', {
                    bubbles: true,
                    cancelable: true,
                    view: window,
                    button: 0,
                    buttons: 0,
                    clientX: x,
                    clientY: y,
                    screenX: x,
                    screenY: y
                  });
                  element.dispatchEvent(mouseupEvent);
                  
                  const clickEvent = new MouseEvent('click', {
                    bubbles: true,
                    cancelable: true,
                    view: window,
                    button: 0,
                    buttons: 0,
                    clientX: x,
                    clientY: y,
                    screenX: x,
                    screenY: y
                  });
                  element.dispatchEvent(clickEvent);
                  
                         return true;
                } catch(e) {
                       return false;
                     }
                   };
                   
              // Open order dialog and execute single trade - STRICTLY SEQUENTIAL
              const openOrderDialogAndExecuteTrade = async (tradeNumber, totalTrades) => {
                try {
                  sendMessage('step_update', '📋 Opening order dialog for trade ' + tradeNumber + '/' + totalTrades + '...');
                  
                  const findHideToolbar = () =>
                    document.querySelector('div.icon-button.svelte-1iwf8ix.withText[title="Hide Trade Form (F9)"]') ||
                    Array.from(document.querySelectorAll('div.icon-button.svelte-1iwf8ix.withText')).find((btn) => {
                      const title = btn.getAttribute('title') || '';
                      return title.includes('Hide Trade Form') || (title.includes('Trade Form') && title.includes('Hide'));
                    });
                  const findShowToolbar = () =>
                    document.querySelector('div.icon-button.svelte-1iwf8ix.withText[title="Show Trade Form (F9)"]') ||
                    Array.from(document.querySelectorAll('div.icon-button.svelte-1iwf8ix.withText')).find((btn) => {
                      const title = btn.getAttribute('title') || '';
                      return title.includes('Show Trade Form') || (title.includes('Trade Form') && title.includes('Show'));
                    });

                  let orderDialogTrigger = null;
                  const hideToolbarBtn2 = findHideToolbar();
                  if (hideToolbarBtn2 && hideToolbarBtn2.offsetParent) {
                    orderDialogTrigger = hideToolbarBtn2;
                    sendMessage('step_update', '✅ Order panel already open (not toggling Hide — avoids close)');
                  } else {
                    orderDialogTrigger = findShowToolbar();
                    if (orderDialogTrigger) {
                      const clicked = mouseClick(orderDialogTrigger);
                      if (clicked) {
                        sendMessage('step_update', '✅ Order dialog opened (mouse click)');
                      } else {
                        orderDialogTrigger.click();
                        sendMessage('step_update', '✅ Order dialog opened (fallback click)');
                      }
                    } else {
                      orderDialogTrigger = document.querySelector('div.group.svelte-aqy1pm') ||
                        Array.from(document.querySelectorAll('div.group.svelte-aqy1pm')).find((el) => el.offsetParent !== null);
                      if (orderDialogTrigger) {
                        const clickedG = mouseClick(orderDialogTrigger);
                        if (clickedG) {
                          sendMessage('step_update', '✅ Order dialog opened via group div (mouse click)');
                        } else {
                          orderDialogTrigger.click();
                          sendMessage('step_update', '✅ Order dialog opened via group div (fallback click)');
                        }
                      }
                    }
                  }
                  
                  if (!orderDialogTrigger) {
                    sendMessage('error', '❌ Order dialog trigger not found');
                    return false;
                  }
                  
                  await sleep(2000);
                  
                  let retries = 0;
                  let dialogElement = null;
                  let dialogReady = false;
                  while (retries < 10) {
                    const volumeInput = document.querySelector('input[inputmode="decimal"]');
                    const commentInput = document.querySelector('input.svelte-mtorg2');
                    const tradeButton = document.querySelector('button.trade-button.svelte-ailjot');
                    
                    if (!dialogElement) {
                      dialogElement = document.querySelector('[class*="trade-form"]') ||
                                    document.querySelector('[class*="order-dialog"]') ||
                                    document.querySelector('[class*="trade-dialog"]') ||
                                    document.querySelector('form') ||
                                    volumeInput?.closest('div') ||
                                    volumeInput?.closest('form');
                    }
                    
                    if (volumeInput && commentInput && tradeButton) {
                      sendMessage('step_update', '✅ Order dialog ready with all form elements');
                      dialogReady = true;
                      break;
                    }
                    await sleep(500);
                    retries++;
                  }
                  
                  if (!dialogReady) {
                    sendMessage('error', '❌ Order dialog not ready after waiting');
                    return false;
                  }
                  
                  if (dialogElement) {
                    dialogElement.focus();
                    const rect = dialogElement.getBoundingClientRect();
                    const x = rect.left + rect.width / 2;
                    const y = rect.top + rect.height / 2;
                    const focusClick = new MouseEvent('click', {
                      bubbles: true,
                      cancelable: true,
                      view: window,
                      button: 0,
                      clientX: x,
                      clientY: y
                    });
                    dialogElement.dispatchEvent(focusClick);
                    await sleep(500);
                  }
                  
                  await sleep(500);
                  
                  sendMessage('step_update', '📝 Filling order form for trade ' + tradeNumber + '/' + totalTrades + '...');
                  const tradeSuccess = await fillOrderFormAndConfirm(tradeNumber, totalTrades);
                  
                  if (!tradeSuccess) {
                    sendMessage('error', '❌ Trade ' + tradeNumber + ' execution failed');
                    return false;
                  }
                  
                  sendMessage('step_update', '⏳ Confirming trade ' + tradeNumber + '...');
                  await sleep(1500);
                  
                  const okButton = Array.from(document.querySelectorAll('button.trade-button.svelte-ailjot')).find((btn) => {
                    const text = (btn.innerText || btn.textContent || '').trim();
                    if (/^(buy|sell)/i.test(text)) return false;
                    return text === 'OK' || text === 'ok';
                  });
                  
                  if (okButton) {
                    okButton.click();
                    sendMessage('step_update', '✅ Trade ' + tradeNumber + ' confirmed (OK clicked)');
                    await sleep(1000);
                  } else {
                    sendMessage('step_update', '✅ Trade ' + tradeNumber + ' auto-confirmed');
                  }
                  
                  return true;
                } catch(e) {
                  sendMessage('error', '❌ Error in trade ' + tradeNumber + ': ' + e.message);
                  return false;
                }
              };

              // Fill order form and confirm trade - STRICTLY SEQUENTIAL
              const fillOrderFormAndConfirm = async (tradeNumber, totalTrades) => {
                try {
                  var _p = window.__eaActiveTradePayload;
                  const symbol = (_p && _p.symbol) ? String(_p.symbol) : '${symbolValue}';
                  const action = (_p && _p.action) ? String(_p.action) : '${actionValue}';
                  const volume = (_p && _p.volume) ? String(_p.volume) : '${volumeValue}';
                  var slRaw = (_p && _p.sl != null && String(_p.sl) !== '') ? String(_p.sl) : '${slValue}';
                  var tpRaw = (_p && _p.tp != null && String(_p.tp) !== '') ? String(_p.tp) : '${tpValue}';
                  function hasTradeLevel(v) {
                    if (v == null) return false;
                    var s = String(v).trim().replace(/,/g, '');
                    if (!s) return false;
                    var n = parseFloat(s);
                    return Number.isFinite(n) && n !== 0;
                  }
                  const sl = hasTradeLevel(slRaw) ? slRaw : '';
                  const tp = hasTradeLevel(tpRaw) ? tpRaw : '';
                  const orderComment = '${robotNameValue}';

                  const setInputValueNative = function(el, val) {
                    if (!el || val == null) return;
                    try {
                      el.focus();
                      el.value = '';
                      el.dispatchEvent(new Event('input', { bubbles: true }));
                      el.dispatchEvent(new Event('change', { bubbles: true }));
                      const desc = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
                      const nativeSetter = desc && desc.set;
                      if (nativeSetter) nativeSetter.call(el, val);
                      else el.value = val;
                      el.dispatchEvent(new Event('input', { bubbles: true }));
                      el.dispatchEvent(new Event('change', { bubbles: true }));
                      el.dispatchEvent(new Event('blur', { bubbles: true }));
                    } catch (eSet) {
                      try { el.value = val; } catch (e2) {}
                    }
                  };
                  
                  const decimalInputs = Array.from(document.querySelectorAll('input[inputmode="decimal"]'));
                  
                  if (decimalInputs.length > 0 && volume) {
                    setInputValueNative(decimalInputs[0], volume);
                    sendMessage('step_update', '✅ Volume: ' + volume);
                  }
                  
                  if (decimalInputs.length > 1 && sl) {
                    await sleep(200);
                    setInputValueNative(decimalInputs[1], sl.toString());
                    sendMessage('step_update', '✅ Stop Loss: ' + sl);
                  } else if (decimalInputs.length > 1) {
                    sendMessage('step_update', 'No Stop Loss on signal — leaving blank');
                  }
                  
                  if (decimalInputs.length > 2 && tp) {
                    await sleep(200);
                    setInputValueNative(decimalInputs[2], tp.toString());
                    sendMessage('step_update', '✅ Take Profit: ' + tp);
                  } else if (decimalInputs.length > 2) {
                    sendMessage('step_update', 'No Take Profit on signal — leaving blank');
                  }
                  
                  if (orderComment) {
                    await sleep(200);
                    const commentInput = document.querySelector('input.svelte-mtorg2') ||
                                        Array.from(document.querySelectorAll('input[autocomplete="off"]')).find(inp => 
                                          inp.classList.contains('svelte-mtorg2')
                                        );
                    
                    if (commentInput) {
                      setInputValueNative(commentInput, orderComment);
                      sendMessage('step_update', '✅ Comment: ' + orderComment);
                    }
                  }
                  
                  await sleep(500);
                  
                  const buyButton = document.querySelector('button.trade-button.svelte-ailjot:not(.red)') ||
                                   Array.from(document.querySelectorAll('button.trade-button.svelte-ailjot')).find(btn => 
                                     (btn.innerText || btn.textContent || '').trim().includes('Buy')
                                   );
                  
                  const sellButton = document.querySelector('button.trade-button.svelte-ailjot.red') ||
                                    Array.from(document.querySelectorAll('button.trade-button.svelte-ailjot.red')).find(btn => 
                                      (btn.innerText || btn.textContent || '').trim().includes('Sell')
                                    );
                  
                  const actionLower = (action || '').toLowerCase();
                  
                  if (actionLower === 'buy' && buyButton) {
                    buyButton.click();
                    sendMessage('step_update', '🚀 Trade ' + tradeNumber + '/' + totalTrades + ': BUY order executed');
                  } else if (actionLower === 'sell' && sellButton) {
                    sellButton.click();
                    sendMessage('step_update', '🚀 Trade ' + tradeNumber + '/' + totalTrades + ': SELL order executed');
                  } else {
                    sendMessage('error', '❌ Trade button not found for action: ' + action);
                    return false;
                  }
                  
                  await sleep(1500);
                  
                  return true;
                } catch(e) {
                  sendMessage('error', '❌ Error filling order form: ' + e.message);
                  return false;
                }
              };

              // Execute multiple trades based on configured number - EXACTLY as configured
              const executeMultipleTrades = async () => {
                const numberOfTrades = parseInt('${numberOfTradesValue}', 10);
                if (isNaN(numberOfTrades) || numberOfTrades < 1) {
                  sendMessage('error', 'Invalid number of trades configured: ' + numberOfTrades);
                  return;
                }

                sendMessage('step_update', '📊 Configured to execute EXACTLY ' + numberOfTrades + ' trade(s)');
                console.log('🎯 STRICT EXECUTION: Will execute exactly ' + numberOfTrades + ' trades, no more, no less');
                
                var _eqEx0 = scrapeTerminalAccountStats();
                if (_eqEx0.equity || _eqEx0.balance) {
                  sendMessage('equity_snapshot', 'Account updated', { equity: _eqEx0.equity, balance: _eqEx0.balance });
                }
                
                let successfulTrades = 0;
                let failedTrades = 0;
                
                for (let i = 0; i < numberOfTrades; i++) {
                  const tradeNumber = i + 1;
                  sendMessage('step_update', '🔄 Executing trade ' + tradeNumber + ' of ' + numberOfTrades + '...');
                  console.log('▶️ Starting trade ' + tradeNumber + '/' + numberOfTrades);
                  
                  try {
                    var _eqPre = scrapeTerminalAccountStats();
                    if (_eqPre.equity || _eqPre.balance) {
                      sendMessage('equity_snapshot', 'Account updated', { equity: _eqPre.equity, balance: _eqPre.balance });
                    }
                    const tradeSuccess = await openOrderDialogAndExecuteTrade(tradeNumber, numberOfTrades);
                    let ok = tradeSuccess;
                    if (!ok) {
                      sendMessage('step_update', 'Retrying trade ' + tradeNumber + '/' + numberOfTrades + '...');
                      await sleep(1400);
                      await acceptDisclaimersAndConfirmDeep();
                      await dismissLoginOverlay();
                      ok = await openOrderDialogAndExecuteTrade(tradeNumber, numberOfTrades);
                    }
                    
                    if (ok) {
                      successfulTrades++;
                      sendMessage('step_update', '✅ Trade ' + tradeNumber + '/' + numberOfTrades + ' completed successfully');
                      console.log('✅ Trade ' + tradeNumber + ' completed successfully');
                      await sleep(1500);
                      var snapAfter = scrapeTerminalAccountStats();
                      if (snapAfter.equity || snapAfter.balance) {
                        sendMessage('equity_snapshot', 'Account updated', { equity: snapAfter.equity, balance: snapAfter.balance });
                      }
                    } else {
                      failedTrades++;
                      sendMessage('step_update', '❌ Trade ' + tradeNumber + '/' + numberOfTrades + ' failed');
                      console.log('❌ Trade ' + tradeNumber + ' failed');
                    }
                    
                    if (i < numberOfTrades - 1) {
                      sendMessage('step_update', '⏳ Preparing for next trade...');
                      await sleep(1500);
                    }
                  } catch (error) {
                    failedTrades++;
                    sendMessage('error', 'Error executing trade ' + tradeNumber + ': ' + error.message);
                    console.error('❌ Error executing trade ' + tradeNumber + ':', error);
                  }
                }
                
                const summaryMessage = '✅ Completed: ' + successfulTrades + '/' + numberOfTrades + ' trades executed';
                sendMessage('step_update', summaryMessage);
                console.log('📊 EXECUTION COMPLETE: ' + successfulTrades + ' successful, ' + failedTrades + ' failed out of ' + numberOfTrades + ' total');
                
                await sleep(2000);
                var statsFinal = scrapeTerminalAccountStats();
                if (successfulTrades === numberOfTrades) {
                  sendMessage('all_trades_completed', 'All ' + numberOfTrades + ' trades completed successfully', { equity: statsFinal.equity, balance: statsFinal.balance });
                } else {
                  sendMessage('all_trades_completed', successfulTrades + '/' + numberOfTrades + ' trades completed', { equity: statsFinal.equity, balance: statsFinal.balance });
                }
                
                await sleep(1000);
                window.__eaActiveTradePayload = null;
              };

              window.__eaRunExecuteMultipleTrades = executeMultipleTrades;
              try {
                window.parent.postMessage(JSON.stringify({ type: 'ea_mt5_automation_ready' }), '*');
              } catch (eReady) {}
              
              var __eaStartAuthOnce = (function() {
                var done = false;
                return function() {
                  if (done) return;
                  done = true;
                  void authenticateMT5();
                };
              })();
              if (document.readyState === 'complete' || document.readyState === 'interactive') {
                __eaStartAuthOnce();
              } else {
                document.addEventListener('DOMContentLoaded', __eaStartAuthOnce);
                setTimeout(__eaStartAuthOnce, 2500);
              }
            })();
          `;

          // Inject script before closing body tag
          const patchedTradingScript = patchMt5InlineAuthScript(tradingScript);
          if (html.includes('</body>')) {
            html = html.replace('</body>', `<script>${patchedTradingScript}</script></body>`);
            console.log('✅ MT5 trading script injected before </body> tag');
          } else if (html.includes('</html>')) {
            html = html.replace('</html>', `<script>${patchedTradingScript}</script></html>`);
            console.log('✅ MT5 trading script injected before </html> tag');
          } else {
            html += `<script>${patchedTradingScript}</script>`;
            console.log('✅ MT5 trading script appended to HTML');
          }

          // Verify script was injected
          if (html.includes('authenticateMT5')) {
            console.log('✅ Trading script injection verified - authenticateMT5 function found in HTML');
          } else {
            console.error('❌ Trading script injection failed - authenticateMT5 function not found in HTML');
          }

          // Return modified HTML with CORS headers
          return new Response(html, {
            headers: {
              'Content-Type': 'text/html; charset=utf-8',
              'Content-Language': 'en',
              'Access-Control-Allow-Origin': '*',
              'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
              'Access-Control-Allow-Headers': 'Content-Type',
              'X-Frame-Options': 'SAMEORIGIN',
            },
          });
        } catch (error) {
          console.error('❌ MT5 trading proxy error:', error);
          return new Response(`Proxy error: ${error}`, { status: 500 });
        }
      }
      return new Response('Method Not Allowed', { status: 405 });
    }

    // Get new signals for EA since a specific time
    if (pathname === '/api/get-new-signals') {
      if (request.method === 'GET') {
        const eaId = url.searchParams.get('eaId');
        const since = url.searchParams.get('since');

        if (!eaId) {
          return new Response(JSON.stringify({ error: 'EA ID required' }), {
            status: 400,
            headers: { 'Content-Type': 'application/json' },
          });
        }

        let conn = null;
        try {
          const pool = getPool();
          conn = await pool.getConnection();

          let query: string;
          let params: any[];

          if (since) {
            // Convert ISO timestamp to MySQL-compatible format
            // Remove 'Z' suffix and replace 'T' with space for MySQL DATETIME compatibility
            let mysqlTimestamp = since;
            try {
              // Parse the ISO timestamp and convert to MySQL format
              const date = new Date(since);
              if (!isNaN(date.getTime())) {
                // Format: YYYY-MM-DD HH:MM:SS
                mysqlTimestamp = date.toISOString().slice(0, 19).replace('T', ' ');
              }
            } catch (parseError) {
              console.warn('⚠️ Could not parse timestamp, using as-is:', since);
            }

            console.log(`📊 Fetching signals for EA ${eaId} since ${mysqlTimestamp} (original: ${since})`);

            // Get signals since a specific time
            // Query only existing columns: id, ea, asset, latestupdate, action, price, tp, sl, time
            query = `
              SELECT id, ea, asset, latestupdate, action, price, tp, sl, time, lot
              FROM \`signals\` 
              WHERE ea = ? AND latestupdate > ?
              ORDER BY latestupdate DESC
              LIMIT 50
            `;
            params = [eaId, mysqlTimestamp];
          } else {
            // Get recent signals for EA (last 50)
            query = `
              SELECT id, ea, asset, latestupdate, action, price, tp, sl, time, lot
              FROM \`signals\` 
              WHERE ea = ?
              ORDER BY latestupdate DESC
              LIMIT 50
            `;
            params = [eaId];
          }

          const [rows] = await conn.execute(query, params);

          const result = rows as any[];
          console.log(`✅ Found ${result.length} new signals for EA ${eaId} since ${since || 'beginning'}`);

          return new Response(JSON.stringify({ signals: result }), {
            headers: {
              'Content-Type': 'application/json',
              'Access-Control-Allow-Origin': '*',
            },
          });
        } catch (error: any) {
          console.error('❌ Database error in get-new-signals:', error?.message || error);
          console.error('❌ Error details:', { eaId, since, stack: error?.stack });
          return new Response(JSON.stringify({
            error: 'Database error',
            message: error?.message || 'Unknown error',
            details: process.env.NODE_ENV === 'development' ? error?.stack : undefined
          }), {
            status: 500,
            headers: {
              'Content-Type': 'application/json',
              'Access-Control-Allow-Origin': '*',
            },
          });
        } finally {
          if (conn) {
            try {
              conn.release();
            } catch (releaseError) {
              console.error('❌ Failed to release connection:', releaseError);
            }
          }
        }
      }
      return new Response('Method Not Allowed', { status: 405 });
    }

    // Web Push for iOS PWA - VAPID public key
    if (pathname === '/api/vapid-public-key') {
      if (request.method === 'GET') {
        return new Response(JSON.stringify({ publicKey: getVapidPublicKey() }), {
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        });
      }
      return new Response('Method Not Allowed', { status: 405 });
    }

    // Web Push - register subscription (when bot activated on iOS PWA)
    if (pathname === '/api/register-push-subscription') {
      if (request.method === 'POST') {
        try {
          const body = await request.json() as {
            subscription: { endpoint: string; keys: { p256dh: string; auth: string } };
            licenseKey: string;
            eaId: string;
          };
          if (!body?.subscription?.endpoint || !body?.licenseKey || !body?.eaId) {
            return new Response(JSON.stringify({ error: 'subscription, licenseKey, eaId required' }), {
              status: 400,
              headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
            });
          }
          const sub = {
            endpoint: body.subscription.endpoint,
            keys: body.subscription.keys,
            licenseKey: body.licenseKey,
            eaId: String(body.eaId),
          };
          addSubscription(sub);
          // Persist to DB so subscriptions survive server restarts (critical for Render cold starts)
          try {
            const p = getPool();
            await p.execute(
              'INSERT INTO push_subscriptions (endpoint, p256dh, auth, license_key, ea_id) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE p256dh=VALUES(p256dh), auth=VALUES(auth), license_key=VALUES(license_key), ea_id=VALUES(ea_id)',
              [sub.endpoint, sub.keys.p256dh, sub.keys.auth, sub.licenseKey, sub.eaId]
            );
          } catch (dbErr) {
            console.warn('[Push] Failed to persist subscription:', dbErr);
          }
          // Immediate poll so new subscriber gets any recent signals right away
          pollWebPushSignalsNow(getPool).catch(() => { });
          return new Response(JSON.stringify({ ok: true }), {
            headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          });
        } catch (e) {
          return new Response(JSON.stringify({ error: 'Invalid JSON' }), {
            status: 400,
            headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          });
        }
      }
      return new Response('Method Not Allowed', { status: 405 });
    }

    // Web Push - unregister subscription (when bot deactivated)
    if (pathname === '/api/unregister-push-subscription') {
      if (request.method === 'POST') {
        try {
          const body = await request.json() as { endpoint?: string };
          if (body?.endpoint) {
            removeSubscription(body.endpoint);
            try {
              await getPool().execute('DELETE FROM push_subscriptions WHERE endpoint = ?', [body.endpoint]);
            } catch (dbErr) {
              console.warn('[Push] Failed to delete subscription from DB:', dbErr);
            }
          }
          return new Response(JSON.stringify({ ok: true }), {
            headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          });
        } catch {
          return new Response(JSON.stringify({ ok: true }), {
            headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          });
        }
      }
      return new Response('Method Not Allowed', { status: 405 });
    }

    return new Response('Not Found', { status: 404 });
  } catch (error) {
    console.error('API handler error:', error);
    return new Response('Internal Server Error', { status: 500 });
  }
}

const server = Bun.serve({
  port: PORT,
  async fetch(request: Request) {
    const url = new URL(request.url);

    // Health check
    if (url.pathname === '/health' || url.pathname === '/_health' || url.pathname === '/status') {
      return new Response(JSON.stringify({ ok: true }), {
        headers: { 'Content-Type': 'application/json' },
      });
    }

    // Keep-alive: resets Render inactivity timer so server stays awake for Web Push
    if (url.pathname === '/api/keep-alive') {
      return new Response(JSON.stringify({ ok: true }), {
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
      });
    }

    // Handle terminal assets (CSS, JS, etc.) - proxy to the original MT5 terminal
    if (url.pathname.startsWith('/terminal/')) {
      try {
        const assetPath = url.pathname.replace('/terminal/', '');

        // Determine broker URL from referer header, query param, or default
        const referer = request.headers.get('referer') || '';
        const brokerParam = url.searchParams.get('broker');
        let brokerBaseUrl = DEFAULT_MT5_BROKER_BASE_URL;
        const brokerUrlMap = MT5_BROKER_BASE_URL_MAP;

        // Try to detect broker from query param first
        if (brokerParam) {
          const normalized = normalizeMt5ServerKey(brokerParam);
          if (normalized) {
            brokerBaseUrl = resolveMt5BrokerBaseUrl(normalized);
          } else {
            const brokerKey = brokerParam.toLowerCase().replace(/\s+/g, '-');
            if (brokerUrlMap[brokerKey]) {
              brokerBaseUrl = brokerUrlMap[brokerKey];
            } else {
              // Try partial match
              for (const [key, mappedUrl] of Object.entries(brokerUrlMap)) {
                if (brokerKey.includes(key.replace(/-/g, '')) || key.includes(brokerKey.replace(/-/g, ''))) {
                  brokerBaseUrl = mappedUrl;
                  break;
                }
              }
            }
          }
        }

        // Fallback: Check referer for broker domain when still on default
        if (brokerBaseUrl === DEFAULT_MT5_BROKER_BASE_URL) {
          for (const brokerUrl of Object.values(brokerUrlMap)) {
            const domain = brokerUrl.replace('https://', '').replace('http://', '').split('/')[0];
            if (domain && referer.includes(domain) && brokerUrl !== DEFAULT_MT5_BROKER_BASE_URL) {
              brokerBaseUrl = brokerUrl;
              break;
            }
          }
        }

        // Brokers that serve terminal from root (no /terminal path)
        const rootTerminalBrokers = ['mt5.profinwealth.com', 'profinwealth'];
        const isRootTerminal = rootTerminalBrokers.some(b => brokerBaseUrl.includes(b));

        // Try to fetch from broker's terminal directory (or root for root-terminal brokers)
        let targetUrl = isRootTerminal
          ? `${brokerBaseUrl.replace(/\/$/, '')}/${assetPath}`
          : `${brokerBaseUrl}/terminal/${assetPath}`;

        let response = await fetchMt5Remote(targetUrl, {
          headers: {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Referer': brokerBaseUrl,
            'Accept': request.headers.get('accept') || '*/*',
          },
        });

        // Fallback: for root-terminal brokers, try /terminal/ if root fetch fails
        if (!response.ok && isRootTerminal) {
          const fallbackUrl = `${brokerBaseUrl}/terminal/${assetPath}`;
          response = await fetchMt5Remote(fallbackUrl, {
            headers: {
              'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
              'Accept-Language': MT5_ENGLISH_ACCEPT_LANGUAGE,
              'Referer': brokerBaseUrl,
              'Accept': request.headers.get('accept') || '*/*',
            },
          });
          if (response.ok) targetUrl = fallbackUrl;
        }
        // Fallback: for standard brokers, try root if /terminal/ returns 404
        if (!response.ok && !isRootTerminal) {
          const fallbackUrl = `${brokerBaseUrl.replace(/\/$/, '')}/${assetPath}`;
          const fallbackResponse = await fetchMt5Remote(fallbackUrl, {
            headers: {
              'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
              'Accept-Language': MT5_ENGLISH_ACCEPT_LANGUAGE,
              'Referer': brokerBaseUrl,
              'Accept': request.headers.get('accept') || '*/*',
            },
          });
          if (fallbackResponse.ok) {
            response = fallbackResponse;
            targetUrl = fallbackUrl;
          }
        }

        if (response.ok) {
          const content = await response.arrayBuffer();

          // Always infer content type from file extension (more reliable than server response)
          const ext = assetPath.split('.').pop()?.toLowerCase();
          let contentType: string;

          if (ext === 'js' || assetPath.includes('.js')) {
            contentType = 'application/javascript; charset=utf-8';
          } else if (ext === 'css' || assetPath.includes('.css')) {
            contentType = 'text/css; charset=utf-8';
          } else if (ext === 'json') {
            contentType = 'application/json; charset=utf-8';
          } else if (ext === 'png') {
            contentType = 'image/png';
          } else if (ext === 'jpg' || ext === 'jpeg') {
            contentType = 'image/jpeg';
          } else if (ext === 'svg') {
            contentType = 'image/svg+xml';
          } else if (ext === 'woff' || ext === 'woff2') {
            contentType = `font/${ext}`;
          } else {
            // Fallback to response content type or default
            contentType = response.headers.get('content-type') || 'application/octet-stream';
            // But never allow text/html for assets
            if (contentType.includes('text/html')) {
              contentType = 'application/octet-stream';
            }
          }

          // Check if we got HTML instead of the actual asset (some brokers return error pages)
          const contentStr = new TextDecoder().decode(content.slice(0, 500));
          const isHtml = contentStr.trim().startsWith('<!') ||
            contentStr.includes('<html') ||
            contentStr.includes('<!DOCTYPE') ||
            contentStr.includes('<sprite>') ||
            response.headers.get('content-type')?.includes('text/html');

          // If we got HTML but expected an asset, try fetching directly from broker (bypass proxy)
          if (isHtml && (ext === 'js' || ext === 'css')) {
            console.error(`⚠️ Got HTML instead of ${ext.toUpperCase()} for asset: ${targetUrl}`);
            console.error(`Broker: ${brokerParam || 'unknown'}, BrokerBaseUrl: ${brokerBaseUrl}`);
            console.error(`Response preview: ${contentStr.substring(0, 300)}`);
            console.error(`Attempting direct fetch from broker...`);

            // Return a redirect or fetch directly - but for now, return the broker URL directly
            // The browser will fetch it directly, bypassing CORS issues if possible
            // Actually, better to return 302 redirect to original broker URL
            return new Response(null, {
              status: 302,
              headers: {
                'Location': targetUrl,
                'Access-Control-Allow-Origin': '*',
              },
            });
          }

          return new Response(content, {
            headers: {
              'Content-Type': contentType,
              'Cache-Control': 'public, max-age=3600',
              'Access-Control-Allow-Origin': '*',
              'Access-Control-Allow-Methods': 'GET, OPTIONS',
              'Access-Control-Allow-Headers': 'Content-Type',
            },
          });
        } else {
          console.error(`Failed to fetch asset: ${targetUrl}, status: ${response.status}`);
          // Return redirect to original URL so browser can try direct fetch
          return new Response(null, {
            status: 302,
            headers: {
              'Location': targetUrl,
              'Access-Control-Allow-Origin': '*',
            },
          });
        }
      } catch (error) {
        console.error('Terminal asset proxy error:', error);
      }

      return new Response('Asset not found', { status: 404 });
    }

    // Handle WebSocket upgrade requests - proxy to broker's WebSocket server
    if (url.pathname === '/terminal/ws' && request.headers.get('upgrade') === 'websocket') {
      // Extract broker info from referer or query params
      const referer = request.headers.get('referer') || '';
      let brokerWsUrl = `wss://${DEFAULT_MT5_BROKER_BASE_URL.replace(/^https?:\/\//, '')}/terminal/ws`;

      if (referer.includes('rcgmarkets.com')) {
        brokerWsUrl = 'wss://webtrader.rcgmarkets.com/terminal/ws';
      } else if (referer.includes('accumarkets.co.za')) {
        brokerWsUrl = 'wss://webterminal.accumarkets.co.za/terminal/ws';
      } else if (referer.includes('razormarkets.co.za')) {
        brokerWsUrl = 'wss://webtrader.razormarkets.co.za/terminal/ws';
      }

      // For WebSocket proxying, we'd need to upgrade the connection
      // Since Bun doesn't easily support WebSocket proxying in this context,
      // we'll return an error suggesting direct connection
      return new Response('WebSocket proxying not supported. Please connect directly to broker.', {
        status: 426, // Upgrade Required
        headers: {
          'Upgrade': 'websocket',
          'Connection': 'Upgrade',
        },
      });
    }

    // API routes — same-origin on Render (proxied to VPS); VPS uses local MySQL directly
    if (url.pathname.startsWith('/api/')) {
      if (request.method === 'OPTIONS') {
        return new Response(null, { status: 204, headers: corsHeaders(request) });
      }
      // Gemini + MT5 terminal proxies must run here (same host as WebView). DB APIs relay to VPS.
      if (API_UPSTREAM && !shouldHandleApiLocally(url.pathname)) {
        return withCors(request, await proxyApiToUpstream(request));
      }
      return withCors(request, await handleApi(request));
    }

    // Static files
    return serveStatic(request);
  },
});

// Initialize push: create table, load subscriptions from DB, set cleanup callback
async function initPushSubscriptions() {
  if (!isPushConfigured()) return;
  try {
    const p = getPool();
    await p.execute(`
      CREATE TABLE IF NOT EXISTS push_subscriptions (
        endpoint VARCHAR(512) PRIMARY KEY,
        p256dh TEXT NOT NULL,
        auth TEXT NOT NULL,
        license_key VARCHAR(255) NOT NULL,
        ea_id VARCHAR(64) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
    `);
    const [rows] = await p.execute(
      'SELECT endpoint, p256dh, auth, license_key, ea_id FROM push_subscriptions'
    ) as [any[], any];
    const subs = (rows || []).map((r: any) => ({
      endpoint: r.endpoint,
      keys: { p256dh: r.p256dh, auth: r.auth },
      licenseKey: r.license_key,
      eaId: String(r.ea_id),
    }));
    loadSubscriptions(subs);
    console.log(`[Push] Loaded ${subs.length} subscriptions from DB`);
    setOnSubscriptionRemoved(async (endpoint) => {
      try {
        await getPool().execute('DELETE FROM push_subscriptions WHERE endpoint = ?', [endpoint]);
      } catch (e) {
        console.warn('[Push] Failed to delete expired subscription from DB:', e);
      }
    });
  } catch (e) {
    console.warn('[Push] Init failed (DB may not have push_subscriptions):', e);
  }
}

if (!API_UPSTREAM) {
  initPushSubscriptions().then(() => {
    startWebPushSignalsPolling(getPool);
    if (isPushConfigured()) {
      console.log('✅ Web Push enabled for iOS PWA background notifications');
    }
  });
} else {
  console.log(`Web Push polling runs on VPS (${API_UPSTREAM}); Render relays /api/* except local AI + MT5 terminal proxies`);
}

console.log(`Server running on http://localhost:${server.port}`);
if (API_UPSTREAM) {
  console.log(
    `API upstream → ${API_UPSTREAM}/api/* (local: analyze-chart, mt5-trade-sizing, mt5-proxy, mt5-trading-proxy, terminal-proxy, brand-asset, send-email)`
  );
}


