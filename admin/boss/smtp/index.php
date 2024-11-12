<?php
session_start();

if (!isset($_SESSION['adminuser'])) {
    header("Location: ../");
    exit();
}

$userid = $_SESSION['adminuser'];

$alerts_html = "";

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
$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}

$translations = json_decode(file_get_contents($langFile), true);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['smtp_host'])) {
        $smtp_host = $_POST['smtp_host'] ?? '';
        $smtp_port = $_POST['smtp_port'] ?? '';
        $smtp_username = $_POST['smtp_username'] ?? '';
        $smtp_password = $_POST['smtp_password'] ?? '';
        $smtp_encryption = $_POST['smtp_encryption'] ?? '';

        $env_data['MAIL_HOST'] = $smtp_host;
        $env_data['MAIL_PORT'] = $smtp_port;
        $env_data['MAIL_USERNAME'] = $smtp_username;
        $env_data['MAIL_PASSWORD'] = $smtp_password;
        $env_data['MAIL_ENCRYPTION'] = $smtp_encryption;

        $env_content = '';
        foreach ($env_data as $key => $value) {
            $env_content .= "$key=$value\n";
        }

        if (file_put_contents('../../../.env', $env_content) !== false) {
            $alerts_html .= "<div class='alert alert-success'>{$translations["success-update"]}</div>";
            $action = $translations['success-update-env-smtp'];
            $actioncolor = 'success';
            $sql = "INSERT INTO logs (userid, action, actioncolor, time) 
            VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $userid, $action, $actioncolor);
            $stmt->execute();

            header("Refresh:2");
        } else {
            $alerts_html .= "<div class='alert alert-danger'>{$translations["error-env"]}</div>";
            header("Refresh:2");
        }
    } elseif (isset($_POST['test_email_address'])) {
        require_once '../../../vendor/autoload.php';

        $transport = (new Swift_SmtpTransport($env_data['MAIL_HOST'], $env_data['MAIL_PORT']))
            ->setUsername($env_data['MAIL_USERNAME'])
            ->setPassword($env_data['MAIL_PASSWORD'])
            ->setEncryption($env_data['MAIL_ENCRYPTION']);

        $mailer = new Swift_Mailer($transport);

        $message = (new Swift_Message($translations['test-mail-header']))
            ->setFrom([$env_data['MAIL_USERNAME'] => $business_name])
            ->setTo([$_POST['test_email_address']])
            ->setBody($translations["test-mail-body"]);

        try {
            $result = $mailer->send($message);
            $alerts_html .= "<div class='alert alert-success'>{$translations["testemail-sented"]}</div>";
            header("Refresh:2");
        } catch (Exception $e) {
            $alerts_html .= "<div class='alert alert-danger'>Failed to send test email: " . $e->getMessage() . "</div>";
            header("Refresh:2");
        }
    }
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
                            <li class="sidebar-item">
                                <a class="sidebar-link" href="../hours">
                                    <i class="bi bi-clock"></i>
                                    <span><?php echo $translations["openhourspage"]; ?></span>
                                </a>
                            </li>
                            <li class="sidebar-item active">
                                <a class="sidebar-link" href="#">
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
                                if ($is_boss == 1) {
                                        ?>
                                        <form method="POST">
                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label for="smtp_host">SMTP <?php echo $translations["host"]; ?>:</label>
                                                    <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                                                        value="<?= htmlspecialchars($env_data['MAIL_HOST'] ?? '') ?>">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label for="smtp_port">SMTP <?php echo $translations["port"]; ?>:</label>
                                                    <input type="number" min="1" max="65535" class="form-control" id="smtp_port"
                                                        name="smtp_port"
                                                        value="<?= htmlspecialchars($env_data['MAIL_PORT'] ?? '') ?>">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label for="smtp_encryption">SMTP
                                                        <?php echo $translations["encry"]; ?>:</label>
                                                    <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                                                        <option value="TLS" <?= ($env_data['MAIL_ENCRYPTION'] ?? '') == 'TLS' ? 'selected' : '' ?>>TLS</option>
                                                        <option value="SSL" <?= ($env_data['MAIL_ENCRYPTION'] ?? '') == 'SSL' ? 'selected' : '' ?>>SSL</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label for="smtp_username">SMTP
                                                        <?php echo $translations["username"]; ?>:</label>
                                                    <input type="text" class="form-control" id="smtp_username"
                                                        name="smtp_username"
                                                        value="<?= htmlspecialchars($env_data['MAIL_USERNAME'] ?? '') ?>">
                                                </div>
                                                <div class="form-group col-md-6">
                                                    <label for="smtp_password">SMTP
                                                        <?php echo $translations["password"]; ?>:</label>
                                                    <input type="password" class="form-control" id="smtp_password"
                                                        name="smtp_password"
                                                        value="<?= htmlspecialchars($env_data['MAIL_PASSWORD'] ?? '') ?>">
                                                </div>
                                            </div>
                                            <button type="submit"
                                                class="btn btn-primary"><?php echo $translations["save"]; ?></button>
                                            <button type="button" class="btn btn-success" data-toggle="modal"
                                                data-target="#emailModal">
                                                <?php echo $translations["mailtest"]; ?>
                                            </button>
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

    <!-- EMAIL MODAL -->
    <div class="modal fade" id="emailModal" tabindex="-1" role="dialog" aria-labelledby="emailModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="emailModalLabel"><?php echo $translations["mailtest"]; ?></h5>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="test_email_address"><?php echo $translations["test-email"]; ?></label>
                            <input type="email" class="form-control" id="test_email_address" name="test_email_address"
                                required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary"
                            data-dismiss="modal"><?php echo $translations["close"]; ?></button>
                        <button type="submit" class="btn btn-primary"><?php echo $translations["send"]; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SCRIPTS! -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>

</html>