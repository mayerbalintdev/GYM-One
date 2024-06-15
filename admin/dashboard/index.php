<?php
session_start();

// Ellenőrzés, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['userid'])) {
    header("Location: ../");
    exit();
}

$userid = $_SESSION['userid'];

// Adatbázis kapcsolódás beállítása
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

$env_data = read_env_file('../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

// Nyelvi fájl betöltése és dekódolása
$translations = json_decode(file_get_contents($langFile), true);

// Kapcsolat létrehozása
$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

// Kapcsolat ellenőrzése
if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}

// SQL lekérdezés összeállítása és végrehajtása az új regisztrációk számára
$sqlRegistrations = "SELECT DATE_FORMAT(registration_date, '%Y-%m') as reg_month, 
                            COUNT(*) as count 
                     FROM users 
                     WHERE registration_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                     GROUP BY reg_month
                     ORDER BY reg_month";
$resultRegistrations = $conn->query($sqlRegistrations);

$dataRegistrations = array();

if ($resultRegistrations->num_rows > 0) {
    // Adatok kinyerése
    while ($row = $resultRegistrations->fetch_assoc()) {
        $dataRegistrations[] = $row;
    }

    // Hónapok magyar nevei
    $months = [
        "01" => $translations["Jan"],
        "02" => $translations["Feb"],
        "03" => $translations["Mar"],
        "04" => $translations["Apr"],
        "05" => $translations["May"],
        "06" => $translations["Jun"],
        "07" => $translations["Jul"],
        "08" => $translations["Aug"],
        "09" => $translations["Sep"],
        "10" => $translations["Oct"],
        "11" => $translations["Nov"],
        "12" => $translations["Dec"]
    ];

    // Adatok előkészítése
    $categories = array();
    foreach ($dataRegistrations as $row) {
        $year_month = explode("-", $row['reg_month']);
        $year = $year_month[0];
        $month = $year_month[1];
        $categories[] = $months[$month] . ' ' . $year;
    }
}

// SQL lekérdezés összeállítása és végrehajtása az összes felhasználó számára
$sqlUserCount = "SELECT COUNT(*) as count FROM users";
$resultUserCount = $conn->query($sqlUserCount);

$userCount = 0;

if ($resultUserCount->num_rows > 0) {
    // Eredmény feldolgozása
    $row = $resultUserCount->fetch_assoc();
    $userCount = $row["count"];
}

// Kapcsolat bezárása
$conn->close();
?>


<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <title><?php echo $translations["dashboard"]; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
                <h2><img src="../../assets/img/logo.png" width="105px" alt="Logo"></h2>
                <p class="lead mb-4 fs-4"><?php echo $business_name ?> - <?php echo $version; ?></p>
                <ul class="nav nav-pills nav-stacked">
                    <li class="active"><a href="#"><?php echo $translations["mainpage"]; ?></a></li>
                    <li><a href="#section2">Age</a></li>
                    <li><a href="#section3">Gender</a></li>
                    <li><a href="#section3">Geo</a></li>
                    <li><a href="../global"><?php echo $translations["businessedit"]; ?></a></li>
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
                                <h5 class="card-title mb-0 fw-semibold"><?php echo $translations["users"]; ?></h5>
                                <h1><strong><?php echo $userCount; ?></strong></h1>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-0 fw-semibold">
                                    <?php echo $translations["new-users"]; ?>
                                </h5>
                                <div id="chart"></div>
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

    <!-- SCRIPTS! -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // PHP által generált JSON adatokat használunk
            let data = <?php echo json_encode($dataRegistrations); ?>;
            let categories = <?php echo json_encode($categories); ?>;
            let seriesData = data.map(item => parseInt(item.count));

            var options = {
                chart: {
                    type: 'area',
                    fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif',
                    toolbar: {
                        show: false
                    },
                    zoom: {
                        enabled: false
                    }
                },
                colors: ['#59F8E4'],
                series: [{
                    name: '<?php echo $translations["reg-number"]; ?>',
                    data: seriesData
                }],
                xaxis: {
                    categories: categories,
                },
                yaxis: {
                    tickAmount: Math.max(...seriesData),
                    min: 0,
                    labels: {
                        formatter: function (value) {
                            return Math.floor(value);
                        }
                    }
                },
            };

            var chart = new ApexCharts(document.querySelector("#chart"), options);
            chart.render();
        });
    </script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>

</html>