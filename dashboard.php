<?php
session_start();
include 'config.php';
include 'log_function.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

logActivity($_SESSION['username'], "Visited Dashboard");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Vital Monitoring Rescue</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <nav class="navbar-top">
        <button class="btn btn-primary menu-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#staticBackdrop" aria-controls="staticBackdrop">
            <span class="navbar-toggler-icon"></span>
        </button>
        <h2 class="navbar-brand">Patient Vitals Rescue</h2>
    </nav>

    <div class="offcanvas offcanvas-start" data-bs-backdrop="static" tabindex="-1" id="staticBackdrop" aria-labelledby="staticBackdropLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="staticBackdropLabel">Menu</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <nav class="navbar-nav flex-column">
                <a class="nav-link" href="dashboard.php">Home</a>
<a class="nav-link" href="live_monitoring.php">Live Monitoring</a>
<a class="nav-link" href="alerts.php">Alerts / Notifications</a>
<a class="nav-link" href="patient_status_notes.php">Patient Status Notes</a>
<a class="nav-link" href="profile.php">Profile</a>
<a class="nav-link" href="logout.php">Logout</a>
            </nav>
        </div>
    </div>

    <h1>Welcome to the Patient Vital Monitoring Rescue Interface</h1>
    <p>This is a placeholder for the Rescue dashboard where you can monitor patient vitals.</p>

    <h2>System Activity Log</h2>

<table class="table table-bordered">
    <tr>
        <th>ID</th>
        <th>User</th>
        <th>Activity</th>
        <th>Date & Time</th>
    </tr>

<?php
$result = $conn->query("SELECT * FROM activity_logs ORDER BY log_time DESC");

while($row = $result->fetch_assoc()) {
    echo "<tr>
        <td>{$row['log_id']}</td>
        <td>{$row['username']}</td>
        <td>{$row['activity']}</td>
        <td>{$row['log_time']}</td>
    </tr>";
}
?>

</table>

<a href="dashboard.php" class="btn btn-primary">Back</a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>