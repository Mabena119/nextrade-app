<?php
// Skip heavy DataTables assets — this page uses a plain paginated table.
$GLOBALS['admin_light_assets'] = true;

/**
 * IMPORTANT: use require (not require_once). Header → get_admin() already
 * require()'s connect.php inside a function, so require_once would skip and
 * leave $con undefined in this scope (blank Analytics page).
 */
require __DIR__ . '/../php-includes/connect.php';
require_once __DIR__ . '/include/stats-keys-cache.php';

include('include/header.php');

$ownerId = (int) get_admin($_SESSION['username'], 'id');
$isTrusted = get_admin($_SESSION['username'], 'trusted') == true;

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$bundle = nextrade_load_stats_keys_page($con, $ownerId, $page, 50, $q);
$licenceRows = $bundle['rows'];
$totalKeys = $bundle['total'];
$currentPage = $bundle['page'];
$totalPages = $bundle['pages'];

if (!function_exists('nextrade_stats_page_url')) {
    function nextrade_stats_page_url(int $page, string $q): string
    {
        $params = ['page' => max(1, $page)];
        if ($q !== '') {
            $params['q'] = $q;
        }
        return 'stats.php?' . http_build_query($params);
    }
}
?>

<div class="aura-console-page">
  <header class="aura-console-head">
    <div>
      <p class="aura-kicker">Analytics</p>
      <h1>All access codes</h1>
      <p><?php echo (int) $totalKeys; ?> code<?php echo $totalKeys === 1 ? '' : 's'; ?> · 50 per page for a fast load.</p>
    </div>
    <a href="key.php" class="aura-btn aura-btn-primary"><i class="ti ti-plus"></i> New code</a>
  </header>

  <section class="aura-panel">
    <form method="get" action="stats.php" style="display:flex;gap:0.55rem;flex-wrap:wrap;margin-bottom:1rem;align-items:center;">
      <input
        type="search"
        name="q"
        value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
        class="aura-input"
        placeholder="Search code, user, or automation"
        style="flex:1;min-width:12rem;margin:0;"
        autocomplete="off"
      />
      <button type="submit" class="aura-btn aura-btn-primary"><i class="ti ti-search"></i> Search</button>
      <?php if ($q !== ''): ?>
        <a href="stats.php" class="aura-btn aura-btn-ghost">Clear</a>
      <?php endif; ?>
    </form>

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
          <?php if ($totalKeys === 0): ?>
            <tr>
              <td colspan="6" style="text-align:center;padding:2.5rem;color:var(--aura-muted);">
                <i class="ti ti-key" style="display:block;font-size:1.6rem;margin-bottom:0.5rem;color:var(--aura-cyan);"></i>
                <?php if ($q !== ''): ?>
                  No matches for “<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>”.
                <?php else: ?>
                  No codes yet — <a href="key.php" style="color:var(--aura-cyan);">mint your first one</a>.
                <?php endif; ?>
              </td>
            </tr>
          <?php else: foreach ($licenceRows as $row):
            $keyId = (int) $row['id'];
            $keyUserEsc = htmlspecialchars($row['user'], ENT_QUOTES, 'UTF-8');
            $keyCodeEsc = htmlspecialchars($row['k_ey'], ENT_QUOTES, 'UTF-8');
            $keyEa = (int) $row['ea'];
            $keyStatus = $row['status'];
            $eaNameEsc = htmlspecialchars($row['ea_name'] !== '' ? $row['ea_name'] : '—', ENT_QUOTES, 'UTF-8');
            $createdTs = strtotime($row['created']);
            $createdLabel = $createdTs ? date('d M Y', $createdTs) : '—';
            $keyUrl = 'key-info.php?key=' . rawurlencode($row['k_ey']);
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
              <td><span style="color:var(--aura-muted);font-size:0.85rem;"><?php echo htmlspecialchars($createdLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
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
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav style="display:flex;flex-wrap:wrap;gap:0.45rem;align-items:center;justify-content:space-between;margin-top:1rem;" aria-label="Pagination">
      <p style="margin:0;color:var(--aura-muted);font-size:0.85rem;">
        Page <?php echo (int) $currentPage; ?> of <?php echo (int) $totalPages; ?>
      </p>
      <div style="display:flex;flex-wrap:wrap;gap:0.35rem;">
        <?php if ($currentPage > 1): ?>
          <a class="aura-btn aura-btn-ghost" href="<?php echo htmlspecialchars(nextrade_stats_page_url($currentPage - 1, $q), ENT_QUOTES, 'UTF-8'); ?>">Prev</a>
        <?php endif; ?>
        <?php
          $windowStart = max(1, $currentPage - 2);
          $windowEnd = min($totalPages, $currentPage + 2);
          for ($p = $windowStart; $p <= $windowEnd; $p++):
        ?>
          <a
            class="aura-btn <?php echo $p === $currentPage ? 'aura-btn-primary' : 'aura-btn-ghost'; ?>"
            href="<?php echo htmlspecialchars(nextrade_stats_page_url($p, $q), ENT_QUOTES, 'UTF-8'); ?>"
          ><?php echo (int) $p; ?></a>
        <?php endfor; ?>
        <?php if ($currentPage < $totalPages): ?>
          <a class="aura-btn aura-btn-ghost" href="<?php echo htmlspecialchars(nextrade_stats_page_url($currentPage + 1, $q), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
        <?php endif; ?>
      </div>
    </nav>
    <?php endif; ?>
  </section>
</div>

<?php include('include/footer.php'); ?>
