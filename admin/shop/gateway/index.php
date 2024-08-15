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

$query = "SELECT type FROM shop_gateway WHERE type IN ('paypal', 'stripe')";
$result = $conn->query($query);

$existing_types = array();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $existing_types[] = $row['type'];
    }
}
$sql = "SELECT * FROM `shop_gateway`";
$result = $conn->query($sql);

if (isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];

    $sql = "DELETE FROM shop_gateway WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        header("Refresh:0");   
    }

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
                    <?php if ($is_boss == 1) {
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
                ?>
                <div class="row">
                    <div class="col-sm-12">
                        <?php echo $alerts_html; ?>
                        <?php
                        if ($is_boss == 1) {
                                ?>
                                <div class="card shadow mb-4">
                                    <div class="card-header py-3">
                                        <h5 class="card-title mb-0">
                                            <?php echo $translations["paygatway"]; ?>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <?php
                                            if ($result->num_rows > 0) {
                                                while ($row = $result->fetch_assoc()) {
                                                    ?>
                                                    <div class="col-md-3" data-id="<?php echo $row["id"]; ?>">
                                                        <div class="card shadow-sm mb-3">
                                                            <div class="card-header"><?php echo $row["name"]; ?></div>
                                                            <div class="card-body text-center">
                                                                <div class="mb-3">
                                                                    <img src="../../../assets/img/gatway/<?php echo $row["type"]; ?>.svg"
                                                                        style="max-height: 45px;" class="img-fluid"
                                                                        alt="<?php echo $row["name"]; ?>">
                                                                </div>
                                                                <a href="edit/<?php echo $row["type"]; ?>" class="btn btn-primary mt-1">
                                                                    <i class="bi bi-pencil-square"></i>
                                                                    <?php echo $translations["editbtn"]; ?>
                                                                </a>
                                                                <form class="delete-form" method="POST">
                                                                    <input type="hidden" name="delete_id"
                                                                        value="<?php echo $row["id"]; ?>">
                                                                    <button type="submit" class="btn btn-danger mt-1">
                                                                        <i class="bi bi-trash"></i>
                                                                        <?php echo $translations["delete"];?>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php
                                                }
                                            } else {
                                                echo '<div class="col-5 text-center">' . $translations["nogatewayadded"] . '</div>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            } else {
                                echo $translations["dont-access"];
                            }
                        ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <?php
                        if ($is_boss == 1) {
                                ?>
                                <div class="card card-default">
                                    <div class="card-heading">
                                        <h3 class="card-title">
                                            <?php echo $translations["addnewgateway"]; ?>
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="typeSelect"><?php echo $translations["type"]; ?></label>
                                            <select class="form-control" id="typeSelect" name="type" required="">
                                                <?php if (!in_array('paypal', $existing_types)): ?>
                                                    <option value="create/paypal">PayPal</option>
                                                <?php endif; ?>
                                            </select>
                                        </div>

                                        <a href="#"
                                            onclick="document.location.href = document.getElementById('typeSelect').value;"
                                            class="btn btn-primary">
                                            <span class="glyphicon glyphicon-plus"></span> <?php echo $translations["add"]; ?>
                                        </a>
                                    </div>
                                </div>

                                <?php
                            } else {
                                echo $translations["dont-access"];
                            }
                        ?>
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

    <script src="../../../assets/js/date-time.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"
        integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa"
        crossorigin="anonymous"></script>
</body>

</html>