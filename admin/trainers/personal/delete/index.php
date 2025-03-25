<?php
session_start();

if (!isset($_SESSION['adminuser'])) {
    header("Location: ../../../../");
    exit();
}


$userid = $_SESSION['adminuser'];

function read_env_file($file_path)
{
    $env_file = file_get_contents($file_path);
    $env_lines = explode("\n", $env_file);
    $env_data = [];

    foreach ($env_lines as $line) {
        $line_parts = explode('=', $line);
        if (count($line_parts) == 2) {
            $key = trim($line_parts[0]);
            $value = trim($line_parts[1]);
            $env_data[$key] = $value;
        }
    }

    return $env_data;
}

$env_data = read_env_file('../../../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';
$capacity = $env_data["CAPACITY"] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../../../../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}
if (!isset($_GET['id'])) {
    header("Location: ../");
    exit();
}

$trainer_id = intval($_GET['id']);

$sql = "SELECT is_boss FROM workers WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userid);
$stmt->execute();
$stmt->store_result();

$is_boss = null;

if ($stmt->num_rows > 0) {
    $stmt->bind_result($is_boss);
    $stmt->fetch();
}

$stmt->close();

if ($is_boss != 1) {
    die($translations["dont-access"]);
}

$image_path = "../../../../assets/img/trainers/trainer_$trainer_id.png";
if (file_exists($image_path)) {
    unlink($image_path);
}

$sql = "DELETE FROM trainers WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $trainer_id);

if ($stmt->execute()) {
    $action = $translations["log_delete_trainer"]  . ' ID: ' . $trainer_id;
    $actioncolor = 'danger';
    $sql = "INSERT INTO logs (userid, action, actioncolor, time) 
            VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $userid, $action, $actioncolor);
    $stmt->execute();

    header("Location: ../");
} else {
    echo $translations["delete-error"] . $conn->error;
}

$stmt->close();
$conn->close();
?>
