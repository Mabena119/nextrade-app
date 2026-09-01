const HFM_API_BASE =
  process.env.HFM_IB_API_URL?.replace(/\/$/, '') ||
  'https://nextradeai.io/admin/api/hfmapi/index.php';

const CORS = {
  'Content-Type': 'application/json',
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Methods': 'GET, OPTIONS',
  'Access-Control-Allow-Headers': 'Content-Type',
};

export async function GET(request: Request): Promise<Response> {
  try {
    const url = new URL(request.url);
    const mt5 = url.searchParams.get('mt5');

    if (!mt5?.trim()) {
      return Response.json({ result: 0, error: 'Invalid MT5' }, { status: 400, headers: CORS });
    }

    if (!/^\d{6,12}$/.test(mt5.trim())) {
      return Response.json({ result: 0, error: 'Invalid MT5' }, { status: 400, headers: CORS });
    }

    const externalUrl = `${HFM_API_BASE}/?mt5=${encodeURIComponent(mt5.trim())}`;
    const response = await fetch(externalUrl, {
      method: 'GET',
      headers: {
        Accept: 'application/json',
        'User-Agent': 'NexTradeAI/1.0',
      },
    });

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
