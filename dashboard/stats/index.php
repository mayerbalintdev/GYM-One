<?php
session_start();

if (!isset($_SESSION['userid'])) {
    header("Location: ../../");
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

$translations = json_decode(file_get_contents($langFile), true);

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}

$sql_latest_session = "SELECT duration FROM workout_stats WHERE userid = $userid AND workout_date = CURDATE()";
$result_latest_session = $conn->query($sql_latest_session);
if (!$result_latest_session) {
    die("Hiba a legutóbbi edzés lekérdezésekor: " . $conn->error);
}
$latest_session_time = ($result_latest_session->num_rows > 0) ? $result_latest_session->fetch_assoc()['duration'] : 0;

$sql_avg_time = "SELECT AVG(duration) AS avg_duration FROM workout_stats WHERE userid = $userid";
$result_avg_time = $conn->query($sql_avg_time);
if (!$result_avg_time) {
    die("Hiba az átlagos edzésidő lekérdezésekor: " . $conn->error);
}
$avg_duration = ($result_avg_time->num_rows > 0) ? round($result_avg_time->fetch_assoc()['avg_duration']) : 0;

$sql_latest_training = "SELECT workout_date FROM workout_stats WHERE userid = $userid ORDER BY workout_date DESC LIMIT 1";
$result_latest_training = $conn->query($sql_latest_training);
if (!$result_latest_training) {
    die("Hiba a legutóbbi edzés dátumának lekérdezésekor: " . $conn->error);
}
$latest_training = ($result_latest_training->num_rows > 0) ? $result_latest_training->fetch_assoc()['workout_date'] : $translations["n/a"];


$dates = [];
$durations = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[$date] = 0;
}

$sql_workouts = "
    SELECT workout_date, duration 
    FROM workout_stats 
    WHERE userid = $userid AND workout_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
";
$result = $conn->query($sql_workouts);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $dates[$row['workout_date']] = $row['duration'];
    }
}

$chart_dates = array_keys($dates);
$chart_durations = array_values($dates);

$sql = "SELECT firstname, lastname FROM users WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userid);
$stmt->execute();
$stmt->bind_result($lastname, $firstname);
$stmt->fetch();
$stmt->close();

$conn->close();
?>


<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <title><?php echo $business_name; ?> <?php echo $translations["dashboard"]; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="shortcut icon" href="../../assets/img/brand/favicon.png" type="image/x-icon">
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
                <a class="navbar-brand" href=""><img src="../../assets/img/logo.png" width="70px" alt="Logo"></a>
            </div>
            <div class="collapse navbar-collapse" id="myNavbar">
                <ul class="nav navbar-nav">
                    <li><a href="../"><i class="bi bi-house"></i> <?php echo $translations["mainpage"]; ?></a></li>
                    <li class="active"><a href=""><i class="bi bi-graph-up"></i> <?php echo $translations["statspage"]; ?></a></li>
                    <li><a href="../profile/"><i class="bi bi-person-badge"></i> <?php echo $translations["profilepage"]; ?></a></li>
                    <li><a href="../invoices/"><i class="bi bi-receipt"></i> <?php echo $translations["invoicepage"]; ?></a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row content">
            <div class="col-sm-2 sidenav hidden-xs text-center">
                <h2><img src="../../assets/img/brand/logo.png" width="105px" alt="Logo"></h2>
                <p class="lead mb-4 fs-4"><?php echo $business_name ?></p>
                <ul class="nav nav-pills nav-stacked">
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../">
                            <i class="bi bi-house"></i> <?php echo $translations["mainpage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item active">
                        <a class="sidebar-link" href="">
                            <i class="bi bi-graph-up"></i> <?php echo $translations["statspage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../profile/">
                            <i class="bi bi-person-badge"></i> <?php echo $translations["profilepage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../invoices/">
                            <i class="bi bi-receipt"></i> <?php echo $translations["invoicepage"]; ?>
                        </a>
                    </li>
                </ul><br>
            </div>
            <br>
            <div class="col-sm-10">
                <div class="d-none topnav d-sm-inline-block">
                    <h4><?php echo $translations["welcome"]; ?> <?php echo $lastname; ?> <?php echo $firstname; ?></h4>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#logoutModal">
                        <?php echo $translations["logout"]; ?>
                    </button>
                </div>
                <div class="row">
                    <div class="col-sm-4">
                        <div class="card">
                            <div class="card-body text-start">
                                <div class="row">
                                    <div class="col-xs-10 text-start">
                                        <h4 class="card-title fw-semibold"><?php echo $translations["latestsessiontime"]; ?></h4>
                                    </div>
                                    <div class="col-auto">
                                        <div class="d-inline-block fs-1 lh-1 text-primary roundbg p-4 rounded-pill">
                                            <i class="bi bi-stopwatch"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="row text-center">
                                    <div class="col">
                                        <h2><b><?php echo $latest_session_time; ?></b> <?php echo $translations["minutes"]; ?></h2>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="card">
                            <div class="card-body text-start">
                                <div class="row">
                                    <div class="col-xs-10 text-start">
                                        <h4 class="card-title fw-semibold"><?php echo $translations["averagetraintime"]; ?></h4>
                                    </div>
                                    <div class="col-auto">
                                        <div class="d-inline-block fs-1 lh-1 text-primary roundbg p-4 rounded-pill">
                                            <i class="bi bi-hourglass-split"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="row text-center">
                                    <div class="col">
                                        <h2><b><?php echo $avg_duration; ?></b> <?php echo $translations["minutes"]; ?></h2>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="card">
                            <div class="card-body text-start">
                                <div class="row">
                                    <div class="col-xs-10 text-start">
                                        <h4 class="card-title fw-semibold"><?php echo $translations["latesttraining"]; ?></h4>
                                    </div>
                                    <div class="col-auto">
                                        <div class="d-inline-block fs-1 lh-1 text-primary roundbg p-4 rounded-pill">
                                            <i class="bi bi-calendar-check"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="row text-center">
                                    <div class="col">
                                        <h2><b><?php echo $latest_training; ?></b></h2>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-8">
                        <div class="card shadow">
                            <div class="card-body text-start">
                                <h4 class="card-title fw-semibold">
                                    <?php echo $translations["thirtydaychart"]; ?>
                                </h4>
                                <div id="chart"></div>
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
                        <p class="lead"><?php echo $translations["exit-modal"]; ?></p>
                    </div>
                    <div class="modal-footer">
                        <a type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $translations["not-yet"]; ?></a>
                        <a href="../logout.php" type="button" class="btn btn-danger"><?php echo $translations["confirm"]; ?></a>
                    </div>
                </div>
            </div>
        </div>
        <script>
            var dates = <?php echo json_encode($chart_dates); ?>;
            var durations = <?php echo json_encode($chart_durations); ?>;

            var options = {
                chart: {
                    type: 'line',
                    height: 350,
                    zoom: {
                        enabled: false
                    },
                    toolbar: {
                        show: false
                    }
                },
                stroke: {
                    curve: 'smooth'
                },
                colors: ['#59F8E4'],
                series: [{
                    name: '<?php echo $translations["chartminutestraintime"]; ?>',
                    data: durations
                }],
                xaxis: {
                    categories: dates,
                    labels: {
                        rotate: -45
                    }
                },
                yaxis: {
                    min: 0
                },
            };

            var chart = new ApexCharts(document.querySelector("#chart"), options);
            chart.render();
        </script>
        <!-- SCRIPTS! -->
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>

</html>