<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$rescuerId = $_SESSION['rescuer_id'] ?? 1;

/* Accept an incident (rescuer acknowledges transfer → changes to ongoing) */
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_id'])) {
    $id = (int)$_POST['accept_id'];
    $stmt = $conn->prepare("UPDATE incident SET status='ongoing' WHERE incident_id=? AND rescuer_id=? AND status='transferred'");
    $stmt->bind_param("ii", $id, $rescuerId);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success = "Incident #" . str_pad($id,4,'0',STR_PAD_LEFT) . " accepted. You are now the active rescuer.";
    } else {
        $error = "Could not accept incident. Please try again.";
    }
    $stmt->close();
}

/* Fetch transferred incidents */
$transferredQ = $conn->query("
    SELECT i.*, p.full_name, p.age, p.gender, p.blood_type, p.allergies, p.medical_history
    FROM incident i
    JOIN patient p ON i.patient_id = p.patient_id
    WHERE i.rescuer_id = $rescuerId AND i.status = 'transferred'
    ORDER BY i.start_time DESC
");

/* For detail view: get initial vitals (responder's readings) */
$detailId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$detail = null;
$initialVitals = null;
if ($detailId > 0) {
    $dq = $conn->query("
        SELECT i.*, p.full_name, p.age, p.gender, p.blood_type, p.allergies, p.medical_history, p.contact_number
        FROM incident i
        JOIN patient p ON i.patient_id = p.patient_id
        WHERE i.incident_id = $detailId AND i.rescuer_id = $rescuerId
        LIMIT 1
    ");
    if ($dq && $dq->num_rows > 0) {
        $detail = $dq->fetch_assoc();
        $vq = $conn->query("
            SELECT * FROM vitalstat
            WHERE incident_id = $detailId
            ORDER BY recorded_at ASC
            LIMIT 3
        ");
        $initialVitals = $vq ? $vq->fetch_all(MYSQLI_ASSOC) : [];
    }
}

$alertCount = $transferredQ ? $transferredQ->num_rows : 0;
if ($detail) { $transferredQ->data_seek(0); } /* reset pointer after use */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Incoming Incidents — RescueNet</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="page-content">

<?php include 'navbar.php'; ?>

<?php if ($detail): /* DETAIL VIEW */ ?>

<!-- BACK BUTTON -->
<div style="padding:12px 16px 0;">
    <a href="incoming.php" class="btn btn-secondary" style="padding:8px 14px;font-size:0.85rem;">← Back</a>
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

<!-- PATIENT DETAILS -->
<div class="section-card">
    <div class="section-card-header">👤 Patient Information</div>
    <div class="section-card-body">
        <table style="width:100%;font-size:0.88rem;">
            <tr><td style="color:var(--text-muted);padding:5px 0;width:40%;">Contact</td>
                <td style="font-weight:600;"><?= htmlspecialchars($detail['contact_number'] ?? 'N/A') ?></td></tr>
            <tr><td style="color:var(--text-muted);padding:5px 0;">Allergies</td>
                <td style="font-weight:600;"><?= htmlspecialchars($detail['allergies'] ?? 'None') ?></td></tr>
            <tr><td style="color:var(--text-muted);padding:5px 0;">Medical Hx</td>
                <td style="font-weight:600;"><?= htmlspecialchars($detail['medical_history'] ?? 'None') ?></td></tr>
            <tr><td style="color:var(--text-muted);padding:5px 0;">Location</td>
                <td style="font-weight:600;"><?= htmlspecialchars($detail['location'] ?? 'N/A') ?></td></tr>
            <tr><td style="color:var(--text-muted);padding:5px 0;">Start Time</td>
                <td style="font-weight:600;"><?= date("M d, Y H:i", strtotime($detail['start_time'])) ?></td></tr>
        </table>
    </div>
</div>

<!-- INITIAL VITALS -->
<?php if (!empty($initialVitals)): ?>
<div class="section-card">
    <div class="section-card-header">💓 Initial Vitals (from Responder)</div>
    <div class="section-card-body">
        <?php foreach ($initialVitals as $v): ?>
        <div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--border-light);">
            <div class="text-muted mb-1"><?= date("M d, H:i", strtotime($v['recorded_at'])) ?> · <?= htmlspecialchars($v['recorded_by'] ?? 'Responder') ?></div>
            <div class="vitals-grid">
                <div class="vital-item">
                    <div class="vital-val"><?= $v['heart_rate'] ?? '—' ?></div>
                    <div class="vital-unit">bpm</div>
                    <div class="vital-name">Heart Rate</div>
                </div>
                <div class="vital-item">
                    <div class="vital-val"><?= $v['blood_pressure'] ?? '—' ?></div>
                    <div class="vital-unit">mmHg</div>
                    <div class="vital-name">Blood Pressure</div>
                </div>
                <div class="vital-item">
                    <div class="vital-val"><?= $v['spo2'] ?? '—' ?></div>
                    <div class="vital-unit">%</div>
                    <div class="vital-name">SpO2</div>
                </div>
                <div class="vital-item">
                    <div class="vital-val"><?= $v['temperature'] ?? '—' ?></div>
                    <div class="vital-unit">°C</div>
                    <div class="vital-name">Temperature</div>
                </div>
            </div>
            <?php if ($v['notes']): ?>
            <div style="font-size:0.83rem;color:var(--text-muted);margin-top:6px;">📝 <?= htmlspecialchars($v['notes']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="section-card">
    <div class="section-card-header">💓 Initial Vitals</div>
    <div class="section-card-body">
        <div class="empty-state" style="padding:20px;">
            <p>No vitals recorded by responder yet.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- INCIDENT NOTES -->
<?php if ($detail['notes']): ?>
<div class="section-card">
    <div class="section-card-header">📝 Incident Notes</div>
    <div class="section-card-body" style="font-size:0.9rem;">
        <?= nl2br(htmlspecialchars($detail['notes'])) ?>
    </div>
</div>
<?php endif; ?>

<!-- ACCEPT BUTTON -->
<?php if ($detail['status'] === 'transferred'): ?>
<div style="padding:0 16px 24px;">
    <form method="POST">
        <input type="hidden" name="accept_id" value="<?= $detail['incident_id'] ?>">
        <button type="button" class="btn btn-primary btn-full btn-lg" onclick="showConfirm()">
            ✅ Accept & Start Monitoring
        </button>
    </form>
</div>
<!-- CONFIRM SHEET -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-sheet">
        <h3>Accept Incident?</h3>
        <p>You will become the active rescuer for <strong><?= htmlspecialchars($detail['full_name']) ?></strong>. The incident status will change to <strong>Ongoing</strong>.</p>
        <div class="confirm-btns">
            <button class="btn btn-secondary" onclick="hideConfirm()">Cancel</button>
            <form method="POST" style="margin:0;">
                <input type="hidden" name="accept_id" value="<?= $detail['incident_id'] ?>">
                <button type="submit" class="btn btn-primary btn-full">Confirm</button>
            </form>
        </div>
    </div>
</div>
<script>
function showConfirm() { document.getElementById('confirmOverlay').classList.add('show'); }
function hideConfirm() { document.getElementById('confirmOverlay').classList.remove('show'); }
document.getElementById('confirmOverlay').addEventListener('click', function(e){
    if (e.target === this) hideConfirm();
});
</script>
<?php endif; ?>

<?php else: /* LIST VIEW */ ?>

<div class="page-header">
    <h1>Incoming</h1>
    <p>Cases transferred to you by responders.</p>
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

<div class="section-card">
    <div class="section-card-header">📥 Transferred Incidents
        <?php if ($alertCount > 0): ?>
        <span style="margin-left:auto;background:white;color:var(--primary-red);border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:800;"><?= $alertCount ?></span>
        <?php endif; ?>
    </div>
    <div class="section-card-body">
        <?php if ($transferredQ && $transferredQ->num_rows > 0): ?>
        <div class="incident-list">
            <?php while($row = $transferredQ->fetch_assoc()):
                $sev = strtolower($row['severity']);
            ?>
            <a href="incoming.php?id=<?= $row['incident_id'] ?>" class="incident-card severity-<?= $sev ?>">
                <div class="incident-card-top">
                    <span class="incident-id">#<?= str_pad($row['incident_id'],4,'0',STR_PAD_LEFT) ?></span>
                    <span class="badge-pill badge-transferred">Transferred</span>
                </div>
                <div class="incident-patient"><?= htmlspecialchars($row['full_name']) ?></div>
                <div class="incident-type"><?= htmlspecialchars($row['incident_type']) ?> · Age <?= $row['age'] ?></div>
                <div class="incident-meta">
                    <span class="badge-pill badge-<?= $sev ?>"><?= ucfirst($sev) ?></span>
                    <span class="text-muted"><?= date("M d, H:i", strtotime($row['start_time'])) ?></span>
                    <span style="margin-left:auto;font-size:0.8rem;color:var(--primary-red);font-weight:600;">View →</span>
                </div>
            </a>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📥</div>
            <p>No incoming incidents right now.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
