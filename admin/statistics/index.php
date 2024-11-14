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
$currency = $env_data["CURRENCY"] ?? '';


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

// Query to get gender count
$sql = "SELECT gender, COUNT(*) as count FROM users GROUP BY gender";
$result = $conn->query($sql);

$maleCount = 0;
$femaleCount = 0;

if ($result->num_rows > 0) {
    // Output data of each row
    while ($row = $result->fetch_assoc()) {
        if ($row["gender"] == "Male") {
            $maleCount = $row["count"];
        } elseif ($row["gender"] == "Female") {
            $femaleCount = $row["count"];
        }
    }
}

$sql = "
    SELECT 
        `date`,
        COALESCE(SUM(bank_card), 0) AS bank_card,
        COALESCE(SUM(cash), 0) AS cash
    FROM 
        `revenu_stats`
    WHERE 
        `date` >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY 
        `date`
    ORDER BY 
        `date` ASC;
";
$result = $conn->query($sql);

$dates = [];
$bankCardData = [];
$cashData = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[$date] = ['bank_card' => 0, 'cash' => 0];
}

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $dates[$row['date']]['bank_card'] = (float)$row['bank_card'];
        $dates[$row['date']]['cash'] = (float)$row['cash'];
    }
}

foreach ($dates as $date => $values) {
    $formattedDates[] = $date;
    $bankCardData[] = $values['bank_card'];
    $cashData[] = $values['cash'];
}

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
            <div class="col-sm-2 sidenav hidden-xs text-center ">
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
                    <?php
                    if ($is_boss == 1) {
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
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../boss/tickets">
                                <i class="bi bi-ticket"></i>
                                <span><?php echo $translations["ticketspage"]; ?></span>
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
                            <span><?php echo $translations["updatepage"]; ?></span>
                            <?php if ($is_new_version_available) : ?>
                                <span class="sidebar-badge badge">
                                    <i class="bi bi-exclamation-circle"></i>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li><a href="#section3">Geo</a></li>
                    <li class="sidebar-header"><?php echo $translations["other-header"]; ?></li>
                    <?php
                    if ($is_boss == 1) {
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
                    <div class="col-sm-6">
                        <div class="card">
                            <div class="card-body">
                                <div class="text-center" id="userschart"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="text-center" id="malefamalechart"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col text-center">
                        <h2 class="lead"><?php echo $translations["moneystats"];?></h2>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="card">
                            <div class="card-body">
                                <div class="text-center" id="moneyincomechart"></div>
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
                </div>
                <div class="modal-footer">
                    <a type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $translations["not-yet"]; ?></a>
                    <a href="../logout.php" type="button" class="btn btn-danger"><?php echo $translations["confirm"]; ?></a>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPTS! -->
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

            var chart = new ApexCharts(document.querySelector("#userschart"), options);
            chart.render();
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var options = {
                series: [<?php echo $maleCount; ?>, <?php echo $femaleCount; ?>],
                chart: {
                    type: 'donut',
                    width: '100%',
                    height: 'auto',
                    toolbar: {
                        show: false
                    }
                },
                colors: ['#1E90FF', '#FF69B4'],
                labels: ['<?php echo $translations["boy"]; ?>', '<?php echo $translations["girl"]; ?>'],
                dataLabels: {
                    enabled: true,
                    formatter: function(val, opts) {
                        return val.toFixed(2) + '%';
                    }
                },
                tooltip: {
                    y: {
                        formatter: function(val, opts) {
                            var total = opts.globals.series.reduce((a, b) => a + b, 0);
                            var percent = (val / total) * 100;
                            return percent.toFixed(2) + '%';
                        }
                    }
                },
                legend: {
                    show: true,
                    position: 'bottom'
                },
                responsive: [{
                    breakpoint: 480,
                    options: {
                        chart: {
                            width: '100%'
                        },
                        legend: {
                            position: 'bottom'
                        }
                    }
                }],
            };

            var chart = new ApexCharts(document.querySelector("#malefamalechart"), options);
            chart.render();
        });
    </script>
    <script>
        var options = {
            chart: {
                type: 'area',
                fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif',

                toolbar: {
                    show: false // Eltávolítja az eszköztárat (nincs nyomtatás/exportálás)
                },
                zoom: {
                    enabled: false // Letiltja a zoomolást
                }
            },
            colors: ['#59F8E4', '#FB7B18'],

            series: [{
                name: '<?php echo $translations["card"]; ?>',
                data: <?php echo json_encode($bankCardData); ?>
            }, {
                name: '<?php echo $translations["cash"]; ?>',
                data: <?php echo json_encode($cashData); ?>
            }],
            xaxis: {
                categories: <?php echo json_encode($formattedDates); ?>
            },
            stroke: {
                curve: 'smooth'
            },
            yaxis: {
                title: {
                    text: '<?php echo $translations["incomemoney"]; ?> (<?php echo $currency; ?>)'
                }
            },
            legend: {
                position: 'bottom'
            }
        };

        var chart = new ApexCharts(document.querySelector("#moneyincomechart"), options);
        chart.render();
    </script>
    <script src="../../assets/js/date-time.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
</body>

</html>