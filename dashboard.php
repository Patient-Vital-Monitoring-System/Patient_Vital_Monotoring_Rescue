<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$rescuerId = $_SESSION['rescuer_id'] ?? 1;

/* Stats */
$stats = ['transferred' => 0, 'ongoing' => 0, 'completed' => 0];
foreach ($stats as $status => &$cnt) {
    $q = $conn->query("SELECT COUNT(*) AS c FROM incident WHERE rescuer_id=$rescuerId AND status='$status'");
    $cnt = $q ? (int)$q->fetch_assoc()['c'] : 0;
}
unset($cnt);
$total = array_sum($stats);

/* Incoming count for badge */
$alertQ = $conn->query("SELECT COUNT(*) AS c FROM incident WHERE rescuer_id=$rescuerId AND status='transferred'");
$alertCount = $alertQ ? (int)$alertQ->fetch_assoc()['c'] : 0;

/* Recent incidents */
$recentQ = $conn->query("
    SELECT i.incident_id, i.incident_type, i.severity, i.status, i.start_time,
           p.full_name AS patient_name, p.age, p.gender
    FROM incident i
    JOIN patient p ON i.patient_id = p.patient_id
    WHERE i.rescuer_id = $rescuerId
    ORDER BY i.start_time DESC
    LIMIT 6
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Dashboard — RescueNet</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="page-content">

<?php include 'navbar.php'; ?>

<!-- PAGE HEADER -->
<div class="page-header">
    <h1>Dashboard</h1>
    <p>Monitor patient incidents and vitals.</p>
</div>

<!-- STAT CARDS -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-num"><?= $stats['transferred'] ?></div>
        <div class="stat-label">Transferred</div>
        <div class="stat-sub">Awaiting you</div>
    </div>
    <div class="stat-card stat-ongoing">
        <div class="stat-num"><?= $stats['ongoing'] ?></div>
        <div class="stat-label">Ongoing</div>
        <div class="stat-sub">Active now</div>
    </div>
    <div class="stat-card stat-completed">
        <div class="stat-num"><?= $stats['completed'] ?></div>
        <div class="stat-label">Completed</div>
        <div class="stat-sub">Resolved</div>
    </div>
    <div class="stat-card stat-total">
        <div class="stat-num"><?= $total ?></div>
        <div class="stat-label">Total</div>
        <div class="stat-sub">All cases</div>
    </div>
</div>

<!-- QUICK ACTIONS -->
<div class="section-card">
    <div class="section-card-header">⚡ Quick Actions</div>
    <div class="section-card-body">
        <div class="quick-actions-grid">
            <a href="incoming.php" class="action-btn">
                <span class="action-icon">📥</span>
                Incoming<br>Incidents
            </a>
            <a href="monitoring.php" class="action-btn">
                <span class="action-icon">❤️</span>
                Add Vital<br>Reading
            </a>
            <a href="complete.php" class="action-btn">
                <span class="action-icon">✅</span>
                Complete<br>Incident
            </a>
            <a href="return_device.php" class="action-btn">
                <span class="action-icon">📦</span>
                Return<br>Device
            </a>
        </div>
    </div>
</div>

<!-- RECENT INCIDENTS -->
<div class="section-card">
    <div class="section-card-header">🕐 Recent Incidents</div>
    <div class="section-card-body">
        <?php if ($recentQ && $recentQ->num_rows > 0): ?>
        <div class="incident-list">
            <?php while($row = $recentQ->fetch_assoc()):
                $sev = strtolower($row['severity']);
                $stat = strtolower($row['status']);
            ?>
            <a href="records.php?id=<?= $row['incident_id'] ?>" class="incident-card severity-<?= $sev ?>">
                <div class="incident-card-top">
                    <span class="incident-id">#<?= str_pad($row['incident_id'],4,'0',STR_PAD_LEFT) ?></span>
                    <span class="badge-pill badge-<?= $stat ?>"><?= ucfirst($stat) ?></span>
                </div>
                <div class="incident-patient"><?= htmlspecialchars($row['patient_name']) ?></div>
                <div class="incident-type"><?= htmlspecialchars($row['incident_type']) ?> · Age <?= $row['age'] ?> · <?= ucfirst($row['gender'] ?? '') ?></div>
                <div class="incident-meta">
                    <span class="badge-pill badge-<?= $sev ?>"><?= ucfirst($sev) ?></span>
                    <span class="text-muted"><?= date("M d, H:i", strtotime($row['start_time'])) ?></span>
                </div>
            </a>
            <?php endwhile; ?>
        </div>
        <a href="records.php" class="btn btn-secondary btn-full mt-2" style="margin-top:12px;">View All Records</a>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <p>No incidents assigned yet.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

</div><!-- .page-content -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
