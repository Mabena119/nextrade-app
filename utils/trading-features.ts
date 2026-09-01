import Constants from 'expo-constants';

/** Stored lot when martingale uses signal volume — execution may override from signal. */
export const MARTINGALE_PLACEHOLDER_LOT = '0.01';

export const MARTINGALE_SIGNAL_LOT_LABEL = 'From signal';

/** Martingale: take lot from mentor signal, or use the user's configured lot. */
export type MartingaleLotSource = 'signal' | 'own';

type EaMartingaleLike =
  | { status?: string; userData?: { ea_martingale?: boolean } | null }
  | null
  | undefined;

export function isMartingaleEa(
  eas: EaMartingaleLike[] | null | undefined,
  primaryIndex = 0
): boolean {
  if (!eas?.length) return false;
  const connected = eas.find((e) => e?.status === 'connected');
  const ea = connected ?? eas[primaryIndex];
  return Boolean(ea?.userData?.ea_martingale);
}

function readChartWarmupEnvFlag(): boolean {
  const raw =
    process.env.EXPO_PUBLIC_CHART_WARMUP_ENABLED ??
    Constants.expoConfig?.extra?.EXPO_PUBLIC_CHART_WARMUP_ENABLED;
  if (raw === undefined || raw === null || raw === '') return false;
  if (typeof raw === 'boolean') return raw;
  const s = String(raw).toLowerCase();
  return s === '1' || s === 'true' || s === 'yes';
}

/** Global product flag — off for NexTrade Render (DB signals only, no idle AI scan). */
export function isChartWarmupFeatureEnabled(): boolean {
  return readChartWarmupEnvFlag();
}

/** Standard automations: poll 10× idle → AI trade → 45 min pause → poll again. Martingale: poll only. */
export function isAiChartTradingEnabled(
  eas: EaMartingaleLike[] | null | undefined
): boolean {
  if (!isChartWarmupFeatureEnabled()) return false;
  return !isMartingaleEa(eas);
}

/** Parse lot from automation signal payload (martingale). Returns null if missing/invalid. */
export function parseSignalLot(lot: string | number | undefined | null): string | null {
  if (lot == null || lot === '') return null;
  const parsed = parseFloat(String(lot).replace(/,/g, ''));
  if (!Number.isFinite(parsed) || parsed <= 0) return null;
  return String(parsed);
}
