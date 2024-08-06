<?php
header('Content-Type: application/json');

// Database configuration
$host = 'localhost';
$db = 'gymone';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

$qrCode = isset($_POST['qrcode']) ? $conn->real_escape_string($_POST['qrcode']) : '';

$sql = "SELECT firstname, lastname FROM users WHERE userid = '$qrCode'";
$result = $conn->query($sql);

$response = ['success' => false];

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $response['success'] = true;
    $response['firstname'] = $row['firstname'];
    $response['lastname'] = $row['lastname'];
} else {
    $response['error'] = 'User not found';
}

$conn->close();

echo json_encode($response);
?>
