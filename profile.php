<?php
session_start();
include 'log_function.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

logActivity($_SESSION['username'], "Viewed Profile");
?>

<!DOCTYPE html>
<html>
<head>
<title>Profile</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">

<h2>Rescuer Profile</h2>

<p><strong>Username:</strong> <?php echo $_SESSION['username']; ?></p>

</body>
</html>