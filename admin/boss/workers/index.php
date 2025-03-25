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

$alerts_html = '';

$sql = "SELECT userid, Firstname, Lastname, username, is_boss FROM workers";
$result = $conn->query($sql);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_user"])) {
    $firstname = $_POST["firstname"];
    $lastname = $_POST["lastname"];
    $username = $_POST["username"];
    $password = $_POST["password"];
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $is_this_boss = isset($_POST["is_boss"]) ? 1 : 0;

    $newuserid = mt_rand(1000000000, 9999999999);

    $sql = "INSERT INTO workers (userid, Firstname, Lastname, username, password_hash, is_boss)
            VALUES ($newuserid, '$firstname', '$lastname', '$username', '$hashed_password', $is_this_boss)";

    if ($conn->query($sql) === TRUE) {
        $alerts_html .= "<div class='alert alert-success'>{$translations["success-add"]}</div>";
        $action = $translations['success-add-new-worker'] . ' ' . $newuserid . ' ' . $username;
        $actioncolor = 'success';
        $sql = "INSERT INTO logs (userid, action, actioncolor, time) 
            VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $userid, $action, $actioncolor);
        $stmt->execute();
        header("Refresh:2");
    } else {
        $alerts_html .= "<div class='alert alert-danger'>An error occurred while adding a user: " . $conn->error . "</div>";
    }
}

// FELHASZNÁLÓ TÖRLÉSE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_user"])) {
    $deleteuserid = $_POST["userid"];

    if ($deleteuserid != 9999999999) {
        $sql = "DELETE FROM workers WHERE userid = $deleteuserid";

        if ($conn->query($sql) === TRUE) {
            $alerts_html .= "<div class='alert alert-success'>{$translations["success-delete"]}</div>";
            $action = $translations['success-delete-worker'] . ' ' . $deleteuserid;
            $actioncolor = 'success';
            $sql = "INSERT INTO logs (userid, action, actioncolor, time) 
            VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $userid, $action, $actioncolor);
            $stmt->execute();
            header("Refresh:2");
        } else {
            $alerts_html .= "<div class='alert alert-danger'>An error occurred while deleting the user:: " . $conn->error . "</div>";
        }
    } else {
        $alerts_html .= "<div class='alert alert-warning'> {$translations["cant-delete-main"]}</div>";
        header("Refresh:2");
    }
}

$file_path = 'https://api.gymoneglobal.com/latest/version.txt';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $file_path);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$latest_version = curl_exec($ch);
curl_close($ch);

$current_version = $version;

$is_new_version_available = version_compare($latest_version, $current_version) > 0;

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
                <a class="navbar-brand" href="#"><img src="../../../assets/img/logo.png" width="50px" alt="Logo"></a>
            </div>
            <div class="collapse navbar-collapse" id="myNavbar">
                <ul class="nav navbar-nav">
                    <li><a href="../../dashboard"><i class="bi bi-speedometer"></i> <?php echo $translations["mainpage"]; ?></a></li>
                    <li><a href="../../users"><i class="bi bi-people"></i> <?php echo $translations["users"]; ?></a></li>
                    <li><a href="../../statistics"><i class="bi bi-bar-chart"></i> <?php echo $translations["statspage"]; ?></a></li>
                    <li><a href="../../boss/sell"><i class="bi bi-shop"></i> <?php echo $translations["sellpage"]; ?></a></li>
                    <li><a href="../../invoices"><i class="bi bi-receipt"></i> <?php echo $translations["invoicepage"]; ?></a></li>
                    <?php if ($is_boss === 1) { ?>
                        <li class="dropdown active">
                            <a class="dropdown-toggle" data-toggle="dropdown" href="#"><i class="bi bi-gear"></i> <?php echo $translations["settings"]; ?> <span class="caret"></span></a>
                            <ul class="dropdown-menu">
                                <li><a href="../../boss/mainsettings"><?php echo $translations["businesspage"]; ?></a></li>
                                <li class="active"><a href="../../boss/workers"><?php echo $translations["workers"]; ?></a></li>
                                <li><a href="../../boss/packages"><?php echo $translations["packagepage"]; ?></a></li>
                                <li><a href="../../boss/hours"><?php echo $translations["openhourspage"]; ?></a></li>
                                <li><a href="../../boss/smtp"><?php echo $translations["mailpage"]; ?></a></li>
                                <li><a href="../../boss/chroom"><?php echo $translations["chroompage"]; ?></a></li>
                                <li><a href="../../boss/rule"><?php echo $translations["rulepage"]; ?></a></li>
                            </ul>
                        </li>
                    <?php } ?>
                    <li><a href="../../shop/tickets"><i class="bi bi-ticket"></i> <?php echo $translations["ticketspage"]; ?></a></li>
                    <li><a href="../../trainers/timetable"><i class="bi bi-calendar-event"></i> <?php echo $translations["timetable"]; ?></a></li>
                    <li><a href="../../trainers/personal"><i class="bi bi-award"></i> <?php echo $translations["trainers"]; ?></a></li>
                    <?php if ($is_boss === 1) { ?>
                        <li><a href="../../updater"><i class="bi bi-cloud-download"></i> <?php echo $translations["updatepage"]; ?>
                                <?php if ($is_new_version_available) : ?>
                                    <span class="badge badge-warning"><i class="bi bi-exclamation-circle"></i></span>
                                <?php endif; ?>
                            </a></li>
                    <?php } ?>
                    <li><a href="../../log"><i class="bi bi-clock-history"></i> <?php echo $translations["logpage"]; ?></a></li>
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
                        <a class="sidebar-link" href="../../dashboard/">
                            <i class="bi bi-speedometer"></i> <?php echo $translations["mainpage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../../users">
                            <i class="bi bi-people"></i> <?php echo $translations["users"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../../statistics">
                            <i class="bi bi-bar-chart"></i> <?php echo $translations["statspage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../../boss/sell">
                            <i class="bi bi-shop"></i> <?php echo $translations["sellpage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../../invoices/" class="sidebar-link">
                            <i class="bi bi-receipt"></i> <?php echo $translations["invoicepage"]; ?>
                        </a>
                    </li>
                    <?php
                    if ($is_boss === 1) {
                    ?>
                        <li class="sidebar-header">
                            <?php echo $translations["settings"]; ?>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../boss/mainsettings">
                                <i class="bi bi-gear"></i>
                                <span><?php echo $translations["businesspage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item active">
                            <a class="sidebar-link" href="#">
                                <i class="bi bi-people"></i>
                                <span><?php echo $translations["workers"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../boss/packages">
                                <i class="bi bi-box-seam"></i>
                                <span><?php echo $translations["packagepage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../boss/hours">
                                <i class="bi bi-clock"></i>
                                <span><?php echo $translations["openhourspage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../boss/smtp">
                                <i class="bi bi-envelope-at"></i>
                                <span><?php echo $translations["mailpage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../boss/chroom">
                                <i class="bi bi-duffle"></i>
                                <span><?php echo $translations["chroompage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../boss/rule">
                                <i class="bi bi-file-ruled"></i>
                                <span><?php echo $translations["rulepage"]; ?></span>
                            </a>
                        </li>
                    <?php
                    }
                    ?>
                    <li class="sidebar-header">
                        <?php echo $translations["shopcategory"]; ?>

                    </li>
                    <li class="sidebar-item">
                        <!-- <a class="sidebar-ling" href="../shop/gateway">
                            <i class="bi bi-shield-lock"></i>
                            <span><?php echo $translations["gatewaypage"]; ?></span>
                        </a> -->
                        <a class="sidebar-ling" href="../../shop/tickets">
                            <i class="bi bi-ticket"></i>
                            <span><?php echo $translations["ticketspage"]; ?></span>
                        </a>
                    </li>
                    <li class="sidebar-header">
                        <?php echo $translations["trainersclass"]; ?>
                    </li>
                    <li><a class="sidebar-link" href="../../trainers/timetable">
                            <i class="bi bi-calendar-event"></i>
                            <span><?php echo $translations["timetable"]; ?></span>
                        </a></li>
                    <li><a class="sidebar-link" href="../../trainers/personal">
                            <i class="bi bi-award"></i>
                            <span><?php echo $translations["trainers"]; ?></span>
                        </a></li>
                    <li class="sidebar-header"><?php echo $translations["other-header"]; ?></li>
                    <?php
                    if ($is_boss === 1) {
                    ?>
                        <li class="sidebar-item">
                            <a class="sidebar-ling" href="../../updater">
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
                    <h5 id="clock" style="display: inline-block; margin-bottom: 0;"></h5>

                </div>
                <div class="row">
                    <div class="col-sm-12">
                        <?php echo $alerts_html; ?>
                        <div class="card shadow">
                            <div class="card-body">
                                <?php
                                if ($is_boss == 1) {
                                ?>
                                    <div class="table-responsive">

                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th scope="col"><?php echo $translations["firstname"]; ?></th>
                                                    <th scope="col"><?php echo $translations["lastname"]; ?></th>
                                                    <th scope="col"><?php echo $translations["username"]; ?></th>
                                                    <th scope="col"><?php echo $translations["position"]; ?></th>
                                                    <th scope="col"><?php echo $translations["action"]; ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if ($result->num_rows > 0) {
                                                    while ($row = $result->fetch_assoc()) {
                                                        echo "<tr>
                                <td>{$row["Firstname"]}</td>
                                <td>{$row["Lastname"]}</td>
                                <td>{$row["username"]}</td>
                                <td>";

                                                        if ($row["is_boss"] == 1) {
                                                            echo $translations["boss"];
                                                        } else {
                                                            echo $translations["worker"];
                                                        }

                                                        echo "</td>
                                <td>
                                    <form method='post' style='display: inline;'>
                                        <input type='hidden' name='userid' value='{$row["userid"]}'>
                                        <button type='submit' class='btn btn-danger btn-sm' name='delete_user'>{$translations["delete"]}</button>
                                    </form>
                                </td>
                              </tr>";
                                                    }
                                                } else {
                                                    echo "<tr><td colspan='5'>Users do not exist!</td></tr>";
                                                }
                                                ?>
                                            </tbody>
                                        </table>
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
                                <div class="card">
                                    <div class="card-body">
                                        <?php
                                        if ($is_boss == 1) {
                                        ?>
                                            <form method="post"
                                                action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                                <div class="form-row">
                                                    <div class="form-group col-md-3">
                                                        <input type="text" class="form-control" name="firstname"
                                                            placeholder="<?php echo $translations["firstname"]; ?>"
                                                            required>
                                                    </div>
                                                    <div class="form-group col-md-3">
                                                        <input type="text" class="form-control" name="lastname"
                                                            placeholder="<?php echo $translations["lastname"]; ?>" required>
                                                    </div>
                                                    <div class="form-group col-md-3">
                                                        <input type="text" class="form-control" name="username"
                                                            placeholder="<?php echo $translations["username"]; ?>" required>
                                                    </div>
                                                    <div class="form-group col-md-3">
                                                        <input type="password" class="form-control" name="password"
                                                            placeholder="<?php echo $translations["password"]; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-group form-check">
                                                    <input type="checkbox" class="form-check-input" id="is_boss"
                                                        name="is_boss" value="1">
                                                    <label class="form-check-label"
                                                        for="is_boss"><?php echo $translations["isboss-or-not"]; ?></label>
                                                </div>
                                                <button type="submit" class="btn btn-primary"
                                                    name="add_user"><?php echo $translations["register"]; ?></button>
                                            </form>
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
                </div>
            </div>
        </div>
    </div>

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
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>

</html>