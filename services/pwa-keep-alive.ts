/**
 * Keeps the Render server awake so Web Push can deliver background signal notifications.
 * Render free tier spins down after 15 min inactivity - pinging resets the timer.
 */
import Constants from 'expo-constants';

import { resolveApiBaseUrl } from '../utils/api-base-url';

function getApiBase(): string {
  return resolveApiBaseUrl();
}

const KEEP_ALIVE_INTERVAL_MS = 4 * 60 * 1000; // 4 min (Render sleeps at 15 min)

let keepAliveIntervalId: ReturnType<typeof setInterval> | null = null;

/** Ping server to reset inactivity timer. Use sendBeacon when page is unloading/hidden. */
export function pingKeepAlive(useBeacon = false): void {
  const url = `${getApiBase()}/api/keep-alive`;
  if (useBeacon && typeof navigator !== 'undefined' && navigator.sendBeacon) {
    navigator.sendBeacon(url);
    return;
  }
  fetch(url).catch(() => {});
}

/** Start periodic keep-alive pings (call when app is visible and bot active). */
export function startKeepAlive(): void {
  if (keepAliveIntervalId) return;
  pingKeepAlive();
  keepAliveIntervalId = setInterval(() => pingKeepAlive(), KEEP_ALIVE_INTERVAL_MS);
}

/** Stop periodic pings. */
export function stopKeepAlive(): void {
  if (keepAliveIntervalId) {
    clearInterval(keepAliveIntervalId);
    keepAliveIntervalId = null;
  }
}
