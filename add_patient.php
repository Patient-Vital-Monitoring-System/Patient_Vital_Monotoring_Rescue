<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$rescuerId   = $_SESSION['rescuer_id'] ?? 1;
$rescuerName = $_SESSION['username'] ?? 'Rescuer';
$success = $error = '';
$newIncidentId = null;

/* ── HANDLE FORM SUBMISSION ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* Patient fields */
    $fullName      = $conn->real_escape_string(trim($_POST['full_name'] ?? ''));
    $age           = (int)($_POST['age'] ?? 0);
    $gender        = $conn->real_escape_string($_POST['gender'] ?? '');
    $bloodType     = $conn->real_escape_string($_POST['blood_type'] ?? '');
    $allergies     = $conn->real_escape_string(trim($_POST['allergies'] ?? 'None'));
    $medHistory    = $conn->real_escape_string(trim($_POST['medical_history'] ?? 'None'));
    $contactNumber = $conn->real_escape_string(trim($_POST['contact_number'] ?? ''));

    /* Incident fields */
    $incidentType  = $conn->real_escape_string(trim($_POST['incident_type'] ?? ''));
    $severity      = $conn->real_escape_string($_POST['severity'] ?? 'medium');
    $location      = $conn->real_escape_string(trim($_POST['location'] ?? ''));
    $notes         = $conn->real_escape_string(trim($_POST['notes'] ?? ''));
    $status        = $conn->real_escape_string($_POST['status'] ?? 'transferred');

    /* Initial vitals (optional) */
    $heartRate  = trim($_POST['heart_rate'] ?? '');
    $bloodPres  = $conn->real_escape_string(trim($_POST['blood_pressure'] ?? ''));
    $spo2       = trim($_POST['spo2'] ?? '');
    $temp       = trim($_POST['temperature'] ?? '');
    $respRate   = trim($_POST['respiratory_rate'] ?? '');
    $gcs        = trim($_POST['gcs_score'] ?? '');
    $vitalNotes = $conn->real_escape_string(trim($_POST['vital_notes'] ?? ''));

    /* Validation */
    if (empty($fullName)) {
        $error = "Patient full name is required.";
    } elseif ($age < 1 || $age > 120) {
        $error = "Please enter a valid age.";
    } elseif (empty($incidentType)) {
        $error = "Incident type is required.";
    } elseif (empty($location)) {
        $error = "Location is required.";
    } else {
        /* INSERT PATIENT */
        $sqlPatient = "INSERT INTO patient
            (full_name, age, gender, blood_type, allergies, medical_history, contact_number)
            VALUES ('$fullName', $age, '$gender', '$bloodType', '$allergies', '$medHistory', '$contactNumber')";

        if ($conn->query($sqlPatient)) {
            $patientId = $conn->insert_id;

            /* INSERT INCIDENT */
            $sqlIncident = "INSERT INTO incident
                (patient_id, rescuer_id, incident_type, severity, status, location, notes, start_time)
                VALUES ($patientId, $rescuerId, '$incidentType', '$severity', '$status', '$location', '$notes', NOW())";

            if ($conn->query($sqlIncident)) {
                $newIncidentId = $conn->insert_id;

                /* INSERT INITIAL VITALS if provided */
                if (!empty($heartRate) && !empty($bloodPres) && !empty($spo2) && !empty($temp)) {
                    $hr   = (int)$heartRate;
                    $sp   = (float)$spo2;
                    $tp   = (float)$temp;
                    $rr   = !empty($respRate) ? (int)$respRate : 'NULL';
                    $gc   = !empty($gcs) ? (int)$gcs : 'NULL';
                    $recBy = $conn->real_escape_string("rescuer:$rescuerName");

                    $sqlVital = "INSERT INTO vitalstat
                        (incident_id, heart_rate, blood_pressure, spo2, temperature,
                         respiratory_rate, gcs_score, notes, recorded_by, recorded_at)
                        VALUES ($newIncidentId, $hr, '$bloodPres', $sp, $tp,
                                $rr, $gc, '$vitalNotes', '$recBy', NOW())";
                    $conn->query($sqlVital);
                }

                $success = "Patient and incident successfully added!";
            } else {
                $error = "Failed to create incident: " . $conn->error;
                /* Rollback patient insert */
                $conn->query("DELETE FROM patient WHERE patient_id=$patientId");
            }
        } else {
            $error = "Failed to add patient: " . $conn->error;
        }
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
<title>Add Patient — RescueNet</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
<style>
.step-indicator {
    display: flex;
    align-items: center;
    gap: 0;
    margin: 0 16px 16px;
    background: white;
    border: 1px solid var(--border-light);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}

.step {
    flex: 1;
    text-align: center;
    padding: 12px 8px;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.4px;
    border-right: 1px solid var(--border-light);
    transition: all 0.3s;
    cursor: pointer;
}

.step:last-child { border-right: none; }

.step.active {
    background: var(--primary-red);
    color: white;
}

.step.done {
    background: rgba(25,135,84,0.1);
    color: var(--success);
}

.step-num {
    display: block;
    font-size: 1.1rem;
    margin-bottom: 2px;
}

.form-step {
    display: none;
}

.form-step.active {
    display: block;
}

.step-nav {
    display: flex;
    gap: 10px;
    padding: 0 16px 24px;
}

.required-star {
    color: var(--primary-red);
    margin-left: 2px;
}

.field-hint {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 3px;
}

.success-screen {
    text-align: center;
    padding: 40px 20px;
}

.success-screen .success-icon {
    font-size: 4rem;
    margin-bottom: 16px;
    animation: popIn 0.4s ease;
}

@keyframes popIn {
    from { transform: scale(0); opacity: 0; }
    to   { transform: scale(1); opacity: 1; }
}

.success-screen h2 {
    font-size: 1.6rem;
    font-family: var(--font-display);
    margin-bottom: 8px;
}

.success-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 24px;
}
</style>
</head>
<body>
<div class="page-content">

<?php include 'navbar.php'; ?>

<div class="page-header">
    <h1>Add Patient</h1>
    <p>Register a new patient and create an incident.</p>
</div>

<?php if ($success && $newIncidentId): /* SUCCESS SCREEN */ ?>

<div class="section-card">
    <div class="section-card-body">
        <div class="success-screen">
            <div class="success-icon">✅</div>
            <h2>Patient Added!</h2>
            <p style="color:var(--text-muted);padding:0;margin:0;">
                The patient and incident have been successfully created and assigned to you.
            </p>

            <div style="background:var(--light-bg);border-radius:var(--radius);padding:16px;margin-top:20px;text-align:left;">
                <div style="font-size:0.78rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Incident Created</div>
                <div style="font-size:1.3rem;font-weight:800;font-family:var(--font-display);color:var(--primary-red);">
                    #<?= str_pad($newIncidentId, 4, '0', STR_PAD_LEFT) ?>
                </div>
            </div>

            <div class="success-actions">
                <?php
                $stmt = $conn->query("SELECT status FROM incident WHERE incident_id=$newIncidentId LIMIT 1");
                $inc = $stmt ? $stmt->fetch_assoc() : null;
                $incStatus = $inc['status'] ?? 'transferred';
                ?>
                <?php if ($incStatus === 'ongoing'): ?>
                <a href="monitoring.php?id=<?= $newIncidentId ?>" class="btn btn-primary btn-full btn-lg">
                    ❤️ Start Monitoring Patient
                </a>
                <?php else: ?>
                <a href="incoming.php?id=<?= $newIncidentId ?>" class="btn btn-primary btn-full btn-lg">
                    📥 View Incident
                </a>
                <?php endif; ?>
                <a href="add_patient.php" class="btn btn-secondary btn-full">
                    ➕ Add Another Patient
                </a>
                <a href="dashboard.php" class="btn btn-secondary btn-full">
                    🏠 Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<?php else: /* FORM */ ?>

<?php if ($error): ?>
<div style="padding:0 16px;">
    <div class="alert alert-danger">⚠️ <?= $error ?></div>
</div>
<?php endif; ?>

<!-- STEP INDICATORS -->
<div class="step-indicator">
    <div class="step active" id="stepTab1" onclick="goToStep(1)">
        <span class="step-num">👤</span>
        Patient
    </div>
    <div class="step" id="stepTab2" onclick="goToStep(2)">
        <span class="step-num">🚨</span>
        Incident
    </div>
    <div class="step" id="stepTab3" onclick="goToStep(3)">
        <span class="step-num">💓</span>
        Vitals
    </div>
</div>

<form method="POST" id="mainForm" onsubmit="return validateFinal()">

    <!-- ══════════════════════════════════════
         STEP 1 — PATIENT INFO
    ══════════════════════════════════════ -->
    <div class="form-step active" id="step1">
        <div class="section-card">
            <div class="section-card-header">👤 Patient Information</div>
            <div class="section-card-body">

                <div class="form-section mb-2">
                    <label class="form-label">Full Name <span class="required-star">*</span></label>
                    <input type="text" name="full_name" id="full_name" class="form-control"
                        placeholder="e.g. Juan dela Cruz"
                        value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                </div>

                <div class="form-row mb-2">
                    <div class="form-section">
                        <label class="form-label">Age <span class="required-star">*</span></label>
                        <input type="number" name="age" id="age" class="form-control"
                            placeholder="e.g. 35" min="1" max="120"
                            value="<?= htmlspecialchars($_POST['age'] ?? '') ?>" required>
                    </div>
                    <div class="form-section">
                        <label class="form-label">Gender <span class="required-star">*</span></label>
                        <select name="gender" class="form-select" required>
                            <option value="">Select...</option>
                            <option value="male"   <?= ($_POST['gender'] ?? '') === 'male'   ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= ($_POST['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                            <option value="other"  <?= ($_POST['gender'] ?? '') === 'other'  ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row mb-2">
                    <div class="form-section">
                        <label class="form-label">Blood Type</label>
                        <select name="blood_type" class="form-select">
                            <option value="">Unknown</option>
                            <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                            <option value="<?= $bt ?>" <?= ($_POST['blood_type'] ?? '') === $bt ? 'selected' : '' ?>><?= $bt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-section">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control"
                            placeholder="e.g. 09123456789"
                            value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-section mb-2">
                    <label class="form-label">Allergies</label>
                    <input type="text" name="allergies" class="form-control"
                        placeholder="e.g. Penicillin, Aspirin — or type None"
                        value="<?= htmlspecialchars($_POST['allergies'] ?? '') ?>">
                </div>

                <div class="form-section mb-2">
                    <label class="form-label">Medical History</label>
                    <textarea name="medical_history" class="form-control" rows="3"
                        placeholder="e.g. Hypertension, Diabetes — or type None"><?= htmlspecialchars($_POST['medical_history'] ?? '') ?></textarea>
                </div>

            </div>
        </div>

        <div class="step-nav">
            <a href="dashboard.php" class="btn btn-secondary" style="flex:1;">Cancel</a>
            <button type="button" class="btn btn-primary" style="flex:2;" onclick="nextStep(1)">
                Next: Incident Info →
            </button>
        </div>
    </div>

    <!-- ══════════════════════════════════════
         STEP 2 — INCIDENT INFO
    ══════════════════════════════════════ -->
    <div class="form-step" id="step2">
        <div class="section-card">
            <div class="section-card-header">🚨 Incident Information</div>
            <div class="section-card-body">

                <div class="form-section mb-2">
                    <label class="form-label">Incident Type <span class="required-star">*</span></label>
                    <select name="incident_type" id="incident_type" class="form-select" required>
                        <option value="">Select type...</option>
                        <optgroup label="Cardiac">
                            <option value="Cardiac Arrest"     <?= ($_POST['incident_type'] ?? '') === 'Cardiac Arrest'     ? 'selected':'' ?>>Cardiac Arrest</option>
                            <option value="Chest Pain"         <?= ($_POST['incident_type'] ?? '') === 'Chest Pain'         ? 'selected':'' ?>>Chest Pain</option>
                            <option value="Heart Attack"       <?= ($_POST['incident_type'] ?? '') === 'Heart Attack'       ? 'selected':'' ?>>Heart Attack</option>
                        </optgroup>
                        <optgroup label="Respiratory">
                            <option value="Respiratory Distress" <?= ($_POST['incident_type'] ?? '') === 'Respiratory Distress' ? 'selected':'' ?>>Respiratory Distress</option>
                            <option value="Asthma Attack"        <?= ($_POST['incident_type'] ?? '') === 'Asthma Attack'        ? 'selected':'' ?>>Asthma Attack</option>
                        </optgroup>
                        <optgroup label="Trauma">
                            <option value="Trauma — MVA"    <?= ($_POST['incident_type'] ?? '') === 'Trauma — MVA'    ? 'selected':'' ?>>Trauma — MVA</option>
                            <option value="Trauma — Fall"   <?= ($_POST['incident_type'] ?? '') === 'Trauma — Fall'   ? 'selected':'' ?>>Trauma — Fall</option>
                            <option value="Trauma — Assault"<?= ($_POST['incident_type'] ?? '') === 'Trauma — Assault'? 'selected':'' ?>>Trauma — Assault</option>
                        </optgroup>
                        <optgroup label="Medical">
                            <option value="Diabetic Emergency"  <?= ($_POST['incident_type'] ?? '') === 'Diabetic Emergency'  ? 'selected':'' ?>>Diabetic Emergency</option>
                            <option value="Seizure"             <?= ($_POST['incident_type'] ?? '') === 'Seizure'             ? 'selected':'' ?>>Seizure</option>
                            <option value="Stroke"              <?= ($_POST['incident_type'] ?? '') === 'Stroke'              ? 'selected':'' ?>>Stroke</option>
                            <option value="Allergic Reaction"   <?= ($_POST['incident_type'] ?? '') === 'Allergic Reaction'   ? 'selected':'' ?>>Allergic Reaction</option>
                            <option value="Poisoning"           <?= ($_POST['incident_type'] ?? '') === 'Poisoning'           ? 'selected':'' ?>>Poisoning</option>
                        </optgroup>
                        <optgroup label="Other">
                            <option value="Obstetric Emergency" <?= ($_POST['incident_type'] ?? '') === 'Obstetric Emergency' ? 'selected':'' ?>>Obstetric Emergency</option>
                            <option value="Drowning"            <?= ($_POST['incident_type'] ?? '') === 'Drowning'            ? 'selected':'' ?>>Drowning</option>
                            <option value="Burns"               <?= ($_POST['incident_type'] ?? '') === 'Burns'               ? 'selected':'' ?>>Burns</option>
                            <option value="Other"               <?= ($_POST['incident_type'] ?? '') === 'Other'               ? 'selected':'' ?>>Other</option>
                        </optgroup>
                    </select>
                </div>

                <div class="form-row mb-2">
                    <div class="form-section">
                        <label class="form-label">Severity <span class="required-star">*</span></label>
                        <select name="severity" class="form-select" required>
                            <option value="low"      <?= ($_POST['severity'] ?? '') === 'low'      ? 'selected':'' ?>>🟢 Low</option>
                            <option value="medium"   <?= ($_POST['severity'] ?? 'medium') === 'medium'   ? 'selected':'' ?>>🟡 Medium</option>
                            <option value="high"     <?= ($_POST['severity'] ?? '') === 'high'     ? 'selected':'' ?>>🟠 High</option>
                            <option value="critical" <?= ($_POST['severity'] ?? '') === 'critical' ? 'selected':'' ?>>🔴 Critical</option>
                        </select>
                    </div>
                    <div class="form-section">
                        <label class="form-label">Initial Status</label>
                        <select name="status" class="form-select">
                            <option value="transferred" <?= ($_POST['status'] ?? 'transferred') === 'transferred' ? 'selected':'' ?>>Transferred</option>
                            <option value="ongoing"     <?= ($_POST['status'] ?? '') === 'ongoing'     ? 'selected':'' ?>>Ongoing</option>
                        </select>
                        <div class="field-hint">Set "Ongoing" if you're already on scene.</div>
                    </div>
                </div>

                <div class="form-section mb-2">
                    <label class="form-label">Location <span class="required-star">*</span></label>
                    <input type="text" name="location" id="location" class="form-control"
                        placeholder="e.g. Brgy. Poblacion, Purok 3, Libona"
                        value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" required>
                </div>

                <div class="form-section mb-2">
                    <label class="form-label">Incident Notes</label>
                    <textarea name="notes" class="form-control" rows="3"
                        placeholder="Initial observations, situation details..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                </div>

            </div>
        </div>

        <div class="step-nav">
            <button type="button" class="btn btn-secondary" style="flex:1;" onclick="goToStep(1)">← Back</button>
            <button type="button" class="btn btn-primary" style="flex:2;" onclick="nextStep(2)">
                Next: Initial Vitals →
            </button>
        </div>
    </div>

    <!-- ══════════════════════════════════════
         STEP 3 — INITIAL VITALS (optional)
    ══════════════════════════════════════ -->
    <div class="form-step" id="step3">
        <div class="section-card">
            <div class="section-card-header">💓 Initial Vitals <span style="font-size:0.8rem;font-weight:400;opacity:0.85;">(Optional)</span></div>
            <div class="section-card-body">

                <div class="alert alert-info" style="font-size:0.83rem;">
                    ℹ️ Fill in vitals if you have initial readings. You can skip this and add readings later in <strong>Monitoring</strong>.
                </div>

                <div class="form-row mb-2">
                    <div class="form-section">
                        <label class="form-label">Heart Rate</label>
                        <input type="number" name="heart_rate" class="form-control"
                            placeholder="bpm" min="20" max="250"
                            value="<?= htmlspecialchars($_POST['heart_rate'] ?? '') ?>">
                    </div>
                    <div class="form-section">
                        <label class="form-label">Blood Pressure</label>
                        <input type="text" name="blood_pressure" class="form-control"
                            placeholder="120/80"
                            value="<?= htmlspecialchars($_POST['blood_pressure'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row mb-2">
                    <div class="form-section">
                        <label class="form-label">SpO2</label>
                        <input type="number" name="spo2" class="form-control"
                            placeholder="%" step="0.1" min="50" max="100"
                            value="<?= htmlspecialchars($_POST['spo2'] ?? '') ?>">
                    </div>
                    <div class="form-section">
                        <label class="form-label">Temperature</label>
                        <input type="number" name="temperature" class="form-control"
                            placeholder="°C" step="0.1" min="30" max="45"
                            value="<?= htmlspecialchars($_POST['temperature'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row mb-2">
                    <div class="form-section">
                        <label class="form-label">Resp. Rate</label>
                        <input type="number" name="respiratory_rate" class="form-control"
                            placeholder="breaths/min" min="5" max="60"
                            value="<?= htmlspecialchars($_POST['respiratory_rate'] ?? '') ?>">
                    </div>
                    <div class="form-section">
                        <label class="form-label">GCS Score</label>
                        <input type="number" name="gcs_score" class="form-control"
                            placeholder="3–15" min="3" max="15"
                            value="<?= htmlspecialchars($_POST['gcs_score'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-section mb-2">
                    <label class="form-label">Vital Notes</label>
                    <textarea name="vital_notes" class="form-control" rows="2"
                        placeholder="Patient condition, observations..."><?= htmlspecialchars($_POST['vital_notes'] ?? '') ?></textarea>
                </div>

            </div>
        </div>

        <!-- SUMMARY PREVIEW -->
        <div class="section-card" id="summaryCard">
            <div class="section-card-header">📋 Summary</div>
            <div class="section-card-body" id="summaryBody" style="font-size:0.88rem;">
                <!-- filled by JS -->
            </div>
        </div>

        <div class="step-nav">
            <button type="button" class="btn btn-secondary" style="flex:1;" onclick="goToStep(2)">← Back</button>
            <button type="submit" class="btn btn-primary" style="flex:2;" id="submitBtn">
                ✅ Save Patient & Incident
            </button>
        </div>
    </div>

</form>

<?php endif; ?>

</div><!-- .page-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentStep = 1;

function goToStep(step) {
    /* Hide all steps */
    document.querySelectorAll('.form-step').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));

    /* Show selected */
    document.getElementById('step' + step).classList.add('active');
    document.getElementById('stepTab' + step).classList.add('active');

    /* Mark previous steps as done */
    for (let i = 1; i < step; i++) {
        document.getElementById('stepTab' + i).classList.add('done');
    }

    currentStep = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });

    if (step === 3) buildSummary();
}

function nextStep(from) {
    /* Validate current step */
    if (from === 1) {
        const name = document.getElementById('full_name').value.trim();
        const age  = document.getElementById('age').value;
        const gender = document.querySelector('[name="gender"]').value;
        if (!name) { alert('Please enter the patient\'s full name.'); return; }
        if (!age || age < 1)  { alert('Please enter a valid age.'); return; }
        if (!gender) { alert('Please select a gender.'); return; }
    }
    if (from === 2) {
        const type = document.getElementById('incident_type').value;
        const loc  = document.getElementById('location').value.trim();
        if (!type) { alert('Please select an incident type.'); return; }
        if (!loc)  { alert('Please enter the incident location.'); return; }
    }
    goToStep(from + 1);
}

function validateFinal() {
    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<span class="spinner"></span> Saving...';
    btn.disabled = true;
    return true;
}

function buildSummary() {
    const name    = document.querySelector('[name="full_name"]').value || '—';
    const age     = document.querySelector('[name="age"]').value || '—';
    const gender  = document.querySelector('[name="gender"]').value || '—';
    const blood   = document.querySelector('[name="blood_type"]').value || 'Unknown';
    const type    = document.querySelector('[name="incident_type"]').value || '—';
    const sev     = document.querySelector('[name="severity"]').value || '—';
    const status  = document.querySelector('[name="status"]').value || '—';
    const loc     = document.querySelector('[name="location"]').value || '—';
    const hr      = document.querySelector('[name="heart_rate"]').value;
    const bp      = document.querySelector('[name="blood_pressure"]').value;

    const sevColors = { low:'#198754', medium:'#856404', high:'#fd7e14', critical:'#dc3545' };
    const sevColor  = sevColors[sev] || '#333';

    document.getElementById('summaryBody').innerHTML = `
        <table style="width:100%;font-size:0.87rem;">
            <tr><td style="color:var(--text-muted);padding:4px 0;width:38%;">Patient</td>
                <td style="font-weight:700;">${name}</td></tr>
            <tr><td style="color:var(--text-muted);padding:4px 0;">Age / Gender</td>
                <td style="font-weight:600;">${age} / ${gender}</td></tr>
            <tr><td style="color:var(--text-muted);padding:4px 0;">Blood Type</td>
                <td style="font-weight:600;">${blood}</td></tr>
            <tr><td style="color:var(--text-muted);padding:4px 0;">Incident</td>
                <td style="font-weight:600;">${type}</td></tr>
            <tr><td style="color:var(--text-muted);padding:4px 0;">Severity</td>
                <td style="font-weight:700;color:${sevColor};">${sev.toUpperCase()}</td></tr>
            <tr><td style="color:var(--text-muted);padding:4px 0;">Status</td>
                <td style="font-weight:600;">${status}</td></tr>
            <tr><td style="color:var(--text-muted);padding:4px 0;">Location</td>
                <td style="font-weight:600;">${loc}</td></tr>
            ${hr ? `<tr><td style="color:var(--text-muted);padding:4px 0;">Initial HR</td>
                <td style="font-weight:600;">${hr} bpm</td></tr>` : ''}
            ${bp ? `<tr><td style="color:var(--text-muted);padding:4px 0;">Initial BP</td>
                <td style="font-weight:600;">${bp} mmHg</td></tr>` : ''}
        </table>
    `;
}
</script>
</body>
</html>
