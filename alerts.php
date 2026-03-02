<?php
session_start();
include 'config.php';
include 'log_function.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

logActivity($_SESSION['username'], "Viewed Alerts");
?>

<!DOCTYPE html>
<html>
<head>
<title>Alerts</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">

<h2>Emergency Alerts</h2>

<?php
$result = $conn->query("SELECT * FROM alerts ORDER BY alert_time DESC");

while($row = $result->fetch_assoc()) {
    echo "<div class='alert alert-danger'>
        <strong>{$row['patient_name']}</strong>: {$row['alert_message']}
        <br><small>{$row['alert_time']}</small>
    </div>";
}
?>

</body>
</html>