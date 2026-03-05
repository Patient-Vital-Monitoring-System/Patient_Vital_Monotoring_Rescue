<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$rescuerId = $_SESSION['rescuer_id'] ?? 1;
$success = $error = '';

/* Handle password change */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($new !== $confirm) {
        $error = "New passwords do not match.";
    } elseif (strlen($new) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $uq = $conn->query("SELECT * FROM users WHERE username='" . $conn->real_escape_string($_SESSION['username']) . "' LIMIT 1");
        if ($uq && $uq->num_rows > 0) {
            $user = $uq->fetch_assoc();
            if (password_verify($current, $user['password_hash'])) {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $conn->query("UPDATE users SET password_hash='$hash' WHERE user_id=" . $user['user_id']);
                $success = "Password updated successfully.";
            } else {
                $error = "Current password is incorrect.";
            }
        }
    }
}

/* Fetch rescuer stats */
$statsQ = $conn->query("
    SELECT
        SUM(status='transferred') AS transferred,
        SUM(status='ongoing') AS ongoing,
        SUM(status='completed') AS completed,
        COUNT(*) AS total
    FROM incident WHERE rescuer_id=$rescuerId
");
$stats = $statsQ ? $statsQ->fetch_assoc() : [];

$alertQ = $conn->query("SELECT COUNT(*) AS c FROM incident WHERE rescuer_id=$rescuerId AND status='transferred'");
$alertCount = $alertQ ? (int)$alertQ->fetch_assoc()['c'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Profile — RescueNet</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="page-content">

<?php include 'navbar.php'; ?>

<div class="page-header">
    <h1>Profile</h1>
    <p>Your account and activity summary.</p>
</div>

<!-- PROFILE CARD -->
<div class="section-card">
    <div class="section-card-body" style="text-align:center;padding:24px 16px;">
        <div style="font-size:3.5rem;margin-bottom:10px;">👤</div>
        <div style="font-size:1.4rem;font-weight:800;font-family:var(--font-display);color:var(--text-dark);">
            <?= htmlspecialchars($_SESSION['username']) ?>
        </div>
        <div class="badge-pill badge-ongoing" style="margin-top:8px;">🚑 Rescuer</div>
    </div>
</div>

<!-- ACTIVITY STATS -->
<div class="section-card">
    <div class="section-card-header">📊 Your Activity</div>
    <div class="section-card-body">
        <div class="stats-grid" style="padding:0;">
            <div class="stat-card">
                <div class="stat-num"><?= $stats['total'] ?? 0 ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card stat-ongoing">
                <div class="stat-num"><?= $stats['ongoing'] ?? 0 ?></div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card stat-completed">
                <div class="stat-num"><?= $stats['completed'] ?? 0 ?></div>
                <div class="stat-label">Done</div>
            </div>
            <div class="stat-card">
                <div class="stat-num"><?= $stats['transferred'] ?? 0 ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
    </div>
</div>

<!-- CHANGE PASSWORD -->
<div class="section-card">
    <div class="section-card-header">🔒 Change Password</div>
    <div class="section-card-body">
        <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger">⚠️ <?= $error ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="form-section mb-2">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="form-section mb-2">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" required minlength="8">
            </div>
            <div class="form-section mb-2">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full">🔐 Update Password</button>
        </form>
    </div>
</div>

<!-- LOGOUT -->
<div style="padding:0 16px 32px;">
    <a href="logout.php" class="btn btn-secondary btn-full"
        onclick="return confirm('Sign out of RescueNet?')">🔓 Sign Out</a>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
