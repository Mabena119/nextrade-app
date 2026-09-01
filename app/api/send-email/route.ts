import { isAllowedRelayEmail, sendGmailEmail, verifyEmailRelaySecret } from '@/utils/gmail-smtp';

/**
 * POST /api/send-email
 * Relay for auraai-vps.com cPanel (outbound SMTP blocked) → Gmail SMTP.
 * Headers: x-auraai-email-secret
 * Body: { to, subject, html, text? }
 */
export async function POST(request: Request): Promise<Response> {
  if (!verifyEmailRelaySecret(request)) {
    return Response.json({ ok: false, error: 'Unauthorized' }, { status: 401 });
  }

  try {
    const body = await request.json().catch(() => ({}));
    const to = typeof body?.to === 'string' ? body.to.trim() : '';
    const subject = typeof body?.subject === 'string' ? body.subject.trim() : '';
    const html = typeof body?.html === 'string' ? body.html : '';
    const text = typeof body?.text === 'string' ? body.text : undefined;

    if (!to || !subject || !html) {
      return Response.json({ ok: false, error: 'to, subject, html required' }, { status: 400 });
    }

    const allowed = isAllowedRelayEmail({ to, subject, html, text });
    if (!allowed.ok) {
      return Response.json({ ok: false, error: allowed.error || 'Email rejected' }, { status: 403 });
    }

    const result = await sendGmailEmail({ to, subject, html, text });
    if (!result.ok) {
      return Response.json({ ok: false, error: result.error || 'Send failed' }, { status: 502 });
    }

    return Response.json({ ok: true, messageId: result.messageId }, { status: 200 });
  } catch (error) {
    console.error('send-email error:', error);
    return Response.json({ ok: false, error: 'Server error' }, { status: 500 });
  }
}

export async function GET(): Promise<Response> {
  return Response.json({ ok: true, service: 'send-email' });
}
