/** MT5 logins allowed even when not under HFM IB / campaigns. */
export const IB_EXEMPT_MT5_LOGINS = ['55038840'] as const;

export function isIbExemptMt5(login: string): boolean {
  return (IB_EXEMPT_MT5_LOGINS as readonly string[]).includes(login.trim());
}
