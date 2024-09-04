<?php
define('DB_HOST', 'localhost');
define('DB_USER', '');
define('DB_PASS', '');
define('DB_NAME', '');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}
?>
