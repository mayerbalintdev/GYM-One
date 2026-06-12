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

// Összes edzés száma (30 nap) – kis extra a kártyához
$total_sessions = 0;
foreach ($chart_durations as $d) {
    if ($d > 0) {
        $total_sessions++;
    }
}

$sql = "SELECT firstname, lastname FROM users WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userid);
$stmt->execute();
$stmt->bind_result($firstname, $lastname,);
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
    <style>
        /* ====== Modern dashboard tartalom (scoped: .dsh) ====== */
        .dsh {
            --d-accent: #0950dc;
            --d-ink: #0f172a;
            --d-muted: #64748b;
            --d-line: rgba(15, 23, 42, .08);
        }

        /* Üdvözlő fejléc */
        .dsh-welcome {
            display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
            background: linear-gradient(135deg, #0950dc, #2f73f0);
            color: #fff; border-radius: 20px; padding: 22px 26px; margin-bottom: 22px;
            box-shadow: 0 16px 40px rgba(9, 80, 220, .28);
        }
        .dsh-welcome-hi { font-size: 13px; text-transform: uppercase; letter-spacing: .08em; opacity: .85; }
        .dsh-welcome-name { font-size: 24px; font-weight: 800; margin-top: 2px; }
        .dsh-logout {
            display: inline-flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, .16);
            color: #fff; border: 1px solid rgba(255, 255, 255, .35); border-radius: 12px;
            padding: 9px 18px; font-weight: 700; cursor: pointer; transition: .15s;
        }
        .dsh-logout:hover { background: rgba(255, 255, 255, .26); color: #fff; }

        /* Statisztika kártyák */
        .dsh-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 16px; }
        @media (max-width: 991px) { .dsh-stats { grid-template-columns: 1fr; } }

        .dsh-stat {
            display: flex; align-items: center; gap: 14px;
            background: #fff; border: 1px solid var(--d-line); border-radius: 18px; padding: 18px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .06); transition: transform .15s, box-shadow .15s;
        }
        .dsh-stat:hover { transform: translateY(-3px); box-shadow: 0 16px 38px rgba(9, 80, 220, .12); }
        .dsh-stat-icon {
            width: 52px; height: 52px; flex: 0 0 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center; font-size: 24px;
        }
        .dsh-ic-blue { background: #e6efff; color: #0950dc; }
        .dsh-ic-green { background: #dcfce7; color: #16a34a; }
        .dsh-ic-amber { background: #fef3c7; color: #d97706; }
        .dsh-ic-violet { background: #ede9fe; color: #7c3aed; }
        .dsh-stat-label { font-size: 13px; color: var(--d-muted); font-weight: 600; }
        .dsh-stat-value { font-size: 22px; font-weight: 800; color: var(--d-ink); line-height: 1.15; margin-top: 2px; word-break: break-word; }
        .dsh-stat-value .dsh-unit { font-size: 14px; font-weight: 700; color: var(--d-muted); margin-left: 3px; }

        /* Kártya (grafikon) */
        .dsh-card {
            background: #fff; border: 1px solid var(--d-line); border-radius: 18px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .06); overflow: hidden; margin-bottom: 16px;
        }
        .dsh-card-head { display: flex; align-items: center; gap: 10px; padding: 16px 18px; border-bottom: 1px solid var(--d-line); }
        .dsh-card-head i { color: var(--d-accent); font-size: 18px; }
        .dsh-card-head h4 { margin: 0; font-size: 16px; font-weight: 800; color: var(--d-ink); }
        .dsh-card-head .dsh-chip { margin-left: auto; font-size: 12px; font-weight: 700; color: var(--d-accent); background: #eef4ff; padding: 4px 12px; border-radius: 999px; }
        .dsh-card-body { padding: 14px 12px 6px; }
    </style>
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
                    <li class="active"><a href=""><i class="bi bi-graph-up"></i>
                            <?php echo $translations["statspage"]; ?></a></li>
                    <li><a href="../profile/"><i class="bi bi-person-badge"></i>
                            <?php echo $translations["profilepage"]; ?></a></li>
                    <li><a href="../invoices/"><i class="bi bi-receipt"></i>
                            <?php echo $translations["invoicepage"]; ?></a></li>
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
                <div class="dsh">
                    <!-- Üdvözlő fejléc -->
                    <div class="dsh-welcome">
                        <div>
                            <div class="dsh-welcome-hi"><?php echo $translations["welcome"]; ?></div>
                            <div class="dsh-welcome-name"><?php echo htmlspecialchars($lastname . ' ' . $firstname); ?></div>
                        </div>
                        <button type="button" class="dsh-logout" data-toggle="modal" data-target="#logoutModal">
                            <i class="bi bi-box-arrow-right"></i> <?php echo $translations["logout"]; ?>
                        </button>
                    </div>

                    <!-- Statisztika kártyák -->
                    <div class="dsh-stats">
                        <div class="dsh-stat">
                            <div class="dsh-stat-icon dsh-ic-blue"><i class="bi bi-stopwatch"></i></div>
                            <div>
                                <div class="dsh-stat-label"><?php echo $translations["latestsessiontime"]; ?></div>
                                <div class="dsh-stat-value"><?php echo $latest_session_time; ?><span class="dsh-unit"><?php echo $translations["minutes"]; ?></span></div>
                            </div>
                        </div>
                        <div class="dsh-stat">
                            <div class="dsh-stat-icon dsh-ic-violet"><i class="bi bi-hourglass-split"></i></div>
                            <div>
                                <div class="dsh-stat-label"><?php echo $translations["averagetraintime"]; ?></div>
                                <div class="dsh-stat-value"><?php echo $avg_duration; ?><span class="dsh-unit"><?php echo $translations["minutes"]; ?></span></div>
                            </div>
                        </div>
                        <div class="dsh-stat">
                            <div class="dsh-stat-icon dsh-ic-amber"><i class="bi bi-calendar-check-fill"></i></div>
                            <div>
                                <div class="dsh-stat-label"><?php echo $translations["latesttraining"]; ?></div>
                                <div class="dsh-stat-value"><?php echo htmlspecialchars($latest_training); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- 30 napos grafikon -->
                    <div class="dsh-card">
                        <div class="dsh-card-head">
                            <i class="bi bi-graph-up-arrow"></i>
                            <h4><?php echo $translations["thirtydaychart"]; ?></h4>
                            <span class="dsh-chip"><i class="bi bi-activity"></i> <?php echo (int) $total_sessions; ?> / 30</span>
                        </div>
                        <div class="dsh-card-body">
                            <div id="chart"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- EXIT MODAL -->
        <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" style="margin-top: 100px;">
                <div class="modal-content" style="border: none; box-shadow: 0 0 40px rgba(0,0,0,.2);">
                    <div class="modal-body text-center" style="padding: 40px;">

                        <div style="margin-bottom: 25px;">
                            <div style="width: 80px; height: 80px; margin: 0 auto;
                                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
                                border-radius: 50%;
                                display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-box-arrow-right" style="color: #fff; font-size: 40px;"></i>
                            </div>
                        </div>

                        <h4 style="font-weight: bold; margin-bottom: 15px;">
                            <p><?php echo $translations["exit-modal"]; ?></p>
                        </h4>

                        <div class="text-center">
                            <a type="button" class="btn btn-default" data-dismiss="modal"
                                style="padding: 8px 25px; margin-right: 10px;">
                                <i class="bi bi-x-circle" style="margin-right: 5px;"></i>
                                <?php echo $translations["not-yet"]; ?>
                            </a>

                            <a href="../logout.php" type="button" class="btn btn-danger" style="padding: 8px 25px;">
                                <i class="bi bi-check-circle" style="margin-right: 5px;"></i>
                                <?php echo $translations["confirm"]; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            var dates = <?php echo json_encode($chart_dates); ?>;
            var durations = <?php echo json_encode($chart_durations); ?>;
            var options = {
                chart: {
                    type: 'area',
                    height: 350,
                    fontFamily: 'Segoe UI, system-ui, sans-serif',
                    zoom: { enabled: false },
                    toolbar: { show: false }
                },
                stroke: {
                    curve: 'smooth',
                    width: 3
                },
                colors: ['#0950dc'],
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.35,
                        opacityTo: 0.02,
                        stops: [0, 90, 100]
                    }
                },
                dataLabels: { enabled: false },
                series: [{
                    name: '<?php echo $translations["chartminutestraintime"]; ?>',
                    data: durations
                }],
                xaxis: {
                    categories: dates,
                    labels: {
                        rotate: -45,
                        style: { colors: '#94a3b8', fontSize: '11px' }
                    },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    tooltip: { enabled: false }
                },
                yaxis: {
                    min: 0,
                    labels: { style: { colors: '#94a3b8' } }
                },
                grid: {
                    borderColor: '#eef2f7',
                    strokeDashArray: 4,
                    padding: { left: 8, right: 8 }
                },
                markers: { size: 0, hover: { size: 5 } },
                tooltip: { theme: 'light' }
            };

            var chart = new ApexCharts(document.querySelector("#chart"), options);
            chart.render();
        </script>
        <!-- SCRIPTS! -->
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>

</html>