/** Pause after a successful trade before polling/executing the next signal. */
export const POST_EXECUTION_PAUSE_MS = 15_000;

export function signalIdKey(signalId: string | number | null | undefined): string | null {
  const key = String(signalId ?? '').trim();
  if (!key || key === 'undefined' || key === 'null') return null;
  return key;
}

/** Payload row for Android overlay headless execute (exact Quotes symbol + sizing). */
export function buildOverlaySignalPayloadRow(input: {
  id?: string | number;
  ea?: string;
  asset: string;
  latestupdate?: string;
  type?: string;
  action?: string;
  price?: string;
  tp?: string;
  sl?: string;
  time?: string;
  results?: string;
  lot?: string;
  numberOfTrades?: string | number;
}): Record<string, string> {
  const lot = input.lot != null && String(input.lot).trim() !== '' ? String(input.lot).trim() : '';
  const trades =
    input.numberOfTrades != null && String(input.numberOfTrades).trim() !== ''
      ? String(input.numberOfTrades).trim()
      : '';
  return {
    id: input.id != null ? String(input.id) : '',
    ea: input.ea != null ? String(input.ea) : '',
    asset: String(input.asset || ''),
    latestupdate: String(input.latestupdate ?? input.time ?? ''),
    type: String(input.type ?? ''),
    action: String(input.action ?? ''),
    price: String(input.price ?? ''),
    tp: String(input.tp ?? ''),
    sl: String(input.sl ?? ''),
    time: String(input.time ?? ''),
    results: String(input.results ?? ''),
    lot,
    numberOfTrades: trades,
  };
}
