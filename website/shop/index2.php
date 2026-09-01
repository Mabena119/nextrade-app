<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ip-block-guard.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/site-config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/shop-chrome.php';

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

$shopPriceDisplay = (string) NEXTRADE_SHOP_PRICE_ZAR;
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
$appUrlEsc = htmlspecialchars(NEXTRADE_APP_URL, ENT_QUOTES, 'UTF-8');
$apkUrlEsc = htmlspecialchars(NEXTRADE_APK_URL, ENT_QUOTES, 'UTF-8');
$supportEsc = htmlspecialchars(NEXTRADE_SUPPORT_EMAIL, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php nextrade_shop_head('Access · R' . $shopPriceDisplay . ' · NexTradeAI'); ?>
</head>
<body class="shop-page">
  <?php nextrade_shop_topbar(); ?>

  <main class="shop-main">
    <div class="shop-wrap">
      <header class="shop-hero">
        <p class="shop-hero__eyebrow">Membership</p>
        <h1>Get cloud access</h1>
        <p>One payment. Your VPS stays online. Control everything from the NexTradeAI app.</p>
        <div class="shop-price-tag">One-time <strong>R<?php echo htmlspecialchars($shopPriceDisplay); ?></strong></div>
      </header>

      <div class="shop-layout">
        <aside class="shop-benefits">
          <h2>What you get</h2>
          <ol class="shop-steps">
            <li>
              <span class="num">01</span>
              <span><strong>Always-on VPS</strong> — keep MetaTrader automations running 24/7 without your phone open.</span>
            </li>
            <li>
              <span class="num">02</span>
              <span><strong>Instant activation</strong> — membership links to your email right after payment.</span>
            </li>
            <li>
              <span class="num">03</span>
              <span><strong>Mobile control</strong> — manage access, licenses, and signals from the app.</span>
            </li>
            <li>
              <span class="num">04</span>
              <span><strong>No subscription</strong> — pay R<?php echo htmlspecialchars($shopPriceDisplay); ?> once. Hosting access only; EA keys come from your mentor.</span>
            </li>
          </ol>
          <p class="shop-note">
            After checkout, open the <a href="<?php echo $appUrlEsc; ?>" target="_blank" rel="noopener noreferrer">web app</a>
            or <a href="<?php echo $apkUrlEsc; ?>">Android APK</a> with the same email.
          </p>
        </aside>

        <section class="shop-checkout" aria-labelledby="checkout-title">
          <div class="shop-checkout__head">
            <div>
              <h2 id="checkout-title">Checkout</h2>
              <small>Secure payment via Paystack</small>
            </div>
            <div class="shop-checkout__due">
              Due today
              <strong>R<?php echo htmlspecialchars($shopPriceDisplay); ?></strong>
            </div>
          </div>

          <div class="checkout-form-panel" id="checkoutFormPanel">
            <div class="shop-field">
              <label for="customerEmail">Email</label>
              <input type="email" id="customerEmail" name="customerEmail" value="<?php echo $emailEsc; ?>"
                placeholder="you@example.com" autocomplete="email" required />
              <p class="shop-hint">Use this email to sign in to the app.</p>
            </div>

            <div class="shop-agree">
              <label>
                <input type="checkbox" id="termsCheck" name="termsCheck" required />
                <span>I agree to the <a href="/privacy-policy.html" target="_blank" rel="noopener">Privacy Policy</a>, <a href="/terms-of-service.html" target="_blank" rel="noopener">Terms</a>, and <a href="/terms-of-service.html#refund" target="_blank" rel="noopener">Refund Policy</a>.</span>
              </label>
              <label>
                <input type="checkbox" id="vpsLicenseCheck" name="vpsLicenseCheck" required />
                <span>I understand this is VPS hosting access — not an automation license. Keys are sold separately by a mentor.</span>
              </label>
            </div>

            <button type="button" class="shop-pay-btn payment-gate-btn" id="paystackBtn"
              data-base-url="<?php echo htmlspecialchars($paystackCheckoutBase); ?>">
              Pay R<?php echo htmlspecialchars($shopPriceDisplay); ?>
            </button>
            <p class="shop-secure">Encrypted checkout · Card payments</p>
          </div>
        </section>
      </div>

      <p class="shop-help">
        Questions? <a href="mailto:<?php echo $supportEsc; ?>"><?php echo $supportEsc; ?></a>
        · <a href="/how-to-install/">Install guide</a>
        · <a href="/">Home</a>
      </p>
    </div>
  </main>

  <div class="shop-overlay" id="checkoutOverlay" aria-hidden="true">
    <div class="shop-overlay__sheet" role="dialog" aria-labelledby="overlay-title">
      <div class="shop-overlay__bar">
        <h3 id="overlay-title">Complete payment</h3>
        <button type="button" class="shop-overlay__close" id="checkoutBackBtn">Close</button>
      </div>
      <div class="shop-overlay__loading is-active" id="checkoutLoading">Loading checkout…</div>
      <div class="shop-overlay__frame">
        <iframe id="paystackFrame" title="Paystack secure checkout" allow="payment *"></iframe>
      </div>
    </div>
  </div>

  <?php nextrade_shop_footer(); ?>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var topbar = document.getElementById('shop-topbar');
      var termsCheck = document.getElementById('termsCheck');
      var vpsLicenseCheck = document.getElementById('vpsLicenseCheck');
      var emailInput = document.getElementById('customerEmail');
      var gateBtns = document.querySelectorAll('.payment-gate-btn');
      var affiliateRef = <?php echo json_encode($affiliateRef); ?>;
      var visitorId = <?php echo json_encode($visitorId); ?>;
      var paystackBtn = document.getElementById('paystackBtn');
      var checkoutOverlay = document.getElementById('checkoutOverlay');
      var checkoutBackBtn = document.getElementById('checkoutBackBtn');
      var checkoutLoading = document.getElementById('checkoutLoading');
      var paystackFrame = document.getElementById('paystackFrame');
      var checkoutUrl = <?php echo json_encode($paystackCheckoutUrl); ?>;

      if (topbar) {
        window.addEventListener('scroll', function () {
          topbar.classList.toggle('is-scrolled', window.scrollY > 8);
        }, { passive: true });
      }

      function isValidEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
      }

      function buildCheckoutUrl() {
        var email = String(emailInput.value || '').trim();
        var base = paystackBtn ? paystackBtn.getAttribute('data-base-url') : '';
        if (!base) return checkoutUrl;
        var params = new URLSearchParams();
        if (email) params.set('email', email);
        if (affiliateRef) params.set('ref', affiliateRef);
        var qs = params.toString();
        checkoutUrl = qs ? base + '?' + qs : base;
        return checkoutUrl;
      }

      function trackAffiliate(email) {
        if (!email) return Promise.resolve();
        var payload = { email: email, vid: visitorId };
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
        var emailOk = isValidEmail(emailInput.value);
        emailInput.classList.toggle('is-invalid', emailInput.value.trim() !== '' && !emailOk);
        buildCheckoutUrl();
        var ready = canProceedToPay();
        gateBtns.forEach(function (btn) {
          btn.classList.toggle('is-ready', ready);
        });
      }

      function isMobileCheckout() {
        return window.matchMedia('(max-width: 820px)').matches || /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
      }

      function openCheckout(url) {
        if (isMobileCheckout()) {
          window.location.assign(url);
          return;
        }
        if (!checkoutOverlay || !paystackFrame) {
          window.location.assign(url);
          return;
        }
        checkoutOverlay.classList.add('is-open');
        checkoutOverlay.setAttribute('aria-hidden', 'false');
        if (checkoutLoading) checkoutLoading.classList.add('is-active');
        paystackFrame.onload = function () {
          if (checkoutLoading) checkoutLoading.classList.remove('is-active');
        };
        paystackFrame.src = url;
        document.body.style.overflow = 'hidden';
      }

      function closeCheckout() {
        if (!checkoutOverlay || !paystackFrame) return;
        checkoutOverlay.classList.remove('is-open');
        checkoutOverlay.setAttribute('aria-hidden', 'true');
        if (checkoutLoading) checkoutLoading.classList.remove('is-active');
        paystackFrame.removeAttribute('src');
        paystackFrame.onload = null;
        document.body.style.overflow = '';
      }

      termsCheck.addEventListener('change', toggleButton);
      vpsLicenseCheck.addEventListener('change', toggleButton);
      emailInput.addEventListener('input', toggleButton);
      emailInput.addEventListener('blur', toggleButton);

      if (checkoutBackBtn) checkoutBackBtn.addEventListener('click', closeCheckout);
      if (checkoutOverlay) {
        checkoutOverlay.addEventListener('click', function (e) {
          if (e.target === checkoutOverlay) closeCheckout();
        });
      }

      gateBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (!termsCheck.checked || !vpsLicenseCheck.checked) {
            alert('Please accept the terms and confirm that this purchase is hosting access only.');
            return;
          }
          var email = String(emailInput.value || '').trim();
          if (!isValidEmail(email)) {
            alert('Please enter a valid email address.');
            emailInput.focus();
            return;
          }
          var url = buildCheckoutUrl();
          trackAffiliate(email).finally(function () { openCheckout(url); });
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
