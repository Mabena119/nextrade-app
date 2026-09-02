/** Canonical NexTrade public URLs (app + marketing site). */
export const NEXTRADE_SITE_URL = 'https://www.nextradeai.io';
export const NEXTRADE_APP_URL = 'https://nextrade-app-uklj.onrender.com';

export function isNextradeSiteHost(hostname: string): boolean {
  return (
    hostname === 'nextradeai.io' ||
    hostname === 'www.nextradeai.io'
  );
}
