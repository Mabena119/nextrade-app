/**
 * NexTrade copy-trade rows store MySQL DATETIME in UTC (`gmdate` on the API).
 * Bare `YYYY-MM-DD HH:mm:ss` strings must not be parsed as local browser time.
 */
export function parseSignalUtcDatetime(
  value: string | number | Date | null | undefined
): Date | null {
  if (value == null || value === '') return null;
  if (value instanceof Date) {
    return Number.isNaN(value.getTime()) ? null : value;
  }

  const raw = String(value).trim();
  if (!raw) return null;

  if (/[zZ]$/.test(raw) || /[+-]\d{2}:?\d{2}$/.test(raw)) {
    const d = new Date(raw);
    return Number.isNaN(d.getTime()) ? null : d;
  }

  const match = raw.match(
    /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2}):(\d{2})(?:\.(\d{1,3}))?$/
  );
  if (match) {
    const [, year, month, day, hour, minute, second, millis] = match;
    const utcMs = Date.UTC(
      Number(year),
      Number(month) - 1,
      Number(day),
      Number(hour),
      Number(minute),
      Number(second),
      millis ? Number(millis.padEnd(3, '0')) : 0
    );
    const d = new Date(utcMs);
    return Number.isNaN(d.getTime()) ? null : d;
  }

  const fallback = new Date(raw);
  return Number.isNaN(fallback.getTime()) ? null : fallback;
}

/** Prefer `latestupdate`, then `time`, treating both as UTC MySQL datetimes. */
export function resolveSignalTimestamp(
  time?: string | null,
  latestupdate?: string | null
): Date | null {
  let signalTime = parseSignalUtcDatetime(time ?? undefined);
  const latest = parseSignalUtcDatetime(latestupdate ?? undefined);
  if (latest && (!signalTime || latest.getTime() > signalTime.getTime())) {
    signalTime = latest;
  }
  return signalTime;
}

export function signalAgeInSeconds(
  time?: string | null,
  latestupdate?: string | null,
  nowMs: number = Date.now()
): { ageInSeconds: number; signalTime: Date | null } {
  const signalTime = resolveSignalTimestamp(time, latestupdate);
  if (!signalTime) {
    return { ageInSeconds: -1, signalTime: null };
  }
  return { ageInSeconds: (nowMs - signalTime.getTime()) / 1000, signalTime };
}
