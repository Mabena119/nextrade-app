import { NEXTRADE_SITE_URL } from '@/config/nextrade-site';

/** NexTrade cPanel PHP API — members, licences, signals (not Render-proxied). */
export function getDbApiBaseUrl(): string {
  return NEXTRADE_SITE_URL.replace(/\/$/, '');
}

export function dbApiUrl(path: string): string {
  const p = path.startsWith('/') ? path : `/${path}`;
  return `${getDbApiBaseUrl()}${p}`;
}
