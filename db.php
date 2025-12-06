<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'hrmsv1'; // adjust if your DB name differs

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Database connection failed');
}

$conn->set_charset('utf8mb4');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
