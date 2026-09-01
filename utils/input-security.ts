/**
 * Shared input validation for public API routes.
 */

const EMAIL_MAX_LEN = 254;
const LICENSE_KEY_MAX_LEN = 128;

export function normalizeEmail(value: unknown): string | null {
  if (typeof value !== 'string' && typeof value !== 'number') return null;
  const email = String(value).trim().toLowerCase();
  if (email.length < 3 || email.length > EMAIL_MAX_LEN) return null;
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? email : null;
}

export function normalizeLicenseKey(value: unknown): string | null {
  if (typeof value !== 'string' && typeof value !== 'number') return null;
  const key = String(value).trim();
  if (key.length < 4 || key.length > LICENSE_KEY_MAX_LEN) return null;
  return /^[A-Za-z0-9\-_]+$/.test(key) ? key : null;
}

export function normalizePhoneSecret(value: unknown): string | null {
  if (value == null || value === '') return null;
  if (typeof value !== 'string' && typeof value !== 'number') return null;
  const secret = String(value).trim();
  if (secret.length > 128) return null;
  return /^[A-Za-z0-9\-_]+$/.test(secret) ? secret : null;
}

export async function readJsonBody(request: Request, maxBytes = 65536): Promise<Record<string, unknown>> {
  const raw = await request.text();
  if (raw.length > maxBytes) return {};
  try {
    const parsed = JSON.parse(raw);
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed as Record<string, unknown> : {};
  } catch {
    return {};
  }
}
