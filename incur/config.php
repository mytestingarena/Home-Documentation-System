<?php
// Database connection — do NOT commit real passwords to GitHub.
// Put the live-server password in config.local.php (see config.local.php.example).

$servername = "localhost";
$username   = "house";
$dbname     = "house_info";
$password   = "your_mysql_password_here";

if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

mysqli_report(MYSQLI_REPORT_OFF);

$conn = @new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
