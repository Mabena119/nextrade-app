#!/usr/bin/env bun
/**
 * Send all NexTradeAI email templates to a test inbox.
 * Usage: bun scripts/send-all-test-emails.ts [recipient] [--direct]
 *
 * --direct  Send via Gmail SMTP from .env (default when relay returns 401)
 */
import { sendGmailEmail } from '../utils/gmail-smtp';

const args = process.argv.slice(2);
const forceDirect = args.includes('--direct');
const TO = args.find((a) => !a.startsWith('--')) || 'webitsolu@gmail.com';
const RELAY = 'https://nextrade-app-uklj.onrender.com/api/send-email';
const SECRET = process.env.AURAAI_EMAIL_RELAY_SECRET || '';
const APP = 'https://nextradeai.io/';
const ADMIN = 'https://nextradeai.io/admin/';
const LOGO = 'https://www.nextradeai.io/assets/img/sitelogo.png';

function wrap(title: string, content: string, cta?: { label: string; url: string }) {
  const ctaHtml = cta
    ? `<table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px auto 0;"><tr><td style="border-radius:10px;background:linear-gradient(180deg,#007bff 0%,#0056b3 100%);"><a href="${cta.url}" style="display:inline-block;padding:14px 28px;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;">${cta.label}</a></td></tr></table>`
    : '';
  return `<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#0d0d0d;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0d0d0d;padding:32px 16px;"><tr><td align="center"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#1a1a1a;border:1px solid #333;border-radius:16px;overflow:hidden;"><tr><td style="padding:28px 28px 16px;text-align:center;"><img src="${LOGO}" alt="NexTradeAI" width="64" height="64" style="display:block;margin:0 auto 16px;border-radius:12px;"><h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:800;">${title}</h1></td></tr><tr><td style="padding:8px 28px 28px;color:#d4d4d8;font-size:15px;line-height:1.65;">${content}${ctaHtml}<p style="margin:28px 0 0;color:#71717a;font-size:12px;">You received this email because of activity on your NexTradeAI account.<br>&copy; ${new Date().getFullYear()} NexTradeAI.</p></td></tr></table></td></tr></table></body></html>`;
}

const tests: { name: string; subject: string; html: string }[] = [
  {
    name: 'mentor_signup_pending',
    subject: 'NexTradeAI — Mentor registration received',
    html: wrap('Registration received', '<p>Hi <strong>Test Mentor</strong>,</p><p>Thank you for registering as an NexTradeAI mentor. Your account is <strong style="color:#fbbf24;">pending review</strong>.</p><p>Our team will verify your details and activate your admin panel.</p>', { label: 'Visit NexTradeAI', url: APP }),
  },
  {
    name: 'mentor_signup_admin',
    subject: 'NexTradeAI — New mentor signup',
    html: wrap('New mentor signup', '<p>A new mentor has signed up and is awaiting approval.</p><p><strong>Test Mentor</strong> — test@example.com</p>', { label: 'Open admin panel', url: ADMIN }),
  },
  {
    name: 'mentor_status_active',
    subject: 'NexTradeAI — Your mentor account is active',
    html: wrap('Account activated', '<p>Hi <strong>Test Mentor</strong>,</p><p>Your NexTradeAI mentor account is now <strong style="color:#22c55e;">active</strong>.</p>', { label: 'Sign in to admin', url: ADMIN }),
  },
  {
    name: 'mentor_status_pending',
    subject: 'NexTradeAI — Account pending review',
    html: wrap('Account pending', '<p>Hi <strong>Test Mentor</strong>,</p><p>Your mentor account status has been set to <strong style="color:#fbbf24;">pending</strong>.</p>', { label: 'Visit NexTradeAI', url: APP }),
  },
  {
    name: 'mentor_status_blocked',
    subject: 'NexTradeAI — Account access restricted',
    html: wrap('Account blocked', '<p>Hi <strong>Test Mentor</strong>,</p><p>Your NexTradeAI mentor account has been <strong style="color:#ef4444;">blocked</strong>.</p>'),
  },
  {
    name: 'member_whop',
    subject: 'NexTradeAI — Welcome, your membership is active',
    html: wrap('Welcome to NexTradeAI', `<p>Hi,</p><p>Your NexTradeAI membership payment was successful.</p><p>Your email <strong>${TO}</strong> is now linked to your membership.</p>`, { label: 'Get started', url: APP }),
  },
  {
    name: 'member_paystack',
    subject: 'NexTradeAI — Welcome, your membership is active',
    html: wrap('Welcome to NexTradeAI', `<p>Hi,</p><p>Your NexTradeAI membership payment was successful.</p><p>Your email <strong>${TO}</strong> is now linked to your membership.</p>`, { label: 'Get started', url: APP }),
  },
  {
    name: 'scanner_activated',
    subject: 'NexTradeAI — AI Scanner activated',
    html: wrap('AI Scanner unlocked', `<p>Hi,</p><p>Your <strong style="color:#8b5cf6;">AI Scanner</strong> is now unlocked for <strong>${TO}</strong>.</p>`, { label: 'Open NexTradeAI', url: APP }),
  },
  {
    name: 'license_key',
    subject: 'NexTradeAI — Your license key',
    html: wrap('Your license key', '<p>Hi,</p><p><strong>Test Mentor</strong> has shared an NexTradeAI license key with you.</p><div style="margin:16px 0;padding:16px 20px;background:#111;border:1px solid #007bff;border-radius:10px;text-align:center;"><span style="font-family:monospace;font-size:18px;font-weight:700;color:#60a5fa;">ABC-123-DEF-456</span></div>', { label: 'Download NexTradeAI', url: APP }),
  },
  {
    name: 'password_reset',
    subject: 'NexTradeAI — Reset your password',
    html: wrap('Reset your password', '<p>Hi <strong>Test User</strong>,</p><p>Click below to reset your NexTradeAI admin password.</p>', { label: 'Reset password', url: ADMIN }),
  },
  {
    name: 'password_reset_confirmation',
    subject: 'NexTradeAI — Password updated',
    html: wrap('Password updated', '<p>Hi,</p><p>Your NexTradeAI admin password was changed successfully.</p>'),
  },
];

async function sendViaRelay(name: string, subject: string, html: string) {
  if (!SECRET) return { ok: false as const, error: 'AURAAI_EMAIL_RELAY_SECRET not set' };
  const res = await fetch(RELAY, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'x-auraai-email-secret': SECRET,
    },
    body: JSON.stringify({
      to: TO,
      subject,
      html,
      text: subject,
      gmailUser: process.env.GMAIL_USER,
      gmailPass: process.env.GMAIL_PASS,
      fromName: process.env.GMAIL_FROM_NAME || 'NexTradeAI',
    }),
  });
  const data = await res.json().catch(() => ({}));
  const ok = res.ok && (data as { ok?: boolean }).ok;
  return { ok, error: ok ? undefined : (data as { error?: string }).error || String(res.status) };
}

async function sendDirect(subject: string, html: string) {
  return sendGmailEmail({
    to: TO,
    subject,
    html,
    text: subject,
    gmailUser: process.env.GMAIL_USER,
    gmailPass: process.env.GMAIL_PASS,
    fromName: process.env.GMAIL_FROM_NAME || 'NexTradeAI',
  });
}

let useDirect = forceDirect;

async function send(name: string, subject: string, html: string) {
  if (!useDirect) {
    const relay = await sendViaRelay(name, subject, html);
    if (relay.ok) {
      console.log(`[OK]   ${name} (relay)`);
      return true;
    }
    if (relay.error === 'Unauthorized' || relay.error === '401') {
      console.log('[info] Relay unauthorized — switching to direct Gmail SMTP for remaining tests');
      useDirect = true;
    } else if (!forceDirect) {
      console.log(`[FAIL] ${name} — relay: ${relay.error}`);
      return false;
    }
  }

  const direct = await sendDirect(subject, html);
  const ok = !!direct.ok;
  console.log(`${ok ? '[OK]  ' : '[FAIL]'} ${name}${ok ? ' (direct)' : ` — ${direct.error || 'send failed'}`}`);
  return ok;
}

console.log(`Sending ${tests.length} NexTradeAI templates to ${TO}...\n`);
let passed = 0;
for (const t of tests) {
  if (await send(t.name, t.subject, t.html)) passed++;
  await Bun.sleep(800);
}
console.log(`\nDone: ${passed}/${tests.length} sent.`);
