<?php
session_start();

if (!isset($_SESSION['adminuser'])) {
    header("Location: ../");
    exit();
}

$userid = $_SESSION['adminuser'];
$logid = $_SESSION['adminuser'];

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

if (isset($_GET['userid'])) {
    $tickerbuyerid = isset($_GET['userid']) ? intval($_GET['userid']) : 0;
    $ticketid = isset($_GET['ticketid']) ? intval($_GET['ticketid']) : 0;
} else {
    exit;
}

$env_data = read_env_file('../../../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';
$country = $env_data['COUNTRY'] ?? '';
$street = $env_data['STREET'] ?? '';
$city = $env_data['CITY'] ?? '';
$hause_no = $env_data['HOUSE_NUMBER'] ?? '';
$description = $env_data['DESCRIPTION'] ?? '';
$metakey = $env_data['META_KEY'] ?? '';
$gkey = $env_data['GOOGLE_KEY'] ?? '';
$mailadress = $env_data['MAIL_USERNAME'] ?? '';
$phoneno = $env_data['PHONE_NO'] ?? '';
$smtp_host = $env_data['MAIL_HOST'] ?? '';
$smtp_port = $env_data['MAIL_PORT'] ?? '';
$smtp_encryption = $env_data['MAIL_ENCRYPTION'] ?? '';
$smtp_username = $env_data['MAIL_USERNAME'] ?? '';
$smtp_password = $env_data['MAIL_PASSWORD'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';
$currency = $env_data["CURRENCY"] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';

$lang = $lang_code;

$alerts_html = "";

$langDir = __DIR__ . "/../../../../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}
$curryear = date("Y");


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

$sql = "SELECT * FROM users WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tickerbuyerid);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $userid = $row["userid"];
    $firstname = $row["firstname"];
    $lastname = $row["lastname"];
    $email = $row["email"];
    $gender = $row["gender"];
    $birthdate = $row["birthdate"];
    $city = $row["city"];
    $street = $row["street"];
    $house_number = $row["house_number"];
    $profile_balance_odd = $row["profile_balance"];
}

$stmt->close();

$sql = "SELECT firstname, lastname FROM workers WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['adminuser']);
$stmt->execute();
$stmt->bind_result($workerfirstname, $workerlastname);
$stmt->fetch();
$stmt->close();


$sql = "SELECT name, expire_days, price, occasions FROM tickets WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $ticketid);
$stmt->execute();
$stmt->bind_result($ticketname, $expire_day, $ticketprice, $occasions);
$stmt->fetch();
$stmt->close();
$currentDate = new DateTime();

if (is_null($expire_day) || $expire_day == 0) {
    $expire_date = $translations["unlimited"];
} else {
    $currentDate = new DateTime();
    $currentDate->add(new DateInterval('P' . $expire_day . 'D'));
    $expire_date = $currentDate->format('Y-m-d');
}


$modalpayertext = str_replace('{$moneyplaceholder}', $ticketprice, $translations["modalpayertext"]);


require_once __DIR__ . '/../../../../vendor/autoload.php';

use Mpdf\Mpdf;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = $_POST['paymentMethod'] ?? '';
    $date = date('Y-m-d');
    $amount = $ticketprice;
    $method = $paymentMethod;

    if ($paymentMethod !== 'profile') {
        $field = ($method === 'card') ? 'bank_card' : 'cash';

        $sql = "SELECT id FROM revenu_stats WHERE date = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();

            $updateSql = "UPDATE revenu_stats SET $field = $field + ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("di", $amount, $row['id']);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            $insertSql = "INSERT INTO revenu_stats (date, bank_card, cash) VALUES (?, ?, ?)";
            $insertStmt = $conn->prepare($insertSql);

            $bank_card = ($method === 'card') ? $amount : 0;
            $cash = ($method === 'cash') ? $amount : 0;

            $insertStmt->bind_param("sdd", $date, $bank_card, $cash);
            $insertStmt->execute();
            $insertId = $insertStmt->insert_id;
            $insertStmt->close();
        }
    }

    $userid = $tickerbuyerid;
    if ($paymentMethod == 'cash') {
        $paymentMethod = $translations["cash"];
    } elseif ($paymentMethod == 'card') {
        $paymentMethod = $translations["card"];
    } elseif ($paymentMethod == 'profile') {
        $sql = "UPDATE users SET profile_balance = profile_balance - ? WHERE userid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("di", $ticketprice, $tickerbuyerid);
        $stmt->execute();
        $paymentMethod = $translations["profilebalancepay"];
    }

    $invoiceNumber = bin2hex(random_bytes(8));
    $date = date('Y-m-d');
    $dueDate = $expire_date;
    $clientName = $firstname . ' ' . $lastname;
    $clientCity = $city;
    $clientAddress = $street . ' ' . $hause_no;
    $clientEmail = $email;

    $logoPath = __DIR__ . '/../../../../assets/img/brand/logo.png';
    $logoData = base64_encode(file_get_contents($logoPath));
    $logoSrc = 'data:image/png;base64,' . $logoData;

    $partnerLogoPath = __DIR__ . '/../../../../assets/img/logo.png';
    $partnerLogoData = base64_encode(file_get_contents($partnerLogoPath));
    $partnerLogoSrc = 'data:image/png;base64,' . $partnerLogoData;

    $invoiceHtml = "
    <!doctype html>
    <html lang='hu'>
    <head>
        <meta charset='utf-8'>
        <title>" . $translations["invoice"] . " - " . $invoiceNumber . "</title>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { width: 100%; max-width: 800px; margin: auto; }
            .text-center { text-align: center; }
            .text-left { text-align: left; }
            .text-right { text-align: right; }
            .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            .table, .table th, .table td { border: 1px solid black; }
            .table th, .table td { padding: 8px; text-align: center; }
            .hr { border-top: 1px solid black; margin-top: 20px; margin-bottom: 20px; }
            .img-fluid { max-width: 100%; height: auto; }
            .row { width: 100%; display: table; }
            .col-6 { display: table-cell; width: 50%; vertical-align: middle; }
            .col-12 { width: 100%; }
            .align-center { vertical-align: middle; }
            .mb-0 { margin-bottom: 0; }
            .mt-2 { margin-top: 20px; }
            .me-2 { margin-right: 8px; }
            .d-flex { display: flex; }
            .justify-content-center { justify-content: center; }
            .align-items-center { align-items: center; }
            .blue{color:#0950dc;}
        </style>
    </head>

    <body>
        <div class='container mt-2'>
            <!-- Fejléc -->
            <table class='row text-center'>
                <tr>
                    <td class='col-6 align-center'>
                        <img src='$logoSrc' class='img-fluid' alt='Logo'>
                    </td>
                    <td class='col-6 align-center'>
                        <h1 class='blue'>" . $translations['invoice'] . "</h1>
                    </td>
                </tr>
            </table>
            <hr class='hr' />
            <table class='row text-left'>
                <tr>
                    <td class='col-6 align-center'>
                        <h4 class='blue'>" . $business_name . "</h4>
                        <p><small>" . $smtp_username . "</small></p>
                        <p><small>" . $phoneno . "</small></p>
                    </td>
                    <td class='col-6 align-center text-right'>
                        <p><b class='blue'>" . $translations["date-log"] . ":</b> $date</p>
                        <p><b class='blue'>" . $translations["invoiceid"] . "</b> $invoiceNumber</p>
                        <p><b class='blue'>" . $translations["userid"] . "</b> $userid</p>
                    </td>
                </tr>
            </table>
            <hr class='hr' />
            <div class='text-left'>
                <p><strong>" . $translations["adressedinvoice"] . "</strong></p>
                <p>&emsp;<strong>$clientName</strong></p>
                <p>&emsp;$clientCity</p>
                <p>&emsp;$clientAddress</p>
                <p>&emsp;$clientEmail</p>
            </div>
            <hr class='hr' />
            <table class='table'>
                <thead>
                    <tr>
                        <th>" . $translations["workerinvoice"] . "</th>
                        <th>" . $translations["paymenttype"] . "</th>
                        <th>" . $translations["date-log"] . "</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>$workerfirstname $workerlastname</td>
                        <td>" .
        ($method == 'profile' ? $translations["profilebalancepay"] : ($paymentMethod == 'cash' ? $translations["cash"] : $translations["card"])) .
        "</td>
                        <td>$date</td>
                    </tr>
                </tbody>
            </table>
            <hr class='hr' />
            <table class='table'>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>" . $translations["invoicedescription"] . "</th>
                        <th>" . $translations["unitprice"] . "</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>" . $ticketid . "</td>
                        <td>" . $ticketname . "</td>
                        <td>" . $ticketprice . "</td>
                    </tr>
                    <tr>
                        <td colspan='2' class='text-right'><strong>" . $translations["invoiceamount"] . "</strong></td>
                        <td><strong>" . $ticketprice . " " . $currency . "</strong></td>
                    </tr>
                </tbody>
            </table>
            <!-- Lábléc -->
            <table class='row' style='margin-top: 20px;'>
                <tr>
                    <td class='col-6 align-center'>
                        <img src='$partnerLogoSrc' width='100' class='img-fluid' alt='GYM ONE Logo COPYRIGHT DO NOT REMOVE'>
                    </td>
                    <td class='col-6 align-center text-left'>
                        <p class='mb-0'>Partner Program - © " . $curryear . " GYM One</p>
                    </td>
                </tr>
            </table>
        </div>
    </body>
    </html>
    ";

    $mpdf = new Mpdf();
    $mpdf->WriteHTML($invoiceHtml);

    $invoicePath = __DIR__ . "/../../../../assets/docs/invoices/{$userid}-{$invoiceNumber}.pdf";
    $mpdf->Output($invoicePath, \Mpdf\Output\Destination::FILE);

    $alerts_html .= '<div class="alert alert-success" role="alert">
                            ' . $translations["invoicecreated"] . '
                        </div>';
    $fullname = "{$firstname} {$lastname}";

    $stmt = $conn->prepare("INSERT INTO invoices (userid, name, price, status, route, created_at) VALUES (?, ?, ?, ?, ?, NOW())");

    $status = "paid";
    $pathinvoicesql = "{$userid}-{$invoiceNumber}.pdf";
    $stmt->bind_param("isdss", $userid, $fullname, $ticketprice, $status, $pathinvoicesql);

    if ($stmt->execute()) {
        $alerts_html .= '<div class="alert alert-success" role="alert">
                            ' . $translations["invoiceadded"] . '
                        </div>';
    } else {
        echo "Hiba történt: " . $stmt->error;
    }
    $stmt->close();


    $sql = "INSERT INTO current_tickets (userid, ticketname, buydate, expiredate, opportunities) 
        VALUES (?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param("isssi", $userid, $ticketname, $date, $expire_date, $occasions);

    if ($stmt->execute()) {
        $alerts_html .= '<div class="alert alert-success" role="alert">
                            ' . $translations["ticketadded"] . '
                        </div>';
        $action = $translations['log_ticketbuy'] . ' ID: ' . $tickerbuyerid . ' - ' . $ticketname . ' - ' . $paymentMethod . ' - ' . $userid . '-' . $invoiceNumber . '.pdf';
        $actioncolor = 'success';
        $sql = "INSERT INTO logs (userid, action, actioncolor, time) VALUES (?, ?, ?, NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $logid, $action, $actioncolor);
        $stmt->execute();
        header("Location: ../../../dashboard");
    } else {
        echo "Hiba történt: " . $stmt->error;
    }

    $stmt->close();
}

$message = "";

$file_path = 'https://api.gymoneglobal.com/latest/version.txt';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $file_path);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$latest_version = curl_exec($ch);
curl_close($ch);

$current_version = $version;

$is_new_version_available = version_compare($latest_version, $current_version) > 0;

?>


<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <title><?php echo $translations["dashboard"]; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../../../assets/css/dashboard.css">
    <link rel="shortcut icon" href="https://gymoneglobal.com/assets/img/logo.png" type="image/x-icon">
</head>
<!-- ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="../../../../assets/js/tinymce/js/tinymce/tinymce.min.js" referrerpolicy="origin"></script>


<body>
    <nav class="navbar navbar-inverse visible-xs">
        <div class="container-fluid">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#myNavbar">
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="#"><img src="../../../../assets/img/logo.png" width="50px" alt="Logo"></a>
            </div>
            <div class="collapse navbar-collapse" id="myNavbar">
                <ul class="nav navbar-nav">
                    <li><a href="../../../dashboard"><i class="bi bi-speedometer"></i> <?php echo $translations["mainpage"]; ?></a></li>
                    <li><a href="../../../users"><i class="bi bi-people"></i> <?php echo $translations["users"]; ?></a></li>
                    <li><a href="../../../statistics"><i class="bi bi-bar-chart"></i> <?php echo $translations["statspage"]; ?></a></li>
                    <li class="active"><a href="../"><i class="bi bi-shop"></i> <?php echo $translations["sellpage"]; ?></a></li>
                    <li><a href="../../../invoices"><i class="bi bi-receipt"></i> <?php echo $translations["invoicepage"]; ?></a></li>
                    <?php if ($is_boss === 1) { ?>
                        <li class="dropdown">
                            <a class="dropdown-toggle" data-toggle="dropdown" href="#"><i class="bi bi-gear"></i> <?php echo $translations["settings"]; ?> <span class="caret"></span></a>
                            <ul class="dropdown-menu">
                                <li><a href="../../../boss/mainsettings"><?php echo $translations["businesspage"]; ?></a></li>
                                <li><a href="../../../boss/workers"><?php echo $translations["workers"]; ?></a></li>
                                <li><a href="../../../boss/packages"><?php echo $translations["packagepage"]; ?></a></li>
                                <li><a href="../../../boss/hours"><?php echo $translations["openhourspage"]; ?></a></li>
                                <li><a href="../../../boss/smtp"><?php echo $translations["mailpage"]; ?></a></li>
                                <li><a href="../../../boss/chroom"><?php echo $translations["chroompage"]; ?></a></li>
                                <li><a href="../../../boss/rule"><?php echo $translations["rulepage"]; ?></a></li>
                            </ul>
                        </li>
                    <?php } ?>
                    <li><a href="../../../shop/tickets"><i class="bi bi-ticket"></i> <?php echo $translations["ticketspage"]; ?></a></li>
                    <li><a href="../../../trainers/timetable"><i class="bi bi-calendar-event"></i> <?php echo $translations["timetable"]; ?></a></li>
                    <li><a href="../../../trainers/personal"><i class="bi bi-award"></i> <?php echo $translations["trainers"]; ?></a></li>
                    <?php if ($is_boss === 1) { ?>
                        <li><a href="../../../updater"><i class="bi bi-cloud-download"></i> <?php echo $translations["updatepage"]; ?>
                                <?php if ($is_new_version_available) : ?>
                                    <span class="badge badge-warning"><i class="bi bi-exclamation-circle"></i></span>
                                <?php endif; ?>
                            </a></li>
                    <?php } ?>
                    <li><a href="../../../log"><i class="bi bi-clock-history"></i> <?php echo $translations["logpage"]; ?></a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row content">
            <div class="col-sm-2 sidenav hidden-xs text-center">
                <h2><img src="../../../../assets/img/logo.png" width="105px" alt="Logo"></h2>
                <p class="lead mb-4 fs-4"><?php echo $business_name ?> - <?php echo $version; ?></p>
                <ul class="nav nav-pills nav-stacked">
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../../../dashboard/">
                            <i class="bi bi-speedometer"></i> <?php echo $translations["mainpage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../../../users/">
                            <i class="bi bi-people"></i> <?php echo $translations["users"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../../../statistics">
                            <i class="bi bi-bar-chart"></i> <?php echo $translations["statspage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item active">
                        <a class="sidebar-link" href="#">
                            <i class="bi bi-shop"></i> <?php echo $translations["sellpage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../../../invoices/" class="sidebar-link">
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
                            <a class="sidebar-link" href="../../../boss/mainsettings">
                                <i class="bi bi-gear"></i>
                                <span><?php echo $translations["businesspage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../../boss/workers">
                                <i class="bi bi-people"></i>
                                <span><?php echo $translations["workers"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../../boss/packages">
                                <i class="bi bi-box-seam"></i>
                                <span><?php echo $translations["packagepage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../../boss/hours">
                                <i class="bi bi-clock"></i>
                                <span><?php echo $translations["openhourspage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../../boss/smtp">
                                <i class="bi bi-envelope-at"></i>
                                <span><?php echo $translations["mailpage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../../boss/chroom">
                                <i class="bi bi-duffle"></i>
                                <span><?php echo $translations["chroompage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../../boss/rule">
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
                        <a class="sidebar-ling" href="../../../shop/tickets">
                            <i class="bi bi-ticket"></i>
                            <span><?php echo $translations["ticketspage"]; ?></span>
                        </a>
                    </li>
                    <li class="sidebar-header">
                        <?php echo $translations["trainersclass"]; ?>
                    </li>
                    <li><a class="sidebar-link" href="../../../trainers/timetable">
                            <i class="bi bi-calendar-event"></i>
                            <span><?php echo $translations["timetable"]; ?></span>
                        </a></li>
                    <li><a class="sidebar-link" href="../../../trainers/personal">
                            <i class="bi bi-award"></i>
                            <span><?php echo $translations["trainers"]; ?></span>
                        </a></li>
                    <li class="sidebar-header"><?php echo $translations["other-header"]; ?></li>
                    <?php
                    if ($is_boss === 1) {
                    ?>
                        <li class="sidebar-item">
                            <a class="sidebar-ling" href="../../../updater">
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
                        <a class="sidebar-ling" href="../../../log">
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
                <div class="row">
                    <?= $alerts_html; ?>
                    <div class="col-sm-6">
                        <div class="card">
                            <div class="card-body">
                                <p class="lead"><?php echo $firstname; ?> <?php echo $lastname; ?> (<?= $tickerbuyerid; ?>)</p>
                                <p><?= $email; ?></p>
                                <p><?= $city; ?> <?= $street; ?> <?= $house_number; ?></p>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <button type="button" class="btn btn-success mt-3" data-toggle="modal" data-target="#paymentModal">
                                    <?php echo $translations["paybutton"]; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card">
                            <div class="card-body">
                                <p class="lead"><?= $translations["ticketinfo"]; ?></p>
                                <p><?php echo $translations["ticketspassname"]; ?>: <b><?= $ticketname; ?></b></p>
                                <p><?= $translations["price"]; ?>: <B><?= $ticketprice; ?></B> <?= $currency; ?></p>
                                <p><?= $translations["expiredate"]; ?> <code><b><?= $expire_date; ?></b></code> (<?= $expire_day; ?> <?= $translations["day"]; ?>)</p>
                                <p><?= $translations["occasions"]; ?>: <b><?= $occasions; ?></b></p>

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
                    <p class="lead"><?php echo $translations["exit-modal"]; ?></p>
                </div>
                <div class="modal-footer">
                    <a type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $translations["not-yet"]; ?></a>
                    <a href="../../../logout.php" type="button" class="btn btn-danger"><?php echo $translations["confirm"]; ?></a>
                </div>
            </div>
        </div>
    </div>
    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <h1><?= $translations["payment"]; ?></h1>
                    <p><?= $modalpayertext; ?></p>
                    <p><?= $translations["invoiceamount"]; ?>: <?php echo $ticketprice; ?> <?php echo $currency; ?></p>
                    <form method="post">
                        <div class="form">
                            <select id="paymentMethod" name="paymentMethod" class="form-control">
                                <option selected value="cash"><?= $translations["cash"]; ?></option>
                                <option value="card"><?= $translations["card"]; ?></option>
                                <?php if ($profile_balance_odd >= $ticketprice): ?>
                                    <option value="profile"><?= $translations["profilebalancepay"]; ?></option>
                                <?php endif; ?>

                            </select>
                        </div>
                        <button type="submit" class="btn btn-success mt-3"><?= $translations["next"]; ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    $conn->close();
    ?>
    <!-- SCRIPTS! -->
    <script src="../../../../assets/js/date-time.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>

</html>