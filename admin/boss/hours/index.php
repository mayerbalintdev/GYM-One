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

$alerts_html = '';

$sql = "SELECT userid, Firstname, Lastname, username, is_boss FROM workers";
$result = $conn->query($sql);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $days = [$translations["Mon"], $translations["Tue"], $translations["Wed"], $translations["Thu"], $translations["Fri"], $translations["Sat"], $translations["Sun"]];
    foreach ($days as $key => $day) {
        $open_time = $_POST["open_time"][$key];
        $close_time = $_POST["close_time"][$key];

        if ($open_time == "closed" || $close_time == "closed") {
            $open_time = NULL;
            $close_time = NULL;
        }

        $update_sql = "UPDATE opening_hours SET open_time=?, close_time=? WHERE day_of_week=?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssi", $open_time, $close_time, $key);
        $update_stmt->execute();
    }
    $alerts_html .= "<div class='alert alert-success'>{$translations["time-update-success"]}</div>";
    $action = $translations['success-update-hours'];
    $actioncolor = 'success';
    $sql = "INSERT INTO logs (userid, action, actioncolor, time) 
            VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $userid, $action, $actioncolor);
    $stmt->execute();
    header("Refresh:2");
}
$days = [$translations["Mon"], $translations["Tue"], $translations["Wed"], $translations["Thu"], $translations["Fri"], $translations["Sat"], $translations["Sun"]];

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
                    <li><a href="#"><?php echo $_SESSION["userid"]; ?></a></li>
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
                        <a class="sidebar-link" href="../../dashboard">
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
                            <li class="sidebar-item active">
                                <a class="sidebar-link" href="#">
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
                    <div class="col-sm-12">
                        <?php echo $alerts_html; ?>
                        <div class="card shadow">
                            <div class="card-body">

                                <?php
                                if ($stmt->num_rows > 0) {
                                    $stmt->bind_result($is_boss);
                                    $stmt->fetch();

                                    if ($is_boss == 1) {
                                        ?>
                                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th><?php echo $translations["day"]; ?></th>
                                                        <th><?php echo $translations["opentime"]; ?></th>
                                                        <th><?php echo $translations["closetime"]; ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($days as $key => $day): ?>
                                                        <tr>
                                                            <td><?php echo $day; ?></td>
                                                            <td>
                                                                <select name="open_time[<?php echo $key; ?>]" class="form-control">
                                                                    <option value="closed" <?php if (!isset($opening_hours[$key]['open_time']))
                                                                        echo 'selected'; ?>><?php echo $translations["closed"]; ?></option>
                                                                    <?php for ($hour = 0; $hour < 24; $hour++): ?>
                                                                        <option value="<?php echo sprintf("%02d:00", $hour); ?>" <?php if (isset($opening_hours[$key]['open_time']) && $opening_hours[$key]['open_time'] == sprintf("%02d:00", $hour))
                                                                                echo 'selected'; ?>>
                                                                            <?php echo sprintf("%02d:00", $hour); ?>
                                                                        </option>
                                                                    <?php endfor; ?>
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <select name="close_time[<?php echo $key; ?>]" class="form-control">
                                                                    <option value="closed" <?php if (!isset($opening_hours[$key]['close_time']))
                                                                        echo 'selected'; ?>><?php echo $translations["closed"]; ?>
                                                                    </option>
                                                                    <?php for ($hour = 0; $hour < 24; $hour++): ?>
                                                                        <option value="<?php echo sprintf("%02d:00", $hour); ?>" <?php if (isset($opening_hours[$key]['close_time']) && $opening_hours[$key]['close_time'] == sprintf("%02d:00", $hour))
                                                                                echo 'selected'; ?>>
                                                                            <?php echo sprintf("%02d:00", $hour); ?>
                                                                        </option>
                                                                    <?php endfor; ?>
                                                                </select>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            <button type="submit"
                                                class="btn btn-primary"><?php echo $translations["save"]; ?></button>
                                        </form>
                                        <?php
                                    } else {
                                        echo $translations["dont-access"];
                                    }
                                } else {
                                    echo "Users do not exist!";
                                }
                                ?>

                            </div>
                        </div>
                    </div>
                </div>
                <footer class="footer">
                    <div class="container-fluid">
                        <p class="mb-0 py-2 text-center text-body-secondary">
                            Powered by <a href="https://azuriom.com" target="_blank"
                                rel="noopener noreferrer">Azuriom</a> ©
                            2019-2024. Panel designed by <a href="https://adminkit.io/" target="_blank"
                                rel="noopener noreferrer">AdminKit</a>. </p>
                    </div>
                </footer>
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
                    <a href="../../logout.php" type="button"
                        class="btn btn-danger"><?php echo $translations["confirm"]; ?></a>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPTS! -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>

</html>