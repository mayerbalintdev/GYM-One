<?php
session_start();

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

if (isset($_GET['userid'])) {
    $ticketbuyerid = htmlspecialchars($_GET['userid']);
} else {
    $ticketbuyerid = 'N/A';
}

$env_data = read_env_file('../../../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';
$currency = $env_data['CURRENCY'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['quantities'])) {
        $userid = $_POST['userid'];
        $quantities = $_POST['quantities'];

        $stmt = $conn->prepare("INSERT INTO temp_cart (product_id, quantity) VALUES (?, ?)");

        foreach ($quantities as $product_id => $quantity) {
            if ($quantity > 0) {
                $stmt->bind_param("ii", $product_id, $quantity);
                $stmt->execute();
            }
        }

        header("Location: ../payment/item/index.php?userid=$userid");
        exit;
    }
}
?>
