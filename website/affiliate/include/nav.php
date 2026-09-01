<?php
/** Branded top navigation for affiliate dashboard */
?>
<nav class="affiliate-nav">
    <a href="https://auraai-vps.com/" class="nav-brand">
        <img src="../admin/assets/sitelogo.png" alt="NexTradeAI">
    </a>
    <span class="nav-badge"><i class="ti ti-share"></i> Affiliate Program</span>
    <div class="nav-actions">
        <?php if (!empty($_SESSION['id']) && !empty($_SESSION['username'])): ?>
        <a href="/admin/home/index.php" class="btn btn-ghost"><i class="ti ti-layout-dashboard"></i> Mentor Dashboard</a>
        <?php endif; ?>
        <a href="https://auraai-vps.com/shop/" class="btn btn-ghost" target="_blank" rel="noopener">Shop</a>
        <a href="logout.php" class="btn btn-secondary"><i class="ti ti-logout"></i> Sign Out</a>
    </div>
</nav>
