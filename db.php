<?php

$conn = mysqli_connect("localhost", "root", "", "dynamic");

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

date_default_timezone_set('Asia/Dhaka');

$conn->query("SET time_zone = '+06:00'");

?>

