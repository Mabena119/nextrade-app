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

<div class="row mb-4">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
      <div>
        <h2 class="dashboard-title">Reactivate Email</h2>
        <p class="dashboard-subtitle">Restore a member email so they can sign in to the app again</p>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-8 col-xl-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center mb-4">
          <div class="icon-badge mr-3" style="width: 56px; height: 56px; border-radius: 12px; background: rgba(34, 197, 94, 0.2); display: flex; align-items: center; justify-content: center;">
            <i class="ti ti-mail" style="font-size: 1.75rem; color: #22c55e;"></i>
          </div>
          <div>
            <h4 class="card-title mb-1">Reactivate member email</h4>
            <p class="text-muted mb-0">Enter the email address you want to reactivate</p>
          </div>
        </div>

        <form action="reactivate_email.php" method="get">
          <div class="form-group">
            <label for="emailInput">Member email</label>
            <input
              name="email"
              type="email"
              class="form-control"
              id="emailInput"
              placeholder="user@example.com"
              required
              autocomplete="email"
            >
            <small class="form-text text-muted">The email must already exist in the members table.</small>
          </div>

          <div class="form-group">
            <div class="form-check" style="background: rgba(34, 197, 94, 0.05); padding: 1rem; border-radius: 8px; border: 1px solid rgba(34, 197, 94, 0.2);">
              <label class="form-check-label" style="display: flex; align-items: center; cursor: pointer; margin: 0;">
                <input type="checkbox" class="form-check-input" required style="margin-right: 0.75rem; margin-top: 0;">
                <span style="font-weight: 500;">I confirm that I have permission to reactivate this email</span>
              </label>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="ti ti-refresh mr-2"></i>Reactivate Email
            </button>
            <a href="index.php" class="btn btn-light">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include 'include/footer.php'; ?>
