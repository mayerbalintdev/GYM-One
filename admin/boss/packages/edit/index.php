<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gymone";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$product = null;
if ($id > 0) {
    $sql = "SELECT * FROM products WHERE id = $id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
    } else {
        die("Nem található ilyen termék.");
    }
} else {
    die("Érvénytelen termékazonosító.");
}

$imageUrl = null;
$imagePath = "../../../../assets/img/packageimg/" . $product['barcode'] . ".png";

if (file_exists($imagePath)) {
    $imageUrl = "../../../../assets/img/packageimg/" . $product['barcode'] . ".png";
} else {
    if ($product && isset($product['barcode'])) {
        $barcode = $product['barcode'];
        $apiUrl = "https://api.gymoneglobal.com/GET/barcodescan/?barcode=" . $barcode;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (isset($data['imageUrl'])) {
            $imageUrl = $data['imageUrl'];
            $imageData = file_get_contents($imageUrl);
            if ($imageData) {
                file_put_contents($imagePath, $imageData);
                $imageUrl = "../../../../assets/img/packageimg/" . $product['barcode'] . ".png"; 
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $description = $conn->real_escape_string($_POST['description']);
    $price = $conn->real_escape_string($_POST['price']);
    $stock = $conn->real_escape_string($_POST['stock']);
    $barcode = $conn->real_escape_string($_POST['barcode']);

    $update_sql = "UPDATE products SET 
                   name = '$name', 
                   description = '$description', 
                   price = '$price', 
                   stock = '$stock', 
                   barcode = '$barcode' 
                   WHERE id = $id";

    if ($conn->query($update_sql) === TRUE) {
        header("Location: index.php?message=Sikeres frissítés!");
        exit;
    } else {
        echo "Hiba a frissítés során: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termék szerkesztése</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Termék Szerkesztése</h1>
        
        <?php if ($product): ?>
        <form method="POST">
            <div class="mb-3">
                <label for="name" class="form-label">Termék neve</label>
                <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Leírás</label>
                <textarea id="description" name="description" class="form-control" rows="3" required><?php echo htmlspecialchars($product['description']); ?></textarea>
            </div>
            <div class="mb-3">
                <label for="price" class="form-label">Ár (Ft)</label>
                <input type="number" id="price" name="price" class="form-control" step="0.01" value="<?php echo htmlspecialchars($product['price']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="stock" class="form-label">Készlet</label>
                <input type="number" id="stock" name="stock" class="form-control" value="<?php echo htmlspecialchars($product['stock']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="barcode" class="form-label">Vonalkód</label>
                <input type="text" id="barcode" name="barcode" class="form-control" value="<?php echo htmlspecialchars($product['barcode']); ?>" required>
            </div>

            <?php if ($imageUrl): ?>
            <div class="mb-3">
                <label class="form-label">Termék Kép</label><br>
                <img src="<?php echo $imageUrl; ?>" alt="Termék kép" class="img-fluid" style="max-width: 200px;">
            </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="index.php" class="btn btn-secondary">Mégse</a>
        </form>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
