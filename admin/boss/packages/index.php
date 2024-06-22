<?php
session_start();

if (!isset($_SESSION['userid'])) {
    header("Location: ../");
    exit();
}

$userid = $_SESSION['userid'];

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
$currency = $env_data['CURRENCY'] ?? '';

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

$sql = "SELECT is_boss FROM workers WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userid);
$stmt->execute();
$stmt->store_result();

$message = "";

// Kategória hozzáadása
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_category"])) {
    $category_name = $_POST["category_name"];

    $check_query = "SELECT * FROM categories WHERE name = '$category_name'";
    $check_result = $conn->query($check_query);

    if ($check_result->num_rows > 0) {
        $message = "A kategória már létezik!";
        header("Refresh:1");
    } else {
        $insert_query = "INSERT INTO categories (name) VALUES ('$category_name')";
        if ($conn->query($insert_query) === TRUE) {
            $message = "Kategória hozzáadva!";
            header("Refresh:1");
        } else {
            $message = "Hiba történt: " . $conn->error;
            header("Refresh:2");
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_category"])) {
    $category_id = $_POST["category_id"];

    $check_product_query = "SELECT * FROM products WHERE category_id = $category_id";
    $check_product_result = $conn->query($check_product_query);

    if ($check_product_result->num_rows > 0) {
        $message = "Nem lehet törölni, mert a kategóriához van termék hozzárendelve!";
        header("Refresh:2");
    } else {
        $delete_query = "DELETE FROM categories WHERE id = $category_id";
        if ($conn->query($delete_query) === TRUE) {
            $message = "Kategória törölve!";
            header("Refresh:1");
        } else {
            $message = "Hiba történt: " . $conn->error;
            header("Refresh:2");
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_product"])) {
    $product_name = $_POST["product_name"];
    $category_id = $_POST["category_id"];
    $price = $_POST["price"];
    $quantity = $_POST["quantity"];

    $insert_product_query = "INSERT INTO products (name, category_id, price, quantity) VALUES ('$product_name', $category_id, $price, $quantity)";

    if ($conn->query($insert_product_query) === TRUE) {
        $message = "Termék hozzáadva a kategóriához!";
        header("Refresh:1");
    } else {
        $message = "Hiba történt: " . $conn->error;
        header("Refresh:2");
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_product"])) {
    $product_id = $_POST["product_id"];

    $delete_product_query = "DELETE FROM products WHERE id = $product_id";

    if ($conn->query($delete_product_query) === TRUE) {
        $message = "Termék törölve!";
        header("Refresh:1");
    } else {
        $message = "Hiba történt: " . $conn->error;
        header("Refresh:2");
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_quantity"])) {
    $product_id = $_POST["product_id"];
    $quantity_change = $_POST["quantity_change"];

    $update_quantity_query = "UPDATE products SET quantity = $quantity_change WHERE id = $product_id";

    if ($conn->query($update_quantity_query) === TRUE) {
        $message = "Mennyiség módosítva!";
        header("Refresh:1");
    } else {
        $message = "Hiba történt: " . $conn->error;
        header("Refresh:2");
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_price"])) {
    $product_id = $_POST["product_id"];
    $price_change = $_POST["price_change"];

    $update_price_query = "UPDATE products SET price = $price_change WHERE id = $product_id";

    if ($conn->query($update_price_query) === TRUE) {
        $message = $translations["success-package-price"];
        $action = $translations['success-update-package-price'] . " {$product_id} -> {$price_change} $currency;";
        $actioncolor = 'success';
        $sql = "INSERT INTO logs (userid, action, actioncolor, time) 
        VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $userid, $action, $actioncolor);
        $stmt->execute();
        header("Refresh:1");
    } else {
        $message = "Hiba történt: " . $conn->error;
        header("Refresh:2");
    }
}

$category_query = "SELECT * FROM categories";
$category_result = $conn->query($category_query);

$total_products = 0;
$allprice = 0;
$outoff = 0;

$product_query = "SELECT * FROM products";
$product_result = $conn->query($product_query);

if ($product_result->num_rows > 0) {
    while ($row = $product_result->fetch_assoc()) {
        $quantity = $row["quantity"];
        $total_products += $quantity;

        $product_price = $row["price"];
        $allprice += $product_price * $quantity;

        if ($quantity < 5) {
            $outoff++;
        }
    }
}

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

$username = 'mayerbalintdev';
$repo = 'GYM-One';
$current_version = $version;

$api_url = "https://api.github.com/repos/{$username}/{$repo}/releases/latest";
$options = [
    'http' => [
        'header' => [
            'User-Agent: PHP'
        ]
    ]
];
$context = stream_context_create($options);
$response = file_get_contents($api_url, false, $context);
$release = json_decode($response);

$is_new_version_available = false;

if ($release && isset($release->tag_name)) {
    $latest_version = $release->tag_name;

    if (version_compare($latest_version, $current_version) > 0) {
        $is_new_version_available = true;
    }
}


$category_sql = "SELECT id, name FROM categories";
$category_result = $conn->query($category_sql);
?>


<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <title><?php echo $translations["dashboard"]; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <link rel="shortcut icon" href="https://gymoneglobal.com/assets/img/logo.png" type="image/x-icon">
</head>
<!-- ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<body>
    <nav class="navbar navbar-inverse visible-xs">
        <div class="container-fluid">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#myNavbar">
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="#"><img src="../../assets/img/logo.png" width="105px" alt="Logo"></a>
            </div>
            <div class="collapse navbar-collapse" id="myNavbar">
                <ul class="nav navbar-nav">
                    <li class="active"><a href="#"><?php echo $translations["mainpage"]; ?></a></li>
                    <li><a href="#">Age</a></li>
                    <li><a href="#">Gender</a></li>
                    <li><a href="#">Geo</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row content">
            <div class="col-sm-2 sidenav hidden-xs text-center">
                <h2><img src="../../../assets/img/logo.png" width="105px" alt="Logo"></h2>
                <p class="lead mb-4 fs-4"><?php echo $business_name ?> - <?php echo $version; ?></p>
                <ul class="nav nav-pills nav-stacked">
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../../">
                            <i class="bi bi-speedometer"></i> <?php echo $translations["mainpage"]; ?>
                        </a>
                    </li>
                    <?php
                    if ($stmt->num_rows > 0) {
                        $stmt->bind_result($is_boss);
                        $stmt->fetch();

                        if ($is_boss == 1) {
                            ?>
                            <li class="sidebar-header">
                                <?php echo $translations["settings"]; ?>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link" href="../workers">
                                    <i class="bi bi-people"></i>
                                    <span><?php echo $translations["workers"]; ?></span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link active" href="#">
                                    <i class="bi bi-box-seam"></i>
                                    <span><?php echo $translations["packagepage"]; ?></span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link" href="../hours">
                                    <i class="bi bi-clock"></i>
                                    <span><?php echo $translations["openhourspage"]; ?></span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link" href="../smtp">
                                    <i class="bi bi-envelope-at"></i>
                                    <span><?php echo $translations["mailpage"]; ?></span>
                                </a>
                            </li>
                            <?php
                        }
                    }
                    ?>
                    <li class="sidebar-header">
                        Bolt
                    </li>
                    <li><a href="#section3">Gender</a></li>
                    <li><a href="#section3">Geo</a></li>
                    <li class="sidebar-header"><?php echo $translations["other-header"]; ?></li>
                    <li class="sidebar-item">
                        <a class="sidebar-ling" href="../../log">
                            <i class="bi bi-clock-history"></i>
                            <span><?php echo $translations["logpage"]; ?></span>
                        </a>
                    </li>
                </ul><br>
            </div>
            <br>
            <div class="col-sm-10">
                <div class="d-none topnav d-sm-inline-block">
                    <a href="https://gymoneglobal.com/discord" class="btn btn-primary mx-1" target="_blank"
                        rel="noopener noreferrer">
                        <i class="bi bi-question-circle"></i>
                        <?php echo $translations["support"]; ?>
                    </a>

                    <a href="https://gymoneglobal.com/docs" class="btn btn-danger" target="_blank"
                        rel="noopener noreferrer">
                        <i class="bi bi-journals"></i>
                        <?php echo $translations["docs"]; ?>
                    </a>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#logoutModal">
                        <?php echo $translations["logout"]; ?>
                    </button>
                </div>
                <div class="row">
                    <div class="col-sm-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-0 fw-semibold"><?php echo $translations["categorymanage"]; ?>
                                </h5>
                                <form method="post" class="mb-4" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
                                    <div class="input-group">
                                        <input type="text" class="form-control"
                                            placeholder="<?php echo $translations["newcategoryname"]; ?>"
                                            name="category_name">
                                        <span class="input-group-btn">
                                            <button type="submit" class="btn btn-primary"
                                                name="add_category"><?php echo $translations["addcategory"]; ?></button>
                                        </span>
                                    </div>
                                </form>
                                <form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
                                    <div class="input-group">
                                        <select class="form-control" name="category_id">
                                            <?php
                                            if ($category_result->num_rows > 0) {
                                                while ($row = $category_result->fetch_assoc()) {
                                                    echo "<option value='" . $row["id"] . "'>" . $row["name"] . "</option>";
                                                }
                                                $category_result->data_seek(0);
                                            }
                                            ?>
                                        </select>
                                        <span class="input-group-btn">
                                            <button type="submit" class="btn btn-danger"
                                                name="delete_category"><?php echo $translations["deletecategory"]; ?></button>
                                        </span>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-0 fw-semibold"><?php echo $translations["allpackageprice"]; ?>
                                </h5>
                                <h2><strong><?php echo $allprice; ?></strong> <?php echo $currency; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-0 fw-semibold"><?php echo $translations["allpackagepiece"]; ?>
                                </h5>
                                <h2><strong><?php echo $total_products; ?></strong>
                                    <?php echo $translations["piece"]; ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-0 fw-semibold"><?php echo $translations["latelyautoff"]; ?></h5>
                                <h2><strong><?php echo $outoff; ?></strong> <?php echo $translations["product"]; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-0 fw-semibold">
                                    <?php echo $translations["addproducttocategory"]; ?>
                                </h5>
                                <form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
                                    <div class="form-group">
                                        <label for="category_id"><?php echo $translations["categoryselect"]; ?></label>
                                        <select class="form-control" id="category_id" name="category_id">
                                            <?php
                                            if ($category_result->num_rows > 0) {
                                                while ($row = $category_result->fetch_assoc()) {
                                                    echo "<option value='" . $row["id"] . "'>" . $row["name"] . "</option>";
                                                }
                                                $category_result->data_seek(0);
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="product_name"><?php echo $translations["product-name"]; ?></label>
                                        <input type="text" class="form-control" id="product_name" name="product_name">
                                    </div>
                                    <div class="form-group">
                                        <label for="price"><?php echo $translations["price"]; ?>:</label>
                                        <input type="text" class="form-control" id="price" name="price">
                                    </div>
                                    <div class="form-group">
                                        <label for="quantity"><?php echo $translations["amount"]; ?>:</label>
                                        <input type="text" class="form-control" id="quantity" name="quantity">
                                    </div>
                                    <button type="submit" class="btn btn-primary"
                                        name="add_product"><?php echo $translations["addpackage"]; ?></button>
                                </form>

                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-0 fw-semibold">
                                    <?php echo $translations["packagedelete"]; ?>
                                </h5>
                                <form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
                                    <div class="form-group">
                                        <label for="product_id"><?php echo $translations["packageselect"]; ?>:</label>
                                        <select class="form-control" id="product_id" name="product_id">
                                            <?php
                                            $product_query = "SELECT * FROM products";
                                            $product_result = $conn->query($product_query);

                                            if ($product_result->num_rows > 0) {
                                                while ($row = $product_result->fetch_assoc()) {
                                                    echo "<option value='" . $row["id"] . "'>" . $row["name"] . "</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-danger"
                                        name="delete_product"><?php echo $translations["packagedelete"]; ?></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="card-title mb-0 fw-semibold">
                                    <?php echo $translations["updatepackage"]; ?>
                                </div>
                                <form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
                                    <div class="form-group">
                                        <label
                                            for="product_id_update"><?php echo $translations["packageselect"]; ?>:</label>
                                        <select class="form-control" id="product_id_update" name="product_id">
                                            <?php
                                            $product_result->data_seek(0);

                                            if ($product_result->num_rows > 0) {
                                                while ($row = $product_result->fetch_assoc()) {
                                                    echo "<option value='" . $row["id"] . "'>" . $row["name"] . "</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="price_change"><?php echo $translations["newprice"]; ?>:</label>
                                        <input type="text" class="form-control" id="price_change" name="price_change">
                                    </div>
                                    <div class="form-group">
                                        <label for="quantity_change"><?php echo $translations["newpiece"]; ?></label>
                                        <input type="text" class="form-control" id="quantity_change"
                                            name="quantity_change">
                                    </div>
                                    <button type="submit" class="btn btn-primary"
                                        name="update_price"><?php echo $translations["pricechange"]; ?></button>
                                    <button type="submit" class="btn btn-primary"
                                        name="update_quantity"><?php echo $translations["piecechange"]; ?></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- <div class="row">
                    <div class="col-sm-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="card-title mb-0 fw-semibold">Áttekintés</div>
                                <div class="tree">
                                    <ul>
                                        <?php
                                        foreach ($tree as $category_id => $category_data) {
                                            echo "<li><i class='bi bi-tag'></i></i> " . $category_data['name'];
                                            if (!empty($category_data['products'])) {
                                                echo "<ul>";
                                                foreach ($category_data['products'] as $product) {
                                                    echo "<li><i class='bi bi-box-fill'></i></i> " . $product['name'] . " - Ár: " . $product['price'] . " Ft, Mennyiség: " . $product['quantity'] . "</li>";
                                                }
                                                echo "</ul>";
                                            }
                                            echo "</li>";
                                        }
                                        ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->
                <div class="row">
                    <div class="col-sm-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="card-title mb-0 fw-semibold"><?php echo $translations["overview"]; ?></div>
                                <div class="tree">
                                    <ul>
                                        <?php
                                        $count = 0;
                                        foreach ($tree as $category_id => $category_data) {
                                            echo "<li><i class='bi bi-tag'></i> " . $category_data['name'];
                                            if (!empty($category_data['products'])) {
                                                echo "<ul>";
                                                foreach ($category_data['products'] as $product) {
                                                    echo "<li><i class='bi bi-box-fill'></i> " . $product['name'] . " - Ár: " . $product['price'] . " Ft, Mennyiség: " . $product['quantity'] . "</li>";
                                                    $count++;
                                                    if ($count % 7 == 0) {
                                                        echo "</ul></div></div></div></div><div class='col-sm-4'><div class='card'><div class='card-body'><div class='card-title mb-0 fw-semibold'>$overview</div><div class='tree'><ul>";
                                                    }
                                                }
                                                echo "</ul>";
                                            }
                                            echo "</li>";
                                        }
                                        ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- EXIT MODAL -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <p class="lead"><?php echo $translations["exit-modal"]; ?></p>
                </div>
                <div class="modal-footer">
                    <a type="button" class="btn btn-secondary"
                        data-dismiss="modal"><?php echo $translations["not-yet"]; ?></a>
                    <a href="../logout.php" type="button"
                        class="btn btn-danger"><?php echo $translations["confirm"]; ?></a>
                </div>
            </div>
        </div>
    </div>
    <?php
    $conn->close();
    ?>
    <!-- SCRIPTS! -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>

</html>