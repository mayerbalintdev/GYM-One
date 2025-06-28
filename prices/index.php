<?php function read_env_file($file_path)
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
$copyright_year = date("Y");

$env_data = read_env_file('../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';
$country = $env_data['COUNTRY'] ?? '';
$street = $env_data['STREET'] ?? '';
$city = $env_data['CITY'] ?? '';
$hause_no = $env_data['HOUSE_NUMBER'] ?? '';
$description = $env_data['DESCRIPTION'] ?? '';
$metakey = $env_data['META_KEY'] ?? '';
$gkey = $env_data['GOOGLE_KEY'] ?? '';
$mailadress = $env_data['MAIL_USERNAME'] ?? '';
$phoneno = $env_data['PHONE_NO'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$currency = $env_data['CURRENCY'] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}

$dayNames = [
    1 => $translations["Mon"],
    2 => $translations["Tue"],
    3 => $translations["Wed"],
    4 => $translations["Thu"],
    5 => $translations["Fri"],
    6 => $translations["Sat"],
    7 => $translations["Sun"]
];

$sql = "SELECT * FROM opening_hours ORDER BY day ASC";
$result = $conn->query($sql);

$days = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $days[] = $row;
    }
}


$sql = "SELECT * FROM tickets";
$result = $conn->query($sql);

?>




<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $business_name; ?> - <?php echo $translations["trainerspage"]; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- CUSTOM STYLE INSERT HERE! -->
    <link rel="stylesheet" href="../assets/css/default.css">
    <!-- CUSTOM STYLE INSERT HERE! -->
    <link rel="shortcut icon" href="../assets/img/brand/favicon.png" type="image/x-icon">
    <meta name="title" content="<?php echo $business_name; ?> - <?php echo $translations["trainerspage"]; ?>">
    <meta name="description" content="<?php echo $description; ?>">
    <meta name="keywords" content="<?php echo $metakey; ?>">
    <meta name="robots" content="index, follow">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="author" content="<?php echo $business_name; ?>">

</head>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $gkey; ?>"></script>
<script>
    window.dataLayer = window.dataLayer || [];

    function gtag() {
        dataLayer.push(arguments);
    }
    gtag('js', new Date());

    gtag('config', '<?php echo $gkey; ?>');
</script>

<body>
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-light">
            <img class="img" src="../assets/img/brand/logo.png" width="148px" alt="<?php echo $business_name; ?> Logo">
            <button class="navbar-toggler custom-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="../"><?php echo $translations["mainpage"]; ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../trainers"><?php echo $translations["trainerspage"]; ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><?php echo $translations["pricespage"]; ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../contact/"><?php echo $translations["contactpage"]; ?></a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a href="../login/" rel="noopener noreferrer" title="Login" class="nav-link ps-0 ps-lg-3 pe-3">
                            <i class="bi bi-person-circle"></i>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    </div>
    <div class="container-fluid">
        <div class="row">
            <div class="col bg-imageback">
            </div>
        </div>
        <div class="row mt-2 text-center justify-content-center">
            <div class="col-sm-5">
                <h1><?php echo $translations["pricelist"];?></h1>
            </div>
        </div>
        <?php
        if ($result->num_rows > 0) {
            echo "<div class='row mt-3'>";

            while ($row = $result->fetch_assoc()) {
                echo "<div class='col-sm-3 mb-4'>";
                echo "<div class='card shadow-sm border-light'>";
                echo "<div class='card-body'>";
                echo "<h5 class='card-title text-first'>" . htmlspecialchars($row["name"]) . "</h5>";
                echo "<p class='card-text'>" . $translations["tickettableexpiry"] . ": " . htmlspecialchars($row["expire_days"]) . "</p>";
                echo "<p class='card-text'><strong>" . $translations["price"] . ": " . htmlspecialchars($row["price"]) . " " . htmlspecialchars($currency) . "</strong></p>";
                $occasions = $row["occasions"] === NULL ? $translations["unlimited"] : htmlspecialchars($row["occasions"]);

                echo "<p class='card-text'>" . $translations["tickettableoccassion"] . ": " . $occasions . "</p>";
                echo "</div>";
                echo "</div>";
                echo "</div>";
            }

            echo "</div>";
        } else {
            echo "<div class='alert alert-warning' role='alert'>" . $translations["notickets"] . "</div>";
        }
        ?>


        <div class="footer">
            <div class="container">
                <div class="row gy-4">
                    <div class="mt-3"></div>
                    <div class="col-md-4 mb-1">
                        <h2 class="mb-4">
                            <img src="../assets/img/brand/logo.png" alt="<?php echo $business_name; ?> - Logo" height="105">
                        </h2>

                        <p><?php echo $city; ?></p>
                        <p><?php echo $street; ?> <?php echo $hause_no; ?></p>
                    </div>
                    <div class="col-md-3 offset-md-1">
                        <?php if (!empty($days)): ?>
                            <div class="list-group">
                                <?php foreach ($days as $day): ?>
                                    <div class="list-group-itemcustom d-flex justify-content-between align-items-center">
                                        <span><strong><?= htmlspecialchars($dayNames[$day['day']]) ?></strong></span>
                                        <span class="text-center justify-content-center">
                                            <?php if (is_null($day['open_time']) && is_null($day['close_time'])): ?>
                                                <span class="badge bg-danger"><?= $translations["closed"]; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <?= date('H:i', strtotime($day['open_time'])) ?> - <?= date('H:i', strtotime($day['close_time'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-2 offset-md-1">
                        <h5 class="text-light mb-4"></h5>

                    </div>
                </div>

                <div class="border-top border-secondary pt-3 mt-3">
                    <p class="small text-center mb-0">
                        Copyright © <?php echo $copyright_year; ?> <?php echo $business_name; ?> - <?php echo $translations["copyright"]; ?>
                        &nbsp;<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="red" class="bi bi-heart-fill" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M8 1.314C12.438-3.248 23.534 4.735 8 15-7.534 4.736 3.562-3.248 8 1.314">
                            </path>
                        </svg>
                        <a href="https://www.gymoneglobal.com/?lang=<?php echo $lang_code; ?>">GYM One</a>
                    </p>
                </div>
            </div>
        </div>
</body>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

</html>