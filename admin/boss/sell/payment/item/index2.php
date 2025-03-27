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

$env_data = read_env_file('../../../../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';
$currency = $env_data['CURRENCY'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../../../../../assets/lang/";

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
    echo "<thead><tr><th>Termék</th><th>Mennyiség</th><th>Ár</th><th>Összesen</th><th>Művelet</th></tr></thead>";
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
        echo "<td><input type='number' value='$quantity' data-product-id='$product_id' class='quantity-input' min='1' /></td>";
        echo "<td>" . number_format($price, 2, ',', '.') . " Ft</td>";
        echo "<td>" . number_format($total_price, 2, ',', '.') . " Ft</td>";
        echo "<td><button class='btn btn-danger' data-product-id='$product_id' onclick='removeProduct(this)'>Törlés</button></td>";
        echo "</tr>";
    }

    echo "</tbody></table>";
    // Kosár kiürítése gomb
    echo "<a href='?clear_cart=true' class='btn btn-danger'>Kosár ürítése</a>";
}
?>

<script>
    // AJAX kérés a kosár frissítéséhez
    function updateCart(productId, quantity) {
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "?update_cart=true&product_id=" + productId + "&quantity=" + quantity, true);
        xhr.send();
    }

    // Termék eltávolítása a kosárból
    function removeProduct(button) {
        var productId = button.getAttribute('data-product-id');
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "?remove_product=true&product_id=" + productId, true);
        xhr.send();

        // Termék eltávolítása a táblázatból
        button.closest('tr').remove();
    }

    // Mennyiség módosításakor frissítjük a kosarat
    document.querySelectorAll('.quantity-input').forEach(input => {
        input.addEventListener('change', function() {
            var productId = this.getAttribute('data-product-id');
            var quantity = this.value;
            updateCart(productId, quantity);
        });
    });
</script>

<?php
// Kosár frissítése
if (isset($_GET['update_cart']) && isset($_GET['product_id']) && isset($_GET['quantity'])) {
    $product_id = intval($_GET['product_id']);
    $quantity = intval($_GET['quantity']);
    
    // Frissítjük a kosarat az új mennyiséggel
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] = $quantity;
    }
    exit();  // AJAX válasz befejezése
}

// Termék eltávolítása a kosárból
if (isset($_GET['remove_product']) && isset($_GET['product_id'])) {
    $product_id = intval($_GET['product_id']);
    
    // Eltávolítjuk a terméket a kosárból
    unset($_SESSION['cart'][$product_id]);
    exit();  // AJAX válasz befejezése
}

// Kosár törlése, ha a GET paramétert megkapjuk
if (isset($_GET['clear_cart']) && $_GET['clear_cart'] === 'true') {
    unset($_SESSION['cart']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
?>
