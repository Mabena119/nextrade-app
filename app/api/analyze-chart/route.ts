/**
 * AI Chart Analysis API - Uses Google Gemini to analyze trading chart images
 * Requires GOOGLE_AI_API_KEY or GEMINI_API_KEY environment variable
 */

import { createHash } from 'node:crypto';
import {
  getSlTpPercentForTradeMode,
  getTakeProfitRiskMultiple,
  ensureMinRewardRisk,
  computeFallbackSlTp,
} from '@/utils/trade-mode-levels';
import type { MT5TradeMode } from '@/providers/app-provider';

const GEMINI_API_BASE = 'https://generativelanguage.googleapis.com/v1beta';
/** Prefer non-thinking flash first — 2.5 thinking can burn maxOutputTokens and return empty candidates. */
const MODELS = ['gemini-2.0-flash', 'gemini-2.5-flash', 'gemini-flash-latest'] as const;
const GEMINI_TIMEOUT_MS = 45000;
const MAX_BASE64_BYTES = 1_000_000; // 1MB max to avoid 502
const CHART_CACHE_MAX = 200;
/** Optional upstream when local Gemini key is missing/invalid (e.g. Render chart-warmup-api). */
const ANALYZE_FALLBACK_URL = process.env.ANALYZE_CHART_FALLBACK_URL?.replace(/\/$/, '') || '';

async function forwardAnalyzeToFallback(payload: {
  image: string;
  mimeType: string;
  tradeMode: MT5TradeMode;
}): Promise<Response | null> {
  if (!ANALYZE_FALLBACK_URL) return null;
  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), GEMINI_TIMEOUT_MS);
    const res = await fetch(ANALYZE_FALLBACK_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      signal: controller.signal,
    });
    clearTimeout(timeoutId);
    const text = await res.text();
    return new Response(text, {
      status: res.status,
      headers: { 'Content-Type': res.headers.get('Content-Type') || 'application/json' },
    });
  } catch (err) {
    console.error('analyze-chart fallback failed:', err);
    return null;
  }
}

function geminiErrorLooksLikeBadKey(status: number, body: string): boolean {
  if (status === 401 || status === 403) return true;
  if (status !== 400) return false;
  return /api key not valid|invalid api key|api_key_invalid/i.test(body);
}

/** Fixed seed + temp 0: same chart image → same model output across requests (when the model honors seed). */
const CHART_ANALYSIS_GENERATION_SEED = 1_728_142_031;

/** Same image bytes + trade mode → identical API output (avoids model drift; speeds retries). */
const chartAnalysisResultCache = new Map<
  string,
  {
    data: {
      symbol: string;
      timeframe: string;
      currentPrice: string;
      signal: 'BUY' | 'SELL';
      confidence: string;
      summary: string;
      reasoning: string;
      suggestion: string;
      entryPrice: string;
      stopLoss: string;
      takeProfit1: string;
      takeProfit2: string;
      takeProfit3: string;
    };
  }
>();

/** Bump when analysis output shape / normalization changes (invalidates cached responses). */
const CHART_CACHE_KEY_VERSION = '2026-07-deterministic-v2';

/**
 * Compact prompt — keeps trade quality rules, cuts ~70% of prompt tokens vs the long form.
 * Image tokens dominate cost; keep screenshots ≤~512–600px wide on the client.
 */
const CHART_ANALYSIS_PROMPT = `MT5/TradingView chart analyst. Reply JSON only (no markdown).

chartDetected: true only if candlesticks/bars/price chart visible; else false (photos/memes/docs → false).
symbol: exact broker CODE from title/watch (# . suffixes kept). Name only if no code. Else "".
signal: BUY or SELL only (never NEUTRAL).
Rules: uptrend→BUY unless breakdown; downtrend→SELL unless reclaim; range→nearest S/R rejection or last 2–3 closes.
currentPrice = live quote or last close. entryPrice = currentPrice.
BUY: SL below entry, TP above. SELL: opposite. Use chart levels; ≥~2:1 R:R. confidence high|medium|low (low if choppy).
Mode: {{MODE}} — scalper=tighter SL/TP; swing=wider when structure allows.
Same image → same signal/levels. reasoning≤2 short sentences. suggestion≤1 sentence. takeProfit2/3="".

{"chartDetected":true,"symbol":"","timeframe":"","currentPrice":"","signal":"BUY","confidence":"medium","summary":"","reasoning":"","suggestion":"","entryPrice":"","stopLoss":"","takeProfit1":"","takeProfit2":"","takeProfit3":""}`;

const CHART_RETRY_PROMPT = `Re-check: is this a trading price chart? JSON only.
chartDetected true only for candles/bars/price series on MT5/TV/cTrader/etc. Else false with empty fields.
If true, same schema/rules as before (BUY|SELL, entry=price, valid SL/TP). Mode: {{MODE}}.`;

function tradeModeLabel(tradeMode: MT5TradeMode): string {
  return tradeMode === 'scalper' ? 'SCALPER' : 'SWING';
}

function chartAnalysisTextForMode(tradeMode: MT5TradeMode): string {
  return CHART_ANALYSIS_PROMPT.replace('{{MODE}}', tradeModeLabel(tradeMode));
}

function chartRetryTextForMode(tradeMode: MT5TradeMode): string {
  return CHART_RETRY_PROMPT.replace('{{MODE}}', tradeModeLabel(tradeMode));
}

function deterministicSignalTieBreak(base64Data: string): 'BUY' | 'SELL' {
  const n = parseInt(createHash('sha256').update(base64Data, 'utf8').digest('hex').slice(0, 8), 16);
  return n % 2 === 0 ? 'BUY' : 'SELL';
}

function cacheKeyForChart(base64Data: string, tradeMode: MT5TradeMode): string {
  return createHash('sha256')
    .update(base64Data, 'utf8')
    .update('\n')
    .update(tradeMode)
    .update('\n')
    .update(CHART_CACHE_KEY_VERSION)
    .digest('hex');
}

function cacheGetChart(base64Data: string, tradeMode: MT5TradeMode) {
  const k = cacheKeyForChart(base64Data, tradeMode);
  return chartAnalysisResultCache.get(k) ?? null;
}

function cacheSetChart(
  base64Data: string,
  tradeMode: MT5TradeMode,
  data: {
    symbol: string;
    timeframe: string;
    currentPrice: string;
    signal: 'BUY' | 'SELL';
    confidence: string;
    summary: string;
    reasoning: string;
    suggestion: string;
    entryPrice: string;
    stopLoss: string;
    takeProfit1: string;
    takeProfit2: string;
    takeProfit3: string;
  }
) {
  if (chartAnalysisResultCache.size >= CHART_CACHE_MAX) {
    const first = chartAnalysisResultCache.keys().next().value;
    if (first) chartAnalysisResultCache.delete(first);
  }
  chartAnalysisResultCache.set(cacheKeyForChart(base64Data, tradeMode), { data });
}

const SYMBOL_MAX_LEN = 72;

function capSymbolLen(s: string): string {
  const t = s.trim();
  if (t.length <= SYMBOL_MAX_LEN) return t;
  return t.slice(0, SYMBOL_MAX_LEN).trim();
}

/**
 * MT5/web terminals often show full names (e.g. "Volatility 100 Index") in the title bar.
 * Keep the entire string when it looks like a compact instrument label, not a sentence.
 */
function isMultiWordInstrumentDisplayName(s: string): boolean {
  const words = s.split(/\s+/).filter(Boolean);
  if (words.length < 2 || words.length > 10) return false;
  const lower = s.toLowerCase();
  if (
    /\b(the|chart|analysis|this|shows|suggest|because|should|would|could|visible|based|however|therefore|currently)\b/.test(
      lower
    )
  ) {
    return false;
  }
  for (const w of words) {
    if (w.length > 20) return false;
    if (!/^[A-Za-z0-9.#][A-Za-z0-9.#_\-]{0,19}$/.test(w)) return false;
  }
  if (/\d/.test(s)) return true;
  if (
    /\b(index|indices|volatility|vix|cfd|cash|mini|micro|futures|future|spot|swap|basket|wti|brent|pair|perpetual|swap-free)\b/i.test(
      s
    )
  ) {
    return true;
  }
  // Space-separated majors: EUR USD
  if (
    words.length === 2 &&
    /^[A-Za-z]{3}$/.test(words[0]) &&
    /^[A-Za-z]{3}$/.test(words[1])
  ) {
    return true;
  }
  // Short stacked labels without obvious prose (e.g. "US Tech 100")
  return words.length <= 6;
}

/** Keep broker symbol text usable for app matching (tickers + full display names). */
function normalizeSymbolFromChart(raw: unknown): string {
  if (raw === null || raw === undefined) return '';
  let s = String(raw).trim();
  if (!s) return '';
  s = s.replace(/^[\s"'`]+|[\s"'`]+$/g, '');
  s = s.replace(/\u00A0/g, '');
  s = s.replace(/\s+/g, ' ');

  const labelPick = s.match(
    /(?:^|\s)(?:symbol|pair|ticker|instrument)\s*[:=]\s*([^\n"|]{1,80})/i
  );
  if (labelPick?.[1]) {
    const v = capSymbolLen(labelPick[1].replace(/^["']|["']$/g, '').trim());
    if (v) return v;
  }
  const slash = s.match(/\b([A-Za-z]{3,10})\s*\/\s*([A-Za-z]{3,10})\b/);
  if (slash) return `${slash[1]}${slash[2]}`.toUpperCase();
  const paren = s.match(/\(([A-Za-z0-9.#][A-Za-z0-9.#_\-]{1,31})\)/);
  if (paren?.[1]) return capSymbolLen(paren[1].trim());

  // Broker codes may start with # or . (e.g. "#BTCUSD", ".DE30.") — keep them byte-exact.
  const oneToken = s.match(/^([A-Za-z0-9.#][A-Za-z0-9.#_\-]{1,31})$/);
  if (oneToken) return s;

  if (isMultiWordInstrumentDisplayName(s)) {
    return capSymbolLen(s);
  }

  const tokens = s.split(/[\s,;|]+/).filter(Boolean);
  for (const t of tokens) {
    if (/^[A-Za-z0-9.#][A-Za-z0-9.#_\-]{1,31}$/.test(t) && /[A-Za-z0-9]/.test(t)) return t;
  }
  const stripped = s.replace(/[^\w.#\-]/g, '');
  const compact = stripped.length <= 48 ? stripped : stripped.slice(0, 48);
  return capSymbolLen(compact);
}

function parsePriceNum(s: string): number {
  const n = parseFloat(String(s || '').replace(/,/g, ''));
  return Number.isFinite(n) ? n : NaN;
}

function makePriceFormatter(anchor: number): (n: number) => string {
  const decimals = anchor > 100 ? 2 : 5;
  return (n: number) => parseFloat(n.toFixed(decimals)).toString();
}

/**
 * Align execution with the chart's last visible quote and enforce valid SL/TP geometry.
 * Wrong-side or missing levels → mode-aware fallback from the same anchor.
 */
function sanitizeTradeLevelsAgainstAnchor(
  direction: 'BUY' | 'SELL',
  currentPrice: string,
  entryPrice: string,
  stopLoss: string,
  takeProfit1: string,
  tradeMode: MT5TradeMode
): { currentPrice: string; entryPrice: string; stopLoss: string; takeProfit1: string } {
  const curN = parsePriceNum(currentPrice);
  const entN = parsePriceNum(entryPrice);
  let anchor = NaN;
  if (Number.isFinite(curN) && Number.isFinite(entN)) {
    const denom = Math.max(Math.abs(curN), 1e-12);
    anchor = Math.abs(curN - entN) / denom <= 0.003 ? (curN + entN) / 2 : curN;
  } else if (Number.isFinite(curN)) {
    anchor = curN;
  } else if (Number.isFinite(entN)) {
    anchor = entN;
  }

  if (!Number.isFinite(anchor)) {
    return { currentPrice, entryPrice, stopLoss, takeProfit1 };
  }

  const fmt = makePriceFormatter(anchor);
  const entryStr = fmt(anchor);
  let slN = parsePriceNum(stopLoss);
  let tpN = parsePriceNum(takeProfit1);

  const validBuy = (e: number, slv: number, tpv: number) =>
    slv < e && tpv > e && e - slv > 0 && tpv - e > 0;
  const validSell = (e: number, slv: number, tpv: number) =>
    slv > e && tpv < e && slv - e > 0 && e - tpv > 0;

  const ok =
    direction === 'BUY' ? validBuy(anchor, slN, tpN) : validSell(anchor, slN, tpN);

  if (!ok) {
    const fb = computeFallbackSlTp(direction, anchor, tradeMode);
    if (fb) {
      slN = parsePriceNum(fb.sl);
      tpN = parsePriceNum(fb.tp);
    }
  }

  return {
    currentPrice: entryStr,
    entryPrice: entryStr,
    stopLoss: Number.isFinite(slN) ? fmt(slN) : stopLoss,
    takeProfit1: Number.isFinite(tpN) ? fmt(tpN) : takeProfit1,
  };
}

function asChartString(v: unknown): string {
  if (v === null || v === undefined) return '';
  if (typeof v === 'boolean') return v ? 'true' : 'false';
  return String(v);
}

function parseGeminiChartResponse(rawText: string): Record<string, string | boolean> {
  let parsed: Record<string, string | boolean>;
  try {
    let cleaned = rawText
      .replace(/```json\s*/gi, '')
      .replace(/```\s*/g, '')
      .trim();
    const braceMatch = cleaned.match(/\{[\s\S]*\}/);
    if (braceMatch) cleaned = braceMatch[0];
    cleaned = cleaned.replace(/^\uFEFF/, '').replace(/,(\s*[}\]])/g, '$1');
    parsed = JSON.parse(cleaned) as Record<string, string | boolean>;
  } catch (parseErr) {
    console.warn('JSON parse failed, using regex fallback:', parseErr);
    console.warn('Raw response (first 500 chars):', rawText.slice(0, 500));
    const extract = (key: string): string => {
      const quoted = rawText.match(new RegExp(`"${key}"\\s*:\\s*"([^"]*)"`, 'i'));
      if (quoted?.[1]) return quoted[1].trim();
      const unquoted = rawText.match(new RegExp(`"${key}"\\s*:\\s*([^,}\\s"\\[\\]]+)`, 'i'));
      if (unquoted?.[1]) return String(unquoted[1].trim());
      return '';
    };
    const chartDetMatch = rawText.match(/"chartDetected"\s*:\s*(true|false)/i)?.[1]?.toLowerCase();
    // Only accept chart if model explicitly said true (missing key → not a chart)
    const chartDet = chartDetMatch === 'true';
    const sig = rawText.match(/"signal"\s*:\s*"(BUY|SELL|NEUTRAL)"/i)?.[1]?.toUpperCase() || 'NEUTRAL';
    parsed = {
      chartDetected: chartDet,
      symbol: extract('symbol') || '',
      timeframe: extract('timeframe') || '',
      currentPrice: extract('currentPrice') || '',
      signal: ['BUY', 'SELL'].includes(sig) ? sig : 'NEUTRAL',
      confidence: extract('confidence') || 'medium',
      summary: extract('summary') || 'Chart analysis completed.',
      reasoning: extract('reasoning') || '',
      suggestion: extract('suggestion') || '',
      entryPrice: extract('entryPrice') || '',
      stopLoss: extract('stopLoss') || '',
      takeProfit1: extract('takeProfit1') || '',
      takeProfit2: extract('takeProfit2') || '',
      takeProfit3: extract('takeProfit3') || '',
    };
  }
  return parsed;
}

export async function POST(request: Request): Promise<Response> {
  const apiKey = process.env.GOOGLE_AI_API_KEY || process.env.GEMINI_API_KEY;

  try {
    const body = await request.json();
    const { image, mimeType = 'image/jpeg', tradeMode: tradeModeRaw } = body as {
      image?: string;
      mimeType?: string;
      tradeMode?: string;
    };
    const tradeMode: MT5TradeMode = tradeModeRaw === 'scalper' ? 'scalper' : 'swing';

    if (!image || typeof image !== 'string') {
      return Response.json(
        { message: 'error', error: 'Image data (base64) is required' },
        { status: 400, headers: { 'Content-Type': 'application/json' } }
      );
    }

    // Strip data URL prefix if present (e.g. "data:image/jpeg;base64,...")
    const base64Data = image.replace(/^data:image\/\w+;base64,/, '');

    if (base64Data.length > MAX_BASE64_BYTES) {
      return Response.json(
        { message: 'error', error: 'Image too large. Use a smaller chart screenshot.' },
        { status: 400, headers: { 'Content-Type': 'application/json' } }
      );
    }

    const cached = cacheGetChart(base64Data, tradeMode);
    if (cached) {
      return Response.json(
        { message: 'accept' as const, data: { ...cached.data } },
        { status: 200, headers: { 'Content-Type': 'application/json' } }
      );
    }

    const fallbackPayload = {
      image: base64Data,
      mimeType,
      tradeMode,
    };

    if (!apiKey) {
      console.error('Missing GOOGLE_AI_API_KEY or GEMINI_API_KEY — using analyze fallback');
      const fb = await forwardAnalyzeToFallback(fallbackPayload);
      if (fb) return fb;
      return Response.json(
        {
          message: 'error',
          error: 'AI analysis not configured. Set GOOGLE_AI_API_KEY in environment.',
        },
        { status: 503, headers: { 'Content-Type': 'application/json' } }
      );
    }

    const geminiPayload = {
      contents: [
        {
          parts: [
            {
              inline_data: {
                mime_type: mimeType,
                data: base64Data,
              },
            },
            { text: chartAnalysisTextForMode(tradeMode) },
          ],
        },
      ],
      generationConfig: {
        temperature: 0,
        topP: 0.1,
        topK: 1,
        maxOutputTokens: 8192,
        responseMimeType: 'application/json',
        candidateCount: 1,
        seed: CHART_ANALYSIS_GENERATION_SEED,
        // Gemini 2.5+ otherwise spends the output budget on hidden "thoughts" → empty JSON.
        thinkingConfig: { thinkingBudget: 0 },
      },
    };

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), GEMINI_TIMEOUT_MS);

    let res: Response | undefined;
    let lastErr: string | null = null;

    for (const model of MODELS) {
      try {
        res = await fetch(
          `${GEMINI_API_BASE}/models/${model}:generateContent?key=${apiKey}`,
          {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(geminiPayload),
            signal: controller.signal,
          }
        );
        if (res.ok) {
          clearTimeout(timeoutId);
          break;
        }
        lastErr = await res.text();
        if (res.status === 404) {
          console.warn(`Model ${model} not found, trying next...`);
          continue;
        }
        // Some models reject thinkingConfig / seed — retry once without those fields.
        if (res.status === 400 && /thinkingConfig|Unknown name|seed/i.test(lastErr)) {
          console.warn(`Model ${model} rejected generationConfig extras — retrying stripped config`);
          try {
            const stripped = {
              ...geminiPayload,
              generationConfig: {
                temperature: 0,
                topP: 0.1,
                topK: 1,
                maxOutputTokens: 8192,
                responseMimeType: 'application/json',
                candidateCount: 1,
              },
            };
            const retryRes = await fetch(
              `${GEMINI_API_BASE}/models/${model}:generateContent?key=${apiKey}`,
              {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(stripped),
                signal: controller.signal,
              }
            );
            if (retryRes.ok) {
              clearTimeout(timeoutId);
              res = retryRes;
              break;
            }
            lastErr = await retryRes.text();
          } catch (stripErr) {
            console.warn('Stripped-config retry failed:', stripErr);
          }
        }
        clearTimeout(timeoutId);
        console.error('Gemini API error:', res.status, lastErr.slice(0, 500));
        if (geminiErrorLooksLikeBadKey(res.status, lastErr)) {
          console.warn('analyze-chart: invalid Gemini key — using EA Trade fallback');
          const fb = await forwardAnalyzeToFallback(fallbackPayload);
          if (fb) return fb;
        }
        // Transient model errors → try next model instead of hard-failing the whole request.
        if (res.status >= 500 || res.status === 429) {
          console.warn(`Model ${model} status ${res.status}, trying next...`);
          continue;
        }
        let hint = 'Please try again.';
        if (geminiErrorLooksLikeBadKey(res.status, lastErr)) {
          hint = 'Check GOOGLE_AI_API_KEY on the API host (VPS/Render).';
        }
        if (res.status === 429) hint = 'Rate limit reached. Wait 1 minute and try again.';
        return Response.json(
          { message: 'error', error: `AI analysis failed. ${hint}` },
          { status: 502, headers: { 'Content-Type': 'application/json' } }
        );
      } catch (fetchErr: unknown) {
        if (fetchErr instanceof Error && fetchErr.name === 'AbortError') {
          clearTimeout(timeoutId);
          return Response.json(
            { message: 'error', error: 'Request timed out. Try a smaller image.' },
            { status: 502, headers: { 'Content-Type': 'application/json' } }
          );
        }
        lastErr = fetchErr instanceof Error ? fetchErr.message : 'Unknown';
      }
    }
    clearTimeout(timeoutId);

    if (!res?.ok) {
      console.error('All Gemini models failed:', lastErr?.slice(0, 300));
      if (lastErr && geminiErrorLooksLikeBadKey(400, lastErr)) {
        const fb = await forwardAnalyzeToFallback(fallbackPayload);
        if (fb) return fb;
      }
      return Response.json(
        { message: 'error', error: 'AI analysis failed. Please try again.' },
        { status: 502, headers: { 'Content-Type': 'application/json' } }
      );
    }

    const data = (await res.json()) as { candidates?: Array<{ content?: { parts?: Array<{ text?: string }> } }> };
    const text =
      data?.candidates?.[0]?.content?.parts?.[0]?.text?.trim() || '';

    if (!text) {
      console.warn('analyze-chart: empty Gemini text — trying EA Trade fallback');
      const fb = await forwardAnalyzeToFallback(fallbackPayload);
      if (fb) return fb;
      return Response.json(
        { message: 'error', error: 'No analysis returned from AI' },
        { status: 502, headers: { 'Content-Type': 'application/json' } }
      );
    }

    let parsed = parseGeminiChartResponse(text);

    const strictChartDetected = (v: unknown): boolean => v === true || v === 'true';
    let chartDetected = strictChartDetected(parsed.chartDetected);
    const MIN_BASE64_FOR_CHART_RETRY = 10_000;
    if (!chartDetected && base64Data.length >= MIN_BASE64_FOR_CHART_RETRY) {
      console.warn('analyze-chart: chartDetected false on substantial image, retrying with second-opinion prompt');
      const retryPayload = {
        contents: [
          {
            parts: [
              {
                inline_data: {
                  mime_type: mimeType,
                  data: base64Data,
                },
              },
              { text: chartRetryTextForMode(tradeMode) },
            ],
          },
        ],
        generationConfig: geminiPayload.generationConfig,
      };
      const retryController = new AbortController();
      const retryTimeoutId = setTimeout(() => retryController.abort(), GEMINI_TIMEOUT_MS);
      try {
        for (const model of MODELS) {
          const rTry = await fetch(
            `${GEMINI_API_BASE}/models/${model}:generateContent?key=${apiKey}`,
            {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(retryPayload),
              signal: retryController.signal,
            }
          );
          if (rTry.ok) {
            const retryData = (await rTry.json()) as {
              candidates?: Array<{ content?: { parts?: Array<{ text?: string }> } }>;
            };
            const retryText = retryData?.candidates?.[0]?.content?.parts?.[0]?.text?.trim() || '';
            if (retryText) {
              parsed = parseGeminiChartResponse(retryText);
              chartDetected = strictChartDetected(parsed.chartDetected);
            }
            break;
          }
          const errBody = await rTry.text();
          if (rTry.status === 404) {
            console.warn(`Model ${model} not found for retry, trying next...`);
            continue;
          }
          console.warn('analyze-chart retry Gemini error:', rTry.status, errBody.slice(0, 400));
          break;
        }
      } catch (retryErr) {
        console.warn('analyze-chart retry failed:', retryErr);
      } finally {
        clearTimeout(retryTimeoutId);
      }
    }

    if (!chartDetected) {
      return Response.json(
        {
          message: 'error',
          error: 'Please upload a chart image. The image does not appear to be a trading chart (candlestick, bar, or line chart from a trading platform).',
        },
        { status: 400, headers: { 'Content-Type': 'application/json' } }
      );
    }

    // Force BUY or SELL only - convert NEUTRAL with deterministic structure keywords + image hash tie-break
    let signal = (asChartString(parsed.signal) || 'BUY').toUpperCase();
    if (signal === 'NEUTRAL') {
      const text = `${asChartString(parsed.reasoning)} ${asChartString(parsed.summary)}`.toLowerCase();
      const sellHints =
        /\b(sell|bearish|downtrend|lower high|resistance|rejection|breakdown|short)\b/.test(text);
      const buyHints =
        /\b(buy|bullish|uptrend|higher low|support|bounce|breakout|long)\b/.test(text);
      if (sellHints && !buyHints) signal = 'SELL';
      else if (buyHints && !sellHints) signal = 'BUY';
      else signal = deterministicSignalTieBreak(base64Data);
    }

    let currentPrice = asChartString(parsed.currentPrice);
    let entryPrice = asChartString(parsed.entryPrice) || currentPrice;
    let stopLoss = asChartString(parsed.stopLoss);
    let takeProfit1 = asChartString(parsed.takeProfit1);
    const suggestion = asChartString(parsed.suggestion);

    // Fallback: extract prices from suggestion text (e.g. "Enter at 1.0850, SL at 1.0800, TP at 1.0920")
    if ((!entryPrice || !stopLoss || !takeProfit1) && suggestion) {
      const enterMatch = suggestion.match(/(?:enter|entry)\s*(?:at|:)?\s*([\d.,]+)/i) || suggestion.match(/([\d.,]+)\s*(?:for\s+)?(?:entry|enter)/i);
      const slMatch = suggestion.match(/(?:sl|stop\s*loss)\s*(?:at|:)?\s*([\d.,]+)/i) || suggestion.match(/([\d.,]+)\s*(?:for\s+)?(?:sl|stop)/i);
      const tpMatch = suggestion.match(/(?:tp|take\s*profit)\s*(?:at|:)?\s*([\d.,]+)/i) || suggestion.match(/([\d.,]+)\s*(?:for\s+)?(?:tp|target)/i);
      if (!entryPrice && enterMatch?.[1]) entryPrice = enterMatch[1].trim();
      if (!stopLoss && slMatch?.[1]) stopLoss = slMatch[1].trim();
      if (!takeProfit1 && tpMatch?.[1]) takeProfit1 = tpMatch[1].trim();
    }

    // Fallback: compute SL/TP from entry when AI returns empty (scalper: tighter; swing: wider)
    let entryNum = parseFloat(String(entryPrice).replace(/,/g, ''));
    if (entryNum && !isNaN(entryNum) && (!stopLoss || !takeProfit1)) {
      const pct = getSlTpPercentForTradeMode(tradeMode);
      const slDist = entryNum * pct;
      const mult = getTakeProfitRiskMultiple(tradeMode);
      const tpDist = entryNum * pct * mult;
      const decimals = entryNum > 100 ? 2 : 5;
      const fmt = (n: number) => parseFloat(n.toFixed(decimals)).toString();
      if (!stopLoss) stopLoss = signal === 'BUY' ? fmt(entryNum - slDist) : fmt(entryNum + slDist);
      if (!takeProfit1) takeProfit1 = signal === 'BUY' ? fmt(entryNum + tpDist) : fmt(entryNum - tpDist);
    }

    const aligned = sanitizeTradeLevelsAgainstAnchor(
      signal as 'BUY' | 'SELL',
      currentPrice,
      entryPrice,
      stopLoss,
      takeProfit1,
      tradeMode
    );
    currentPrice = aligned.currentPrice;
    entryPrice = aligned.entryPrice;
    stopLoss = aligned.stopLoss;
    takeProfit1 = aligned.takeProfit1;

    // Enforce minimum reward:risk (improves expectancy vs tight model TPs)
    entryNum = parseFloat(String(entryPrice).replace(/,/g, ''));
    const slN = parseFloat(String(stopLoss).replace(/,/g, ''));
    const tpN = parseFloat(String(takeProfit1).replace(/,/g, ''));
    if (entryNum && !isNaN(entryNum) && !isNaN(slN) && !isNaN(tpN) && (signal === 'BUY' || signal === 'SELL')) {
      takeProfit1 = ensureMinRewardRisk(
        signal as 'BUY' | 'SELL',
        entryNum,
        slN,
        tpN
      );
    }

    const symbolNormalized = normalizeSymbolFromChart(parsed.symbol);

    const responseData = {
      symbol: symbolNormalized,
      timeframe: asChartString(parsed.timeframe),
      currentPrice,
      signal: signal as 'BUY' | 'SELL',
      confidence: asChartString(parsed.confidence) || 'low',
      summary: asChartString(parsed.summary),
      reasoning: (() => {
        const r = asChartString(parsed.reasoning).replace(/chart analysis completed\.?/gi, '').trim();
        const summary = asChartString(parsed.summary).trim();
        if (r && r.length > 80 && !/entry\s*\d|consider trend|technical analysis indicates/i.test(r)) return r;
        if (summary && summary.length > 30 && !/chart analysis completed/i.test(summary)) return summary;
        return r || summary;
      })(),
      suggestion: (() => {
        const s = (suggestion || '').replace(/review.*levels above\.?/gi, '').trim();
        if (s && s.length > 50 && !/^place\s*(buy|sell)\s*order\s*at\s*[\d.]+\.?\s*stop\s*loss:/i.test(s)) return s;
        return s || (stopLoss && takeProfit1 ? `SL: ${stopLoss}, TP: ${takeProfit1}. Use proper position sizing.` : '');
      })(),
      entryPrice,
      stopLoss,
      takeProfit1,
      takeProfit2: asChartString(parsed.takeProfit2),
      takeProfit3: asChartString(parsed.takeProfit3),
    };
    cacheSetChart(base64Data, tradeMode, responseData);

    return Response.json(
      {
        message: 'accept',
        data: responseData,
      },
      { status: 200, headers: { 'Content-Type': 'application/json' } }
    );
  } catch (error) {
    console.error('analyze-chart error:', error);
    return Response.json(
      {
        message: 'error',
        error: 'Analysis failed. Please try again.',
      },
      { status: 500, headers: { 'Content-Type': 'application/json' } }
    );
  }
}

export async function GET(): Promise<Response> {
  return Response.json({ message: 'Use POST with image data' }, { status: 405 });
}
