import type { ImageSourcePropType } from 'react-native';
import { Platform } from 'react-native';
import { isNextradeSiteHost, NEXTRADE_SITE_URL } from '@/config/nextrade-site';

/** Default EA app icon shown when no connected bot logo is available or remote load fails. */
export const EA_BRAND_HERO_LOCAL = require('@/assets/images/icon.png');

/** Sent with AV / FileSystem CDN access so picky Apache setups accept range requests vs Image. */
export const EA_BRAND_CDN_HEADERS: Record<string, string> = {
  Referer: `${NEXTRADE_SITE_URL}/`,
  Accept: '*/*',
};

function encodePathSegment(seg: string): string {
  try {
    return encodeURIComponent(decodeURIComponent(seg));
  } catch {
    return encodeURIComponent(seg);
  }
}

/**
 * Full HTTPS URL for a logo still under auraai uploads; encodes path segments so hex/dot names load reliably.
 */
export function normalizeEaBrandLogoHttpUrl(rawInput: string | null | undefined): string | null {
  if (!rawInput || typeof rawInput !== 'string') return null;
  const raw = rawInput.trim();
  if (!raw) return null;
  if (/^https?:\/\//i.test(raw)) {
    try {
      const u = new URL(raw);
      const parts = u.pathname.split('/').filter(Boolean).map(encodePathSegment);
      u.pathname = `/${parts.join('/')}`;
      return u.toString();
    } catch {
      return raw;
    }
  }
  const rel = raw.replace(/^\/+/, '');
  const parts = rel.split('/').filter(Boolean).map(encodePathSegment);
  if (parts.length === 0) return null;
  return `${NEXTRADE_SITE_URL}/admin/uploads/${parts.join('/')}`;
}

/**
 * Web/iOS PWA: rewrite VPS upload URLs through same-origin `/api/brand-asset`
 * so `fetch()` for notification icons is not blocked by Apache missing CORS
 * (EA Trade keeps app+API same-host; Aura app is on Render, uploads on VPS).
 */
export function toSameOriginBrandFetchUrl(imageUrl: string | null | undefined): string | null {
  const normalized = normalizeEaBrandLogoHttpUrl(imageUrl);
  if (!normalized) return null;
  if (Platform.OS !== 'web' || typeof window === 'undefined') {
    return normalized;
  }
  try {
    const u = new URL(normalized);
    if (isNextradeSiteHost(u.hostname) && u.pathname.startsWith('/admin/uploads/')) {
      const path = u.pathname.replace(/^\/admin\/uploads\//, '');
      return `/api/brand-asset?path=${encodeURIComponent(path)}`;
    }
  } catch {
    /* fall through */
  }
  return normalized;
}

/** Build `ImageSourcePropType` for the EA brand splash from license `owner.logo` or fallback asset. */
export function resolveEABrandImageSource(logo: string | null | undefined): ImageSourcePropType {
  const raw = (logo ?? '').toString().trim();
  if (!raw) return EA_BRAND_HERO_LOCAL;
  const normalized = /^https?:\/\//i.test(raw)
    ? normalizeEaBrandLogoHttpUrl(raw)
    : normalizeEaBrandLogoHttpUrl(raw.replace(/^\/+/, ''));
  if (!normalized) return EA_BRAND_HERO_LOCAL;
  return { uri: normalized };
}
