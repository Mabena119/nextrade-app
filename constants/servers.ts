/** HF Markets server endpoints — EA Matrix / NexTradeAI MT5 linking. */

export interface ServerConfig {
  name: string;
  url: string;
  type: 'demo' | 'live';
  region: 'sa' | 'global';
}

export const HF_MARKETS_SERVERS: Record<string, ServerConfig> = {
  'hfmarketssa-demo': {
    name: 'HF Markets SA Demo',
    url: 'https://demo-webterminal.hfm-sa.com:1950/terminal?theme=blueRed',
    type: 'demo',
    region: 'sa',
  },
  'hfmarketssa-demo2': {
    name: 'HF Markets SA Demo 2',
    url: 'https://demo-webterminal.hfm-sa-cy.com:1953/terminal?theme=blueRed',
    type: 'demo',
    region: 'sa',
  },
  'hfmarketssa-live1': {
    name: 'HF Markets SA Live 1',
    url: 'https://live-webterminal.hfm-sa.com:1951/terminal?theme=blueRed',
    type: 'live',
    region: 'sa',
  },
  'hfmarketssa-live2': {
    name: 'HF Markets SA Live 2',
    url: 'https://live-webterminal.hfm-sa-cy.com:1952/terminal?theme=blueRed',
    type: 'live',
    region: 'sa',
  },
  'hfmarketssa-live4': {
    name: 'HF Markets SA Live 4',
    url: 'https://live4-webterminal.hfm-sa.com:1951/terminal?theme=blueRed',
    type: 'live',
    region: 'sa',
  },
  'hfmarketsglobal-live1': {
    name: 'HF Markets Global Live 1',
    url: 'https://live.webterminal-hfm.com:1951/terminal?theme=blueRed',
    type: 'live',
    region: 'global',
  },
  'hfmarketsglobal-live3': {
    name: 'HF Markets Global Live 3',
    url: 'https://live3.webterminal-hfm.com:1951/terminal?theme=blueRed',
    type: 'live',
    region: 'global',
  },
  'hfmarketsglobal-live4': {
    name: 'HF Markets Global Live 4',
    url: 'https://live4.webterminal-hfm.com:1951/terminal?theme=blueRed',
    type: 'live',
    region: 'global',
  },
};

/** Whitelist keys for MT5 server picker — exact match only. */
export const HFM_MT5_BROKERS = Object.keys(HF_MARKETS_SERVERS);

export function getServerUrl(serverKey: string): string {
  return HF_MARKETS_SERVERS[serverKey]?.url ?? '';
}

export function getDefaultServerUrl(): string {
  return getServerUrl('hfmarketssa-live1');
}

export function getServerUrlDirect(server: string): string {
  const key = (server || '').trim();
  if (HF_MARKETS_SERVERS[key]) {
    return HF_MARKETS_SERVERS[key].url;
  }
  return getDefaultServerUrl();
}

export function getServerDisplayName(serverKey: string): string {
  return HF_MARKETS_SERVERS[serverKey]?.name ?? serverKey;
}

export function getHFMarketsAuthSelectors() {
  return {
    loginForm: '#loginForm, .login-form, .auth-form, form[name="loginForm"]',
    loginField: 'input[name="login"], input[name="Login"], input[id="login"], input[placeholder*="login" i], input[placeholder*="account" i]',
    passwordField: 'input[name="password"], input[name="Password"], input[type="password"]',
    serverField: 'select[name="server"], select[name="Server"], .server-select, input[name="server"]',
    loginButton: 'button[type="submit"], .login-btn, .connect-btn, button:contains("Login"), button:contains("Connect")',
    successSelectors: [
      '.terminal-container',
      '.trading-terminal',
      '.market-watch',
      '.navigator',
      'input[placeholder*="search" i]',
      '.symbol-search',
      '.account-info',
    ],
    errorSelectors: ['.error-message', '.login-error', '.auth-failed', '[class*="error"]'],
  };
}
