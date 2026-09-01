import type { LicenseAuthResponse } from '@/services/api';

export type LicenseVerdict = 'valid' | 'expired' | 'invalid' | 'used' | 'unavailable';

/** True when auth-license could not be reached or the server returned a non-definitive answer. */
export function isLicenseAuthUnavailable(res: LicenseAuthResponse): boolean {
  return Boolean(res.degraded);
}

/** True when licence status or expiry date indicates the key is no longer active. */
export function isLicenseExpired(status: string | undefined | null, expires: string | undefined | null): boolean {
  const st = String(status ?? '').trim().toLowerCase();
  if (st === 'expired') return true;
  const exp = String(expires ?? '').trim();
  if (exp && !Number.isNaN(Date.parse(exp))) {
    return Date.now() > Date.parse(exp);
  }
  return false;
}

/** Same rules as the license entry screen: auth-license accept/error/used + expiry. */
export function evaluateLicenseAuthResponse(res: LicenseAuthResponse): LicenseVerdict {
  if (isLicenseAuthUnavailable(res)) return 'unavailable';
  if (res.message === 'error') return 'invalid';
  if (res.message === 'used') return 'used';
  if (res.message === 'accept' && res.data) {
    if (isLicenseExpired(res.data.status, res.data.expires)) return 'expired';
    return 'valid';
  }
  return 'invalid';
}
