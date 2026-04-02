<?php
session_start();
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = md5($_POST['password']);

    $query = $conn->query("SELECT * FROM users WHERE username='$username' AND password='$password'");

    if ($query->num_rows > 0) {
        $_SESSION['username'] = $username;

        include 'log_function.php';
        logActivity($username, "Logged into the system");

        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid Login!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Vital Monitoring Rescue - Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background-color: #f4f6f9;
}

.navbar-top {
    background-color: #0d6efd;
    color: white;
    padding: 15px;
    text-align: center;
    font-weight: bold;
    font-size: 20px;
}

.login-container {
    height: 85vh;
    display: flex;
    justify-content: center;
    align-items: center;
}

.card {
    width: 400px;
    border-radius: 10px;
}
</style>

</head>
<body>

<!-- Top Header (Same Theme as Dashboard) -->
<div class="navbar-top">
    Patient Vitals Rescue
</div>

<!-- Login Form -->
<div class="login-container">
    <div class="card shadow">
        <div class="card-header bg-primary text-white text-center">
            Rescuer Login
        </div>
        <div class="card-body">

            <?php if(isset($error)) { ?>
                <div class="alert alert-danger text-center">
                    <?php echo $error; ?>
                </div>
            <?php } ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button class="btn btn-primary w-100">Login</button>
            </form>

        </div>
    </div>
</div>

</body>
</html>