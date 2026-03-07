<?php
session_start();
include 'config.php';

if (isset($_SESSION['username'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $q = $conn->query("SELECT * FROM users WHERE username='$username' AND role='rescuer' LIMIT 1");
    if ($q && $q->num_rows > 0) {
        $user = $q->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['username']   = $user['username'];
            $_SESSION['user_id']    = $user['user_id'];
            $_SESSION['rescuer_id'] = $user['rescuer_id'] ?? 1;
            $_SESSION['role']       = 'rescuer';
            header("Location: dashboard.php");
            exit;
        }
    }
    $error = "Invalid username or password.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Login — RescueNet</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
<style>
.login-wrap {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
    background: linear-gradient(160deg, #fff 60%, rgba(220,53,69,0.06) 100%);
}
.login-logo {
    text-align: center;
    margin-bottom: 32px;
}
.login-logo .logo-icon {
    font-size: 3.5rem;
    line-height: 1;
    margin-bottom: 10px;
}
.login-logo h1 {
    font-size: 2.2rem;
    margin: 0;
    font-family: var(--font-display);
}
.login-logo p {
    color: var(--text-muted);
    font-size: 0.88rem;
    margin: 4px 0 0;
    padding: 0;
}
.login-card {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow-md);
    padding: 28px 24px;
    width: 100%;
    max-width: 400px;
}
</style>
</head>
<body>
<div class="login-wrap">
    <div class="login-logo">
        <div class="logo-icon">🚑</div>
        <h1>RescueNet</h1>
        <p>Rescuer Portal</p>
    </div>

    <div class="login-card">
        <?php if ($error): ?>
        <div class="alert alert-danger">⚠️ <?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-section mb-2">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control"
                    placeholder="Enter username" required autocomplete="username">
            </div>

            <div class="form-section mb-2">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control"
                    placeholder="Enter password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:8px;">
                🔐 Sign In
            </button>
        </form>
    </div>

    <p style="margin-top:20px;font-size:0.78rem;color:var(--text-muted);text-align:center;padding:0;">
        RescueNet Emergency Response System
    </p>
</div>
</body>
</html>
