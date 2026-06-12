<?php
session_start();

if (!isset($_SESSION['adminuser'])) {
    header("Location: ../");
    exit();
}

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
        $userid = isset($_POST['userid']) ? $_POST['userid'] : $ticketbuyerid;
        $quantities = $_POST['quantities'];

        // A temp_cart.user_id NOT NULL int(11); a vásárló userid bigint (túlcsordulna),
        // és a kosarat user_id-szűrés nélkül olvassuk vissza, ezért 0-t tárolunk.
        $cart_user_id = 0;

        // Csak érvényes, raktáron lévő mennyiségeket teszünk be (szerveroldali ellenőrzés).
        $stmt = $conn->prepare("INSERT INTO temp_cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $stock_stmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");

        foreach ($quantities as $product_id => $quantity) {
            $product_id = (int) $product_id;
            $quantity = (int) $quantity;
            if ($product_id <= 0 || $quantity <= 0) {
                continue;
            }

            $stock_stmt->bind_param("i", $product_id);
            $stock_stmt->execute();
            $stock_stmt->store_result();
            $stock_stmt->bind_result($stock);
            $has = $stock_stmt->fetch();
            $stock_stmt->free_result();
            if (!$has) {
                continue;
            }

            // Ne lehessen több a raktárkészletnél.
            if ($quantity > $stock) {
                $quantity = (int) $stock;
            }
            if ($quantity <= 0) {
                continue;
            }

            $stmt->bind_param("iii", $cart_user_id, $product_id, $quantity);
            $stmt->execute();
        }

        $stmt->close();
        $stock_stmt->close();

        header("Location: ../payment/item/index.php?userid=" . urlencode($userid));
        exit;
    }
}

$conn->close();
?>
