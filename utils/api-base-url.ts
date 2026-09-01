import Constants from 'expo-constants';
import { Platform } from 'react-native';

/** Web/PWA API — co-hosted with the static app (Render). */
const DEFAULT_WEB_API = 'https://nextrade-app.onrender.com';

/** Native Android/iOS API — same Render host (proxies auth/DB to upstream). */
const DEFAULT_NATIVE_API = 'https://nextrade-app.onrender.com';

function stripTrailingSlash(url: string): string {
  return url.replace(/\/$/, '');
}

export function getWebApiBaseUrl(): string {
  return stripTrailingSlash(
    process.env.EXPO_PUBLIC_API_BASE_URL ||
      Constants.expoConfig?.extra?.EXPO_PUBLIC_API_BASE_URL ||
      DEFAULT_WEB_API
  );
}

export function getNativeApiBaseUrl(): string {
  return stripTrailingSlash(
    process.env.EXPO_PUBLIC_NATIVE_API_BASE_URL ||
      Constants.expoConfig?.extra?.EXPO_PUBLIC_NATIVE_API_BASE_URL ||
      DEFAULT_NATIVE_API
  );
}

/**
 * Android RCG MT5 HTML proxy host (Render — same /api + /terminal as working web/PWA).
 * Native DB/API stays on VPS; only WebView terminal pages use this host.
 */
export function getAndroidMt5ProxyBaseUrl(): string {
  return stripTrailingSlash(
    process.env.EXPO_PUBLIC_MT5_PROXY_BASE_URL ||
      Constants.expoConfig?.extra?.EXPO_PUBLIC_MT5_PROXY_BASE_URL ||
      DEFAULT_WEB_API
  );
}

/**
 * Resolve API base URL at runtime (EA Trade pattern for web, VPS for native).
 *
 * - Web/PWA: same-origin absolute `window.location.origin` so `/api/*` never hits CORS
 *   (EA Trade uses EXPO_PUBLIC on the same Render host; Aura mirrors that with the live origin).
 * - Expo web dev (:8081): Render absolute API (Metro has no API routes).
 * - Android/iOS native: VPS API at auraai-vps.com (cPanel DB).
 */
export function resolveApiBaseUrl(): string {
  if (Platform.OS !== 'web') {
    return getNativeApiBaseUrl();
  }

  if (typeof window !== 'undefined' && window.location?.origin) {
    try {
      const { hostname, port, origin } = new URL(window.location.origin);
      if (hostname === 'localhost' && port === '8081') {
        return getWebApiBaseUrl();
      }
      return origin;
    } catch {
      // fall through
    }
  }

  return getWebApiBaseUrl();
}

/** @deprecated use getWebApiBaseUrl / getNativeApiBaseUrl */
export function getConfiguredApiBaseUrl(): string {
  return Platform.OS === 'web' ? getWebApiBaseUrl() : getNativeApiBaseUrl();
}
