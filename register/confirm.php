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

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';

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
?>


<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $business_name; ?> - <?php echo $translations["register"]; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/default.css">
    <link rel="shortcut icon" href="../assets/img/brand/favicon.png" type="image/x-icon">
    <meta name="title" content="<?php echo $business_name; ?> - <?php echo $translations["register"]; ?>">
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
    <div class="container-fluid">
        <div class="row mt-5 justify-content-center">
            <div class="col-10 col-md-6 text-center">
                <img src="../assets/img/brand/logo.png" width="200px" alt="<?php echo $business_name; ?>">
                <h2 class="mt-5" id="bussinessnameh2"><?php echo $business_name; ?> - <?php echo $translations["confirmemailpage"];?></h2>
                <?php
                if (isset($_GET['userid'])) {
                    $userid = $_GET['userid'];

                    $sql = "UPDATE users SET confirmed = 'Yes' WHERE userid = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $userid);

                    if ($stmt->execute()) {
                        echo '<div class="alert alert-success mt-5" role="alert">'. $translations["regconfirm"] .'</div>';
                    } else {
                        echo '<div class="alert alert-danger mt-5" role="alert">' . $translations["errorconfirm"] . $conn->error . '</div>';
                    }

                    $stmt->close();
                } else {
                    echo '<div class="alert alert-warning mt-5" role="alert">' . $translations["errornoparameter"]. '</div>';
                }

                $conn->close();
                ?>
            </div>
        </div>
    </div>
    <div class="footer">
        <div class="container">
            <div class="row gy-4">
                <div class="col-md-4 mb-1">

                    <p><?php echo $country; ?>, <?php echo $city; ?></p>
                    <p><?php echo $street; ?> <?php echo $hause_no; ?></p>
                </div>
                <div class="col-md-3 offset-md-1">
                    <h2 class="text-light mb-4"></h2>
                </div>

                <div class="col-md-2 offset-md-1">
                    <h5 class="text-light mb-4"></h5>

                </div>
            </div>

            <div class="border-top border-secondary pt-3 mt-3">
                <p class="small text-center mb-0">
                    Copyright © <?php echo $copyright_year; ?> <?php echo $business_name; ?> - <?php echo $translations["copyright"]; ?>
                    &nbsp;<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-heart-fill" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M8 1.314C12.438-3.248 23.534 4.735 8 15-7.534 4.736 3.562-3.248 8 1.314">
                        </path>
                    </svg>
                    <a href="https://www.gymoneglobal.com/?lang=<?php echo $lang_code; ?>">GYM One</a>
                </p>
            </div>
        </div>
    </div>
</body>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>

</html>