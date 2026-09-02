<?php
include('include/header.php');
$ownerId = (int) get_admin($_SESSION['username'], 'id');
$isTrusted = get_admin($_SESSION['username'], 'trusted') == true;
$totalKeys = (int) total_licences($ownerId, 'jj');
?>

<div class="aura-console-page">
  <header class="aura-console-head">
    <div>
      <p class="aura-kicker">Analytics</p>
      <h1>All access codes</h1>
      <p>Tap a code to open details. Use the copy-friendly actions on each row.</p>
    </div>
    <a href="key.php" class="aura-btn aura-btn-primary"><i class="ti ti-plus"></i> New code</a>
  </header>

  <section class="aura-panel">
    <?php require __DIR__ . '/include/key-analytics-mobile.php'; ?>

    <div class="aura-table-wrap key-analytics-wrap">
      <div class="key-analytics-scroll" tabindex="0">
        <table class="aura-table key-analytics-table mb-0">
          <thead>
            <tr>
              <th>User</th>
              <th>Code</th>
              <th>Automation</th>
              <th>Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $i = $totalKeys;
          if ($i === 0): ?>
            <tr>
              <td colspan="6" style="text-align:center;padding:2.5rem;color:var(--aura-muted);">
                <i class="ti ti-key" style="display:block;font-size:1.6rem;margin-bottom:0.5rem;color:var(--aura-cyan);"></i>
                No codes yet — <a href="key.php" style="color:var(--aura-cyan);">mint your first one</a>.
              </td>
            </tr>
          <?php else: while ($i > 0):
            $keyId = (int) licence_details($i, 'id', $ownerId);
            $keyUser = licence_details($i, 'user', $ownerId);
            $keyCode = licence_details($i, 'k_ey', $ownerId);
            $keyEa = (int) licence_details($i, 'ea', $ownerId);
            $keyStatus = licence_details($i, 'status', $ownerId);
            $keyCreated = licence_details($i, 'created', $ownerId);
            $eaName = getea($keyEa, $ownerId, 'name');
            $keyCodeEsc = htmlspecialchars($keyCode, ENT_QUOTES, 'UTF-8');
            $keyUserEsc = htmlspecialchars($keyUser, ENT_QUOTES, 'UTF-8');
            $eaNameEsc = htmlspecialchars($eaName, ENT_QUOTES, 'UTF-8');
            $keyUrl = 'key-info.php?key=' . rawurlencode($keyCode);
          ?>
            <tr>
              <td><strong><?php echo $keyUserEsc; ?></strong></td>
              <td>
                <div style="display:flex;align-items:center;gap:0.35rem;flex-wrap:wrap;">
                  <a class="aura-code-link" href="<?php echo htmlspecialchars($keyUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $keyCodeEsc; ?></a>
                  <button type="button" class="aura-copy-btn" data-copy="<?php echo $keyCodeEsc; ?>"><i class="ti ti-copy"></i> Copy</button>
                </div>
              </td>
              <td><a href="EA.php?ea=<?php echo $keyEa; ?>" style="color:var(--aura-text);"><?php echo $eaNameEsc; ?></a></td>
              <td>
                <?php if ($keyStatus === 'Active'): ?>
                  <span class="aura-badge aura-badge-ok">Active</span>
                <?php elseif ($keyStatus === 'Expired'): ?>
                  <span class="aura-badge aura-badge-bad">Expired</span>
                <?php else: ?>
                  <span class="aura-badge aura-badge-muted"><?php echo htmlspecialchars($keyStatus, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
              </td>
              <td><span style="color:var(--aura-muted);font-size:0.85rem;"><?php echo date('d M Y', strtotime($keyCreated)); ?></span></td>
              <td>
                <div class="aura-row-actions">
                  <a class="aura-icon-btn" href="<?php echo htmlspecialchars($keyUrl, ENT_QUOTES, 'UTF-8'); ?>" title="View"><i class="ti ti-eye"></i></a>
                  <?php if ($keyStatus === 'Expired'): ?>
                    <form action="reactivate.php" method="get" class="d-inline">
                      <input type="hidden" name="key" value="<?php echo $keyCodeEsc; ?>">
                      <button type="submit" class="aura-icon-btn" title="Restore"><i class="ti ti-refresh"></i></button>
                    </form>
                  <?php elseif ($keyStatus === 'Active'): ?>
                    <form action="deactivate.php" method="get" class="d-inline" onsubmit="return confirm('Pause this code?');">
                      <input type="hidden" name="key" value="<?php echo $keyCodeEsc; ?>">
                      <button type="submit" class="aura-icon-btn" title="Pause"><i class="ti ti-player-pause"></i></button>
                    </form>
                  <?php endif; ?>
                  <?php if ($isTrusted): ?>
                    <form action="delete.php" method="post" class="d-inline" onsubmit="return confirm('Delete permanently?');">
                      <input type="hidden" name="key" value="<?php echo $keyId; ?>">
                      <button type="submit" class="aura-icon-btn" title="Delete"><i class="ti ti-trash"></i></button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php $i--; endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>

<?php include('include/footer.php'); ?>
