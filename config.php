<?php
$conn = new mysqli("localhost", "root", "", "db_patient_rescue");

if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}
?>