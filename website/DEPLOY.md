# NexTradeAI Website — Email System Deployment

Deploy the `website/` folder to **cPanel → File Manager → `public_html/`** on [auraai-vps.com](https://auraai-vps.com).

## 1. Upload core files

Copy these to the server (paths relative to `public_html/`):

| Local file | Server path |
|---|---|
| `website/includes/email-hooks.php` | `includes/email-hooks.php` |
| `website/includes/email-config.php` | `includes/email-config.php` |
| `website/includes/mailer.php` | `includes/mailer.php` |
| `website/includes/auraai-emails.php` | `includes/auraai-emails.php` |
| `website/includes/bootstrap.php` | `includes/bootstrap.php` |
| `website/admin/home/send_license_email.php` | `admin/home/send_license_email.php` |

## 2. Integrate into existing PHP files

Open each file on the server and add the code from the matching snippet **after** the database action succeeds.

| Event | Server file | Snippet |
|---|---|---|
| Mentor signup | `admin/user_request.php` | `integrations/user_request.snippet.php` |
| Mentor status change | `admin/home/users.php` | `integrations/users.snippet.php` |
| Paystack / shop payment | `shop/notifyb.php` | `integrations/notifyb.snippet.php` |
| Whop webhook | `shop/webhook1.php` | `integrations/webhook1.snippet.php` |
| License key email option | `admin/home/alicense.php` | `integrations/alicense.snippet.php` |

### Example — `admin/user_request.php`

After the mentor INSERT succeeds, before the success HTML:

```php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
auraai_email_bootstrap();

$signupEmail = trim($email);
$signupDisplay = trim($displayname);
$signupPhone = trim($phone);

if ($signupEmail !== '') {
    auraai_email_mentor_signup_pending($signupEmail, $signupDisplay);
    auraai_email_mentor_signup_admin($signupEmail, $signupDisplay, $signupPhone);
}
```

### Example — `shop/notifyb.php` / `shop/webhook1.php`

After `members.paid = 1` (and optionally `members.scanner = 1` for AI Scanner):

```php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
auraai_email_bootstrap();

$memberEmail = trim($email); // your extracted customer email
$isScanner = true;           // set true when scanner column is activated

if ($memberEmail !== '') {
    auraai_email_member_payment_success($memberEmail, 'Paystack', $isScanner); // or 'Whop'
}
```

## 3. License key — send via email

**Option A — checkbox on generate form**  
Follow Part A + Part B in `integrations/alicense.snippet.php`.

**Option B — AJAX after generation**

```javascript
fetch('send_license_email.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    license_key: generatedKey,
    recipient_email: document.getElementById('license_email').value,
    ea_name: 'My EA'
  })
});
```

## 4. Test SMTP

1. Upload `website/test-email.php` → `public_html/test-email.php`
2. Edit the `AURAAI_EMAIL_TEST_KEY` constant in that file
3. Visit: `https://auraai-vps.com/test-email.php?to=YOUR_EMAIL&key=YOUR_SECRET`
4. **Delete `test-email.php`** after confirming delivery

## Email scenarios covered

| Trigger | Recipient | Email |
|---|---|---|
| Mentor signs up | Mentor + admin | Pending confirmation + admin alert |
| Status → Active | Mentor | Account activated |
| Status → Pending | Mentor | Still under review |
| Status → Blocked | Mentor | Access restricted |
| Paystack payment | Member | Welcome + scanner note if applicable |
| Whop payment | Member | Welcome + scanner note if applicable |
| Scanner activated | Member | AI Scanner unlocked |
| License generated (optional) | Client | License key |

## Gmail credentials

Configured in `includes/email-config.php`:

- **From:** NexTradeAI `<auraaiio@gmail.com>`
- **SMTP:** Gmail app password (port 587, STARTTLS)

If emails fail, verify the Gmail app password is still valid and that "Less secure app access" / App Passwords are enabled for the Google account.
