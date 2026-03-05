<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$rescuerId = $_SESSION['rescuer_id'] ?? 1;
$success = $error = '';

/* Handle device return request */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_id'])) {
    $logId = (int)$_POST['log_id'];
    $condition = $conn->real_escape_string($_POST['device_condition'] ?? 'good');
    $returnNotes = $conn->real_escape_string($_POST['return_notes'] ?? '');

    /* Verify device log belongs to this rescuer */
    $check = $conn->query("
        SELECT dl.log_id FROM device_log dl
        JOIN incident i ON dl.incident_id = i.incident_id
        WHERE dl.log_id=$logId AND i.rescuer_id=$rescuerId AND dl.date_returned IS NULL
        LIMIT 1
    ");
    if ($check && $check->num_rows > 0) {
        /* Mark as pending return — management will verify */
        $sql = "UPDATE device_log SET
            return_requested_at = NOW(),
            return_condition = '$condition',
            return_notes = '$returnNotes',
            return_status = 'pending'
            WHERE log_id=$logId";
        if ($conn->query($sql)) {
            $success = "Device return request submitted. Management will verify and confirm the return.";
        } else {
            /* If columns don't exist, try simpler update */
            $sql2 = "UPDATE device_log SET date_returned=NOW() WHERE log_id=$logId";
            if ($conn->query($sql2)) {
                $success = "Device return recorded.";
            } else {
                $error = "Failed to submit return: " . $conn->error;
            }
        }
    } else {
        $error = "Device log not found or already returned.";
    }
}

/* Fetch active device assignments for this rescuer */
$devicesQ = $conn->query("
    SELECT dl.log_id, dl.date_issued, dl.return_status,
           d.device_name, d.device_type, d.serial_number,
           i.incident_id, i.incident_type, i.status AS incident_status,
           p.full_name
    FROM device_log dl
    JOIN device d ON dl.device_id = d.device_id
    JOIN incident i ON dl.incident_id = i.incident_id
    JOIN patient p ON i.patient_id = p.patient_id
    WHERE i.rescuer_id = $rescuerId AND dl.date_returned IS NULL
    ORDER BY dl.date_issued DESC
");

/* Returned devices history */
$historyQ = $conn->query("
    SELECT dl.log_id, dl.date_issued, dl.date_returned,
           d.device_name, d.device_type,
           i.incident_id, p.full_name
    FROM device_log dl
    JOIN device d ON dl.device_id = d.device_id
    JOIN incident i ON dl.incident_id = i.incident_id
    JOIN patient p ON i.patient_id = p.patient_id
    WHERE i.rescuer_id = $rescuerId AND dl.date_returned IS NOT NULL
    ORDER BY dl.date_returned DESC
    LIMIT 10
");

$alertQ = $conn->query("SELECT COUNT(*) AS c FROM incident WHERE rescuer_id=$rescuerId AND status='transferred'");
$alertCount = $alertQ ? (int)$alertQ->fetch_assoc()['c'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Return Device — RescueNet</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="page-content">

<?php include 'navbar.php'; ?>

<div class="page-header">
    <h1>Return Device</h1>
    <p>Signal return of assigned equipment.</p>
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

<div class="alert alert-info" style="margin:0 16px 16px;font-size:0.85rem;">
    ℹ️ Submitting a return request notifies management to verify and confirm the device return.
</div>

<!-- ACTIVE DEVICES -->
<div class="section-card">
    <div class="section-card-header">📦 Active Device Assignments</div>
    <div class="section-card-body">
        <?php if ($devicesQ && $devicesQ->num_rows > 0): ?>
        <?php while($dev = $devicesQ->fetch_assoc()):
            $isPending = ($dev['return_status'] ?? '') === 'pending';
        ?>
        <div style="border:1px solid var(--border-light);border-radius:var(--radius);padding:14px;margin-bottom:12px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
                <div>
                    <div style="font-weight:700;font-size:1rem;font-family:var(--font-display);">
                        <?= htmlspecialchars($dev['device_name']) ?>
                    </div>
                    <div class="text-muted">
                        <?= htmlspecialchars($dev['device_type']) ?> · S/N: <?= htmlspecialchars($dev['serial_number'] ?? 'N/A') ?>
                    </div>
                </div>
                <?php if ($isPending): ?>
                <span class="badge-pill badge-pending">Return Pending</span>
                <?php else: ?>
                <span class="badge-pill badge-ongoing">Active</span>
                <?php endif; ?>
            </div>

            <div style="font-size:0.82rem;color:var(--text-muted);margin-bottom:10px;">
                👤 <?= htmlspecialchars($dev['full_name']) ?> ·
                Incident #<?= str_pad($dev['incident_id'],4,'0',STR_PAD_LEFT) ?> ·
                Issued: <?= date("M d, H:i", strtotime($dev['date_issued'])) ?>
            </div>

            <?php if (!$isPending): ?>
            <form method="POST" id="returnForm_<?= $dev['log_id'] ?>">
                <input type="hidden" name="log_id" value="<?= $dev['log_id'] ?>">

                <div class="form-section mb-2">
                    <label class="form-label">Device Condition</label>
                    <select name="device_condition" class="form-select">
                        <option value="good">Good — No issues</option>
                        <option value="minor_damage">Minor damage</option>
                        <option value="damaged">Damaged</option>
                        <option value="lost">Lost / Missing</option>
                    </select>
                </div>

                <div class="form-section mb-2">
                    <label class="form-label">Return Notes (optional)</label>
                    <input type="text" name="return_notes" class="form-control" placeholder="Any notes about the device...">
                </div>

                <button type="button" class="btn btn-primary btn-full"
                    onclick="showReturnConfirm(<?= $dev['log_id'] ?>, '<?= addslashes($dev['device_name']) ?>')">
                    📦 Submit Return Request
                </button>
            </form>
            <?php else: ?>
            <div class="alert alert-info" style="margin:0;font-size:0.83rem;padding:8px 12px;">
                🕐 Return pending management verification.
            </div>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📦</div>
            <p>No active device assignments.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- RETURN HISTORY -->
<?php if ($historyQ && $historyQ->num_rows > 0): ?>
<div class="section-card">
    <div class="section-card-header">📋 Return History</div>
    <div class="section-card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Patient</th>
                        <th>Returned</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($h = $historyQ->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($h['device_name']) ?></strong><br>
                            <span class="text-muted"><?= htmlspecialchars($h['device_type']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($h['full_name']) ?></td>
                        <td><?= date("M d\nH:i", strtotime($h['date_returned'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

</div>

<!-- CONFIRM OVERLAY -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-sheet">
        <h3>Submit Return?</h3>
        <p id="confirmMsg">Submit device return request?</p>
        <div class="confirm-btns">
            <button class="btn btn-secondary" onclick="hideReturnConfirm()">Cancel</button>
            <button class="btn btn-primary" id="confirmReturnBtn">Submit</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let pendingFormId = null;
function showReturnConfirm(logId, deviceName) {
    pendingFormId = logId;
    document.getElementById('confirmMsg').textContent = `Submit return request for "${deviceName}"? Management will verify before marking as returned.`;
    document.getElementById('confirmOverlay').classList.add('show');
}
function hideReturnConfirm() {
    document.getElementById('confirmOverlay').classList.remove('show');
    pendingFormId = null;
}
document.getElementById('confirmReturnBtn').addEventListener('click', function(){
    if (pendingFormId) document.getElementById('returnForm_' + pendingFormId).submit();
});
document.getElementById('confirmOverlay').addEventListener('click', function(e){
    if (e.target === this) hideReturnConfirm();
});
</script>
</body>
</html>
