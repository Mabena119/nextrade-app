/**
 * DeepSeek clients for Aura:
 * - Cloud api.deepseek.com → text (lot sizing)
 * - Self-hosted DeepSeek-VL → chart vision
 *   https://github.com/deepseek-ai/DeepSeek-VL
 *
 * Set DEEPSEEK_VL_URL to your GPU server (see deepseek-vl-server/).
 */

const CLOUD_BASE = (process.env.DEEPSEEK_API_BASE || 'https://api.deepseek.com').replace(/\/$/, '');
const VL_BASE = (process.env.DEEPSEEK_VL_URL || process.env.DEEPSEEK_VL_BASE || '').replace(/\/$/, '');

const CLOUD_MODELS = [
  process.env.DEEPSEEK_MODEL,
  'deepseek-v4-flash',
  'deepseek-v4-pro',
  'deepseek-chat',
].filter(Boolean) as string[];

export function getDeepSeekApiKey(): string | undefined {
  const k = process.env.DEEPSEEK_API_KEY || process.env.DEEPSEEK_KEY;
  return k?.trim() || undefined;
}

export function getDeepSeekVlUrl(): string | undefined {
  return VL_BASE || undefined;
}

export function getDeepSeekApiBase(): string {
  return CLOUD_BASE;
}

type ChatMessage =
  | { role: 'system' | 'user' | 'assistant'; content: string }
  | {
      role: 'user';
      content: Array<
        | { type: 'text'; text: string }
        | { type: 'image_url'; image_url: { url: string } }
      >;
    };

async function cloudChatRaw(params: {
  messages: ChatMessage[];
  timeoutMs?: number;
  temperature?: number;
  json?: boolean;
}): Promise<string | null> {
  const apiKey = getDeepSeekApiKey();
  if (!apiKey) return null;

  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), params.timeoutMs ?? 45000);

  const bodyBase: Record<string, unknown> = {
    messages: params.messages,
    temperature: params.temperature ?? 0,
    max_tokens: 4096,
    thinking: { type: 'disabled' },
  };
  if (params.json !== false) {
    bodyBase.response_format = { type: 'json_object' };
  }

  let lastErr: string | null = null;
  try {
    for (const model of CLOUD_MODELS) {
      try {
        const res = await fetch(`${CLOUD_BASE}/chat/completions`, {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${apiKey}`,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ ...bodyBase, model }),
          signal: controller.signal,
        });
        if (!res.ok) {
          lastErr = await res.text();
          if (res.status === 404) continue;
          console.warn('DeepSeek cloud error', model, res.status, lastErr.slice(0, 280));
          continue;
        }
        const data = (await res.json()) as {
          choices?: Array<{ message?: { content?: string | null } }>;
        };
        const text = data?.choices?.[0]?.message?.content?.trim();
        if (text) {
          clearTimeout(timeoutId);
          return text;
        }
      } catch (e) {
        if (e instanceof Error && e.name === 'AbortError') {
          clearTimeout(timeoutId);
          return null;
        }
        lastErr = e instanceof Error ? e.message : 'Unknown';
      }
    }
  } finally {
    clearTimeout(timeoutId);
  }
  if (lastErr) console.warn('DeepSeek cloud failed:', lastErr.slice(0, 300));
  return null;
}

export async function deepseekChatJson(params: {
  system?: string;
  user: string;
  timeoutMs?: number;
  temperature?: number;
}): Promise<string | null> {
  const messages: ChatMessage[] = [];
  if (params.system) messages.push({ role: 'system', content: params.system });
  messages.push({ role: 'user', content: params.user });
  return cloudChatRaw({
    messages,
    timeoutMs: params.timeoutMs,
    temperature: params.temperature,
    json: true,
  });
}

/**
 * Chart vision via self-hosted DeepSeek-VL (preferred) or OpenAI-style VL base.
 * @see https://github.com/deepseek-ai/DeepSeek-VL
 */
export async function deepseekAnalyzeChartImage(params: {
  prompt: string;
  mimeType: string;
  base64Data: string;
  timeoutMs?: number;
}): Promise<string | null> {
  const dataUrl = `data:${params.mimeType};base64,${params.base64Data}`;
  const apiKey = getDeepSeekApiKey();
  const timeoutMs = params.timeoutMs ?? 120000;
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

  try {
    // 1) Dedicated DeepSeek-VL server
    if (VL_BASE) {
      const headers: Record<string, string> = { 'Content-Type': 'application/json' };
      if (apiKey) headers.Authorization = `Bearer ${apiKey}`;

      // Prefer native /analyze
      try {
        const res = await fetch(`${VL_BASE}/analyze`, {
          method: 'POST',
          headers,
          body: JSON.stringify({
            image: params.base64Data,
            mimeType: params.mimeType,
            prompt: params.prompt,
          }),
          signal: controller.signal,
        });
        if (res.ok) {
          const data = (await res.json()) as { text?: string };
          if (data.text?.trim()) {
            clearTimeout(timeoutId);
            return data.text.trim();
          }
        } else {
          console.warn('DeepSeek-VL /analyze', res.status, (await res.text()).slice(0, 300));
        }
      } catch (e) {
        console.warn('DeepSeek-VL /analyze failed, trying chat completions', e);
      }

      // OpenAI-compatible shim on the VL server
      const res2 = await fetch(`${VL_BASE}/v1/chat/completions`, {
        method: 'POST',
        headers,
        body: JSON.stringify({
          model: process.env.DEEPSEEK_VL_MODEL || 'deepseek-vl',
          messages: [
            {
              role: 'user',
              content: [
                { type: 'text', text: params.prompt },
                { type: 'image_url', image_url: { url: dataUrl } },
              ],
            },
          ],
          temperature: 0,
          max_tokens: 2048,
        }),
        signal: controller.signal,
      });
      if (res2.ok) {
        const data = (await res2.json()) as {
          choices?: Array<{ message?: { content?: string | null } }>;
        };
        const text = data?.choices?.[0]?.message?.content?.trim();
        clearTimeout(timeoutId);
        return text || null;
      }
      console.warn('DeepSeek-VL chat completions', res2.status, (await res2.text()).slice(0, 300));
      clearTimeout(timeoutId);
      return null;
    }

    // 2) Optional: custom DEEPSEEK_API_BASE that accepts image_url (third-party VL host)
    if (!apiKey) {
      clearTimeout(timeoutId);
      return null;
    }
    const res = await fetch(`${CLOUD_BASE}/chat/completions`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${apiKey}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        model: process.env.DEEPSEEK_MODEL || 'deepseek-v4-flash',
        messages: [
          {
            role: 'user',
            content: [
              { type: 'text', text: params.prompt },
              { type: 'image_url', image_url: { url: dataUrl } },
            ],
          },
        ],
        temperature: 0,
        max_tokens: 2048,
      }),
      signal: controller.signal,
    });
    if (!res.ok) {
      const err = await res.text();
      if (/image_url|unknown variant|vision|multimodal/i.test(err)) {
        clearTimeout(timeoutId);
        return null; // expected on official cloud API
      }
      console.warn('DeepSeek vision host error', res.status, err.slice(0, 280));
      clearTimeout(timeoutId);
      return null;
    }
    const data = (await res.json()) as {
      choices?: Array<{ message?: { content?: string | null } }>;
    };
    clearTimeout(timeoutId);
    return data?.choices?.[0]?.message?.content?.trim() || null;
  } catch (e) {
    if (!(e instanceof Error && e.name === 'AbortError')) {
      console.warn('deepseekAnalyzeChartImage failed', e);
    }
    clearTimeout(timeoutId);
    return null;
  }
}
