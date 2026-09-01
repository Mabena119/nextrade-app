import type { ActiveSymbol, MT4Symbol, MT5Symbol, MT5TradeMode } from '@/providers/app-provider';

export function normalizeSymbolKey(s: string): string {
  return s.replace(/\s/g, '').toUpperCase();
}

export function alnumSymbolKey(s: string): string {
  return normalizeSymbolKey(s).replace(/[^A-Z0-9]/g, '');
}

/** Root name without broker prefix/suffix punctuation (handles "#BTCUSD", ".DE30.", "NAS100.i"). */
export function baseSymbolKey(s: string): string {
  const n = normalizeSymbolKey(s);
  const first = n.split(/[.#_]/).find((p) => /[A-Z0-9]/.test(p));
  return alnumSymbolKey(first || n);
}

/**
 * Prefix-only match: XAUUSD → XAUUSD, XAUUSD.trd, XAUUSD.m, XAUUSD.mic, XAUUSDm.
 * Also accepts the reverse (configured XAUUSD.mic vs Market Watch XAUUSD).
 * Anything else (CHFSGD, XAGUSD, NASDAQ for NAS100, BTC→BTCEUR) is not a match.
 */
export function isPrefixSymbolMatch(wanted: string, candidate: string): boolean {
  const wNorm = normalizeSymbolKey(wanted);
  const cNorm = normalizeSymbolKey(candidate);
  if (!wNorm || !cNorm) return false;
  if (wNorm === cNorm) return true;
  if (cNorm.startsWith(`${wNorm}.`) || cNorm.startsWith(`${wNorm}_`) || cNorm.startsWith(`${wNorm}#`)) {
    return true;
  }
  if (wNorm.startsWith(`${cNorm}.`) || wNorm.startsWith(`${cNorm}_`) || wNorm.startsWith(`${cNorm}#`)) {
    return true;
  }
  const w = alnumSymbolKey(wanted);
  const c = alnumSymbolKey(candidate);
  if (!w || !c || w.length < 3 || c.length < 3) return false;
  if (c === w) return true;
  const brokerSuffix = /^(M|I|C|S|PRO|RAW|ECN|STP|MIC|TRD|CNC)$/i;
  if (c.startsWith(w)) {
    return brokerSuffix.test(c.slice(w.length));
  }
  if (w.startsWith(c)) {
    return brokerSuffix.test(w.slice(c.length));
  }
  return false;
}

/** Prefer exact alnum match, then shortest broker-suffixed ticker. */
export function symbolMatchRank(wanted: string, candidate: string): number | null {
  if (!isPrefixSymbolMatch(wanted, candidate)) return null;
  const w = alnumSymbolKey(wanted);
  const c = alnumSymbolKey(candidate);
  if (c === w) return 0;
  return 10 + Math.abs(c.length - w.length);
}

/** Search query variants for Market Watch (full, root, stripped broker suffix). */
export function buildSymbolSearchQueries(symbolName: string): string[] {
  const s = String(symbolName || '').trim();
  const out: string[] = [];
  const pushUnique = (v: string) => {
    const t = String(v || '').trim();
    if (!t) return;
    const alnumLen = t.replace(/[^A-Za-z0-9]/g, '').length;
    if (alnumLen < 4 && t.length < 4) return;
    if (out.some((x) => x.toLowerCase() === t.toLowerCase())) return;
    out.push(t);
  };
  pushUnique(s);
  pushUnique(s.replace(/\s+/g, ' '));
  const dottedRoot = s.split(/[.#_]/)[0]?.trim();
  if (dottedRoot) pushUnique(dottedRoot);
  const alnum = alnumSymbolKey(s);
  if (alnum) pushUnique(alnum);
  const stripped = alnum.replace(/(MIC|TRD|CNC|PRO|RAW|ECN|STP|[MICS])$/i, '');
  if (stripped.length >= 4) pushUnique(stripped);
  const m = s.match(/^(.+?)\s+index$/i);
  if (m) pushUnique(m[1].trim());
  const parts = s.split(/\s+/).filter((p) => p.length > 0);
  if (parts.length >= 3) pushUnique(parts.slice(0, -1).join(' '));
  if (parts.length >= 2) pushUnique(parts.slice(0, 2).join(' '));
  if (parts.length >= 1 && parts[0].length >= 4) pushUnique(parts[0]);
  return out;
}

export function extractTickerFromRow(cellText: string): string {
  const raw = String(cellText || '').trim();
  if (!raw) return '';
  let line = raw.split(/[\r\n]+/)[0].trim();
  const slash = line.indexOf('/');
  if (slash >= 0) line = line.substring(0, slash).trim();
  if (line.length > 40) return '';
  const token = (line.split(/\s+/)[0] || '').trim();
  if (token.length < 3 || token.length > 24) return '';
  return token;
}

export function symbolsAreSimilar(chartSymbol: string, configuredSymbol: string): boolean {
  return isPrefixSymbolMatch(chartSymbol, configuredSymbol) || isPrefixSymbolMatch(configuredSymbol, chartSymbol);
}

function rowTradeMode(row: MT5Symbol): MT5TradeMode {
  return row.tradeMode === 'scalper' ? 'scalper' : 'swing';
}

function resolveBestMt5RowForAsset(asset: string, mt5Symbols: MT5Symbol[]): MT5Symbol | null {
  if (!mt5Symbols.length) return null;
  const raw = asset.trim();
  if (!raw) {
    if (mt5Symbols.length === 1) return mt5Symbols[0];
    return null;
  }
  const exact = mt5Symbols.find((s) => normalizeSymbolKey(s.symbol) === normalizeSymbolKey(raw));
  if (exact) return exact;
  const hits = mt5Symbols.filter((s) => isPrefixSymbolMatch(raw, s.symbol) || isPrefixSymbolMatch(s.symbol, raw));
  hits.sort((a, b) => a.symbol.length - b.symbol.length);
  return hits[0] ?? null;
}

/**
 * Maps analysis / chart symbol text to exactly one configured Quotes symbol for the **active EA** (same lists as Quotes).
 * Returns null if no match — avoids executing on an instrument the user did not configure for the current EA.
 */
export function resolveConfiguredTradeSymbol(
  analysisSymbol: string | undefined,
  mt5Symbols: MT5Symbol[],
  mt4Symbols: MT4Symbol[],
  activeSymbols: ActiveSymbol[]
): { symbol: string } | null {
  const fromMt5 = mt5Symbols.map((x) => x.symbol);
  const fromMt4 = mt4Symbols.map((x) => x.symbol);
  const fromActive = activeSymbols.map((x) => x.symbol);
  const unique = [...new Set([...fromMt5, ...fromMt4, ...fromActive].filter(Boolean))];
  return resolveAgainstConfiguredList(analysisSymbol, unique);
}

/** MT5 Quotes only (mt5Symbols + legacy active rows marked MT5) — used for MT5 terminal execution. */
export function listMt5QuotesSymbols(
  mt5Symbols: MT5Symbol[],
  activeSymbols: ActiveSymbol[]
): string[] {
  return [
    ...new Set(
      [
        ...mt5Symbols.map((x) => x.symbol),
        ...activeSymbols.filter((s) => s.platform === 'MT5').map((x) => x.symbol),
      ].filter(Boolean)
    ),
  ];
}

/**
 * Resolve to an MT5 Quotes row only. Never returns an MT4-only / unlisted instrument for MT5 auto-trade.
 */
export function resolveConfiguredMt5QuotesSymbol(
  analysisSymbol: string | undefined,
  mt5Symbols: MT5Symbol[],
  activeSymbols: ActiveSymbol[]
): { symbol: string } | null {
  return resolveAgainstConfiguredList(analysisSymbol, listMt5QuotesSymbols(mt5Symbols, activeSymbols));
}

function resolveAgainstConfiguredList(
  analysisSymbol: string | undefined,
  unique: string[]
): { symbol: string } | null {
  if (unique.length === 0) return null;

  const raw = (analysisSymbol || '').trim();
  if (!raw) {
    // Do not guess a symbol when multiple Quotes rows exist — caller must supply the asset.
    if (unique.length === 1) return { symbol: unique[0] };
    return null;
  }

  const exact = unique.find((u) => normalizeSymbolKey(u) === normalizeSymbolKey(raw));
  if (exact) return { symbol: exact };

  const hits = unique.filter((u) => isPrefixSymbolMatch(raw, u) || isPrefixSymbolMatch(u, raw));
  hits.sort((a, b) => a.length - b.length);
  return hits[0] ? { symbol: hits[0] } : null;
}

/**
 * Trade mode for analyze-chart API: uses MT5 trade config for the best-matching symbol,
 * or the sole configured symbol when no asset string is known; otherwise swing.
 */
export function getTradeModeForAnalysis(asset: string | undefined, mt5Symbols: MT5Symbol[]): MT5TradeMode {
  const row = resolveBestMt5RowForAsset(asset || '', mt5Symbols);
  if (row) return rowTradeMode(row);
  if (mt5Symbols.length === 1) return rowTradeMode(mt5Symbols[0]);
  return 'swing';
}

export function quoteSetNotFoundMessage(symbol: string): string {
  return `Quote Set Not found ${String(symbol || '').trim()}`;
}
