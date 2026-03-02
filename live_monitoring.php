<?php
session_start();
include 'config.php';
include 'log_function.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

logActivity($_SESSION['username'], "Viewed Live Monitoring");
?>

<!DOCTYPE html>
<html>
<head>
<title>Live Monitoring</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
</head>
<body class="container mt-4">

<h2>Live Patient Monitoring</h2>

<table class="table table-bordered">
<tr>
<th>Name</th>
<th>Heart Rate</th>
<th>Blood Pressure</th>
<th>Oxygen Level</th>
<th>Status</th>
</tr>

<?php
$result = $conn->query("SELECT * FROM patients");

while($row = $result->fetch_assoc()) {
    echo "<tr>
        <td>{$row['patient_name']}</td>
        <td>{$row['heart_rate']} bpm</td>
        <td>{$row['blood_pressure']}</td>
        <td>{$row['oxygen_level']}%</td>
        <td>{$row['status']}</td>
    </tr>";
}
?>

</table>

</body>
</html>