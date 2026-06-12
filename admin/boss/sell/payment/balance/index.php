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
    $balance = isset($_GET['balance']) ? intval($_GET['balance']) : 0;
} else {
    exit;
}

$env_data = read_env_file('../../../../../.env');

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

$langDir = __DIR__ . "/../../../../../assets/lang/";

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
    $current_balance = $row["profile_balance"];
}

$stmt->close();

$sql = "SELECT firstname, lastname FROM workers WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['adminuser']);
$stmt->execute();
$stmt->bind_result($workerfirstname, $workerlastname);
$stmt->fetch();
$stmt->close();


// Egyenleg-feltöltés oldal: nincs jegy, a fizetendő összeg a feltöltendő egyenleg.
$modalpayertext = str_replace('{$moneyplaceholder}', $balance, $translations["modalpayertext"]);


require_once __DIR__ . '/../../../../../vendor/autoload.php';

use Mpdf\Mpdf;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = $_POST['paymentMethod'] ?? '';
    $date = date('Y-m-d');
    $amount = $balance;
    $method = $paymentMethod;

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
    if ($paymentMethod == 'cash') {
        $paymentMethod = $translations["cash"];
    } elseif ($paymentMethod == 'card') {
        $paymentMethod = $translations["card"];
    }
    $userid = $tickerbuyerid;
    $invoiceNumber = bin2hex(random_bytes(8));
    $date = date('Y-m-d');
    $clientName = $firstname . ' ' . $lastname;
    $clientCity = $city;
    $clientAddress = $street . ' ' . $hause_no;
    $clientEmail = $email;

    $logoPath = __DIR__ . '/../../../../../assets/img/brand/logo.png';
    $partnerLogoPath = __DIR__ . '/../../../../../assets/img/logo.png';

    require_once __DIR__ . '/../_invoice.php';

$inv_pm = ($method == 'profile') ? $translations["profilebalancepay"] : (($method == 'cash') ? $translations["cash"] : $translations["card"]);

    $inv_items = "
        <table class='inv-table'>
            <thead>
                <tr>
                    <th class='inv-th'>" . htmlspecialchars($translations["invoicedescription"]) . "</th>
                    <th class='inv-th inv-r' style='width:34%'>" . htmlspecialchars($translations["unitprice"]) . "</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>" . htmlspecialchars($translations["balanceuploadinvoice"]) . "</td>
                    <td class='inv-r'>" . number_format((float) $balance, 0, ',', '.') . " " . $currency . "</td>
                </tr>
                <tr class='inv-total-row'>
                    <td class='inv-r'>" . htmlspecialchars($translations["invoiceamount"]) . "</td>
                    <td class='inv-r'>" . number_format((float) $balance, 0, ',', '.') . " " . $currency . "</td>
                </tr>
            </tbody>
        </table>";

    $invoiceHtml = gymone_invoice_shell([
        't' => $translations,
        'title' => $translations["invoice"],
        'logoPath' => $logoPath,
        'partnerLogoPath' => $partnerLogoPath,
        'year' => $curryear,
        'businessName' => $business_name,
        'businessEmail' => $smtp_username,
        'businessPhone' => $phoneno,
        'date' => $date,
        'invoiceNumber' => $invoiceNumber,
        'userid' => $userid,
        'clientName' => $clientName,
        'clientCity' => $clientCity,
        'clientAddress' => $clientAddress,
        'clientEmail' => $clientEmail,
        'workerName' => $workerfirstname . ' ' . $workerlastname,
        'paymentType' => $inv_pm,
    ], $inv_items);

    $mpdf = new Mpdf();
    $mpdf->WriteHTML($invoiceHtml);

    $invoicePath = __DIR__ . "/../../../../../assets/docs/invoices/{$userid}-{$invoiceNumber}.pdf";
    $mpdf->Output($invoicePath, \Mpdf\Output\Destination::FILE);

    $alerts_html .= '<div class="alert alert-success" role="alert">
                            ' . $translations["invoicecreated"] . '
                        </div>';
    $fullname = "{$firstname} {$lastname}";

    $stmt = $conn->prepare("INSERT INTO invoices (userid, name, price, status, route, created_at) VALUES (?, ?, ?, ?, ?, NOW())");

    $status = "paid";
    $pathinvoicesql = "{$userid}-{$invoiceNumber}.pdf";
    $stmt->bind_param("isdss", $userid, $fullname, $balance, $status, $pathinvoicesql);

    if ($stmt->execute()) {
        $alerts_html .= '<div class="alert alert-success" role="alert">
                            ' . $translations["invoiceadded"] . '
                        </div>';
    } else {
        echo "Hiba történt: " . $stmt->error;
    }
    $stmt->close();


    $sql = "UPDATE users SET profile_balance = profile_balance + ? WHERE userid = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("di", $balance, $userid);
    if ($stmt->execute()) {
        $purchase_date_new = new DateTime("now");

        $purchase_date_formatted = $purchase_date_new->format('Y-m-d H:i:s');

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $domain_url = $protocol . $host;

        $transport = (new Swift_SmtpTransport($smtp_host, $smtp_port, $smtp_encryption))
            ->setUsername($smtp_username)
            ->setPassword($smtp_password);

        $mailer = new Swift_Mailer($transport);
        $PayEmailHero_PLACEHOLDER = str_replace("{first_name}", $firstname, $translations["payemailhero"]);
        $PayEmailFooterWhy_PLACEHOLDER = str_replace("{business_name}", $business_name, $translations["payemailfooterwhy"]);

        $emailHtml = <<<EOD
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Confirmation</title>
    <style>
        /* PRIMARY: #0950DC (vibrant blue) ACCENT-DARK: #0742B8 TEXT: #222 MUTED: #6B7280 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: #f8f9fa;
        }

        .email-container {
            max-width: 680px;
            margin: 0 auto;
            background: white;
        }

        .header {
            padding: 40px 30px 20px;
            text-align: center;
        }

        .logo {
            max-width: 200px;
            height: auto;
        }

        .content {
            padding: 0 30px 30px;
        }

        .success-badge {
            background: #ECFDF5;
            color: #059669;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 20px;
        }

        .hero-title {
            color: #222;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .subtitle {
            color: #6B7280;
            font-size: 16px;
            margin-bottom: 32px;
        }

        .purchase-details {
            background: #f8f9fa;
            border: 1px solid #E5E7EB;
            padding: 24px;
            border-radius: 8px;
            margin: 24px 0;
        }

        .details-table {
            width: 100%;
        }

        .details-row {
            border-bottom: 1px solid #E5E7EB;
        }

        .details-row:last-child {
            border-bottom: none;
        }

        .details-label {
            color: #6B7280;
            font-weight: 600;
            padding: 12px 0;
            width: 40%;
        }

        .details-value {
            color: #222;
            font-weight: 600;
            padding: 12px 0;
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #0950DC, #0742B8);
            color: white;
            text-decoration: none;
            padding: 16px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(9, 80, 220, 0.3);
        }

        .cta-container {
            text-align: center;
            margin: 32px 0;
        }

        .support-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 32px 0;
        }

        .support-title {
            color: #222;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .footer {
            background: #f8f9fa;
            padding: 24px 30px;
            text-align: center;
            color: #6B7280;
            font-size: 12px;
        }

        .footer a {
            color: #0950DC;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td>
                <div class="email-container">
                    <div class="header">
                        <img src="{$domain_url}/assets/img/brand/logo.png" alt="GYM Logo" class="logo">
                    </div>

                    <div class="content">
                        <div style="text-align: center;">
                            <span class="success-badge">✓ {$translations["payemailbadge"]}</span>
                        </div>

                        <h1 class="hero-title">{$PayEmailHero_PLACEHOLDER}</h1>
                        <p class="subtitle">{$translations["payemailsubtitle"]}</p>

                        <div class="purchase-details">
                            <table class="details-table">
                                <tr class="details-row">
                                    <td class="details-label">{$translations["buytime"]}:</td>
                                    <td class="details-value">{$purchase_date_formatted}</td>
                                </tr>
                                <tr class="details-row">
                                    <td class="details-label">{$translations["payemailitem"]}</td>
                                    <td class="details-value">{$translations["balanceuploadinvoice"]}</td>
                                </tr>
                                <tr class="details-row">
                                    <td class="details-label">{$translations["payemailamount"]}:</td>
                                    <td class="details-value">{$balance} {$currency}</td>
                                </tr>
                                <tr class="details-row">
                                    <td class="details-label">{$translations["invoice"]} #:</td>
                                    <td class="details-value">{$userid}-{$invoiceNumber}</td>
                                </tr>
                            </table>
                        </div>

                        <div class="cta-container">
                            <a href="{$domain_url}/assets/docs/invoices/{$pathinvoicesql}" class="cta-button">{$translations["payemailcta"]}</a>
                        </div>
                    </div>

                    <div class="footer">
                        <p>{$PayEmailFooterWhy_PLACEHOLDER}</p>
                        <p style="font-size:10px; color:#D1D5DB; display:flex; align-items:center; justify-content:center; gap:8px;">
                            <span>⚡</span>
                            <span>Engineered with <span style="color:#ef4444;">♥</span> by <a href="https://gymoneglobal.com" style="color:#0950DC;">GYM One</a></span>
                            <span>⚡</span>
                        </p>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>

</html>
EOD;

        $recipientEmail = $email;
        $subject = $translations["payemailsubject"];

        $message = (new Swift_Message($subject))
            ->setFrom(["{$smtp_username}" => "{$business_name}"])
            ->setTo([$recipientEmail])
            ->setBody($emailHtml, 'text/html');

        $mailer->send($message);
        header("Location: ../../../../dashboard");
    } else {
        echo "Unexpected Error: " . $stmt->error;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../../../../assets/css/dashboard.css">
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
                <a class="navbar-brand" href="#"><img src="../../../../../assets/img/logo.png" width="50px" alt="Logo"></a>
            </div>
            <div class="collapse navbar-collapse" id="myNavbar">
                <ul class="nav navbar-nav">
                    <li><a href="../../../../dashboard"><i class="bi bi-speedometer"></i> <?php echo $translations["mainpage"]; ?></a></li>
                    <li><a href="../../../../users"><i class="bi bi-people"></i> <?php echo $translations["users"]; ?></a></li>
                    <li><a href="../../../../statistics"><i class="bi bi-bar-chart"></i> <?php echo $translations["statspage"]; ?></a></li>
                    <li class="active"><a href="../../"><i class="bi bi-shop"></i> <?php echo $translations["sellpage"]; ?></a></li>
                    <li><a href="../../../../invoices"><i class="bi bi-receipt"></i> <?php echo $translations["invoicepage"]; ?></a></li>
                    <?php if ($is_boss === 1) { ?>
                        <li class="dropdown">
                            <a class="dropdown-toggle" data-toggle="dropdown" href="#"><i class="bi bi-gear"></i> <?php echo $translations["settings"]; ?> <span class="caret"></span></a>
                            <ul class="dropdown-menu">
                                <li><a href="../../../../boss/mainsettings"><?php echo $translations["businesspage"]; ?></a></li>
                                <li><a href="../../../../boss/workers"><?php echo $translations["workers"]; ?></a></li>
                                <li><a href="../../../../boss/packages"><?php echo $translations["packagepage"]; ?></a></li>
                                <li><a href="../../../../boss/hours"><?php echo $translations["openhourspage"]; ?></a></li>
                                <li><a href="../../../../boss/smtp"><?php echo $translations["mailpage"]; ?></a></li>
                                <li><a href="../../../../boss/chroom"><?php echo $translations["chroompage"]; ?></a></li>
                                <li><a href="../../../../boss/rule"><?php echo $translations["rulepage"]; ?></a></li>
                            </ul>
                        </li>
                    <?php } ?>
                    <li><a href="../../../../shop/tickets"><i class="bi bi-ticket"></i> <?php echo $translations["ticketspage"]; ?></a></li>
                    <li><a href="../../../../trainers/timetable"><i class="bi bi-calendar-event"></i> <?php echo $translations["timetable"]; ?></a></li>
                    <li><a href="../../../../trainers/personal"><i class="bi bi-award"></i> <?php echo $translations["trainers"]; ?></a></li>
                    <?php if ($is_boss === 1) { ?>
                        <li><a href="../../../../updater"><i class="bi bi-cloud-download"></i> <?php echo $translations["updatepage"]; ?>
                                <?php if ($is_new_version_available) : ?>
                                    <span class="badge badge-warning"><i class="bi bi-exclamation-circle"></i></span>
                                <?php endif; ?>
                            </a></li>
                    <?php } ?>
                    <li><a href="../../../../log"><i class="bi bi-clock-history"></i> <?php echo $translations["logpage"]; ?></a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row content">
            <div class="col-sm-2 sidenav hidden-xs text-center">
                <h2><img src="../../../../../assets/img/logo.png" width="105px" alt="Logo"></h2>
                <p class="lead mb-4 fs-4"><?php echo $business_name ?> - <?php echo $version; ?></p>
                <ul class="nav nav-pills nav-stacked">
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../../../../dashboard/">
                            <i class="bi bi-speedometer"></i> <?php echo $translations["mainpage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../../../../users/">
                            <i class="bi bi-people"></i> <?php echo $translations["users"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../../../../statistics">
                            <i class="bi bi-bar-chart"></i> <?php echo $translations["statspage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item active">
                        <a class="sidebar-link" href="#">
                            <i class="bi bi-shop"></i> <?php echo $translations["sellpage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../../../../invoices/" class="sidebar-link">
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
                            <a class="sidebar-link" href="../../../../boss/mainsettings">
                                <i class="bi bi-gear"></i>
                                <span><?php echo $translations["businesspage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../../../boss/workers">
                                <i class="bi bi-people"></i>
                                <span><?php echo $translations["workers"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../../../boss/packages">
                                <i class="bi bi-box-seam"></i>
                                <span><?php echo $translations["packagepage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../../../boss/hours">
                                <i class="bi bi-clock"></i>
                                <span><?php echo $translations["openhourspage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../../../boss/smtp">
                                <i class="bi bi-envelope-at"></i>
                                <span><?php echo $translations["mailpage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../../../boss/chroom">
                                <i class="bi bi-duffle"></i>
                                <span><?php echo $translations["chroompage"]; ?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../../../boss/rule">
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
                        <a class="sidebar-ling" href="../../../../shop/tickets">
                            <i class="bi bi-ticket"></i>
                            <span><?php echo $translations["ticketspage"]; ?></span>
                        </a>
                    </li>
                    <li class="sidebar-header">
                        <?php echo $translations["trainersclass"]; ?>
                    </li>
                    <li><a class="sidebar-link" href="../../../../trainers/timetable">
                            <i class="bi bi-calendar-event"></i>
                            <span><?php echo $translations["timetable"]; ?></span>
                        </a></li>
                    <li><a class="sidebar-link" href="../../../../trainers/personal">
                            <i class="bi bi-award"></i>
                            <span><?php echo $translations["trainers"]; ?></span>
                        </a></li>
                    <li class="sidebar-header"><?php echo $translations["other-header"]; ?></li>
                    <?php
                    if ($is_boss === 1) {
                    ?>
                        <li class="sidebar-item">
                            <a class="sidebar-ling" href="../../../../updater">
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
                        <a class="sidebar-ling" href="../../../../log">
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
                $pc_ini = function ($a, $b) {
                    $i = mb_strtoupper(mb_substr(trim((string) $a), 0, 1, 'UTF-8') . mb_substr(trim((string) $b), 0, 1, 'UTF-8'), 'UTF-8');
                    return $i !== '' ? $i : '?';
                };
                ?>
                <div class="pc">
                    <div class="pc-head">
                        <a href="../../ticket/?userid=<?php echo urlencode($tickerbuyerid); ?>" class="pc-back">
                            <i class="bi bi-arrow-left"></i> <?php echo $translations['sell-change'] ?? 'Vissza'; ?>
                        </a>
                        <div class="pc-head-title">
                            <span class="pc-head-icon"><i class="bi bi-wallet2"></i></span>
                            <h3><?php echo $translations['customaddmoneyheader'] ?? 'Egyenleg feltöltés'; ?></h3>
                        </div>
                    </div>

                    <div class="pc-alerts"><?= $alerts_html; ?></div>

                    <div class="pc-grid">
                        <!-- Vásárló -->
                        <div class="pc-card">
                            <div class="pc-card-head">
                                <span class="pc-card-icon"><i class="bi bi-person"></i></span>
                                <h5><?php echo $translations['selected-member'] ?? 'Vásárló'; ?></h5>
                            </div>
                            <div class="pc-buyer">
                                <div class="pc-avatar">
                                    <span class="pc-ava-ini"><?php echo htmlspecialchars($pc_ini($firstname, $lastname)); ?></span>
                                    <img class="pc-ava-img" src="../../../../../assets/img/profiles/<?php echo htmlspecialchars($tickerbuyerid); ?>.png" alt="" onerror="this.remove()">
                                </div>
                                <div>
                                    <div class="pc-buyer-name"><?php echo htmlspecialchars($firstname . ' ' . $lastname); ?></div>
                                    <div class="pc-buyer-id"><i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($tickerbuyerid); ?></div>
                                </div>
                            </div>
                            <ul class="pc-meta">
                                <li><i class="bi bi-envelope"></i> <span><?php echo htmlspecialchars($email); ?></span></li>
                                <li><i class="bi bi-geo-alt"></i> <span><?php echo htmlspecialchars(trim($city . ' ' . $street . ' ' . $house_number)); ?></span></li>
                            </ul>
                            <button type="button" class="pc-btn pc-btn-primary pc-block" data-toggle="modal" data-target="#paymentModal">
                                <i class="bi bi-wallet2"></i> <?php echo $translations["paybutton"]; ?>
                            </button>
                        </div>

                        <!-- Összegzés -->
                        <div class="pc-card pc-summary">
                            <div class="pc-card-head">
                                <span class="pc-card-icon"><i class="bi bi-cash-stack"></i></span>
                                <h5><?php echo $translations["ticketinfo"]; ?></h5>
                            </div>
                            <div class="pc-line">
                                <span><?php echo $translations["price"]; ?></span>
                                <b>+<?php echo number_format((float) $balance, 0, ',', '.'); ?> <?php echo $currency; ?></b>
                            </div>
                            <div class="pc-line">
                                <span><?php echo $translations['profilebalancepay'] ?? 'Jelenlegi egyenleg'; ?></span>
                                <b><?php echo number_format((float) $current_balance, 0, ',', '.'); ?> <?php echo $currency; ?></b>
                            </div>
                            <div class="pc-total">
                                <span><?php echo $translations["newprofilebalance"]; ?></span>
                                <span class="pc-total-val"><?php echo number_format((float) $current_balance + (float) $balance, 0, ',', '.'); ?> <?php echo $currency; ?></span>
                            </div>
                        </div>
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

                        <a href="../../../../logout.php" type="button" class="btn btn-danger" style="padding: 8px 25px;">
                            <i class="bi bi-check-circle" style="margin-right: 5px;"></i>
                            <?php echo $translations["confirm"]; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Payment Modal -->
    <div class="modal fade pc-modal" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="pc-modal-top">
                        <span class="pc-modal-icon"><i class="bi bi-wallet2"></i></span>
                        <h4><?= $translations["payment"]; ?></h4>
                        <p class="pc-modal-sub"><?= $modalpayertext; ?></p>
                    </div>
                    <div class="pc-modal-amount">
                        <span><?= $translations["invoiceamount"]; ?></span>
                        <b><?php echo number_format((float) $balance, 0, ',', '.'); ?> <?php echo $currency; ?></b>
                    </div>
                    <form method="post">
                        <div class="pc-methods">
                            <label class="pc-method">
                                <input type="radio" name="paymentMethod" value="cash" checked>
                                <span class="pc-method-box"><i class="bi bi-cash-coin"></i><span><?= $translations["cash"]; ?></span></span>
                            </label>
                            <label class="pc-method">
                                <input type="radio" name="paymentMethod" value="card">
                                <span class="pc-method-box"><i class="bi bi-credit-card-2-front"></i><span><?= $translations["card"]; ?></span></span>
                            </label>
                        </div>
                        <div class="pc-modal-actions">
                            <button type="button" class="pc-btn pc-btn-ghost" data-dismiss="modal"><?= $translations["not-yet"] ?? 'Mégse'; ?></button>
                            <button type="submit" class="pc-btn pc-btn-success"><i class="bi bi-check-lg"></i> <?= $translations["next"]; ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../_pc_assets.php'; ?>

    <?php
    $conn->close();
    ?>
    <!-- SCRIPTS! -->
    <script src="../../../../assets/js/date-time.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>

</html>