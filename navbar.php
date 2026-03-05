<?php
/* navbar.php — shared navigation include */
$currentPage = basename($_SERVER['PHP_SELF']);
function navLink($href, $icon, $label, $current) {
    $active = (basename($href) === $current) ? ' active' : '';
    echo "<a class=\"nav-link{$active}\" href=\"{$href}\">
        <span class=\"nav-icon\">{$icon}</span> {$label}
    </a>";
}
?>

<!-- NAVBAR TOP -->
<nav class="navbar-top">
    <button class="btn menu-toggle"
        data-bs-toggle="offcanvas"
        data-bs-target="#sidebarMenu"
        aria-controls="sidebarMenu">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div>
        <h2 class="navbar-brand">Patient Vitals Rescue
            <span>Rescuer System</span>
        </h2>
    </div>
    <div class="navbar-right">
        <?php if (isset($alertCount) && $alertCount > 0): ?>
        <a href="incoming.php" class="badge-alert" title="Incoming"><?= $alertCount ?></a>
        <?php endif; ?>
    </div>
</nav>

<!-- SIDEBAR -->
<div class="offcanvas offcanvas-start"
    data-bs-backdrop="static"
    tabindex="-1"
    id="sidebarMenu">

    <div class="offcanvas-header">
        <div class="d-flex" style="width:100%;justify-content:space-between;align-items:center;">
            <span class="offcanvas-title">Patient Vitals Rescue</span>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="sidebar-user">
            Logged in as<br>
            <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Rescuer') ?></strong>
        </div>
    </div>

    <div class="offcanvas-body">
        <div class="sidebar-section-label">Main</div>
        <?php navLink('dashboard.php',  '🏠', 'Dashboard',           $currentPage); ?>
        <?php navLink('incoming.php',   '📥', 'Incoming Incidents',  $currentPage); ?>
        <?php navLink('monitoring.php', '❤️', 'Continue Monitoring', $currentPage); ?>
        <?php navLink('complete.php',   '✅', 'Complete Incident',   $currentPage); ?>

        <hr class="sidebar-divider">
        <div class="sidebar-section-label">Records</div>
        <?php navLink('records.php',      '📋', 'Incident Records',  $currentPage); ?>
        <?php navLink('return_device.php','📦', 'Return Device',     $currentPage); ?>

        <hr class="sidebar-divider">
        <div class="sidebar-section-label">Account</div>
        <?php navLink('profile.php',  '👤', 'Profile',  $currentPage); ?>
        <?php navLink('logout.php',   '🔓', 'Logout',   $currentPage); ?>
    </div>
</div>
