/** Pause after a successful trade before polling/executing the next signal. */
export const POST_EXECUTION_PAUSE_MS = 15_000;

export function signalIdKey(signalId: string | number | null | undefined): string | null {
  const key = String(signalId ?? '').trim();
  if (!key || key === 'undefined' || key === 'null') return null;
  return key;
}
