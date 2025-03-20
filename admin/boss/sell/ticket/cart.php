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

// Helyes adatbázis kapcsolat használata
$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}

// Ha nincs termék a kosárban, üzenetet jelenítünk meg
if (empty($_SESSION['cart'])) {
    echo "<p>A kosár üres.</p>";
} else {
    echo "<h3>Kosár tartalma:</h3>";
    echo "<table class='table'>";
    echo "<thead><tr><th>Termék</th><th>Mennyiség</th><th>Ár</th><th>Összesen</th></tr></thead>";
    echo "<tbody>";

    // Itt bejárjuk a kosárban lévő termékeket
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        // A termék adatainak lekérése az adatbázisból
        $query = "SELECT name, price FROM products WHERE id = ?";
        $stmt = $conn->prepare($query);  // Használjuk a helyes változót: $conn
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $stmt->bind_result($name, $price);
        $stmt->fetch();
        $stmt->close();

        // Megjelenítjük a termék adatait
        $total_price = $price * $quantity;
        echo "<tr>";
        echo "<td>" . htmlspecialchars($name) . "</td>";
        echo "<td>" . htmlspecialchars($quantity) . "</td>";
        echo "<td>" . number_format($price, 2, ',', '.') . " Ft</td>";
        echo "<td>" . number_format($total_price, 2, ',', '.') . " Ft</td>";
        echo "</tr>";
    }

    echo "</tbody></table>";
    // Kosár kiürítése gomb
    echo "<a href='?clear_cart=true' class='btn btn-danger'>Kosár ürítése</a>";
}
?>

<!-- JavaScript a kosár törléséhez, ha elhagyják az oldalt -->
<script>
    // Amikor az oldalról való kilépést próbálja a felhasználó
    window.addEventListener("beforeunload", function(event) {
        // AJAX kérés a kosár törléséhez
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "?clear_cart=true", true);  // Az aktuális oldalra küldött kérés, hogy töröljük a kosarat
        xhr.send();

        // Az alapértelmezett viselkedés megakadályozása, hogy a felhasználót figyelmeztessük
        event.preventDefault();
        event.returnValue = '';  // Modern böngészők nem jelenítenek meg üzenetet, ha ezt nem állítjuk
    });
</script>

<?php
// Kosár törlése, ha a GET paramétert megkapjuk
if (isset($_GET['clear_cart']) && $_GET['clear_cart'] === 'true') {
    unset($_SESSION['cart']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
?>
