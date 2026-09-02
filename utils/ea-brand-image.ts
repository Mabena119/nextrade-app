import type { ImageSourcePropType } from 'react-native';
import { Platform } from 'react-native';
import { isNextradeSiteHost, NEXTRADE_SITE_URL } from '@/config/nextrade-site';
import { resolveApiBaseUrl } from '@/utils/api-base-url';

/** Default NexTrade mark when no mentor logo is available or remote load fails. */
export const EA_BRAND_HERO_LOCAL = require('@/assets/images/nextrade-logo.png');

const PLACEHOLDER_LOGO_BASENAMES = new Set([
  'default.png',
  'default.jpg',
  'default.jpeg',
  'placeholder.png',
  'sitelogo.png',
  'none',
  'null',
]);

/** True when mentor/admin has no custom logo (empty DB value or stock placeholder filename). */
export function isPlaceholderEaOwnerLogo(rawInput: string | null | undefined): boolean {
  if (rawInput == null) return true;
  if (typeof rawInput !== 'string') return true;
  const raw = rawInput.trim();
  if (!raw) return true;

  const withoutQuery = raw.split('?')[0]?.split('#')[0] ?? raw;
  const basename = withoutQuery.split('/').pop()?.trim().toLowerCase() ?? '';
  if (!basename) return true;
  if (PLACEHOLDER_LOGO_BASENAMES.has(basename)) return true;

  return false;
}

/**
 * Resolve `owner.logo` to a CDN URL, or `null` when unset / placeholder.
 * Callers should fall back to {@link EA_BRAND_HERO_LOCAL}.
 */
export function resolveEaOwnerLogoUrl(rawInput: string | null | undefined): string | null {
  if (isPlaceholderEaOwnerLogo(rawInput)) return null;
  const raw = String(rawInput).trim();
  return /^https?:\/\//i.test(raw)
    ? normalizeEaBrandLogoHttpUrl(raw)
    : normalizeEaBrandLogoHttpUrl(raw.replace(/^\/+/, ''));
}

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
/** Strip mentor DB paths (`../uploads/foo.png`, `admin/uploads/foo.png`) down to the filename. */
function mentorLogoUploadBasename(rawInput: string): string {
  const withoutQuery = rawInput.split('?')[0]?.split('#')[0]?.trim() ?? '';
  if (!withoutQuery) return '';

  if (/^https?:\/\//i.test(withoutQuery)) {
    try {
      const u = new URL(withoutQuery);
      const lowerPath = u.pathname.toLowerCase();
      const uploadsIdx = lowerPath.lastIndexOf('/uploads/');
      if (uploadsIdx >= 0) {
        return u.pathname.slice(uploadsIdx + '/uploads/'.length).split('/').filter(Boolean).pop() ?? '';
      }
      return u.pathname.split('/').filter(Boolean).pop() ?? '';
    } catch {
      return withoutQuery.split('/').filter(Boolean).pop() ?? withoutQuery;
    }
  }

  let rel = withoutQuery.replace(/^\/+/, '');
  const lower = rel.toLowerCase();
  const uploadsIdx = lower.lastIndexOf('uploads/');
  if (uploadsIdx >= 0) {
    rel = rel.slice(uploadsIdx + 'uploads/'.length);
  } else if (rel.includes('/')) {
    rel = rel.split('/').filter(Boolean).pop() ?? rel;
  }
  return rel.trim();
}

export function normalizeEaBrandLogoHttpUrl(rawInput: string | null | undefined): string | null {
  if (!rawInput || typeof rawInput !== 'string') return null;
  const raw = rawInput.trim();
  if (!raw || isPlaceholderEaOwnerLogo(raw)) return null;

  const cacheBust = (() => {
    const q = raw.indexOf('?');
    return q >= 0 ? raw.slice(q) : '';
  })();

  if (/^https?:\/\//i.test(raw)) {
    try {
      const u = new URL(raw.split('?')[0] ?? raw);
      const parts = u.pathname.split('/').filter(Boolean).map(encodePathSegment);
      u.pathname = `/${parts.join('/')}`;
      return u.toString() + cacheBust;
    } catch {
      return raw;
    }
  }

  const basename = mentorLogoUploadBasename(raw);
  if (!basename || isPlaceholderEaOwnerLogo(basename)) return null;
  const parts = basename.split('/').filter(Boolean).map(encodePathSegment);
  if (parts.length === 0) return null;
  return `${NEXTRADE_SITE_URL}/admin/uploads/${parts.join('/')}${cacheBust}`;
}

/**
 * Proxy mentor uploads through the app API (Render) so native + web can load images
 * without hotlink/CORS issues on nextradeai.io/admin/uploads.
 */
export function toBrandAssetProxyUrl(imageUrl: string | null | undefined): string | null {
  const normalized = normalizeEaBrandLogoHttpUrl(imageUrl);
  if (!normalized) return null;
  try {
    const u = new URL(normalized);
    if (!isNextradeSiteHost(u.hostname) || !u.pathname.startsWith('/admin/uploads/')) {
      return normalized;
    }
    const apiBase = resolveApiBaseUrl();
    const proxy = `${apiBase}/api/brand-asset?url=${encodeURIComponent(normalized)}`;
    if (
      Platform.OS === 'web' &&
      typeof window !== 'undefined' &&
      window.location?.origin &&
      !window.location.origin.includes('localhost')
    ) {
      return `/api/brand-asset?url=${encodeURIComponent(normalized)}`;
    }
    return proxy;
  } catch {
    return normalized;
  }
}

/** @deprecated use {@link toBrandAssetProxyUrl} */
export function toSameOriginBrandFetchUrl(imageUrl: string | null | undefined): string | null {
  return toBrandAssetProxyUrl(imageUrl);
}

/** Raw `owner.logo` from licence auth → display URL, or null to use app fallback asset. */
export function resolveEaOwnerProfileLogoUrl(rawLogo: string | null | undefined): string | null {
  return toBrandAssetProxyUrl(rawLogo);
}

/** Build `ImageSourcePropType` for the EA brand splash from license `owner.logo` or fallback asset. */
export function resolveEABrandImageSource(logo: string | null | undefined): ImageSourcePropType {
  const normalized = resolveEaOwnerLogoUrl(logo);
  if (!normalized) return EA_BRAND_HERO_LOCAL;
  return { uri: normalized };
}
