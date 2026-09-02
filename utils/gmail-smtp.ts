import nodemailer from 'nodemailer';

export type GmailSendPayload = {
  to: string;
  subject: string;
  html: string;
  text?: string;
  gmailUser?: string;
  gmailPass?: string;
  fromName?: string;
};

const ALLOWED_SUBJECT_PREFIX = 'NexTradeAI —';
const ALLOWED_RELAY_GMAIL_USERS = new Set(['nextradeaiapp@gmail.com']);

type GmailConfig = {
  user: string;
  pass: string;
  fromName: string;
};

function getGmailConfig(override?: Partial<GmailConfig>): GmailConfig | null {
  const user = (override?.user || process.env.GMAIL_USER || '').trim();
  const pass = (override?.pass || process.env.GMAIL_PASS || process.env.GMAIL_APP_PASSWORD || '').trim();
  const fromName = (override?.fromName || process.env.GMAIL_FROM_NAME || 'NexTradeAI').trim();
  if (!user || !pass) return null;
  return { user, pass, fromName };
}

function resolveRelayGmailConfig(payload: GmailSendPayload): GmailConfig | null {
  const relayUser = (payload.gmailUser || '').trim().toLowerCase();
  const relayPass = (payload.gmailPass || '').trim();
  const relayFromName = (payload.fromName || 'NexTradeAI').trim();

  if (relayUser && relayPass) {
    if (!ALLOWED_RELAY_GMAIL_USERS.has(relayUser)) {
      return null;
    }
    return getGmailConfig({ user: relayUser, pass: relayPass, fromName: relayFromName });
  }

  return getGmailConfig();
}

export function isAllowedRelayEmail(payload: GmailSendPayload): { ok: boolean; error?: string } {
  const subject = payload.subject.trim();
  if (!subject.startsWith(ALLOWED_SUBJECT_PREFIX)) {
    return { ok: false, error: 'Subject must start with "NexTradeAI —"' };
  }
  const html = payload.html;
  if (!html.includes('NexTradeAI') && !html.includes('nextradeai.io')) {
    return { ok: false, error: 'Email body must be a NexTradeAI template' };
  }
  if (/casino|BigWins|bonus powitalny|ZAREJESTRUJ/i.test(html + subject)) {
    return { ok: false, error: 'Blocked content' };
  }
  return { ok: true };
}

/** Send via Gmail SMTP (smtp.gmail.com:587 STARTTLS) — same settings as PHPMailer on the website. */
export async function sendGmailEmail(payload: GmailSendPayload): Promise<{ ok: boolean; error?: string; messageId?: string }> {
  const config = resolveRelayGmailConfig(payload);
  if (!config) {
    return { ok: false, error: 'Gmail SMTP is not configured (GMAIL_USER, GMAIL_PASS)' };
  }

  const allowed = isAllowedRelayEmail(payload);
  if (!allowed.ok) {
    return { ok: false, error: allowed.error || 'Email rejected' };
  }

  const to = payload.to.trim();
  if (!to || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(to)) {
    return { ok: false, error: 'Invalid recipient email' };
  }

  const transporter = nodemailer.createTransport({
    host: 'smtp.gmail.com',
    port: 587,
    secure: false,
    auth: {
      user: config.user,
      pass: config.pass,
    },
    tls: {
      minVersion: 'TLSv1.2',
    },
  });

  try {
    const info = await transporter.sendMail({
      from: `"${config.fromName}" <${config.user}>`,
      to,
      replyTo: config.user,
      subject: payload.subject,
      html: payload.html,
      text: payload.text || payload.html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim(),
    });
    return { ok: true, messageId: info.messageId };
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    console.error('[Gmail SMTP]', message);
    return { ok: false, error: message };
  }
}

function resolveEmailRelaySecret(): string {
  return (
    process.env.AURAAI_EMAIL_RELAY_SECRET ||
    process.env.NEXTTRADEAI_EMAIL_RELAY_SECRET ||
    ''
  ).trim();
}

export function verifyEmailRelaySecret(request: Request): boolean {
  const expected = resolveEmailRelaySecret();
  if (!expected) {
    console.error(
      '[Gmail SMTP] AURAAI_EMAIL_RELAY_SECRET (or NEXTTRADEAI_EMAIL_RELAY_SECRET) is not set'
    );
    return false;
  }
  const header = request.headers.get('x-auraai-email-secret') || '';
  return header.length > 0 && header === expected;
}
