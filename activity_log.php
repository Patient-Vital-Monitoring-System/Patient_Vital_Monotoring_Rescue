<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Activity Log</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">

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

</body>
</html>