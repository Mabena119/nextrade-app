import AsyncStorage from '@react-native-async-storage/async-storage';

const REF_STORAGE_KEY = 'auraai_affiliate_ref';
const VISITOR_STORAGE_KEY = 'auraai_visitor_id';
const REF_PATTERN = /^AFF[A-F0-9]{5,}$/i;
const VISITOR_PATTERN = /^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i;
const ATTRIBUTION_PING_URL = 'https://auraai-vps.com/shop/attribution-ping.php';
const PAYMENT_ATTRIBUTION_URL = 'https://auraai-vps.com/shop/payment-attribution.php';

export function normalizeAffiliateRef(value: string | null | undefined): string | null {
  const trimmed = String(value ?? '').trim().toUpperCase();
  return REF_PATTERN.test(trimmed) ? trimmed : null;
}

export function normalizeVisitorId(value: string | null | undefined): string | null {
  const trimmed = String(value ?? '').trim().toLowerCase();
  return VISITOR_PATTERN.test(trimmed) ? trimmed : null;
}

function createVisitorId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  const bytes = new Uint8Array(16);
  if (typeof crypto !== 'undefined' && crypto.getRandomValues) {
    crypto.getRandomValues(bytes);
  } else {
    for (let i = 0; i < bytes.length; i += 1) {
      bytes[i] = Math.floor(Math.random() * 256);
    }
  }
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export async function getOrCreateVisitorId(): Promise<string> {
  const stored = normalizeVisitorId(await AsyncStorage.getItem(VISITOR_STORAGE_KEY));
  if (stored) return stored;
  const created = createVisitorId();
  await AsyncStorage.setItem(VISITOR_STORAGE_KEY, created);
  return created;
}

export function extractAffiliateRefFromUrl(url: string): string | null {
  try {
    const parsed = new URL(url);
    for (const key of ['ref', 'affiliate', 'affiliate_code']) {
      const ref = normalizeAffiliateRef(parsed.searchParams.get(key));
      if (ref) return ref;
    }
  } catch {
  }
  return null;
}

export async function getStoredAffiliateRef(): Promise<string | null> {
  return normalizeAffiliateRef(await AsyncStorage.getItem(REF_STORAGE_KEY));
}

export async function storeAffiliateRef(code: string | null | undefined): Promise<void> {
  const normalized = normalizeAffiliateRef(code);
  if (normalized) {
    await AsyncStorage.setItem(REF_STORAGE_KEY, normalized);
    return;
  }
  await AsyncStorage.removeItem(REF_STORAGE_KEY);
}

export async function pingAffiliateAttribution(ref: string, email?: string | null): Promise<void> {
  const normalizedRef = normalizeAffiliateRef(ref);
  if (!normalizedRef) return;
  const vid = await getOrCreateVisitorId();
  try {
    await fetch(ATTRIBUTION_PING_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        ref: normalizedRef,
        vid,
        ...(email ? { email: email.trim() } : {}),
      }),
    });
  } catch {
  }
}

/** Re-sync stored ref + visitor id with the server (IP + device attribution). */
export async function syncStoredAffiliateAttribution(email?: string | null): Promise<void> {
  const ref = await getStoredAffiliateRef();
  if (!ref) return;
  await pingAffiliateAttribution(ref, email);
}

/** After checkout, confirm payment IP + affiliate once the member row exists. */
export async function confirmPaymentAffiliation(email: string): Promise<boolean> {
  const trimmed = email.trim();
  if (!trimmed.includes('@')) return false;
  const ref = await getStoredAffiliateRef();
  const vid = await getOrCreateVisitorId();
  try {
    const response = await fetch(PAYMENT_ATTRIBUTION_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: trimmed,
        ...(ref ? { ref } : {}),
        vid,
      }),
    });
    if (!response.ok) return false;
    const data = await response.json();
    return Boolean(data?.paid);
  } catch {
    return false;
  }
}

export async function captureAffiliateRefFromUrl(url: string | null | undefined): Promise<string | null> {
  if (!url) return null;
  const ref = extractAffiliateRefFromUrl(url);
  if (ref) {
    await storeAffiliateRef(ref);
    await pingAffiliateAttribution(ref);
  }
  return ref;
}

export function buildShopPaymentUrl(email: string, ref?: string | null, visitorId?: string | null): string {
  const params = new URLSearchParams();
  params.set('email', email.trim());
  const normalizedRef = normalizeAffiliateRef(ref);
  if (normalizedRef) {
    params.set('ref', normalizedRef);
  }
  const normalizedVisitor = normalizeVisitorId(visitorId);
  if (normalizedVisitor) {
    params.set('vid', normalizedVisitor);
  }
  return `https://auraai-vps.com/shop/?${params.toString()}`;
}
