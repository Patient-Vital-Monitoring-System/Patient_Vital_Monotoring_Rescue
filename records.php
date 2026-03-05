<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$rescuerId = $_SESSION['rescuer_id'] ?? 1;

/* Filter */
$filterStatus = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$search       = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';

$where = "WHERE i.rescuer_id = $rescuerId";
if ($filterStatus) $where .= " AND i.status='$filterStatus'";
if ($search) $where .= " AND (p.full_name LIKE '%$search%' OR i.incident_type LIKE '%$search%')";

$recordsQ = $conn->query("
    SELECT i.incident_id, i.incident_type, i.severity, i.status,
           i.start_time, i.end_time, i.outcome,
           p.full_name, p.age, p.gender,
           (SELECT COUNT(*) FROM vitalstat v WHERE v.incident_id = i.incident_id) AS vital_count
    FROM incident i
    JOIN patient p ON i.patient_id = p.patient_id
    $where
    ORDER BY i.start_time DESC
    LIMIT 50
");

/* Detail view */
$detailId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$detail = null;
$allVitals = [];

if ($detailId > 0) {
    $dq = $conn->query("
        SELECT i.*, p.full_name, p.age, p.gender, p.blood_type, p.allergies,
               p.medical_history, p.contact_number
        FROM incident i
        JOIN patient p ON i.patient_id = p.patient_id
        WHERE i.incident_id=$detailId AND i.rescuer_id=$rescuerId
        LIMIT 1
    ");
    if ($dq && $dq->num_rows > 0) {
        $detail = $dq->fetch_assoc();
        $vq = $conn->query("
            SELECT * FROM vitalstat WHERE incident_id=$detailId ORDER BY recorded_at ASC
        ");
        $allVitals = $vq ? $vq->fetch_all(MYSQLI_ASSOC) : [];
    }
}

$alertQ = $conn->query("SELECT COUNT(*) AS c FROM incident WHERE rescuer_id=$rescuerId AND status='transferred'");
$alertCount = $alertQ ? (int)$alertQ->fetch_assoc()['c'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Incident Records — RescueNet</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="page-content">

<?php include 'navbar.php'; ?>

<?php if ($detail): /* FULL DETAIL VIEW */ ?>

<div style="padding:12px 16px 0;">
    <a href="records.php" class="btn btn-secondary" style="padding:8px 14px;font-size:0.85rem;">← Back</a>
</div>

<!-- PATIENT HEADER -->
<div class="patient-header">
    <div class="patient-name"><?= htmlspecialchars($detail['full_name']) ?></div>
    <div class="patient-meta">
        Age <?= $detail['age'] ?> · <?= ucfirst($detail['gender'] ?? '') ?> · Blood: <?= $detail['blood_type'] ?? 'Unknown' ?>
    </div>
    <div class="patient-badges">
        <span class="badge-white"><?= htmlspecialchars($detail['incident_type']) ?></span>
        <span class="badge-white"><?= ucfirst($detail['severity']) ?></span>
        <span class="badge-white"><?= ucfirst($detail['status']) ?></span>
    </div>
</div>

<!-- INCIDENT DETAILS -->
<div class="section-card">
    <div class="section-card-header">📋 Incident #<?= str_pad($detail['incident_id'],4,'0',STR_PAD_LEFT) ?></div>
    <div class="section-card-body">
        <table style="width:100%;font-size:0.88rem;">
            <tr><td style="color:var(--text-muted);padding:5px 0;width:38%;">Location</td>
                <td style="font-weight:600;"><?= htmlspecialchars($detail['location'] ?? 'N/A') ?></td></tr>
            <tr><td style="color:var(--text-muted);padding:5px 0;">Start Time</td>
                <td style="font-weight:600;"><?= date("M d, Y H:i", strtotime($detail['start_time'])) ?></td></tr>
            <?php if ($detail['end_time']): ?>
            <tr><td style="color:var(--text-muted);padding:5px 0;">End Time</td>
                <td style="font-weight:600;"><?= date("M d, Y H:i", strtotime($detail['end_time'])) ?></td></tr>
            <?php endif; ?>
            <?php if ($detail['outcome']): ?>
            <tr><td style="color:var(--text-muted);padding:5px 0;">Outcome</td>
                <td style="font-weight:600;"><?= ucfirst(str_replace('_',' ',$detail['outcome'])) ?></td></tr>
            <?php endif; ?>
            <tr><td style="color:var(--text-muted);padding:5px 0;">Contact</td>
                <td style="font-weight:600;"><?= htmlspecialchars($detail['contact_number'] ?? 'N/A') ?></td></tr>
            <tr><td style="color:var(--text-muted);padding:5px 0;">Allergies</td>
                <td style="font-weight:600;"><?= htmlspecialchars($detail['allergies'] ?? 'None') ?></td></tr>
            <tr><td style="color:var(--text-muted);padding:5px 0;">Medical Hx</td>
                <td style="font-weight:600;"><?= htmlspecialchars($detail['medical_history'] ?? 'None') ?></td></tr>
            <tr><td style="color:var(--text-muted);padding:5px 0;">Vitals Recorded</td>
                <td style="font-weight:600;"><?= count($allVitals) ?> readings</td></tr>
        </table>

        <?php if ($detail['close_notes']): ?>
        <div style="margin-top:12px;padding:10px;background:var(--light-bg);border-radius:var(--radius-sm);font-size:0.85rem;">
            <strong>Closing Notes:</strong> <?= nl2br(htmlspecialchars($detail['close_notes'])) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- FULL VITAL MONITORING HISTORY -->
<?php if (!empty($allVitals)): ?>
<div class="section-card">
    <div class="section-card-header">📊 Full Monitoring History (<?= count($allVitals) ?> readings)</div>
    <div class="section-card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>HR</th>
                        <th>BP</th>
                        <th>SpO2</th>
                        <th>Temp</th>
                        <th>RR</th>
                        <th>GCS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($allVitals as $v): ?>
                    <tr>
                        <td>
                            <div style="font-size:0.78rem;font-weight:600;"><?= date("M d", strtotime($v['recorded_at'])) ?></div>
                            <div style="font-size:0.72rem;color:var(--text-muted);"><?= date("H:i", strtotime($v['recorded_at'])) ?></div>
                        </td>
                        <td>
                            <span style="font-weight:700;color:<?= ($v['heart_rate']>100||$v['heart_rate']<60)?'#fd7e14':'inherit' ?>">
                                <?= $v['heart_rate'] ?>
                            </span>
                        </td>
                        <td><?= $v['blood_pressure'] ?></td>
                        <td>
                            <span style="font-weight:700;color:<?= ($v['spo2']<95)?'var(--primary-red)':'var(--success)' ?>">
                                <?= $v['spo2'] ?>%
                            </span>
                        </td>
                        <td><?= $v['temperature'] ?>°</td>
                        <td><?= $v['respiratory_rate'] ?? '—' ?></td>
                        <td><?= $v['gcs_score'] ?? '—' ?></td>
                    </tr>
                    <?php if ($v['notes']): ?>
                    <tr style="background:var(--light-bg);">
                        <td colspan="7" style="font-size:0.78rem;color:var(--text-muted);padding:4px 12px;">
                            📝 <?= htmlspecialchars($v['notes']) ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TREND VISUALIZATION (simple inline chart) -->
<div class="section-card">
    <div class="section-card-header">📈 Heart Rate Trend</div>
    <div class="section-card-body">
        <canvas id="hrChart" height="120"></canvas>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('hrChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($v){ return '"'.date("H:i", strtotime($v['recorded_at'])).'"'; }, $allVitals)); ?>],
        datasets: [{
            label: 'Heart Rate (bpm)',
            data: [<?php echo implode(',', array_column($allVitals, 'heart_rate')); ?>],
            borderColor: '#dc3545',
            backgroundColor: 'rgba(220,53,69,0.08)',
            borderWidth: 2,
            fill: true,
            tension: 0.3,
            pointBackgroundColor: '#dc3545',
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: false, grid: { color: '#f0f0f0' } },
            x: { grid: { display: false } }
        }
    }
});
</script>

<?php else: ?>
<div class="section-card">
    <div class="section-card-header">📊 Monitoring History</div>
    <div class="section-card-body">
        <div class="empty-state" style="padding:20px;">
            <p>No vital readings recorded for this incident.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ACTIONS -->
<?php if ($detail['status'] === 'ongoing'): ?>
<div style="padding:0 16px 24px;display:flex;gap:10px;">
    <a href="monitoring.php?id=<?= $detail['incident_id'] ?>" class="btn btn-primary" style="flex:1;">❤️ Add Vitals</a>
    <a href="complete.php?id=<?= $detail['incident_id'] ?>" class="btn btn-secondary" style="flex:1;">✅ Complete</a>
</div>
<?php endif; ?>

<?php else: /* LIST VIEW */ ?>

<div class="page-header">
    <h1>Records</h1>
    <p>All your handled incidents.</p>
</div>

<!-- SEARCH & FILTER -->
<div class="section-card">
    <div class="section-card-body">
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <input type="text" name="q" class="form-control" placeholder="Search patient or type..." value="<?= htmlspecialchars($search) ?>" style="flex:1;">
            <select name="status" class="form-select" style="flex:0 0 120px;">
                <option value="">All</option>
                <option value="transferred" <?= $filterStatus==='transferred'?'selected':'' ?>>Transferred</option>
                <option value="ongoing" <?= $filterStatus==='ongoing'?'selected':'' ?>>Ongoing</option>
                <option value="completed" <?= $filterStatus==='completed'?'selected':'' ?>>Completed</option>
            </select>
            <button type="submit" class="btn btn-primary" style="padding:11px 16px;">🔍</button>
        </form>
    </div>
</div>

<div class="section-card">
    <div class="section-card-header">📋 Incident Records</div>
    <div class="section-card-body">
        <?php if ($recordsQ && $recordsQ->num_rows > 0): ?>
        <div class="incident-list">
            <?php while($row = $recordsQ->fetch_assoc()):
                $sev  = strtolower($row['severity']);
                $stat = strtolower($row['status']);
            ?>
            <a href="records.php?id=<?= $row['incident_id'] ?>" class="incident-card severity-<?= $sev ?>">
                <div class="incident-card-top">
                    <span class="incident-id">#<?= str_pad($row['incident_id'],4,'0',STR_PAD_LEFT) ?></span>
                    <span class="badge-pill badge-<?= $stat ?>"><?= ucfirst($stat) ?></span>
                </div>
                <div class="incident-patient"><?= htmlspecialchars($row['full_name']) ?></div>
                <div class="incident-type"><?= htmlspecialchars($row['incident_type']) ?> · Age <?= $row['age'] ?></div>
                <div class="incident-meta">
                    <span class="badge-pill badge-<?= $sev ?>"><?= ucfirst($sev) ?></span>
                    <span class="text-muted"><?= date("M d, H:i", strtotime($row['start_time'])) ?></span>
                    <span class="text-muted">📊 <?= $row['vital_count'] ?></span>
                    <?php if ($row['outcome']): ?>
                    <span class="text-muted">· <?= ucfirst(str_replace('_',' ',$row['outcome'])) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <p>No incident records found.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
