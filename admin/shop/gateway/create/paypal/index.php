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

$env_data = read_env_file('../../../../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../../../../../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

$alerts_html = "";

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

$file_path = 'https://api.gymoneglobal.com/latest/version.txt';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $file_path);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$latest_version = curl_exec($ch);
curl_close($ch);

$current_version = $version;

$is_new_version_available = version_compare($latest_version, $current_version) > 0;


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $type = $_POST['type'];
    $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;

    $current_timestamp = date('Y-m-d H:i:s');

    $sql = "INSERT INTO shop_gateway (name, type, data, is_enabled, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        die('MySQL prepare error: ' . $conn->error);
    }

    $stmt->bind_param("ssssss", $name, $type, $email, $is_enabled, $current_timestamp, $current_timestamp);

    if (!$stmt->execute()) {
        die('Execute error: ' . $stmt->error);
    }

    header("Location: ../../");


    $stmt->close();
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
    <link rel="stylesheet" href="../../../../../assets/css/dashboard.css">
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
            <div class="col-sm-2 sidenav hidden-xs text-center">
                <h2><img src="../../../assets/img/logo.png" width="105px" alt="Logo"></h2>
                <p class="lead mb-4 fs-4"><?php echo $business_name ?> - <?php echo $version; ?></p>
                <ul class="nav nav-pills nav-stacked">
                    <li class="sidebar-item active">
                        <a class="sidebar-link" href="#">
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
                            <?php
                        }
                    }
                    ?>
                    <li class="sidebar-header">
                        Bolt
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-ling" href="../shop/gateway">
                            <i class="bi bi-shield-lock"></i>
                            <span><?php echo $translations["updatepage"]; ?></span>
                            <?php if ($is_new_version_available): ?>
                                <span class="sidebar-badge badge">
                                    <i class="bi bi-exclamation-circle"></i>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li><a href="#section3">Geo</a></li>
                    <li class="sidebar-header"><?php echo $translations["other-header"]; ?></li>
                    <?php
                    if ($stmt->num_rows > 0) {
                        $stmt->bind_result($is_boss);
                        $stmt->fetch();

                        if ($is_boss == 1) {
                            ?>
                            <li class="sidebar-item">
                                <a class="sidebar-ling" href="../updater">
                                    <i class="bi bi-cloud-download"></i>
                                    <span><?php echo $translations["updatepage"]; ?></span>
                                    <?php if ($is_new_version_available): ?>
                                        <span class="sidebar-badge badge">
                                            <i class="bi bi-exclamation-circle"></i>
                                        </span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <?php
                        }
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
                    <h5 id="clock" style="display: inline-block; margin-bottom: 0;"></h5>
                </div>
                <?php
                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($is_boss);
                    $stmt->fetch();

                    if ($is_boss == 1 && $is_new_version_available) {
                        ?>
                        <div class="row justify-content-center">
                            <div class="col-sm-5">
                                <div class="alert alert-danger">
                                    <?php echo $translations["newupdate-text"]; ?>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
                <div class="row">
                    <div class="col-sm-12">
                        <?php echo $alerts_html; ?>
                        <?php
                        if ($stmt->num_rows > 0) {
                            $stmt->bind_result($is_boss);
                            $stmt->fetch();

                            if ($is_boss == 1) {
                                ?>
                                <div class="card shadow mb-4">
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="type" value="paypal">

                                            <div class="mb-3">
                                                <label class="form-label" for="nameInput"><?php echo $translations["paypalusername"];?></label>
                                                <input type="text" class="form-control " id="nameInput" name="name"
                                                    value="PayPal">

                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label" for="emailInput"><?php echo $translations["paypalemail"];?></label>
                                                <input type="email" class="form-control " id="emailInput" name="email" value=""
                                                    required="" placeholder="hello@world.com">

                                            </div>

                                            <div class="alert alert-info" role="alert">
                                                <i class="bi bi-info-circle"></i> <?php echo $translations["makesurepaypal"];?>
                                            </div>

                                            <div class="mb-3 form-check form-switch">
                                                <input type="checkbox" class="form-check-input" id="enableSwitch"
                                                    name="is_enabled" checked="">
                                                <label class="form-check-label" for="enableSwitch"><?php echo $translations["gatewayokey"];?></label>
                                            </div>


                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-save"></i> <?php echo $translations["save"];?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
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
    </div>
    </div>

    <!-- EXIT MODAL -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <p><?php echo $translations["exit-modal"]; ?></p>
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

    <script src="../../../../../assets/js/date-time.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"
        integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa"
        crossorigin="anonymous"></script>
</body>

</html>