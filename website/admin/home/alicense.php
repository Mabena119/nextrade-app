<?php include("include/header.php"); ?>

<div class="aura-console-page" style="max-width:560px;">
  <header class="aura-console-head">
    <div>
      <p class="aura-kicker">Restore licence</p>
      <h1>Bring a code back online</h1>
      <p>Paste the access code and confirm — that’s it.</p>
    </div>
  </header>

  <div class="aura-panel">
    <form action="reactivate_key.php" method="get">
      <div class="aura-field">
        <label for="keyInput">Access code</label>
        <input class="aura-input" name="key" type="text" id="keyInput" placeholder="XXXX-XXXX-XXXX-XXXX" required style="font-family:ui-monospace,Menlo,Consolas,monospace;letter-spacing:0.04em;">
      </div>
      <div class="aura-field">
        <div class="aura-check">
          <label>
            <input type="checkbox" required>
            <span>I confirm I’m allowed to restore this code</span>
          </label>
        </div>
      </div>
      <button class="aura-btn aura-btn-primary aura-btn-block" type="submit"><i class="ti ti-refresh"></i> Restore licence</button>
      <p style="text-align:center;margin:1rem 0 0;"><a href="index.php" style="color:var(--aura-muted);">Cancel</a></p>
    </form>
  </div>
</div>

<?php include("include/footer.php"); ?>
