<?php
require __DIR__ . '/include/require_super.php';
include 'include/header.php';
?>

<div class="aura-console-page" style="max-width:560px;">
  <header class="aura-console-head">
    <div>
      <p class="aura-kicker">Restore email</p>
      <h1>Bring an inbox back online</h1>
      <p>Paste the member email and confirm — that’s it.</p>
    </div>
  </header>

  <div class="aura-panel">
    <form action="reactivate_email.php" method="get">
      <div class="aura-field">
        <label for="emailInput">Member email</label>
        <input class="aura-input" name="email" type="email" id="emailInput" placeholder="user@example.com" required autocomplete="email">
      </div>
      <div class="aura-field">
        <div class="aura-check">
          <label>
            <input type="checkbox" required>
            <span>I confirm I’m allowed to restore this inbox</span>
          </label>
        </div>
      </div>
      <button class="aura-btn aura-btn-primary aura-btn-block" type="submit"><i class="ti ti-refresh"></i> Restore email</button>
      <p style="text-align:center;margin:1rem 0 0;"><a href="index.php" style="color:var(--aura-muted);">Cancel</a></p>
    </form>
  </div>
</div>

<?php include 'include/footer.php'; ?>
