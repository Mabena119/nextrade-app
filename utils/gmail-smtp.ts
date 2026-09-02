import nodemailer from 'nodemailer';

export type GmailSendPayload = {
  to: string;
  subject: string;
  html: string;
  text?: string;
};

const ALLOWED_SUBJECT_PREFIX = 'NexTradeAI —';

function getGmailConfig() {
  const user = process.env.GMAIL_USER || '';
  const pass = process.env.GMAIL_PASS || process.env.GMAIL_APP_PASSWORD || '';
  const fromName = process.env.GMAIL_FROM_NAME || 'NexTradeAI';
  if (!user || !pass) return null;
  return { user, pass, fromName };
}

export function isAllowedRelayEmail(payload: GmailSendPayload): { ok: boolean; error?: string } {
  const subject = payload.subject.trim();
  if (!subject.startsWith(ALLOWED_SUBJECT_PREFIX)) {
    return { ok: false, error: 'Subject must start with "NexTradeAI —"' };
  }
  const html = payload.html;
  if (!html.includes('Aura AI') && !html.includes('NexTradeAI') && !html.includes('nextradeai.io')) {
    return { ok: false, error: 'Email body must be an Aura AI template' };
  }
  if (/casino|BigWins|bonus powitalny|ZAREJESTRUJ/i.test(html + subject)) {
    return { ok: false, error: 'Blocked content' };
  }
  return { ok: true };
}

/** Send via Gmail SMTP (smtp.gmail.com:587 STARTTLS) — same settings as PHPMailer on the website. */
export async function sendGmailEmail(payload: GmailSendPayload): Promise<{ ok: boolean; error?: string; messageId?: string }> {
  const config = getGmailConfig();
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

export function verifyEmailRelaySecret(request: Request): boolean {
  const expected = process.env.AURAAI_EMAIL_RELAY_SECRET || '';
  if (!expected) {
    console.error('[Gmail SMTP] AURAAI_EMAIL_RELAY_SECRET is not set');
    return false;
  }
  const header = request.headers.get('x-auraai-email-secret') || '';
  return header.length > 0 && header === expected;
}
