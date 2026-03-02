<?php
include 'config.php';

function logActivity($username, $activity) {
    global $conn;

    $stmt = $conn->prepare("INSERT INTO activity_logs (username, activity) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $activity);
    $stmt->execute();
    $stmt->close();
}
?>