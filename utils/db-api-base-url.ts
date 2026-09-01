import { resolveApiBaseUrl } from '@/utils/api-base-url';

/**
 * DB-backed API routes (members, licences, signals).
 * Always use the app host (Render same-origin on web) — the server proxies to NexTrade.
 * Never call nextradeai.io from the browser (DNS/CORS failures on many networks).
 */
export function dbApiUrl(path: string): string {
  const base = resolveApiBaseUrl().replace(/\/$/, '');
  const p = path.startsWith('/') ? path : `/${path}`;
  return `${base}${p}`;
}
