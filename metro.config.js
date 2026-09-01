const { getDefaultConfig } = require('expo/metro-config');
const http = require('http');

const config = getDefaultConfig(__dirname);

// Exclude API routes from mobile builds (they're only for web/server)
config.resolver.blacklistRE = /app\/api\/.*/;

const MT5_PROXY_TARGET = process.env.MT5_PROXY_TARGET || 'http://127.0.0.1:3000';

function proxyToMt5Server(req, res) {
  const targetUrl = new URL(req.url || '/', MT5_PROXY_TARGET);
  const headers = { ...req.headers, host: targetUrl.host };
  delete headers['accept-encoding'];

  const proxyReq = http.request(
    {
      hostname: targetUrl.hostname,
      port: targetUrl.port,
      path: `${targetUrl.pathname}${targetUrl.search}`,
      method: req.method,
      headers,
    },
    (proxyRes) => {
      res.writeHead(proxyRes.statusCode || 502, proxyRes.headers);
      proxyRes.pipe(res);
    }
  );

  proxyReq.on('error', (err) => {
    if (!res.headersSent) {
      res.writeHead(502, { 'Content-Type': 'text/plain' });
    }
    res.end(`MT5 proxy unavailable (${MT5_PROXY_TARGET}): ${err.message}`);
  });

  if (req.method === 'GET' || req.method === 'HEAD') {
    proxyReq.end();
    return;
  }

  req.pipe(proxyReq);
}

config.server = {
  ...config.server,
  enhanceMiddleware: (middleware) => {
    return (req, res, next) => {
      const url = req.url || '';
      if (url.startsWith('/api/') || url.startsWith('/terminal/')) {
        proxyToMt5Server(req, res);
        return;
      }
      return middleware(req, res, next);
    };
  },
};

module.exports = config;
