const LIGHTSAIL_ORIGIN = 'http://35.168.213.207';
const HFM_API_PATH = '/admin/api/hfmapi/index.php';

const CORS = {
  'Content-Type': 'application/json',
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Methods': 'GET, OPTIONS',
  'Access-Control-Allow-Headers': 'Content-Type',
};

function buildHfmLookupUrl(mt5: string): { url: string; headers: Record<string, string> } {
  const custom = process.env.HFM_IB_API_URL?.trim().replace(/\/$/, '');
  if (custom) {
    const join = custom.includes('?') ? '&' : '?';
    return {
      url: `${custom}${join}mt5=${encodeURIComponent(mt5)}`,
      headers: {
        Accept: 'application/json',
        'User-Agent': 'NexTradeAI/1.0',
      },
    };
  }

  // Lightsail IP + Host header — reliable from Render and when domain DNS fails.
  return {
    url: `${LIGHTSAIL_ORIGIN}${HFM_API_PATH}?mt5=${encodeURIComponent(mt5)}`,
    headers: {
      Accept: 'application/json',
      'User-Agent': 'NexTradeAI/1.0',
      Host: 'nextradeai.io',
    },
  };
}

export async function GET(request: Request): Promise<Response> {
  try {
    const url = new URL(request.url);
    const mt5 = url.searchParams.get('mt5')?.trim() ?? '';

    if (!mt5) {
      return Response.json({ result: 0, error: 'Invalid MT5' }, { status: 400, headers: CORS });
    }

    if (!/^\d{6,12}$/.test(mt5)) {
      return Response.json({ result: 0, error: 'Invalid MT5' }, { status: 400, headers: CORS });
    }

    const { url: externalUrl, headers } = buildHfmLookupUrl(mt5);
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 15000);

    const response = await fetch(externalUrl, {
      method: 'GET',
      headers,
      signal: controller.signal,
    }).finally(() => clearTimeout(timer));

    if (!response.ok) {
      return Response.json(
        { result: 0, error: 'Failed to validate MT5' },
        { status: response.status, headers: CORS }
      );
    }

    const data = await response.json();
    return Response.json(data, { status: 200, headers: CORS });
  } catch (error) {
    console.error('Error validating MT5:', error);
    return Response.json({ result: 0, error: 'Error validating MT5' }, { status: 500, headers: CORS });
  }
}

export async function OPTIONS(): Promise<Response> {
  return new Response(null, { status: 200, headers: CORS });
}
