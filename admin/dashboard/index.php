<?php
session_start();

if (!isset($_SESSION['adminuser'])) {
    header("Location: ../");
    exit();
}

$userid = $_SESSION['adminuser'];

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
$capacity = $env_data["CAPACITY"] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}

// Előre definiált hónapok tömbje
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

$current_month = (int) date('m');
$current_year = (int) date('Y');

$categories = array();
$dataRegistrations = array();

for ($i = 11; $i >= 0; $i--) {
    $timestamp = mktime(0, 0, 0, $current_month - $i, 1, $current_year);
    $year_month = date("Y-m", $timestamp);
    $categories[] = $months[date('m', $timestamp)] . ' ' . date('Y', $timestamp);
    $dataRegistrations[$year_month] = 0;
}

$sqlRegistrations = "SELECT DATE_FORMAT(registration_date, '%Y-%m') as reg_month, 
                            COUNT(*) as count 
                     FROM users 
                     WHERE registration_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                     GROUP BY reg_month
                     ORDER BY reg_month";
$resultRegistrations = $conn->query($sqlRegistrations);

if ($resultRegistrations->num_rows > 0) {
    while ($row = $resultRegistrations->fetch_assoc()) {
        $dataRegistrations[$row['reg_month']] = $row['count'];
    }
}


$sqlUserCount = "SELECT COUNT(*) as count FROM users";
$resultUserCount = $conn->query($sqlUserCount);

$userCount = 0;

if ($resultUserCount->num_rows > 0) {
    $row = $resultUserCount->fetch_assoc();
    $userCount = $row["count"];
}

$sql = "SELECT is_boss FROM workers WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userid);
$stmt->execute();
$stmt->store_result();

$is_boss = null;

if ($stmt->num_rows > 0) {
    $stmt->bind_result($is_boss);
    $stmt->fetch();
}
$stmt->close();

$file_path = 'https://api.gymoneglobal.com/latest/version.txt';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $file_path);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$latest_version = curl_exec($ch);
curl_close($ch);

$current_version = $version;

$is_new_version_available = version_compare($latest_version, $current_version) > 0;
// SUM DAILY USERS

$sql = "SELECT COALESCE(SUM(number_of_people), 0) AS total_people FROM temp_dailyworkout";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
}
// SUM DAILY USERS !!!!END!!!!

// TEMP USERS TABLE!!!

$sql = "SELECT name, userid, login_date FROM temp_loggeduser";
$result = $conn->query($sql);

$sql_count = "SELECT COUNT(*) AS total_count FROM temp_loggeduser";
$result_count = $conn->query($sql_count);

if ($result_count) {
    $row_count = $result_count->fetch_assoc();
    $total_count = $row_count['total_count'];

    if ($capacity > 0) {
        $capacityPercent = ($total_count / $capacity) * 100;
    } else {
        $capacityPercent = 0;
    }
}
$progresscolor = '';

if ($capacityPercent >= 0 && $capacityPercent < 70) {
    $progresscolor = 'success';
} elseif ($capacityPercent >= 70 && $capacityPercent < 90) {
    $progresscolor = 'warning';
} elseif ($capacityPercent >= 90) {
    $progresscolor = 'danger';
}

$sql = "SELECT lastname FROM workers WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userid);
$stmt->execute();
$stmt->bind_result($username);
$stmt->fetch();

$conn->close();

$ipInfoUrl = 'https://ipinfo.io/json';

$ipInfo = json_decode(file_get_contents($ipInfoUrl), true);
$countryCode = $ipInfo['country'];

$jsonFile = 'https://emergencynumberapi.com/api/data/all';

$jsonData = @file_get_contents($jsonFile);
if (!$jsonData) {
    exit;
}

$data = json_decode($jsonData, true);
if (!$data) {
    exit;
}

$ambulanceNumbers = $translations["unknown"];
$fireNumbers = $translations["unknown"];
$policeNumbers = $translations["unknown"];

foreach ($data as $item) {
    if (isset($item['Country']['ISOCode']) && $item['Country']['ISOCode'] == $countryCode) {
        $ambulanceNumbers = isset($item['Ambulance']['All']) ? implode(', ', $item['Ambulance']['All']) : "Ismeretlen";
        $fireNumbers = isset($item['Fire']['All']) ? implode(', ', $item['Fire']['All']) : "Ismeretlen";
        $policeNumbers = isset($item['Police']['All']) ? implode(', ', $item['Police']['All']) : "Ismeretlen";
        break;
    }
}

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
    <script src="https://unpkg.com/@zxing/library@latest"></script>

    <link rel="shortcut icon" href="https://gymoneglobal.com/assets/img/logo.png" type="image/x-icon">
</head>
<!-- ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<style>
    #video-container {
        position: relative;
        width: 100%;
        height: 300px;
    }

    #video {
        width: 100%;
        height: 100%;
    }

    #video.scanned {
        filter: brightness(0.5) sepia(100%);
    }

    #video.error {
        filter: brightness(0.5) contrast(1.5) sepia(1) hue-rotate(-50deg);
    }

    #checkmark,
    #error {
        display: none;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 4em;
    }

    #checkmark {
        color: green;
    }

    #error {
        color: red;
    }
</style>

<body>
    <nav class="navbar navbar-inverse visible-xs">
        <div class="container-fluid">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#myNavbar">
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="#"><img src="../../assets/img/logo.png" width="50px" alt="Logo"></a>
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
                    <li class="sidebar-item active">
                        <a class="sidebar-link" href="#">
                            <i class="bi bi-speedometer"></i> <?php echo $translations["mainpage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../users">
                            <i class="bi bi-people"></i> <?php echo $translations["users"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../statistics">
                            <i class="bi bi-bar-chart"></i> <?php echo $translations["statspage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../boss/sell">
                            <i class="bi bi-shop"></i> <?php echo $translations["sellpage"]; ?>
                        </a>
                    </li>
                    <?php
                    if ($is_boss === 1) {
                    ?>
                        <li class="sidebar-header">
                            <?php echo $translations["settings"]; ?>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../boss/mainsettings">
                                <i class="bi bi-gear"></i>
                                <span><?php echo $translations["businesspage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../boss/workers">
                                <i class="bi bi-people"></i>
                                <span><?php echo $translations["workers"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../boss/packages">
                                <i class="bi bi-box-seam"></i>
                                <span><?php echo $translations["packagepage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../boss/hours">
                                <i class="bi bi-clock"></i>
                                <span><?php echo $translations["openhourspage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../boss/smtp">
                                <i class="bi bi-envelope-at"></i>
                                <span><?php echo $translations["mailpage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../boss/chroom">
                                <i class="bi bi-duffle"></i>
                                <span><?php echo $translations["chroompage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../boss/rule">
                                <i class="bi bi-file-ruled"></i>
                                <span><?php echo $translations["rulepage"]; ?></span>
                            </a>
                        </li>
                    <?php
                    }
                    ?>
                    <li class="sidebar-header">
                        Bolt
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-ling" href="../shop/gateway">
                            <i class="bi bi-shield-lock"></i>
                            <span><?php echo $translations["gatewaypage"]; ?></span>
                        </a>
                        <a class="sidebar-ling" href="../shop/tickets">
                            <i class="bi bi-ticket"></i>
                            <span><?php echo $translations["ticketspage"]; ?></span>
                        </a>
                    </li>
                    <li class="sidebar-header">
                        <?php echo $translations["trainersclass"]; ?>
                    </li>
                    <li><a class="sidebar-link" href="../trainers/timetable">
                            <i class="bi bi-calendar-event"></i>
                            <span><?php echo $translations["timetable"]; ?></span>
                        </a></li>
                    <li><a class="sidebar-link" href="../trainers/personal">
                            <i class="bi bi-award"></i>
                            <span><?php echo $translations["trainers"]; ?></span>
                        </a></li>
                    <li class="sidebar-header"><?php echo $translations["other-header"]; ?></li>
                    <?php
                    if ($is_boss === 1) {
                    ?>
                        <li class="sidebar-item">
                            <a class="sidebar-ling" href="../updater">
                                <i class="bi bi-cloud-download"></i>
                                <span><?php echo $translations["updatepage"]; ?></span>
                                <?php if ($is_new_version_available) : ?>
                                    <span class="sidebar-badge badge">
                                        <i class="bi bi-exclamation-circle"></i>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php
                    }
                    ?>
                    <li class="sidebar-item">
                        <a class="sidebar-ling" href="../log">
                            <i class="bi bi-clock-history"></i>
                            <span><?php echo $translations["logpage"]; ?></span>
                        </a>
                    </li>
                </ul><br>
            </div>
            <br>
            <div class="col-sm-10">
                <div class="d-none topnav d-sm-inline-block">
                    <a href="https://gymoneglobal.com/discord" class="btn btn-primary mx-1" target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-question-circle"></i>
                        <?php echo $translations["support"]; ?>
                    </a>

                    <a href="https://gymoneglobal.com/docs" class="btn btn-danger" target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-journals"></i>
                        <?php echo $translations["docs"]; ?>
                    </a>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#logoutModal">
                        <?php echo $translations["logout"]; ?>
                    </button>
                    <h5 id="clock" style="display: inline-block; margin-bottom: 0;"></h5>
                </div>
                <?php
                if ($is_boss == 1 && $is_new_version_available) {
                ?>
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="alert alert-danger">
                                <?php echo $translations["newupdate-text"]; ?>
                            </div>
                        </div>
                    </div>
                <?php
                }
                ?>
                <div class="row">
                    <div class="col-sm-12">
                        <?php
                        if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
                            echo '<div id="notHttpsAlert" class="alert alert-warning shadow-sm" role="alert">';
                            echo '<i class="bi bi-exclamation-triangle"></i> ' . $translations['notusehttps'];
                            echo '</div>';
                        }
                        ?>
                        <?php
                        $ruleContent = file_get_contents('../boss/rule/rule.html');

                        if (empty($ruleContent)) {
                            echo '<div class="alert alert-danger">';
                            echo '<i class="bi bi-exclamation-triangle"></i> ' . $translations['gymrulenotset'];
                            echo '</div>';
                        }
                        ?>

                    </div>
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
                    <div class="col-sm-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-0 fw-semibold"><?php echo $translations["dailyusers"]; ?></h5>
                                <h1><strong><?php echo $row["total_people"]; ?></strong></h1>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-0 fw-semibold"><?php echo $translations["users"]; ?></h5>
                                <h1><strong><?php echo $userCount; ?></strong></h1>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-0 fw-semibold"><?php echo $translations["userlogginer"]; ?></h5>
                                <div class="text-center">
                                    <a data-toggle="modal" data-target="#Logginer_MODAL" class="btn btn-success">
                                        <h4>BELEPTETÉS</h4>
                                    </a>
                                </div>
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
                    <div class="col-sm-6">
                        <div class="card">
                            <div class="card-body">
                                <p><?php echo $translations["dayopendayclose"]; ?></p>
                                <div class="d-flex justify-content-between text-center">
                                    <a href="" class="btn btn-success"><?php echo $translations["dayopen"]; ?></a>
                                    <a href="" class="btn btn-danger"><?php echo $translations["dayclose"]; ?></a>
                                </div>
                            </div>
                        </div>
                        <div class="card bg-danger">
                            <div class="card-body">
                                <p><?php echo $translations["emernumtext"]; ?></p>
                                <div class="justify-content-between text-center">
                                    <h2><?php echo $translations["ambulance"]; ?> <b class="text-danger"><?php echo $ambulanceNumbers; ?></b></h2>
                                    <h2><?php echo $translations["fireresistor"]; ?> <b class="text-danger"><?php echo $fireNumbers; ?></b></h2>
                                    <h2><?php echo $translations["police"]; ?> <b class="text-danger"><?php echo $policeNumbers; ?></b></h2>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <table class="table table-dark table-bordered text-center">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th><?php echo $translations["fullname"]; ?></th>
                                            <th><?php echo $translations["logintime"]; ?></th>
                                            <th><?php echo $translations["userlogout"]; ?></th>
                                            <th><?php echo $translations["editbtn"]; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($result->num_rows > 0) {
                                            $counter = 1;
                                            while ($row = $result->fetch_assoc()) {
                                                $current_time = new DateTime();
                                                $login_time = new DateTime($row["login_date"]);
                                                $interval = $current_time->diff($login_time);

                                                $elapsed_time = $interval->format(' %h óra %i perc');

                                                echo "<tr>";
                                                echo "<td>" . $counter . "</td>";
                                                echo "<td>" . $row["name"] . "</td>";
                                                echo "<td>" . $elapsed_time . "</td>";
                                                // KILÉPTETÉST MEGCSINÁLNI!
                                                echo '<td><a class="btn btn-danger" href="logout.php?user=' . $row["userid"] . '">Kiléptetés ERROR!</a></td>';
                                                echo '<td><a class= "btn btn-secondary" href="../users/edit/?user=' . $row["userid"] . '">' . $translations["editbtn"] . '</a></td>';
                                                echo "</tr>";

                                                $counter++;
                                            }
                                        } else {
                                            echo "<tr><td colspan='5'>" . $translations["noonetraining"] . "</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-content-between text-center">
                    <div class="col-sm-2">
                        <div class="card">
                            <p>Az edzőterem telitettsége:</p>
                            <div class="card-body">
                                <div class="progress">
                                    <div class="progress-bar-<?php echo $progresscolor; ?>" role="progressbar" style="width: <?php echo number_format($capacityPercent, 2); ?>%;" aria-valuenow="<?php echo number_format($capacityPercent, 2); ?>" aria-valuemin="0" aria-valuemax="100"><?php echo number_format($capacityPercent, 0); ?>%</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- EXIT MODAL -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <p><?php echo $translations["exit-modal"]; ?></p>
                    <div class="footer text-center">
                        <a type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $translations["not-yet"]; ?></a>
                        <a href="../logout.php" type="button" class="btn btn-danger"><?php echo $translations["confirm"]; ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- WELCOME MODAL -->

    <div class="modal fade" id="welcomeModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="row text-center">
                        <div class="col">
                            <img src="../../assets/img/brand/logo.png" width="50%" class="img img-fluid" alt="Logo">
                            <h1 id="modalMessage"></h1>
                            <p class="lead"><?php echo $translations["haveagoodday"]; ?></p>
                        </div>
                    </div>
                    <div class="footer text-center">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $translations["next"]; ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- BELÉPTETŐ MODAL -->
    <div class="modal fade" id="Logginer_MODAL" tabindex="-1" role="dialog" aria-labelledby="LogginerModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="LogginerModalLabel"><?php echo $translations["userlogginer"]; ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row text-center">
                        <div class="col-12">
                            <div id="video-container">
                                <video id="video" width="500px" autoplay></video>
                                <div id="checkmark">✔</div>
                                <div id="error">✘</div>
                            </div>
                            <p id="result"><?php echo $translations["qrscann"]; ?></p>
                        </div>
                        <h1><?php echo $translations["or"]; ?></h1>
                        <form class="form-inline my-2 my-lg-0">
                            <input id="search" class="form-control mr-sm-2" type="search" placeholder="<?php echo $translations["name-search"]; ?> " aria-label="Search">
                        </form>
                        <div id="results" class="mt-4"></div>
                        <input hidden id="qrcodeContent">
                    </div>
                </div>
                <div class="modal-footer">
                    <a type="button" id="continueButton" class="btn btn-primary" style="display: none;"><?php echo $translations["next"]; ?></a>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $translations["close"]; ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="UserDetails_MODAL" tabindex="-1" role="dialog" aria-labelledby="userDetailsLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userDetailsLabel">Felhasználó Adatok</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Bezárás">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="userDetails"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Bezárás</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPTS! -->
    <script>
        $(document).ready(function() {
            $("#search").on("input", function() {
                var query = $(this).val();
                if (query.length > 2) {
                    $.ajax({
                        url: 'search.php',
                        method: 'POST',
                        data: {
                            search: query
                        },
                        success: function(data) {
                            $("#results").html(data);
                        }
                    });
                } else {
                    $("#results").html('');
                }
            });
        });

        const codeReader = new ZXing.BrowserQRCodeReader();
        const video = document.getElementById('video');
        const resultElement = document.getElementById('result');
        const checkmark = document.getElementById('checkmark');
        const error = document.getElementById('error');
        const qrCodeContent = document.getElementById('qrcodeContent');
        const continueButton = document.getElementById('continueButton');
        const userDetails = document.getElementById('userDetails');

        let scanCompleted = false;
        let scanning = false;

        let userData = {}; // Új változó a felhasználói adatok tárolására

        var translations = <?php echo json_encode($translations); ?>;

        function startScanning() {
            if (scanCompleted || scanning) return;

            scanning = true;
            codeReader.decodeFromVideoDevice(null, video, (result, error) => {
                if (result && !scanCompleted) {
                    scanCompleted = true;
                    const qrCodeText = result.text;
                    resultElement.textContent = `QR Code Result: ${qrCodeText}`;
                    qrCodeContent.value = qrCodeText;
                    continueButton.style.display = 'inline';

                    fetch('process.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `qrcode=${encodeURIComponent(qrCodeText)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                userData = {
                                    firstname: data.firstname,
                                    lastname: data.lastname,
                                    birthdate: data.birthdate
                                };
                                resultElement.innerHTML = `${translations.firstname}: ${data.firstname}<br>${translations.lastname}: ${data.lastname}`;
                                video.classList.add('scanned');
                                checkmark.style.display = 'block';
                            } else {
                                resultElement.textContent = `${translations['qr-error']}`;
                                video.classList.add('error');
                                error.style.display = 'block';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            resultElement.textContent = `${translations['qr-error']}`;
                            video.classList.add('error');
                            error.style.display = 'block';
                        });
                }
                if (error && !scanCompleted) {
                    console.error(error);
                }
            }).catch(error => console.error(error));
        }

        function stopScanning() {
            if (scanning) {
                codeReader.reset(); // Stop the scanner
                video.srcObject = null; // Stop the video stream
                scanning = false;
                scanCompleted = false;
                resultElement.textContent = '';
                checkmark.style.display = 'none';
                error.style.display = 'none';
                video.classList.remove('scanned', 'error'); // Remove the scanned and error classes
                continueButton.style.display = 'none'; // Hide the continue button
            }
        }

        $('#continueButton').on('click', function() {
            $('#Logginer_MODAL').modal('hide'); // Hide the current modal
            stopScanning(); // Reset the scanner

            // Frissítsd a userDetails modal tartalmát az userData változó alapján
            userDetails.innerHTML = `
            First Name: ${userData.firstname}<br>
            Last Name: ${userData.lastname}<br>
            Birthday: ${userData.birthdate}
        `;

            $('#UserDetails_MODAL').modal('show'); // Show the next modal
        });

        $('#Logginer_MODAL').on('shown.bs.modal', startScanning);
        $('#Logginer_MODAL').on('hidden.bs.modal', stopScanning);
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let seriesData = Object.values(<?php echo json_encode($dataRegistrations); ?>);

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
                    categories: <?php echo json_encode($categories); ?>,
                },
                yaxis: {
                    tickAmount: Math.max(...seriesData),
                    min: 0,
                    labels: {
                        formatter: function(value) {
                            return Math.floor(value);
                        }
                    }
                },
            };

            var chart = new ApexCharts(document.querySelector("#chart"), options);
            chart.render();
        });
    </script>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const hours = now.getHours();
            const minutes = now.getMinutes();
            let message = '';

            if ((hours === 0 && minutes >= 0) || (hours < 11) || (hours === 11 && minutes < 30)) {
                message = '<?php echo $translations["morninghello"]; ?>';
            } else if ((hours === 11 && minutes >= 30) || (hours < 17)) {
                message = '<?php echo $translations["dayhello"]; ?>';
            } else {
                message = '<?php echo $translations["nighthello"]; ?>';
            }
            const username = "<?php echo $username; ?>";
            const finalMessage = `${message} ${username}!`;

            const today = new Date().toISOString().split('T')[0];

            if (localStorage.getItem('modalShownDate') !== today) {
                document.getElementById('modalMessage').innerText = finalMessage;
                $('#welcomeModal').modal('show');
                localStorage.setItem('modalShownDate', today);
            }
        });
    </script>
    <script src="../../assets/js/date-time.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
</body>

</html>