import { Platform } from 'react-native';
import * as FileSystem from 'expo-file-system';
import { Image } from 'expo-image';

import { NEXTRADE_SITE_URL } from '@/config/nextrade-site';
import {
  EA_BRAND_CDN_HEADERS,
  resolveEaOwnerProfileLogoUrl,
} from '@/utils/ea-brand-image';
import { deriveEaBrandImageStemFromUrl } from '@/utils/ea-logo-video-url';

const CACHE_SUBDIR = 'ea-brand-logos/';
/** Skip HTML/error stubs and tiny corrupt downloads. */
const MIN_LOGO_BYTES = 64;

const memoryByStem = new Map<string, string>();
const inflightByStem = new Map<string, Promise<string>>();

const CDN_DOWNLOAD_HEADER_ATTEMPTS: Record<string, string>[] = [
  EA_BRAND_CDN_HEADERS,
  {
    Referer: `${NEXTRADE_SITE_URL}/`,
    Accept: 'image/*,*/*',
    'User-Agent':
      'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  },
  {
    Accept: 'image/*,*/*',
    'User-Agent':
      'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
  },
  {},
];

function storageBaseUri(): string | null {
  return FileSystem.cacheDirectory ?? FileSystem.documentDirectory ?? null;
}

function sanitizeStem(stem: string): string | null {
  const s = stem.trim();
  if (!s || s.includes('/') || s.includes('..')) return null;
  if (!/^[-a-zA-Z0-9._]+$/.test(s)) return null;
  return s;
}

function extensionFromRemoteUrl(remoteUri: string): string {
  const path = (remoteUri.split('?')[0] ?? remoteUri).toLowerCase();
  const m = path.match(/\.(png|jpe?g|webp|gif)$/i);
  if (!m?.[1]) return 'png';
  const ext = m[1].toLowerCase();
  return ext === 'jpeg' ? 'jpg' : ext;
}

/** Stem for disk/memory cache from remote URL, brand-asset proxy, or raw owner.logo. */
export function deriveEaBrandLogoCacheStem(
  rawLogo: string | null | undefined,
  remoteUrl?: string | null
): string | null {
  const remote = (remoteUrl ?? resolveEaOwnerProfileLogoUrl(rawLogo) ?? '').trim();
  if (remote) {
    const fromHttps = deriveEaBrandImageStemFromUrl(remote);
    if (fromHttps) return sanitizeStem(fromHttps);

    try {
      const u = new URL(remote, 'https://nextrade.local');
      const pathParam = u.searchParams.get('path');
      if (pathParam) {
        const base = pathParam.split('/').filter(Boolean).pop() ?? '';
        const stem = base.replace(/\.(png|jpe?g|webp|gif|bmp|tif|tiff)$/i, '');
        const ok = sanitizeStem(stem);
        if (ok) return ok;
      }
    } catch {
      /* ignore */
    }
  }

  const raw = String(rawLogo || '').trim();
  if (!raw) return null;
  const withoutQuery = raw.split('?')[0]?.split('#')[0] ?? raw;
  const base = withoutQuery.split('/').filter(Boolean).pop() ?? '';
  const stem = base.replace(/\.(png|jpe?g|webp|gif|bmp|tif|tiff)$/i, '');
  return sanitizeStem(stem);
}

/** Sync hit — use so the hero never paints empty while awaiting disk. */
export function getCachedEaBrandLogoUriSync(stem: string): string | null {
  const key = sanitizeStem(stem);
  if (!key) return null;
  return memoryByStem.get(key) ?? null;
}

async function downloadRemoteLogoAttempt(remoteUri: string, localUri: string): Promise<number> {
  let lastStatus = -1;
  for (const headers of CDN_DOWNLOAD_HEADER_ATTEMPTS) {
    const dl = await FileSystem.downloadAsync(remoteUri, localUri, {
      headers: Object.keys(headers).length ? headers : undefined,
    });
    lastStatus = dl.status;
    if (dl.status >= 200 && dl.status < 300) {
      return dl.status;
    }
    try {
      await FileSystem.deleteAsync(localUri, { idempotent: true });
    } catch {
      /* ignore */
    }
  }
  return lastStatus;
}

/**
 * Download mentor logo once into app storage; reuse `file://` on later mounts.
 * Web: memory + expo-image prefetch (no FileSystem write).
 */
export async function ensureEaBrandLogoCached(remoteUri: string, imageBasenameStem: string): Promise<string> {
  const stem = sanitizeStem(imageBasenameStem);
  if (!stem) throw new Error('ea-brand-logo: invalid cache stem');

  const cached = memoryByStem.get(stem);
  if (cached) return cached;

  const inflight = inflightByStem.get(stem);
  if (inflight) return inflight;

  const work = (async () => {
    if (Platform.OS === 'web') {
      try {
        await Image.prefetch(remoteUri, 'memory-disk');
      } catch {
        /* still use remote URI */
      }
      memoryByStem.set(stem, remoteUri);
      return remoteUri;
    }

    const base = storageBaseUri();
    if (!base) throw new Error('ea-brand-logo: no writable app directory');

    const dir = `${base}${CACHE_SUBDIR}`;
    const ext = extensionFromRemoteUrl(remoteUri);
    const localUri = `${dir}${stem}.${ext}`;

    await FileSystem.makeDirectoryAsync(dir, { intermediates: true });

    const before = await FileSystem.getInfoAsync(localUri);
    if (
      before.exists &&
      !before.isDirectory &&
      typeof before.size === 'number' &&
      before.size >= MIN_LOGO_BYTES
    ) {
      memoryByStem.set(stem, localUri);
      return localUri;
    }

    const dlStatus = await downloadRemoteLogoAttempt(remoteUri, localUri);
    const ok = dlStatus >= 200 && dlStatus < 300;
    if (!ok) {
      try {
        await FileSystem.deleteAsync(localUri, { idempotent: true });
      } catch {
        /* ignore */
      }
      throw new Error(`ea-brand-logo: HTTP ${dlStatus}`);
    }

    const after = await FileSystem.getInfoAsync(localUri);
    if (!after.exists || (typeof after.size === 'number' && after.size < MIN_LOGO_BYTES)) {
      try {
        await FileSystem.deleteAsync(localUri, { idempotent: true });
      } catch {
        /* ignore */
      }
      throw new Error('ea-brand-logo: file missing or too small after download');
    }

    memoryByStem.set(stem, localUri);
    return localUri;
  })();

  inflightByStem.set(stem, work);
  try {
    return await work;
  } finally {
    inflightByStem.delete(stem);
  }
}

/** Warm cache from raw `owner.logo` (safe to fire-and-forget). */
export async function prefetchEaBrandLogoFromRaw(rawLogo: string | null | undefined): Promise<string | null> {
  const remoteUrl = resolveEaOwnerProfileLogoUrl(rawLogo);
  if (!remoteUrl) return null;
  const stem = deriveEaBrandLogoCacheStem(rawLogo, remoteUrl);
  if (!stem) return null;
  try {
    return await ensureEaBrandLogoCached(remoteUrl, stem);
  } catch {
    return null;
  }
}
