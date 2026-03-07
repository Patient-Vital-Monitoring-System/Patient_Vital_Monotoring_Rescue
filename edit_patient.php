<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$rescuerId = $_SESSION['rescuer_id'] ?? 1;
$success = $error = '';

/* ── GET INCIDENT ID ── */
$incidentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$incidentId) {
    header("Location: records.php");
    exit;
}

/* ── VERIFY INCIDENT BELONGS TO THIS RESCUER ── */
$incQ = $conn->query("
    SELECT i.*, p.patient_id, p.full_name, p.age, p.gender, p.blood_type,
           p.allergies, p.medical_history, p.contact_number
    FROM incident i
    JOIN patient p ON i.patient_id = p.patient_id
    WHERE i.incident_id = $incidentId AND i.rescuer_id = $rescuerId
    LIMIT 1
");
if (!$incQ || $incQ->num_rows === 0) {
    header("Location: records.php");
    exit;
}
$data = $incQ->fetch_assoc();
$patientId = $data['patient_id'];

/* ══════════════════════════════════════════════
   HANDLE FORM SUBMISSIONS
══════════════════════════════════════════════ */

/* -- UPDATE PATIENT INFO -- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_patient') {
    $fullName   = $conn->real_escape_string(trim($_POST['full_name']));
    $age        = (int)$_POST['age'];
    $gender     = $conn->real_escape_string($_POST['gender']);
    $bloodType  = $conn->real_escape_string($_POST['blood_type']);
    $allergies  = $conn->real_escape_string(trim($_POST['allergies']));
    $medHistory = $conn->real_escape_string(trim($_POST['medical_history']));
    $contact    = $conn->real_escape_string(trim($_POST['contact_number']));

    $sql = "UPDATE patient SET
        full_name='$fullName', age=$age, gender='$gender',
        blood_type='$bloodType', allergies='$allergies',
        medical_history='$medHistory', contact_number='$contact'
        WHERE patient_id=$patientId";

    if ($conn->query($sql)) {
        $success = "Patient information updated successfully.";
    } else {
        $error = "Failed to update patient: " . $conn->error;
    }
    /* Reload data */
    $incQ = $conn->query("SELECT i.*, p.patient_id, p.full_name, p.age, p.gender, p.blood_type, p.allergies, p.medical_history, p.contact_number FROM incident i JOIN patient p ON i.patient_id = p.patient_id WHERE i.incident_id=$incidentId LIMIT 1");
    $data = $incQ->fetch_assoc();
}

/* -- UPDATE INCIDENT DETAILS -- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_incident') {
    $incType   = $conn->real_escape_string($_POST['incident_type']);
    $severity  = $conn->real_escape_string($_POST['severity']);
    $status    = $conn->real_escape_string($_POST['status']);
    $location  = $conn->real_escape_string(trim($_POST['location']));
    $notes     = $conn->real_escape_string(trim($_POST['notes']));
    $outcome   = $conn->real_escape_string($_POST['outcome'] ?? '');
    $closeNotes= $conn->real_escape_string(trim($_POST['close_notes'] ?? ''));
    $endTime   = !empty($_POST['end_time']) ? "'" . $conn->real_escape_string($_POST['end_time']) . "'" : 'NULL';

    $sql = "UPDATE incident SET
        incident_type='$incType', severity='$severity', status='$status',
        location='$location', notes='$notes', outcome='$outcome',
        close_notes='$closeNotes', end_time=$endTime
        WHERE incident_id=$incidentId AND rescuer_id=$rescuerId";

    if ($conn->query($sql)) {
        $success = "Incident details updated successfully.";
    } else {
        $error = "Failed to update incident: " . $conn->error;
    }
    /* Reload */
    $incQ = $conn->query("SELECT i.*, p.patient_id, p.full_name, p.age, p.gender, p.blood_type, p.allergies, p.medical_history, p.contact_number FROM incident i JOIN patient p ON i.patient_id = p.patient_id WHERE i.incident_id=$incidentId LIMIT 1");
    $data = $incQ->fetch_assoc();
}

/* -- UPDATE VITAL READING -- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_vital') {
    $vitalId   = (int)$_POST['vital_id'];
    $hr        = (int)$_POST['heart_rate'];
    $bp        = $conn->real_escape_string($_POST['blood_pressure']);
    $spo2      = (float)$_POST['spo2'];
    $temp      = (float)$_POST['temperature'];
    $rr        = !empty($_POST['respiratory_rate']) ? (int)$_POST['respiratory_rate'] : 'NULL';
    $gcs       = !empty($_POST['gcs_score']) ? (int)$_POST['gcs_score'] : 'NULL';
    $vnotes    = $conn->real_escape_string(trim($_POST['vital_notes'] ?? ''));

    $sql = "UPDATE vitalstat SET
        heart_rate=$hr, blood_pressure='$bp', spo2=$spo2,
        temperature=$temp, respiratory_rate=$rr, gcs_score=$gcs, notes='$vnotes'
        WHERE vital_id=$vitalId AND incident_id=$incidentId";

    if ($conn->query($sql)) {
        $success = "Vital reading updated successfully.";
    } else {
        $error = "Failed to update vital: " . $conn->error;
    }
}

/* -- DELETE VITAL READING -- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete_vital') {
    $vitalId = (int)$_POST['vital_id'];
    $sql = "DELETE FROM vitalstat WHERE vital_id=$vitalId AND incident_id=$incidentId";
    if ($conn->query($sql)) {
        $success = "Vital reading deleted.";
    } else {
        $error = "Failed to delete vital: " . $conn->error;
    }
}

/* ── FETCH VITALS ── */
$vitalsQ = $conn->query("SELECT * FROM vitalstat WHERE incident_id=$incidentId ORDER BY recorded_at ASC");
$vitals  = $vitalsQ ? $vitalsQ->fetch_all(MYSQLI_ASSOC) : [];

/* ── ACTIVE TAB ── */
$tab = $_GET['tab'] ?? 'patient';

$alertQ = $conn->query("SELECT COUNT(*) AS c FROM incident WHERE rescuer_id=$rescuerId AND status='transferred'");
$alertCount = $alertQ ? (int)$alertQ->fetch_assoc()['c'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Edit Record — RescueNet</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
<style>
.tab-bar {
    display: flex;
    background: white;
    border-bottom: 2px solid var(--border-light);
    margin: 0 16px 16px;
    border-radius: var(--radius) var(--radius) 0 0;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.tab-btn {
    flex: 1;
    padding: 12px 6px;
    text-align: center;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: var(--text-muted);
    background: white;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
}
.tab-btn .tab-icon { font-size: 1.1rem; }
.tab-btn:hover { color: var(--primary-red); background: var(--red-light); }
.tab-btn.active {
    color: var(--primary-red);
    border-bottom-color: var(--primary-red);
    background: rgba(220,53,69,0.04);
}
.tab-content { display: none; }
.tab-content.active { display: block; }

.vital-edit-card {
    border: 1px solid var(--border-light);
    border-radius: var(--radius);
    margin-bottom: 12px;
    overflow: hidden;
}
.vital-edit-header {
    background: var(--light-bg);
    padding: 10px 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    border-bottom: 1px solid var(--border-light);
}
.vital-edit-header .vital-time {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-dark);
}
.vital-edit-header .vital-summary {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 2px;
}
.vital-edit-body {
    padding: 14px;
    display: none;
    background: white;
}
.vital-edit-body.open { display: block; }
.delete-btn {
    background: rgba(220,53,69,0.08);
    color: var(--primary-red);
    border: 1px solid rgba(220,53,69,0.2);
    border-radius: var(--radius-sm);
    padding: 6px 12px;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}
.delete-btn:hover {
    background: var(--primary-red);
    color: white;
}
</style>
</head>
<body>
<div class="page-content">

<?php include 'navbar.php'; ?>

<!-- BACK + TITLE -->
<div style="padding:12px 16px 0;display:flex;align-items:center;gap:10px;">
    <a href="records.php?id=<?= $incidentId ?>" class="btn btn-secondary" style="padding:8px 14px;font-size:0.85rem;">← Back</a>
    <div>
        <div style="font-size:0.72rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;">Editing</div>
        <div style="font-weight:800;font-family:var(--font-display);font-size:1.1rem;color:var(--primary-red);">
            <?= htmlspecialchars($data['full_name']) ?>
        </div>
    </div>
</div>

<!-- PATIENT HEADER -->
<div class="patient-header" style="margin-top:12px;">
    <div class="patient-name"><?= htmlspecialchars($data['full_name']) ?></div>
    <div class="patient-meta">
        Age <?= $data['age'] ?> · <?= ucfirst($data['gender'] ?? '') ?> · Blood: <?= $data['blood_type'] ?? 'Unknown' ?>
    </div>
    <div class="patient-badges">
        <span class="badge-white"><?= htmlspecialchars($data['incident_type']) ?></span>
        <span class="badge-white"><?= ucfirst($data['severity']) ?></span>
        <span class="badge-white"><?= ucfirst($data['status']) ?></span>
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

<!-- TAB BAR -->
<div class="tab-bar">
    <a href="edit_patient.php?id=<?= $incidentId ?>&tab=patient"
        class="tab-btn <?= $tab === 'patient' ? 'active' : '' ?>">
        <span class="tab-icon">👤</span> Patient
    </a>
    <a href="edit_patient.php?id=<?= $incidentId ?>&tab=incident"
        class="tab-btn <?= $tab === 'incident' ? 'active' : '' ?>">
        <span class="tab-icon">🚨</span> Incident
    </a>
    <a href="edit_patient.php?id=<?= $incidentId ?>&tab=vitals"
        class="tab-btn <?= $tab === 'vitals' ? 'active' : '' ?>">
        <span class="tab-icon">💓</span> Vitals
        <?php if (count($vitals) > 0): ?>
        <span style="background:var(--primary-red);color:white;border-radius:20px;padding:1px 6px;font-size:0.65rem;">
            <?= count($vitals) ?>
        </span>
        <?php endif; ?>
    </a>
</div>

<!-- ══════════════════════════════════════
     TAB 1 — PATIENT INFO
══════════════════════════════════════ -->
<div class="tab-content <?= $tab === 'patient' ? 'active' : '' ?>">
    <div class="section-card">
        <div class="section-card-header">👤 Edit Patient Information</div>
        <div class="section-card-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_patient">

                <div class="form-section mb-2">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control"
                        value="<?= htmlspecialchars($data['full_name']) ?>" required>
                </div>

                <div class="form-row mb-2">
                    <div class="form-section">
                        <label class="form-label">Age</label>
                        <input type="number" name="age" class="form-control"
                            value="<?= $data['age'] ?>" min="1" max="120" required>
                    </div>
                    <div class="form-section">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="male"   <?= $data['gender']==='male'   ? 'selected':'' ?>>Male</option>
                            <option value="female" <?= $data['gender']==='female' ? 'selected':'' ?>>Female</option>
                            <option value="other"  <?= $data['gender']==='other'  ? 'selected':'' ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row mb-2">
                    <div class="form-section">
                        <label class="form-label">Blood Type</label>
                        <select name="blood_type" class="form-select">
                            <option value="">Unknown</option>
                            <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                            <option value="<?= $bt ?>" <?= $data['blood_type']===$bt ? 'selected':'' ?>><?= $bt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-section">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control"
                            value="<?= htmlspecialchars($data['contact_number'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-section mb-2">
                    <label class="form-label">Allergies</label>
                    <input type="text" name="allergies" class="form-control"
                        value="<?= htmlspecialchars($data['allergies'] ?? '') ?>">
                </div>

                <div class="form-section mb-2">
                    <label class="form-label">Medical History</label>
                    <textarea name="medical_history" class="form-control"
                        rows="3"><?= htmlspecialchars($data['medical_history'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg">
                    💾 Save Patient Info
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     TAB 2 — INCIDENT DETAILS
══════════════════════════════════════ -->
<div class="tab-content <?= $tab === 'incident' ? 'active' : '' ?>">
    <div class="section-card">
        <div class="section-card-header">🚨 Edit Incident Details</div>
        <div class="section-card-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_incident">

                <div class="form-section mb-2">
                    <label class="form-label">Incident Type</label>
                    <select name="incident_type" class="form-select">
                        <?php
                        $types = [
                            'Cardiac' => ['Cardiac Arrest','Chest Pain','Heart Attack'],
                            'Respiratory' => ['Respiratory Distress','Asthma Attack'],
                            'Trauma' => ['Trauma — MVA','Trauma — Fall','Trauma — Assault'],
                            'Medical' => ['Diabetic Emergency','Seizure','Stroke','Allergic Reaction','Poisoning'],
                            'Other' => ['Obstetric Emergency','Drowning','Burns','Other']
                        ];
                        foreach($types as $group => $items):
                        ?>
                        <optgroup label="<?= $group ?>">
                            <?php foreach($items as $t): ?>
                            <option value="<?= $t ?>" <?= $data['incident_type']===$t ? 'selected':'' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row mb-2">
                    <div class="form-section">
                        <label class="form-label">Severity</label>
                        <select name="severity" class="form-select">
                            <option value="low"      <?= $data['severity']==='low'      ? 'selected':'' ?>>🟢 Low</option>
                            <option value="medium"   <?= $data['severity']==='medium'   ? 'selected':'' ?>>🟡 Medium</option>
                            <option value="high"     <?= $data['severity']==='high'     ? 'selected':'' ?>>🟠 High</option>
                            <option value="critical" <?= $data['severity']==='critical' ? 'selected':'' ?>>🔴 Critical</option>
                        </select>
                    </div>
                    <div class="form-section">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="transferred" <?= $data['status']==='transferred' ? 'selected':'' ?>>Transferred</option>
                            <option value="ongoing"     <?= $data['status']==='ongoing'     ? 'selected':'' ?>>Ongoing</option>
                            <option value="completed"   <?= $data['status']==='completed'   ? 'selected':'' ?>>Completed</option>
                        </select>
                    </div>
                </div>

                <div class="form-section mb-2">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control"
                        value="<?= htmlspecialchars($data['location'] ?? '') ?>">
                </div>

                <div class="form-section mb-2">
                    <label class="form-label">Incident Notes</label>
                    <textarea name="notes" class="form-control"
                        rows="3"><?= htmlspecialchars($data['notes'] ?? '') ?></textarea>
                </div>

                <div class="form-section mb-2">
                    <label class="form-label">Outcome</label>
                    <select name="outcome" class="form-select">
                        <option value="">— None —</option>
                        <option value="stable"            <?= ($data['outcome']??'')==='stable'            ? 'selected':'' ?>>Stable — Transported</option>
                        <option value="critical_stable"   <?= ($data['outcome']??'')==='critical_stable'   ? 'selected':'' ?>>Critical but Stable</option>
                        <option value="deceased"          <?= ($data['outcome']??'')==='deceased'          ? 'selected':'' ?>>Deceased</option>
                        <option value="refused_transport" <?= ($data['outcome']??'')==='refused_transport' ? 'selected':'' ?>>Refused Transport</option>
                        <option value="treated_released"  <?= ($data['outcome']??'')==='treated_released'  ? 'selected':'' ?>>Treated and Released</option>
                        <option value="other"             <?= ($data['outcome']??'')==='other'             ? 'selected':'' ?>>Other</option>
                    </select>
                </div>

                <div class="form-section mb-2">
                    <label class="form-label">Closing Notes</label>
                    <textarea name="close_notes" class="form-control"
                        rows="3"><?= htmlspecialchars($data['close_notes'] ?? '') ?></textarea>
                </div>

                <div class="form-section mb-2">
                    <label class="form-label">End Time</label>
                    <input type="datetime-local" name="end_time" class="form-control"
                        value="<?= $data['end_time'] ? date('Y-m-d\TH:i', strtotime($data['end_time'])) : '' ?>">
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg">
                    💾 Save Incident Details
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     TAB 3 — VITAL READINGS
══════════════════════════════════════ -->
<div class="tab-content <?= $tab === 'vitals' ? 'active' : '' ?>">
    <div class="section-card">
        <div class="section-card-header">💓 Edit Vital Readings
            <span style="margin-left:auto;font-size:0.8rem;font-weight:500;opacity:0.85;">
                <?= count($vitals) ?> reading<?= count($vitals) !== 1 ? 's' : '' ?>
            </span>
        </div>
        <div class="section-card-body">

            <?php if (empty($vitals)): ?>
            <div class="empty-state" style="padding:20px;">
                <div class="empty-icon">💓</div>
                <p>No vital readings recorded yet.</p>
                <a href="monitoring.php?id=<?= $incidentId ?>" class="btn btn-primary" style="margin-top:12px;">
                    Add Vitals
                </a>
            </div>
            <?php else: ?>

            <div class="alert alert-info" style="font-size:0.82rem;margin-bottom:14px;">
                ℹ️ Tap any reading to expand and edit. Changes are saved immediately.
            </div>

            <?php foreach($vitals as $i => $v): ?>
            <div class="vital-edit-card">
                <div class="vital-edit-header" onclick="toggleVital(<?= $v['vital_id'] ?>)">
                    <div>
                        <div class="vital-time">
                            #<?= $i+1 ?> · <?= date("M d, Y H:i", strtotime($v['recorded_at'])) ?>
                        </div>
                        <div class="vital-summary">
                            HR: <?= $v['heart_rate'] ?>bpm · BP: <?= $v['blood_pressure'] ?> · SpO2: <?= $v['spo2'] ?>% · Temp: <?= $v['temperature'] ?>°C
                        </div>
                    </div>
                    <span style="font-size:1.2rem;color:var(--text-muted);" id="arrow_<?= $v['vital_id'] ?>">▼</span>
                </div>

                <div class="vital-edit-body" id="vital_<?= $v['vital_id'] ?>">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_vital">
                        <input type="hidden" name="vital_id" value="<?= $v['vital_id'] ?>">

                        <div class="form-row mb-2">
                            <div class="form-section">
                                <label class="form-label">Heart Rate</label>
                                <input type="number" name="heart_rate" class="form-control"
                                    value="<?= $v['heart_rate'] ?>" min="20" max="250" required>
                            </div>
                            <div class="form-section">
                                <label class="form-label">Blood Pressure</label>
                                <input type="text" name="blood_pressure" class="form-control"
                                    value="<?= htmlspecialchars($v['blood_pressure']) ?>" required>
                            </div>
                        </div>

                        <div class="form-row mb-2">
                            <div class="form-section">
                                <label class="form-label">SpO2 (%)</label>
                                <input type="number" name="spo2" class="form-control"
                                    value="<?= $v['spo2'] ?>" step="0.1" min="50" max="100" required>
                            </div>
                            <div class="form-section">
                                <label class="form-label">Temperature (°C)</label>
                                <input type="number" name="temperature" class="form-control"
                                    value="<?= $v['temperature'] ?>" step="0.1" min="30" max="45" required>
                            </div>
                        </div>

                        <div class="form-row mb-2">
                            <div class="form-section">
                                <label class="form-label">Resp. Rate</label>
                                <input type="number" name="respiratory_rate" class="form-control"
                                    value="<?= $v['respiratory_rate'] ?>" min="5" max="60">
                            </div>
                            <div class="form-section">
                                <label class="form-label">GCS Score</label>
                                <input type="number" name="gcs_score" class="form-control"
                                    value="<?= $v['gcs_score'] ?>" min="3" max="15">
                            </div>
                        </div>

                        <div class="form-section mb-2">
                            <label class="form-label">Notes</label>
                            <textarea name="vital_notes" class="form-control"
                                rows="2"><?= htmlspecialchars($v['notes'] ?? '') ?></textarea>
                        </div>

                        <div style="display:flex;gap:10px;">
                            <button type="submit" class="btn btn-primary" style="flex:2;">
                                💾 Save Changes
                            </button>
                            <!-- DELETE BUTTON -->
                            <button type="button" class="delete-btn"
                                onclick="confirmDelete(<?= $v['vital_id'] ?>, '<?= date("M d H:i", strtotime($v['recorded_at'])) ?>')">
                                🗑 Delete
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

            <a href="monitoring.php?id=<?= $incidentId ?>" class="btn btn-secondary btn-full" style="margin-top:4px;">
                ➕ Add New Vital Reading
            </a>

            <?php endif; ?>
        </div>
    </div>
</div>

<!-- DELETE CONFIRM OVERLAY -->
<div class="confirm-overlay" id="deleteOverlay">
    <div class="confirm-sheet">
        <h3 style="color:var(--primary-red);">Delete Vital Reading?</h3>
        <p id="deleteMsg">This vital reading will be permanently deleted.</p>
        <div class="confirm-btns">
            <button class="btn btn-secondary" onclick="hideDelete()">Cancel</button>
            <form method="POST" id="deleteForm" style="margin:0;flex:1;">
                <input type="hidden" name="action" value="delete_vital">
                <input type="hidden" name="vital_id" id="deleteVitalId">
                <button type="submit" class="btn btn-primary btn-full"
                    style="background:#dc3545!important;border-color:#dc3545!important;">
                    🗑 Delete
                </button>
            </form>
        </div>
    </div>
</div>

</div><!-- .page-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* Toggle vital edit panel */
function toggleVital(id) {
    const body  = document.getElementById('vital_' + id);
    const arrow = document.getElementById('arrow_' + id);
    const isOpen = body.classList.contains('open');
    /* Close all */
    document.querySelectorAll('.vital-edit-body').forEach(b => b.classList.remove('open'));
    document.querySelectorAll('[id^="arrow_"]').forEach(a => a.textContent = '▼');
    /* Open clicked if it was closed */
    if (!isOpen) {
        body.classList.add('open');
        arrow.textContent = '▲';
    }
}

/* Delete confirm */
function confirmDelete(vitalId, time) {
    document.getElementById('deleteVitalId').value = vitalId;
    document.getElementById('deleteMsg').textContent = `Reading from ${time} will be permanently deleted. This cannot be undone.`;
    document.getElementById('deleteOverlay').classList.add('show');
}
function hideDelete() {
    document.getElementById('deleteOverlay').classList.remove('show');
}
document.getElementById('deleteOverlay').addEventListener('click', function(e) {
    if (e.target === this) hideDelete();
});

/* Auto open panel if just saved a vital */
<?php if ($success && strpos($success, 'Vital') !== false): ?>
/* scroll to top */
window.scrollTo({ top: 0, behavior: 'smooth' });
<?php endif; ?>
</script>
</body>
</html>
