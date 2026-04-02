<?php
session_start();
include 'config.php';
include 'log_function.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

if (isset($_POST['add_note'])) {
    $patient = $_POST['patient_name'];
    $note = $_POST['note'];
    $user = $_SESSION['username'];

    $stmt = $conn->prepare("INSERT INTO patient_notes (patient_name, note, created_by) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $patient, $note, $user);
    $stmt->execute();
    $stmt->close();

    logActivity($user, "Added note for patient: $patient");
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Patient Notes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">

<h2>Patient Status Notes</h2>

<form method="POST">
<input type="text" name="patient_name" class="form-control mb-2" placeholder="Patient Name" required>
<textarea name="note" class="form-control mb-2" placeholder="Write note..." required></textarea>
<button name="add_note" class="btn btn-primary">Add Note</button>
</form>

<hr>

<?php
$result = $conn->query("SELECT * FROM patient_notes ORDER BY created_at DESC");

while($row = $result->fetch_assoc()) {
    echo "<div class='card mb-2'>
        <div class='card-body'>
        <strong>{$row['patient_name']}</strong><br>
        {$row['note']}<br>
        <small>By {$row['created_by']} - {$row['created_at']}</small>
        </div>
    </div>";
}
?>

</body>
</html>