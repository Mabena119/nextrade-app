import AsyncStorage from '@react-native-async-storage/async-storage';

const STORAGE_KEY = 'licenseDeviceSecrets';

export function licenseKeyFingerprint(key: string): string {
  return key.trim().toUpperCase().replace(/-/g, '');
}

type SecretMap = Record<string, string>;

async function readSecretMap(): Promise<SecretMap> {
  try {
    const raw = await AsyncStorage.getItem(STORAGE_KEY);
    if (!raw) return {};
    const parsed = JSON.parse(raw) as unknown;
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return {};
    const out: SecretMap = {};
    for (const [k, v] of Object.entries(parsed)) {
      if (typeof v === 'string' && v.trim()) {
        out[k] = v.trim();
      }
    }
    return out;
  } catch {
    return {};
  }
}

/** Device-bound secret for a licence key (persists after EA removal on this device). */
export async function getCachedLicenseDeviceSecret(licenseKey: string): Promise<string | null> {
  const fp = licenseKeyFingerprint(licenseKey);
  if (!fp) return null;
  const map = await readSecretMap();
  return map[fp] ?? null;
}

export async function setCachedLicenseDeviceSecret(licenseKey: string, secret: string): Promise<void> {
  const fp = licenseKeyFingerprint(licenseKey);
  const trimmed = secret.trim();
  if (!fp || !trimmed) return;
  const map = await readSecretMap();
  map[fp] = trimmed;
  await AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(map));
}

/** Keep local secrets aligned with linked EAs after restore / migration. */
export async function syncLicenseDeviceSecretsFromEas(
  eas: Array<{ licenseKey?: string; phoneSecretKey?: string; userData?: { phone_secret_key?: string } }>
): Promise<void> {
  const map = await readSecretMap();
  let changed = false;
  for (const ea of eas) {
    const key = ea.licenseKey?.trim();
    const secret =
      ea.phoneSecretKey?.trim() ||
      ea.userData?.phone_secret_key?.trim() ||
      '';
    if (!key || !secret) continue;
    const fp = licenseKeyFingerprint(key);
    if (map[fp] !== secret) {
      map[fp] = secret;
      changed = true;
    }
  }
  if (changed) {
    await AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(map));
  }
}
