<?php
session_start();

if (!isset($_SESSION['userid'])) {
    header("Location: ../");
    exit();
}

$userid = $_SESSION['userid'];

$alerts_html = "";

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

$env_data = read_env_file('../../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../../../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}


$category_query = "SELECT * FROM categories";
$category_result = $conn->query($category_query);

$tree = array();
while ($category = $category_result->fetch_assoc()) {
    $category_id = $category['id'];
    $category_name = $category['name'];

    $tree[$category_id] = array(
        'name' => $category_name,
        'products' => array()
    );

    $product_query = "SELECT * FROM products WHERE category_id = $category_id";
    $product_result = $conn->query($product_query);

    while ($product = $product_result->fetch_assoc()) {
        $product_id = $product['id'];
        $product_name = $product['name'];
        $product_price = $product['price'];
        $product_quantity = $product['quantity'];

        $tree[$category_id]['products'][] = array(
            'name' => $product_name,
            'price' => $product_price,
            'quantity' => $product_quantity
        );
    }
}

?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raktár rendszer</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        .tree {
            padding-left: 20px;
        }
        .tree ul {
            padding-left: 20px;
        }
        .tree li {
            list-style-type: none;
        }
        .tree .fas {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4">Raktár fastruktúra</h2>

        <div class="tree">
            <ul>
                <?php
                foreach ($tree as $category_id => $category_data) {
                    echo "<li><i class='fas fa-folder'></i> " . $category_data['name'];
                    if (!empty($category_data['products'])) {
                        echo "<ul>";
                        foreach ($category_data['products'] as $product) {
                            echo "<li><i class='fas fa-box'></i> " . $product['name'] . " - Ár: " . $product['price'] . " Ft, Mennyiség: " . $product['quantity'] . "</li>";
                        }
                        echo "</ul>";
                    }
                    echo "</li>";
                }
                ?>
            </ul>
        </div>

    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>
