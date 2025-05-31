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

if (empty($_SESSION['cart'])) {
    echo "<p>A kosár üres.</p>";
} else {
    echo "<h3>Kosár tartalma:</h3>";
    echo "<table class='table'>";
    echo "<thead><tr><th>Termék</th><th>Mennyiség</th><th>Ár</th><th>Összesen</th></tr></thead>";
    echo "<tbody>";

    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        $query = "SELECT name, price FROM products WHERE id = ?";
        $stmt = $conn->prepare($query); 
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $stmt->bind_result($name, $price);
        $stmt->fetch();
        $stmt->close();

        $total_price = $price * $quantity;
        echo "<tr>";
        echo "<td>" . htmlspecialchars($name) . "</td>";
        echo "<td>" . htmlspecialchars($quantity) . "</td>";
        echo "<td>" . number_format($price, 2, ',', '.') . " Ft</td>";
        echo "<td>" . number_format($total_price, 2, ',', '.') . " Ft</td>";
        echo "</tr>";
    }

    echo "</tbody></table>";
    echo "<a href='?clear_cart=true' class='btn btn-danger'>Kosár ürítése</a>";
}
?>

<script>
    window.addEventListener("beforeunload", function(event) {
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "?clear_cart=true", true);
        xhr.send();

        event.preventDefault();
        event.returnValue = '';
    });
</script>

<?php
if (isset($_GET['clear_cart']) && $_GET['clear_cart'] === 'true') {
    unset($_SESSION['cart']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
?>
