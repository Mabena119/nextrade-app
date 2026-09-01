<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ip-block-guard.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';

$email = '';
$visitorId = auraai_affiliate_ensure_visitor_cookie(
    auraai_affiliate_normalize_visitor($_GET['vid'] ?? '')
);

$refCode = auraai_affiliate_normalize_code($_GET['ref'] ?? '');
if ($refCode !== null) {
    require('../admin/php-includes/connect.php');
    auraai_affiliate_set_ref_cookie($refCode);
    auraai_affiliate_record_attribution_signals($con, $refCode, $visitorId);
    auraai_affiliate_track_click($con, $refCode, '/shop/', $visitorId);
}

if (isset($_GET['email']) && !empty($_GET['email'])) {
    if (!isset($con)) {
        require('../admin/php-includes/connect.php');
    }
    $safeEmail = auraai_sec_email($_GET['email']);
    $email = $safeEmail ?? '';
}

if (!isset($con)) {
    require('../admin/php-includes/connect.php');
}

$affiliateRef = auraai_affiliate_resolve_ref_for_session($con, $refCode, $visitorId) ?? '';

if ($email !== '') {
    auraai_affiliate_ensure_email_attribution($con, $email, $refCode ?: null, $visitorId);
    if ($affiliateRef === '') {
        $bound = auraai_affiliate_resolve_by_email($con, $email);
        if ($bound) {
            $affiliateRef = (string) $bound['code'];
        }
    }
}

$shopPriceDisplay = '649';
$paystackCheckoutBase = 'https://paystack.shop/pay/qhnur7yjsr';
$paystackCheckoutUrl = $paystackCheckoutBase;
if ($email !== '') {
    $paystackCheckoutUrl .= '?email=' . rawurlencode($email);
    if ($affiliateRef !== '') {
        $paystackCheckoutUrl .= '&ref=' . rawurlencode($affiliateRef);
    }
} elseif ($affiliateRef !== '') {
    $paystackCheckoutUrl .= '?ref=' . rawurlencode($affiliateRef);
}
$emailEsc = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#020B18">
  <title>Checkout · NexTradeAI</title>
  <link rel="icon" type="image/png" href="../assets/img/sitelogo.png" />
  <link rel="stylesheet" href="/assets/css/platform.css" />
  <style>
    body.checkout {
      min-height: 100vh;
      margin: 0;
      display: grid;
      grid-template-columns: 1.05fr 0.95fr;
    }
    .stage-left, .stage-right { position: relative; z-index: 1; min-height: 100vh; }
    .stage-left {
      padding: clamp(1.5rem, 4vw, 3rem);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      border-right: 1px solid var(--aura-border);
      background:
        radial-gradient(600px 380px at 20% 10%, rgba(0,229,255,.18), transparent 60%),
        linear-gradient(165deg, #07090f 0%, #000000 55%, #0a0e16 100%);
    }
    .stage-right {
      padding: clamp(1.5rem, 4vw, 3rem);
      display: grid;
      place-items: center;
      background: #000000;
    }
    .brand-row {
      display: flex; align-items: center; gap: .7rem;
      font-family: var(--aura-font-display); font-weight: 700; font-size: 1.1rem;
    }
    .brand-row img { width: 44px; height: 44px; filter: drop-shadow(0 0 16px var(--aura-cyan-glow)); }
    .brand-row b { color: var(--aura-cyan); }
    .hero-copy { margin: 3rem 0 auto; max-width: 28rem; }
    .hero-copy .kicker {
      color: var(--aura-cyan); font-size: .72rem; font-weight: 700;
      letter-spacing: .14em; text-transform: uppercase; margin: 0 0 .8rem;
    }
    .hero-copy h1 {
      margin: 0 0 .9rem; font-family: var(--aura-font-display);
      font-size: clamp(2rem, 4vw, 2.8rem); letter-spacing: -.04em; line-height: 1.05;
    }
    .hero-copy p { margin: 0 0 1.5rem; color: var(--aura-muted); line-height: 1.55; }
    .perks { list-style: none; margin: 0; padding: 0; display: grid; gap: .7rem; }
    .perks li {
      display: flex; gap: .7rem; align-items: flex-start;
      color: var(--aura-muted); font-size: .95rem;
    }
    .perks i {
      width: 22px; height: 22px; border-radius: 50%;
      background: rgba(0,229,255,.12); color: var(--aura-cyan);
      display: grid; place-items: center; font-style: normal; font-size: .75rem; flex: 0 0 auto;
      border: 1px solid var(--aura-border);
    }
    .foot-mini { color: var(--aura-muted-dim); font-size: .85rem; }
    .foot-mini a { color: var(--aura-cyan); }

    .ticket {
      width: min(440px, 100%);
      border-radius: 28px;
      border: 1px solid var(--aura-border);
      background:
        linear-gradient(160deg, rgba(255,255,255,.04), transparent 42%),
        linear-gradient(180deg, #0c1c30, #07111f);
      box-shadow: 0 30px 80px rgba(0,0,0,.55);
      overflow: hidden;
    }
    .ticket-top {
      padding: 1.4rem 1.4rem 1.2rem;
      border-bottom: 1px dashed rgba(0,229,255,.22);
      display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start;
    }
    .ticket-top h2 {
      margin: .35rem 0 0; font-family: var(--aura-font-display);
      font-size: 1.45rem; letter-spacing: -.03em;
    }
    .chip {
      display: inline-flex; padding: .28rem .65rem; border-radius: 999px;
      background: rgba(0,229,255,.1); border: 1px solid var(--aura-border);
      color: var(--aura-cyan); font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
    }
    .price-block { text-align: right; }
    .price-block small { display: block; color: var(--aura-muted-dim); font-size: .75rem; }
    .price-block strong {
      font-family: var(--aura-font-display); font-size: 2rem; color: var(--aura-cyan);
      letter-spacing: -.04em;
    }
    .ticket-body { padding: 1.35rem 1.4rem 1.5rem; }
    .field { margin-bottom: 1rem; }
    .field label { display: block; margin-bottom: .4rem; font-size: .86rem; font-weight: 600; color: #cbd5e1; }
    .field input[type="email"] {
      width: 100%; background: #020b18; border: 1px solid #1a3a52; color: #fff;
      border-radius: 12px; padding: .9rem 1rem; font: inherit;
    }
    .field input:focus { outline: none; border-color: var(--aura-cyan); box-shadow: 0 0 0 3px rgba(0,229,255,.12); }
    .field input.is-invalid { border-color: #f87171; }
    .hint { margin: .35rem 0 0; color: var(--aura-muted-dim); font-size: .8rem; }
    .checks { display: grid; gap: .75rem; margin: 1.1rem 0 1.25rem; }
    .checks label {
      display: grid; grid-template-columns: 20px 1fr; gap: .7rem; align-items: start;
      color: var(--aura-muted); font-size: .86rem; line-height: 1.45; cursor: pointer;
    }
    .checks input { margin-top: .2rem; accent-color: var(--aura-cyan); }
    .checks a { color: var(--aura-cyan); }
    .pay-btn {
      width: 100%; border: 0; border-radius: 14px; padding: 1rem 1.1rem;
      font: inherit; font-weight: 800; cursor: not-allowed;
      background: linear-gradient(135deg, #5ef6ff, #00a8ff); color: #021018;
      box-shadow: 0 14px 36px rgba(0,229,255,.28); opacity: .55; pointer-events: none;
      text-decoration: none; display: flex; justify-content: center; align-items: center; gap: .5rem;
    }
    .pay-btn.is-ready { opacity: 1; pointer-events: auto; cursor: pointer; }
    .secure {
      margin-top: .9rem; text-align: center; color: var(--aura-muted-dim); font-size: .8rem;
    }

    .checkout-pay-panel {
      display: none;
      flex-direction: column;
      gap: .9rem;
      min-height: 520px;
    }
    .checkout-pay-panel.is-active { display: flex; }
    .checkout-form-panel.is-hidden { display: none; }
    .checkout-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
    }
    .checkout-toolbar h3 {
      margin: 0;
      font-family: var(--aura-font-display);
      font-size: 1.05rem;
      letter-spacing: -.02em;
    }
    .checkout-back-btn {
      border: 1px solid rgba(255,255,255,.12);
      background: rgba(255,255,255,.06);
      color: #fff;
      border-radius: 10px;
      padding: .55rem .85rem;
      font: inherit;
      font-size: .82rem;
      font-weight: 600;
      cursor: pointer;
    }
    .checkout-back-btn:hover { background: rgba(255,255,255,.1); }
    .checkout-frame-wrap {
      flex: 1;
      min-height: 460px;
      border-radius: 16px;
      overflow: hidden;
      border: 1px solid rgba(255,255,255,.08);
      background: #fff;
    }
    .checkout-frame-wrap iframe {
      width: 100%;
      height: 100%;
      min-height: 460px;
      border: 0;
      display: block;
      background: #fff;
    }
    .checkout-loading {
      display: none;
      align-items: center;
      justify-content: center;
      gap: .6rem;
      color: var(--aura-muted);
      font-size: .88rem;
      padding: 1rem 0;
    }
    .checkout-loading.is-active { display: flex; }

    @media (max-width: 900px) {
      body.checkout { grid-template-columns: 1fr; }
      .stage-left { min-height: auto; border-right: 0; border-bottom: 1px solid var(--aura-border); }
      .hero-copy { margin: 1.5rem 0; }
      .stage-right { min-height: auto; padding-bottom: 2.5rem; }
    }
  </style>
</head>
<body class="aura-platform checkout">
  <div class="aura-atmosphere" aria-hidden="true"></div>

  <section class="stage-left">
    <a class="brand-row" href="/">
      <img src="../assets/img/sitelogo.png" alt="NexTradeAI" />
      <span>Aura<b>AI</b>VPS</span>
    </a>

    <div class="hero-copy">
      <p class="kicker">Cloud access</p>
      <h1>Unlock your always-on trading VPS</h1>
      <p>One payment. Instant membership. Run MetaTrader EAs from your phone — 24/7.</p>
      <ul class="perks">
        <li><i>✓</i><span>High-performance cloud VPS access</span></li>
        <li><i>✓</i><span>Activated automatically after payment</span></li>
        <li><i>✓</i><span>Mobile-first control for traders</span></li>
      </ul>
    </div>

    <p class="foot-mini">
      Need help? <a href="mailto:auraaiio@gmail.com">auraaiio@gmail.com</a>
      · <a href="/">Back home</a>
    </p>
  </section>

  <section class="stage-right">
    <div class="ticket">
      <div class="ticket-top">
        <div>
          <span class="chip">One-time</span>
          <h2>NexTradeAI</h2>
        </div>
        <div class="price-block">
          <small>Due today</small>
          <strong>R<?php echo htmlspecialchars($shopPriceDisplay); ?></strong>
        </div>
      </div>
      <div class="ticket-body">
        <div class="checkout-form-panel" id="checkoutFormPanel">
          <div class="field">
            <label for="customerEmail">Membership email</label>
            <input type="email" id="customerEmail" name="customerEmail" value="<?php echo $emailEsc; ?>"
              placeholder="you@example.com" autocomplete="email" required />
            <p class="hint">Access is linked to this email — use the same one in the app.</p>
          </div>

          <div class="checks">
            <label>
              <input type="checkbox" id="termsCheck" name="termsCheck" required />
              <span>I agree to the <a href="../privacy-policy.html" target="_blank" rel="noopener">Privacy Policy</a>, <a href="../terms-of-service.html" target="_blank" rel="noopener">Terms</a>, and <a href="../terms-of-service.html#refund" target="_blank" rel="noopener">Refund Policy</a>.</span>
            </label>
            <label>
              <input type="checkbox" id="vpsLicenseCheck" name="vpsLicenseCheck" required />
              <span>I understand NexTradeAI is hosting access — not an automation license. Automation keys are purchased separately from a mentor.</span>
            </label>
          </div>

          <button type="button"
             class="pay-btn payment-gate-btn" id="paystackBtn"
             data-base-url="<?php echo htmlspecialchars($paystackCheckoutBase); ?>">
            Continue to secure checkout
          </button>
          <p class="secure">Card payments via Paystack · Encrypted checkout</p>
        </div>

        <div class="checkout-pay-panel" id="checkoutPayPanel" aria-hidden="true">
          <div class="checkout-toolbar">
            <h3>Secure checkout</h3>
            <button type="button" class="checkout-back-btn" id="checkoutBackBtn">← Back</button>
          </div>
          <div class="checkout-loading is-active" id="checkoutLoading">
            <span>Loading Paystack checkout…</span>
          </div>
          <div class="checkout-frame-wrap">
            <iframe id="paystackFrame" title="Paystack secure checkout" allow="payment *"></iframe>
          </div>
          <p class="secure">Complete payment below — your membership activates automatically.</p>
        </div>
      </div>
    </div>
  </section>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const termsCheck = document.getElementById('termsCheck');
      const vpsLicenseCheck = document.getElementById('vpsLicenseCheck');
      const emailInput = document.getElementById('customerEmail');
      const gateBtns = document.querySelectorAll('.payment-gate-btn');
      const affiliateRef = <?php echo json_encode($affiliateRef); ?>;
      const visitorId = <?php echo json_encode($visitorId); ?>;
      const paystackBtn = document.getElementById('paystackBtn');
      const checkoutFormPanel = document.getElementById('checkoutFormPanel');
      const checkoutPayPanel = document.getElementById('checkoutPayPanel');
      const checkoutBackBtn = document.getElementById('checkoutBackBtn');
      const checkoutLoading = document.getElementById('checkoutLoading');
      const paystackFrame = document.getElementById('paystackFrame');
      let checkoutUrl = <?php echo json_encode($paystackCheckoutUrl); ?>;

      function isValidEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
      }

      function buildCheckoutUrl() {
        const email = String(emailInput.value || '').trim();
        const base = paystackBtn ? paystackBtn.getAttribute('data-base-url') : '';
        if (!base) return checkoutUrl;
        const params = new URLSearchParams();
        if (email) params.set('email', email);
        if (affiliateRef) params.set('ref', affiliateRef);
        const qs = params.toString();
        checkoutUrl = qs ? base + '?' + qs : base;
        return checkoutUrl;
      }

      function trackAffiliate(email) {
        if (!email) return Promise.resolve();
        const payload = { email: email, vid: visitorId };
        if (affiliateRef) payload.ref = affiliateRef;
        return fetch('track-ref.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        }).catch(function () {});
      }

      function canProceedToPay() {
        return Boolean(
          termsCheck && termsCheck.checked &&
          vpsLicenseCheck && vpsLicenseCheck.checked &&
          isValidEmail(emailInput.value)
        );
      }

      function toggleButton() {
        const emailOk = isValidEmail(emailInput.value);
        emailInput.classList.toggle('is-invalid', emailInput.value.trim() !== '' && !emailOk);
        buildCheckoutUrl();
        const ready = canProceedToPay();
        gateBtns.forEach(function (btn) {
          btn.classList.toggle('is-ready', ready);
        });
      }

      function showCheckoutPanel(url) {
        if (!checkoutFormPanel || !checkoutPayPanel || !paystackFrame) {
          window.location.assign(url);
          return;
        }
        checkoutFormPanel.classList.add('is-hidden');
        checkoutPayPanel.classList.add('is-active');
        checkoutPayPanel.setAttribute('aria-hidden', 'false');
        if (checkoutLoading) checkoutLoading.classList.add('is-active');
        paystackFrame.onload = function () {
          if (checkoutLoading) checkoutLoading.classList.remove('is-active');
        };
        paystackFrame.src = url;
        checkoutPayPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }

      function hideCheckoutPanel() {
        if (!checkoutFormPanel || !checkoutPayPanel || !paystackFrame) return;
        checkoutPayPanel.classList.remove('is-active');
        checkoutPayPanel.setAttribute('aria-hidden', 'true');
        checkoutFormPanel.classList.remove('is-hidden');
        if (checkoutLoading) checkoutLoading.classList.remove('is-active');
        paystackFrame.removeAttribute('src');
        paystackFrame.onload = null;
      }

      termsCheck.addEventListener('change', toggleButton);
      vpsLicenseCheck.addEventListener('change', toggleButton);
      emailInput.addEventListener('input', toggleButton);
      emailInput.addEventListener('blur', toggleButton);

      if (checkoutBackBtn) {
        checkoutBackBtn.addEventListener('click', hideCheckoutPanel);
      }

      gateBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (!termsCheck.checked || !vpsLicenseCheck.checked) {
            alert('Please accept the terms and confirm that NexTradeAI does not include an automation license.');
            return;
          }
          const email = String(emailInput.value || '').trim();
          if (!isValidEmail(email)) {
            alert('Please enter a valid email address to continue.');
            emailInput.focus();
            return;
          }
          const url = buildCheckoutUrl();
          trackAffiliate(email).finally(function () {
            showCheckoutPanel(url);
          });
        });
      });

      toggleButton();
      if (isValidEmail(emailInput.value)) {
        trackAffiliate(String(emailInput.value).trim());
      }
    });
  </script>
</body>
</html>
