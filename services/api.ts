import { resolveApiBaseUrl } from '@/utils/api-base-url';
import { dbApiUrl } from '@/utils/db-api-base-url';

function getBaseUrl(): string {
  return resolveApiBaseUrl();
}

/** Same-origin relative (`''`) or absolute host — both are valid. */
function apiUrl(path: string): string {
  const base = getBaseUrl();
  const p = path.startsWith('/') ? path : `/${path}`;
  return `${base}${p}`;
}

export interface AuthBody {
  email: string;
  password?: string;
  mentor?: string;
}

export interface Account {
  id: string;
  email: string;
  status: string;
  paid: boolean;
  used: boolean;
  invalidMentor?: number;
  /** True when the API answered from a DB-error fallback — not a definitive "not found". */
  degraded?: boolean;
}

export interface App {
  message: string;
  version: number;
}

export interface Signals {
  signals: Signal[];
}

export interface Signal {
  id: string;
  asset: string;
  action: string;
  price: string;
  tp: string;
  sl: string;
  time: string;
  latestupdate: string;
}

export interface SignalsResponse {
  message: 'accept' | 'error';
  data?: Signal;
}

export interface SignalsListResponse {
  message: 'accept' | 'error';
  data?: Signal[];
}

export interface Symbol {
  id: string;
  name: string;
}

export interface SymbolsResponse {
  message: 'accept' | 'error';
  data?: Symbol[];
}

export interface LicenseAuthBody {
  licence: string;
  phone_secret?: string;
}

export interface Owner {
  name: string;
  email: string;
  phone: string;
  logo: string;
}

export interface LicenseData {
  user: string;
  status: string;
  expires: string;
  key: string;
  phone_secret_key: string;
  ea_name: string;
  ea_notification: string;
  ea_martingale?: boolean;
  owner: Owner;
}

export interface LicenseAuthResponse {
  message: 'accept' | 'used' | 'error';
  data?: LicenseData;
  /** True when the server/network could not give a definitive answer — do not invalidate local state. */
  degraded?: boolean;
}

export interface EaFromLicenseResponse {
  id: number | string | null;
  eaId?: number | string | null;
  ea_name?: string | null;
  owner?: Pick<Owner, 'name' | 'logo'> | null;
}

export interface ChartAnalysisResult {
  symbol?: string;
  timeframe?: string;
  currentPrice?: string;
  signal: 'BUY' | 'SELL' | 'NEUTRAL';
  confidence: 'high' | 'medium' | 'low';
  summary: string;
  reasoning: string;
  suggestion: string;
  entryPrice?: string;
  stopLoss?: string;
  takeProfit1?: string;
  takeProfit2?: string;
  takeProfit3?: string;
}

export interface ChartAnalysisResponse {
  message: 'accept' | 'error';
  data?: ChartAnalysisResult;
  error?: string;
}

export interface Mt5TradeSizingRow {
  symbol: string;
  lotSize: string;
  numberOfTrades: string;
}

export interface Mt5TradeSizingResponse {
  message: 'accept' | 'error';
  data?: Mt5TradeSizingRow[];
  error?: string;
}

class ApiService {
  async authenticate(authBody: AuthBody): Promise<Account> {
    if (!authBody?.email) throw new Error('Email is required');
    const endpoint = dbApiUrl('/api/check-email');
    let res: Response;
    try {
      res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email: authBody.email.trim().toLowerCase(),
          mentor: (authBody.mentor || authBody.password || '').toString().trim(),
        }),
      });
    } catch (networkError) {
      console.warn('Email auth network error:', networkError);
      return {
        id: authBody.email,
        email: authBody.email,
        status: 'ok',
        paid: true,
        used: false,
        degraded: true,
      };
    }
    let data: { found?: number; used?: number; paid?: number; invalidMentor?: number; degraded?: number } = {};
    try {
      data = (await res.json()) as { used?: number; paid?: number; invalidMentor?: number; degraded?: number };
    } catch (e) {
      return {
        id: authBody.email,
        email: authBody.email,
        status: 'ok',
        paid: true,
        used: false,
        degraded: true,
      };
    }
    if (!res.ok) {
      return {
        id: authBody.email,
        email: authBody.email,
        status: 'ok',
        paid: true,
        used: false,
        degraded: true,
      };
    }
    const found = Number(data?.found ?? 0) === 1;
    const used = Number(data?.used ?? 0) === 1;
    const paid = Number(data?.paid ?? 0) === 1;
    const invalidMentor = Number(data?.invalidMentor ?? 0);
    const degraded = Number(data?.degraded ?? 0) === 1;

    return {
      id: authBody.email,
      email: authBody.email,
      status: found ? 'ok' : 'not_found',
      paid,
      used,
      invalidMentor,
      degraded,
    };
  }

  async getSignals(phoneSecret: string): Promise<SignalsResponse> {
    // Mock: produce no new signals to avoid network
    void phoneSecret;
    return { message: 'error' };
  }

  async getApp(email: string, use: boolean = false): Promise<App> {
    // Mock: pretend app is available for any email
    void use;
    if (!email) {
      return { message: 'none', version: 1 } as unknown as App;
    }
    return { message: 'accept', version: 1 } as unknown as App;
  }

  async getSymbols(phoneSecret: string): Promise<SymbolsResponse> {
    if (!phoneSecret) return { message: 'error' };
    const res = await fetch(dbApiUrl(`/api/symbols?phone_secret=${encodeURIComponent(phoneSecret)}`), {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
    });
    try {
      const data = (await res.json()) as SymbolsResponse;
      return data;
    } catch {
      return { message: 'error' };
    }
  }

  async authenticateLicense(licenseBody: LicenseAuthBody): Promise<LicenseAuthResponse> {
    if (!licenseBody?.licence) return { message: 'error' };
    const endpoint = dbApiUrl('/api/auth-license');

    // Add timeout to avoid hanging forever on network issues
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 12000);
    let res: Response;
    try {
      res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(licenseBody),
        signal: controller.signal,
      });
    } catch (networkError) {
      clearTimeout(timeout);
      console.warn('License auth network error:', networkError);
      return { message: 'error', degraded: true };
    }
    clearTimeout(timeout);

    if (!res.ok) {
      return { message: 'error', degraded: true };
    }

    try {
      const data = (await res.json()) as LicenseAuthResponse;
      if (Number((data as { degraded?: number }).degraded) === 1) {
        return { ...data, degraded: true };
      }
      return data;
    } catch {
      return { message: 'error', degraded: true };
    }
  }

  /** Public mentor branding for a licence key (no phone secret). */
  async fetchEaFromLicense(licenseKey: string): Promise<EaFromLicenseResponse | null> {
    const key = licenseKey.trim();
    if (!key) return null;
    try {
      const res = await fetch(
        dbApiUrl(`/api/get-ea-from-license?licenseKey=${encodeURIComponent(key)}`),
        { headers: { Accept: 'application/json' } }
      );
      if (!res.ok) return null;
      return (await res.json()) as EaFromLicenseResponse;
    } catch (e) {
      console.warn('fetchEaFromLicense error:', e);
      return null;
    }
  }

  async getScannerStatus(email: string): Promise<{ scanner: boolean }> {
    if (!email) return { scanner: false };
    try {
      const res = await fetch(
        dbApiUrl(`/api/scanner-status?email=${encodeURIComponent(email)}&_=${Date.now()}`),
        { method: 'GET', cache: 'no-store' }
      );
      const data = (await res.json()) as { scanner?: boolean };
      return { scanner: Boolean(data.scanner) };
    } catch {
      return { scanner: false };
    }
  }

  async revokeScannerAccess(email: string): Promise<void> {
    if (!email || !email.trim()) return;
    try {
      await fetch(dbApiUrl('/api/scanner-status'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email }),
      });
    } catch (e) {
      console.error('revokeScannerAccess error:', e);
    }
  }

  async createOzowCheckout(email?: string): Promise<{ url?: string; error?: string }> {
    try {
      const res = await fetch(apiUrl('/api/ozow-checkout'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(email ? { email } : {}),
      });
      const data = (await res.json()) as { url?: string; error?: string };
      if (!res.ok) {
        return { error: data.error || 'Payment could not be started' };
      }
      return data;
    } catch (e) {
      console.error('createOzowCheckout error:', e);
      return { error: 'Payment could not be started' };
    }
  }

  async fetchMt5TradeSizing(body: {
    equity?: string | null;
    balance?: string | null;
    symbols: { symbol: string; instrumentClass: string }[];
  }): Promise<Mt5TradeSizingResponse> {
    if (!body.symbols?.length) return { message: 'error', error: 'No symbols' };
    const endpoint = apiUrl('/api/mt5-trade-sizing');
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 25000);
    try {
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          equity: body.equity ?? '',
          balance: body.balance ?? '',
          symbols: body.symbols,
        }),
        signal: controller.signal,
      });
      clearTimeout(timeoutId);
      const data = (await res.json()) as Mt5TradeSizingResponse & { error?: string };
      if (!res.ok) {
        return {
          message: 'error',
          error: data.error || (res.status === 503 ? 'AI sizing not configured on server' : 'Sizing failed'),
        };
      }
      return data;
    } catch (e) {
      clearTimeout(timeoutId);
      const isTimeout = e instanceof Error && e.name === 'AbortError';
      return {
        message: 'error',
        error: isTimeout ? 'Sizing request timed out.' : 'Network error.',
      };
    }
  }

  async analyzeChart(
    imageBase64: string,
    mimeType = 'image/jpeg',
    options?: { tradeMode?: 'scalper' | 'swing' }
  ): Promise<ChartAnalysisResponse> {
    if (!imageBase64) return { message: 'error', error: 'No image provided' };
    const endpoint = apiUrl('/api/analyze-chart');
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 55000);
    try {
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          image: imageBase64,
          mimeType,
          ...(options?.tradeMode ? { tradeMode: options.tradeMode } : {}),
        }),
        signal: controller.signal,
      });
      clearTimeout(timeoutId);
      let data: ChartAnalysisResponse & { error?: string };
      try {
        data = (await res.json()) as ChartAnalysisResponse & { error?: string };
      } catch {
        return { message: 'error', error: res.status === 502 ? 'Server busy. Try again in 30 seconds (cold start).' : 'Analysis failed.' };
      }
      if (!res.ok) {
        const errMsg = data.error || (res.status === 429 ? 'Rate limit. Wait 1 min.' : 'Analysis failed');
        return { message: 'error', error: errMsg };
      }
      return data;
    } catch (e) {
      clearTimeout(timeoutId);
      console.error('analyzeChart error:', e);
      const isTimeout = e instanceof Error && e.name === 'AbortError';
      return {
        message: 'error',
        error: isTimeout ? 'Request timed out. Server may be waking up—try again in 30 seconds.' : 'Network error. Please try again.',
      };
    }
  }
}

export const apiService = new ApiService();
export default apiService;