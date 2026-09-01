/**
 * iOS Safari / PWA motion access.
 *
 * Android Chrome: DeviceMotion works with no prompt.
 * iOS 13+: must call DeviceMotionEvent.requestPermission() inside a
 * real user gesture (button tap). Silent page-load / touchstart often fails.
 */

export type MotionPermissionState = 'granted' | 'denied' | 'prompt' | 'unsupported';

const STORAGE_KEY = 'aura_web_motion_granted';

export function isWebRuntime(): boolean {
  return typeof window !== 'undefined';
}

/** True when Safari/iOS requires the motion permission dialog. */
export function needsWebMotionPermission(): boolean {
  if (!isWebRuntime()) return false;
  const DME = (window as any).DeviceMotionEvent;
  return typeof DME?.requestPermission === 'function';
}

export function getStoredMotionGranted(): boolean {
  if (!isWebRuntime()) return false;
  try {
    return sessionStorage.getItem(STORAGE_KEY) === '1';
  } catch {
    return false;
  }
}

function setStoredMotionGranted(granted: boolean) {
  if (!isWebRuntime()) return;
  try {
    if (granted) sessionStorage.setItem(STORAGE_KEY, '1');
    else sessionStorage.removeItem(STORAGE_KEY);
  } catch {
    /* private mode */
  }
}

export function getWebMotionPermissionState(): MotionPermissionState {
  if (!isWebRuntime() || !('DeviceMotionEvent' in window)) return 'unsupported';
  if (!needsWebMotionPermission()) return 'granted';
  if (getStoredMotionGranted()) return 'granted';
  return 'prompt';
}

/**
 * Must be called from a click / press handler (transient user activation).
 * Requests both motion + orientation (iOS often needs both).
 */
export async function requestWebMotionPermission(): Promise<MotionPermissionState> {
  if (!isWebRuntime()) return 'unsupported';
  if (!('DeviceMotionEvent' in window)) return 'unsupported';

  const DME = (window as any).DeviceMotionEvent;
  const DOE = (window as any).DeviceOrientationEvent;

  if (typeof DME?.requestPermission !== 'function') {
    setStoredMotionGranted(true);
    return 'granted';
  }

  try {
    const tasks: Promise<string>[] = [DME.requestPermission()];
    if (typeof DOE?.requestPermission === 'function') {
      tasks.push(DOE.requestPermission());
    }
    const results = await Promise.all(tasks);
    const granted = results.every((r) => r === 'granted');
    setStoredMotionGranted(granted);
    if (granted && typeof window !== 'undefined') {
      window.dispatchEvent(new Event('aura-motion-granted'));
    }
    return granted ? 'granted' : 'denied';
  } catch (e) {
    console.warn('[motion] requestPermission failed', e);
    return 'denied';
  }
}

export type WebMotionHandler = (sample: {
  ax: number | null;
  ay: number | null;
  az: number | null;
  gx: number;
  gy: number;
  gz: number;
}) => void;

/** Subscribe to raw device motion (works after iOS permission is granted). */
export function subscribeWebDeviceMotion(handler: WebMotionHandler): () => void {
  if (!isWebRuntime()) return () => undefined;

  const onMotion = (event: DeviceMotionEvent) => {
    const a = event.acceleration;
    const g = event.accelerationIncludingGravity;
    handler({
      ax: a?.x ?? null,
      ay: a?.y ?? null,
      az: a?.z ?? null,
      gx: g?.x ?? 0,
      gy: g?.y ?? 0,
      gz: g?.z ?? 0,
    });
  };

  window.addEventListener('devicemotion', onMotion);
  return () => window.removeEventListener('devicemotion', onMotion);
}
