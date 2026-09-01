<?php
session_start();
require dirname(__DIR__) . '/php-includes/connect.php';
require dirname(__DIR__) . '/php-includes/functions.php';
if (!isset($_SESSION['id']) || !get_admin($_SESSION['username'], 'super')) {
    header('Location: index.php');
    exit();
}
include 'include/header.php';
?>

<div class="aura-console-page" style="max-width:560px;">
  <header class="aura-console-head">
    <div>
      <p class="aura-kicker">Restore inbox</p>
      <h1>Reactivate member email</h1>
      <p>Let a member sign into the app again with their email.</p>
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
      <button class="aura-btn aura-btn-primary aura-btn-block" type="submit"><i class="ti ti-refresh"></i> Restore inbox</button>
    </form>
  </div>
</div>

<?php include 'include/footer.php'; ?>
