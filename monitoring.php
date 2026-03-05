<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$rescuerId = $_SESSION['rescuer_id'] ?? 1;
$rescuerName = $_SESSION['username'] ?? 'Rescuer';

$success = $error = '';

/* Handle vital reading submission */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['incident_id'])) {
    $incidentId  = (int)$_POST['incident_id'];
    $heartRate   = (int)$_POST['heart_rate'];
    $bp          = $conn->real_escape_string($_POST['blood_pressure']);
    $spo2        = (float)$_POST['spo2'];
    $temp        = (float)$_POST['temperature'];
    $respRate    = isset($_POST['respiratory_rate']) ? (int)$_POST['respiratory_rate'] : null;
    $gcs         = isset($_POST['gcs_score']) ? (int)$_POST['gcs_score'] : null;
    $notes       = $conn->real_escape_string($_POST['notes'] ?? '');
    $recordedBy  = $conn->real_escape_string("rescuer:$rescuerName");

    /* Verify this incident belongs to rescuer and is ongoing */
    $check = $conn->query("SELECT incident_id FROM incident WHERE incident_id=$incidentId AND rescuer_id=$rescuerId AND status='ongoing' LIMIT 1");
    if ($check && $check->num_rows > 0) {
        $stmt = $conn->prepare("
            INSERT INTO vitalstat
            (incident_id, heart_rate, blood_pressure, spo2, temperature, respiratory_rate, gcs_score, notes, recorded_by, recorded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iisisiiiss", $incidentId, $heartRate, $bp, $spo2, $temp, $respRate, $gcs, $notes, $recordedBy);

        /* Fix: use correct bind types */
        $stmt->close();
        $stmt = $conn->prepare("
            INSERT INTO vitalstat
            (incident_id, heart_rate, blood_pressure, spo2, temperature, respiratory_rate, gcs_score, notes, recorded_by, recorded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iiddiis ss", $incidentId, $heartRate, $spo2, $temp, $respRate, $gcs, $notes, $bp, $recordedBy);
        $stmt->close();

        /* Simplified direct query */
        $respRateVal = $respRate ? $respRate : 'NULL';
        $gcsVal = $gcs ? $gcs : 'NULL';
        $sql = "INSERT INTO vitalstat
            (incident_id, heart_rate, blood_pressure, spo2, temperature, respiratory_rate, gcs_score, notes, recorded_by, recorded_at)
            VALUES
            ($incidentId, $heartRate, '$bp', $spo2, $temp, $respRateVal, $gcsVal, '$notes', '$recordedBy', NOW())";
        if ($conn->query($sql)) {
            $success = "Vital reading recorded successfully.";
        } else {
            $error = "Failed to save vitals: " . $conn->error;
        }
    } else {
        $error = "Invalid incident or not in ongoing status.";
    }
}

/* Fetch ongoing incidents for this rescuer */
$ongoingQ = $conn->query("
    SELECT i.incident_id, i.incident_type, i.severity, i.start_time,
           p.full_name, p.age, p.gender
    FROM incident i
    JOIN patient p ON i.patient_id = p.patient_id
    WHERE i.rescuer_id = $rescuerId AND i.status = 'ongoing'
    ORDER BY i.start_time DESC
");

/* If specific incident selected, load its recent vitals */
$selectedId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selectedIncident = null;
$recentVitals = [];

if ($selectedId > 0) {
    $sq = $conn->query("
        SELECT i.*, p.full_name, p.age, p.gender, p.blood_type
        FROM incident i
        JOIN patient p ON i.patient_id = p.patient_id
        WHERE i.incident_id = $selectedId AND i.rescuer_id = $rescuerId AND i.status = 'ongoing'
        LIMIT 1
    ");
    if ($sq && $sq->num_rows > 0) {
        $selectedIncident = $sq->fetch_assoc();
        $vq = $conn->query("
            SELECT * FROM vitalstat WHERE incident_id = $selectedId
            ORDER BY recorded_at DESC LIMIT 5
        ");
        $recentVitals = $vq ? $vq->fetch_all(MYSQLI_ASSOC) : [];
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
<title>Monitoring — RescueNet</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="page-content">

<?php include 'navbar.php'; ?>

<?php if ($selectedIncident): /* VITAL ENTRY FORM */ ?>

<div style="padding:12px 16px 0;">
    <a href="monitoring.php" class="btn btn-secondary" style="padding:8px 14px;font-size:0.85rem;">← Back</a>
</div>

<!-- PATIENT HEADER -->
<div class="patient-header">
    <div class="patient-name"><?= htmlspecialchars($selectedIncident['full_name']) ?></div>
    <div class="patient-meta">
        Age <?= $selectedIncident['age'] ?> · <?= ucfirst($selectedIncident['gender'] ?? '') ?> · Blood: <?= $selectedIncident['blood_type'] ?? '?' ?>
    </div>
    <div class="patient-badges">
        <span class="badge-white"><?= htmlspecialchars($selectedIncident['incident_type']) ?></span>
        <span class="badge-white"><?= ucfirst($selectedIncident['severity']) ?></span>
        <span class="badge-white">Ongoing</span>
    </div>
</div>

<?php if ($success): ?>
<div style="padding:0 16px;">
    <div class="alert alert-success">✅ <?= $success ?></div>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div style="padding:0 16px;">
    <div class="alert alert-danger">⚠️ <?= $error ?></div>
</div>
<?php endif; ?>

<!-- VITAL ENTRY FORM -->
<div class="section-card">
    <div class="section-card-header">❤️ Add Vital Reading</div>
    <div class="section-card-body">
        <form method="POST" id="vitalForm">
            <input type="hidden" name="incident_id" value="<?= $selectedIncident['incident_id'] ?>">

            <div class="form-row mb-2">
                <div class="form-section">
                    <label class="form-label">Heart Rate</label>
                    <input type="number" name="heart_rate" class="form-control" placeholder="bpm" min="20" max="250" required>
                </div>
                <div class="form-section">
                    <label class="form-label">Blood Pressure</label>
                    <input type="text" name="blood_pressure" class="form-control" placeholder="120/80" required>
                </div>
            </div>

            <div class="form-row mb-2">
                <div class="form-section">
                    <label class="form-label">SpO2</label>
                    <input type="number" name="spo2" class="form-control" placeholder="%" step="0.1" min="50" max="100" required>
                </div>
                <div class="form-section">
                    <label class="form-label">Temperature</label>
                    <input type="number" name="temperature" class="form-control" placeholder="°C" step="0.1" min="30" max="45" required>
                </div>
            </div>

            <div class="form-row mb-2">
                <div class="form-section">
                    <label class="form-label">Resp. Rate</label>
                    <input type="number" name="respiratory_rate" class="form-control" placeholder="breaths/min" min="5" max="60">
                </div>
                <div class="form-section">
                    <label class="form-label">GCS Score</label>
                    <input type="number" name="gcs_score" class="form-control" placeholder="3-15" min="3" max="15">
                </div>
            </div>

            <div class="form-section mb-2">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" placeholder="Observations, patient condition..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-full btn-lg" id="submitBtn">
                💾 Save Vital Reading
            </button>
        </form>
    </div>
</div>

<!-- RECENT VITALS -->
<?php if (!empty($recentVitals)): ?>
<div class="section-card">
    <div class="section-card-header">📊 Recent Readings</div>
    <div class="section-card-body">
        <?php foreach($recentVitals as $v): ?>
        <div style="padding:12px 0;border-bottom:1px solid var(--border-light);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <span class="text-muted"><?= date("M d, H:i", strtotime($v['recorded_at'])) ?></span>
                <span style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($v['recorded_by'] ?? '') ?></span>
            </div>
            <div class="vitals-grid">
                <div class="vital-item <?= ($v['heart_rate'] > 100 || $v['heart_rate'] < 60) ? 'vital-warning' : 'vital-normal' ?>">
                    <div class="vital-val"><?= $v['heart_rate'] ?></div>
                    <div class="vital-unit">bpm</div>
                    <div class="vital-name">HR</div>
                </div>
                <div class="vital-item">
                    <div class="vital-val" style="font-size:1.1rem;"><?= $v['blood_pressure'] ?></div>
                    <div class="vital-unit">mmHg</div>
                    <div class="vital-name">BP</div>
                </div>
                <div class="vital-item <?= ($v['spo2'] < 95) ? 'vital-critical' : 'vital-normal' ?>">
                    <div class="vital-val"><?= $v['spo2'] ?></div>
                    <div class="vital-unit">%</div>
                    <div class="vital-name">SpO2</div>
                </div>
                <div class="vital-item <?= ($v['temperature'] > 37.5 || $v['temperature'] < 36) ? 'vital-warning' : 'vital-normal' ?>">
                    <div class="vital-val"><?= $v['temperature'] ?></div>
                    <div class="vital-unit">°C</div>
                    <div class="vital-name">Temp</div>
                </div>
            </div>
            <?php if ($v['notes']): ?>
            <div style="font-size:0.82rem;color:var(--text-muted);margin-top:6px;">📝 <?= htmlspecialchars($v['notes']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php else: /* INCIDENT SELECTION LIST */ ?>

<div class="page-header">
    <h1>Monitoring</h1>
    <p>Select an ongoing incident to add vitals.</p>
</div>

<?php if ($success): ?>
<div style="padding:0 16px;">
    <div class="alert alert-success">✅ <?= $success ?></div>
</div>
<?php endif; ?>

<div class="section-card">
    <div class="section-card-header">🟠 Ongoing Incidents</div>
    <div class="section-card-body">
        <?php if ($ongoingQ && $ongoingQ->num_rows > 0): ?>
        <div class="incident-list">
            <?php while($row = $ongoingQ->fetch_assoc()):
                $sev = strtolower($row['severity']);
            ?>
            <a href="monitoring.php?id=<?= $row['incident_id'] ?>" class="incident-card severity-<?= $sev ?>">
                <div class="incident-card-top">
                    <span class="incident-id">#<?= str_pad($row['incident_id'],4,'0',STR_PAD_LEFT) ?></span>
                    <span class="badge-pill badge-ongoing">Ongoing</span>
                </div>
                <div class="incident-patient"><?= htmlspecialchars($row['full_name']) ?></div>
                <div class="incident-type"><?= htmlspecialchars($row['incident_type']) ?> · Age <?= $row['age'] ?></div>
                <div class="incident-meta">
                    <span class="badge-pill badge-<?= $sev ?>"><?= ucfirst($sev) ?></span>
                    <span class="text-muted"><?= date("M d, H:i", strtotime($row['start_time'])) ?></span>
                    <span style="margin-left:auto;font-size:0.8rem;color:var(--primary-red);font-weight:600;">Add Vitals →</span>
                </div>
            </a>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">❤️</div>
            <p>No ongoing incidents. Accept an incoming case first.</p>
            <a href="incoming.php" class="btn btn-primary" style="margin-top:12px;">View Incoming</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('vitalForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<span class="spinner"></span> Saving...';
    btn.disabled = true;
});
</script>
</body>
</html>
