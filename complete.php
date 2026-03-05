<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$rescuerId = $_SESSION['rescuer_id'] ?? 1;
$success = $error = '';

/* Handle completion */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['incident_id'])) {
    $id = (int)$_POST['incident_id'];
    $outcome = $conn->real_escape_string($_POST['outcome'] ?? '');
    $closeNotes = $conn->real_escape_string($_POST['close_notes'] ?? '');

    $check = $conn->query("SELECT incident_id FROM incident WHERE incident_id=$id AND rescuer_id=$rescuerId AND status='ongoing' LIMIT 1");
    if ($check && $check->num_rows > 0) {
        $sql = "UPDATE incident SET
            status='completed',
            end_time=NOW(),
            outcome='$outcome',
            close_notes='$closeNotes'
            WHERE incident_id=$id AND rescuer_id=$rescuerId";
        if ($conn->query($sql)) {
            $success = "Incident #" . str_pad($id,4,'0',STR_PAD_LEFT) . " has been marked as completed.";
        } else {
            $error = "Failed to complete incident: " . $conn->error;
        }
    } else {
        $error = "Incident not found or not in ongoing status.";
    }
}

/* Fetch ongoing incidents */
$ongoingQ = $conn->query("
    SELECT i.incident_id, i.incident_type, i.severity, i.start_time,
           p.full_name, p.age, p.gender,
           (SELECT COUNT(*) FROM vitalstat v WHERE v.incident_id = i.incident_id) AS vital_count
    FROM incident i
    JOIN patient p ON i.patient_id = p.patient_id
    WHERE i.rescuer_id = $rescuerId AND i.status = 'ongoing'
    ORDER BY i.start_time ASC
");

/* Detail view */
$selectedId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selectedIncident = null;
$lastVital = null;

if ($selectedId > 0) {
    $sq = $conn->query("
        SELECT i.*, p.full_name, p.age, p.gender
        FROM incident i
        JOIN patient p ON i.patient_id = p.patient_id
        WHERE i.incident_id=$selectedId AND i.rescuer_id=$rescuerId AND i.status='ongoing'
        LIMIT 1
    ");
    if ($sq && $sq->num_rows > 0) {
        $selectedIncident = $sq->fetch_assoc();
        $lv = $conn->query("SELECT * FROM vitalstat WHERE incident_id=$selectedId ORDER BY recorded_at DESC LIMIT 1");
        $lastVital = ($lv && $lv->num_rows > 0) ? $lv->fetch_assoc() : null;
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
<title>Complete Incident — RescueNet</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="page-content">

<?php include 'navbar.php'; ?>

<?php if ($selectedIncident): ?>

<div style="padding:12px 16px 0;">
    <a href="complete.php" class="btn btn-secondary" style="padding:8px 14px;font-size:0.85rem;">← Back</a>
</div>

<div class="patient-header">
    <div class="patient-name"><?= htmlspecialchars($selectedIncident['full_name']) ?></div>
    <div class="patient-meta">Age <?= $selectedIncident['age'] ?> · <?= ucfirst($selectedIncident['gender'] ?? '') ?></div>
    <div class="patient-badges">
        <span class="badge-white"><?= htmlspecialchars($selectedIncident['incident_type']) ?></span>
        <span class="badge-white"><?= ucfirst($selectedIncident['severity']) ?></span>
    </div>
</div>

<?php if ($success): ?>
<div style="padding:0 16px;">
    <div class="alert alert-success">✅ <?= $success ?>
        <a href="records.php" style="margin-left:8px;font-weight:700;">View Records</a>
    </div>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div style="padding:0 16px;">
    <div class="alert alert-danger">⚠️ <?= $error ?></div>
</div>
<?php endif; ?>

<!-- LAST VITALS SUMMARY -->
<?php if ($lastVital): ?>
<div class="section-card">
    <div class="section-card-header">💓 Last Recorded Vitals</div>
    <div class="section-card-body">
        <div class="text-muted mb-1" style="font-size:0.82rem;margin-bottom:8px;">
            Recorded <?= date("M d, H:i", strtotime($lastVital['recorded_at'])) ?>
        </div>
        <div class="vitals-grid">
            <div class="vital-item">
                <div class="vital-val"><?= $lastVital['heart_rate'] ?></div>
                <div class="vital-unit">bpm</div>
                <div class="vital-name">Heart Rate</div>
            </div>
            <div class="vital-item">
                <div class="vital-val" style="font-size:1.1rem;"><?= $lastVital['blood_pressure'] ?></div>
                <div class="vital-unit">mmHg</div>
                <div class="vital-name">Blood Pressure</div>
            </div>
            <div class="vital-item">
                <div class="vital-val"><?= $lastVital['spo2'] ?></div>
                <div class="vital-unit">%</div>
                <div class="vital-name">SpO2</div>
            </div>
            <div class="vital-item">
                <div class="vital-val"><?= $lastVital['temperature'] ?></div>
                <div class="vital-unit">°C</div>
                <div class="vital-name">Temp</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- COMPLETION FORM -->
<?php if (!$success): ?>
<div class="section-card">
    <div class="section-card-header">✅ Complete Incident</div>
    <div class="section-card-body">
        <div class="alert alert-warning">
            ⚠️ <strong>This action is permanent.</strong> The incident will be marked as completed and the end time will be recorded.
        </div>

        <form method="POST" id="completeForm">
            <input type="hidden" name="incident_id" value="<?= $selectedIncident['incident_id'] ?>">

            <div class="form-section mb-2">
                <label class="form-label">Patient Outcome</label>
                <select name="outcome" class="form-select" required>
                    <option value="">Select outcome...</option>
                    <option value="stable">Stable — Transported to facility</option>
                    <option value="critical_stable">Critical but Stable</option>
                    <option value="deceased">Deceased</option>
                    <option value="refused_transport">Patient Refused Transport</option>
                    <option value="treated_released">Treated and Released On Scene</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="form-section mb-2">
                <label class="form-label">Closing Notes</label>
                <textarea name="close_notes" class="form-control" rows="4"
                    placeholder="Final patient condition, actions taken, handoff details..."></textarea>
            </div>

            <button type="button" class="btn btn-primary btn-full btn-lg" onclick="showConfirm()">
                ✅ Mark as Completed
            </button>
        </form>
    </div>
</div>

<!-- CONFIRM SHEET -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-sheet">
        <h3 style="color:var(--primary-red);">Complete Incident?</h3>
        <p>Incident <strong>#<?= str_pad($selectedIncident['incident_id'],4,'0',STR_PAD_LEFT) ?></strong> for <strong><?= htmlspecialchars($selectedIncident['full_name']) ?></strong> will be closed. This records the end time and cannot be undone.</p>
        <div class="confirm-btns">
            <button class="btn btn-secondary" onclick="hideConfirm()">Cancel</button>
            <button class="btn btn-primary" onclick="document.getElementById('completeForm').submit();">Complete</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php else: /* LIST */ ?>

<div class="page-header">
    <h1>Complete</h1>
    <p>Select an ongoing incident to close.</p>
</div>

<?php if ($success): ?>
<div style="padding:0 16px;">
    <div class="alert alert-success">✅ <?= $success ?></div>
</div>
<?php endif; ?>

<div class="section-card">
    <div class="section-card-header">🟠 Active — Ongoing Incidents</div>
    <div class="section-card-body">
        <?php if ($ongoingQ && $ongoingQ->num_rows > 0): ?>
        <div class="incident-list">
            <?php while($row = $ongoingQ->fetch_assoc()):
                $sev = strtolower($row['severity']);
                $elapsed = round((time() - strtotime($row['start_time'])) / 60);
                $elapsedStr = $elapsed < 60 ? "{$elapsed}m ago" : round($elapsed/60,1) . "h ago";
            ?>
            <a href="complete.php?id=<?= $row['incident_id'] ?>" class="incident-card severity-<?= $sev ?>">
                <div class="incident-card-top">
                    <span class="incident-id">#<?= str_pad($row['incident_id'],4,'0',STR_PAD_LEFT) ?></span>
                    <span class="badge-pill badge-ongoing">Ongoing</span>
                </div>
                <div class="incident-patient"><?= htmlspecialchars($row['full_name']) ?></div>
                <div class="incident-type"><?= htmlspecialchars($row['incident_type']) ?> · Age <?= $row['age'] ?></div>
                <div class="incident-meta">
                    <span class="badge-pill badge-<?= $sev ?>"><?= ucfirst($sev) ?></span>
                    <span class="text-muted">Started <?= $elapsedStr ?></span>
                    <span class="text-muted">📊 <?= $row['vital_count'] ?> readings</span>
                    <span style="margin-left:auto;font-size:0.8rem;color:var(--primary-red);font-weight:600;">Complete →</span>
                </div>
            </a>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">✅</div>
            <p>No ongoing incidents to complete.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showConfirm() { document.getElementById('confirmOverlay').classList.add('show'); }
function hideConfirm() { document.getElementById('confirmOverlay').classList.remove('show'); }
document.getElementById('confirmOverlay')?.addEventListener('click', function(e){
    if (e.target === this) hideConfirm();
});
</script>
</body>
</html>
