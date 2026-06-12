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

$env_data = read_env_file('../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';
$currency = $env_data['CURRENCY'] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}

$sql = "SELECT * FROM current_tickets WHERE userid = ? ORDER BY expiredate DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userid);
$stmt->execute();
$result = $stmt->get_result();

$id = $ticketname = $buydate = $expiredate = $opportunities = null;
$currentDate = new DateTime();
$currentDate = $currentDate->format('Y-m-d');

$validTicketFound = false;

while ($row = $result->fetch_assoc()) {
    $expireDate = new DateTime($row['expiredate']);
    $expireDate = $expireDate->format('Y-m-d');

    if ($expireDate >= $currentDate) {
        $id = $row['id'];
        $ticketname = $row['ticketname'];
        $buydate = $row['buydate'];
        $expiredate = $row['expiredate'];
        $opportunities = $row['opportunities'];
        $validTicketFound = true;
        break;
    }
}

$stmt->close();

$currentDate = new DateTime();

if (!empty($expiredate) && strtotime($expiredate) !== false) {
    $expireDate = new DateTime($expiredate);
    $interval = $currentDate->diff($expireDate);
    $daysRemaining = $interval->days;

    if ($expireDate < $currentDate) {
        $daysRemaining = "-";
    } else {
        $interval = $currentDate->diff($expireDate);
        $daysRemaining = $interval->days;

        if ($expireDate >= $currentDate) {
            $daysRemaining++;
        }
    }
} else {
    $daysRemaining = "-";
}


$sql_latest_training = "SELECT workout_date FROM workout_stats WHERE userid = $userid ORDER BY workout_date DESC LIMIT 1";
$result_latest_training = $conn->query($sql_latest_training);
if (!$result_latest_training) {
    die("Hiba a legutóbbi edzés dátumának lekérdezésekor: " . $conn->error);
}
$latest_training = ($result_latest_training->num_rows > 0) ? $result_latest_training->fetch_assoc()['workout_date'] : $translations["n/a"];



$sql = "SELECT firstname, lastname, profile_balance FROM users WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userid);
$stmt->execute();
$stmt->bind_result($firstname, $lastname, $profile_balance);
$stmt->fetch();

$stmt->close();

$dayNames = [
    1 => $translations["Mon"],
    2 => $translations["Tue"],
    3 => $translations["Wed"],
    4 => $translations["Thu"],
    5 => $translations["Fri"],
    6 => $translations["Sat"],
    7 => $translations["Sun"]
];

$days = [];
$result = $conn->query("SELECT * FROM opening_hours ORDER BY day ASC");
while ($row = $result->fetch_assoc()) {
    $days[] = $row;
}

$today = new DateTime('today');
$maxDate = (new DateTime('today'))->modify('+14 days');

$todayStr = $today->format('Y-m-d');
$maxDateStr = $maxDate->format('Y-m-d');

$exceptions = [];
$stmt = $conn->prepare("
    SELECT * 
    FROM opening_hours_exceptions 
    WHERE date BETWEEN ? AND ?
    ORDER BY date ASC
");
$stmt->bind_param("ss", $todayStr, $maxDateStr);
$stmt->execute();

$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $exceptions[] = $row;
}
$stmt->close();

$months = [
    1 => $translations["Jan"],
    2 => $translations["Feb"],
    3 => $translations["Mar"],
    4 => $translations["Apr"],
    5 => $translations["May"],
    6 => $translations["Jun"],
    7 => $translations["Jul"],
    8 => $translations["Aug"],
    9 => $translations["Sep"],
    10 => $translations["Oct"],
    11 => $translations["Nov"],
    12 => $translations["Dec"],
];



// Legutóbbi tranzakciók (számlák) a belépett taghoz – csak olvasás
$transactions = [];
$tx_stmt = $conn->prepare("SELECT name, price, status, created_at FROM invoices WHERE userid = ? ORDER BY created_at DESC LIMIT 5");
$tx_stmt->bind_param("i", $userid);
$tx_stmt->execute();
$tx_res = $tx_stmt->get_result();
while ($tx_row = $tx_res->fetch_assoc()) {
    $transactions[] = $tx_row;
}
$tx_stmt->close();

// Edzés-összegzés (csak olvasás)
$workout_count = 0;
$workout_total_min = 0;
$workout_month = 0;
if ($wres = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(duration),0) AS total_min FROM workout_stats WHERE userid = $userid")) {
    $wrow = $wres->fetch_assoc();
    $workout_count = (int) $wrow['cnt'];
    $workout_total_min = (int) $wrow['total_min'];
}
if ($wmres = $conn->query("SELECT COUNT(*) AS cnt FROM workout_stats WHERE userid = $userid AND workout_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")) {
    $workout_month = (int) $wmres->fetch_assoc()['cnt'];
}

$conn->close();


require __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Label\Alignment\LabelAlignmentCenter;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\Label\Font\NotoSans;

$filename = __DIR__ . "/../assets/img/logincard/{$userid}.png";
$logoPath = 'https://gymoneglobal.com/assets/img/logo.png';

if (!file_exists($filename)) {
    try {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($userid)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->size(300)
            ->margin(5)
            ->logoPath($logoPath)
            ->logoResizeToWidth(100)
            ->labelText($firstname . ' ' . $lastname)
            ->labelFont(new NotoSans(20))
            ->labelAlignment(new LabelAlignmentCenter())
            ->validateResult(false)
            ->build();

        $result->saveToFile($filename);
        header("Refresh:2");
    } catch (Exception $e) {
        echo ' . $translations["unexpected-error"] . ' . $e->getMessage();
    }
}

// Bérlet kihasználtság (haladássáv) – a már meglévő adatokból
$ticketPercent = 0;
if ($validTicketFound && !empty($buydate) && !empty($expiredate)) {
    try {
        $bd = new DateTime($buydate);
        $ed = new DateTime($expiredate);
        $nw = new DateTime('today');
        $totalDays = (int) $bd->diff($ed)->days;
        $totalDays = $totalDays > 0 ? $totalDays : 1;
        $elapsed = (int) $bd->diff($nw)->days;
        if ($nw < $bd) {
            $elapsed = 0;
        }
        if ($elapsed > $totalDays) {
            $elapsed = $totalDays;
        }
        $ticketPercent = (int) round($elapsed / $totalDays * 100);
    } catch (Exception $e) {
        $ticketPercent = 0;
    }
}
?>


<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <title><?php echo $business_name; ?> - <?php echo $translations["dashboard"]; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="shortcut icon" href="../assets/img/brand/favicon.png" type="image/x-icon">
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
        .dsh-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 16px; }
        @media (max-width: 991px) { .dsh-stats { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 575px) { .dsh-stats { grid-template-columns: 1fr; } }

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

        /* Alsó kártyák */
        .dsh-card {
            background: #fff; border: 1px solid var(--d-line); border-radius: 18px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .06); overflow: hidden; margin-bottom: 16px;
        }
        .dsh-card-head { display: flex; align-items: center; gap: 10px; padding: 16px 18px; border-bottom: 1px solid var(--d-line); }
        .dsh-card-head i { color: var(--d-accent); font-size: 18px; }
        .dsh-card-head h4 { margin: 0; font-size: 16px; font-weight: 800; color: var(--d-ink); }
        .dsh-card-body { padding: 18px; }

        /* QR kártya */
        .dsh-qr { text-align: center; }
        .dsh-qr img.dsh-qr-img { max-width: 240px; width: 100%; border-radius: 12px; }
        .dsh-qr .google-wallet { max-width: 200px; width: 100%; margin-top: 12px; }
        .dsh-qr .lead { color: var(--d-muted); }

        /* Nyitvatartás lista */
        .dsh-hours-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; border-top: 1px solid var(--d-line); font-size: 14px; }
        .dsh-hours-row:first-child { border-top: none; }
        .dsh-hours-row strong { color: var(--d-ink); }
        .dsh-pill { display: inline-flex; align-items: center; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 999px; }
        .dsh-pill-open { background: #dcfce7; color: #15803d; }
        .dsh-pill-closed { background: #fee2e2; color: #b91c1c; }
        .dsh-pill-exc { background: #fef3c7; color: #b45309; }
        .dsh-hours-sub { padding: 9px 16px; background: #f8fafc; font-size: 12px; color: var(--d-muted); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; border-top: 1px solid var(--d-line); }
        .dsh-hours-row .dsh-exc-label i { color: #d97706; margin-right: 6px; }

        /* Tranzakciók */
        .dsh-card-head .dsh-alllink { margin-left: auto; font-size: 13px; font-weight: 700; color: var(--d-accent); text-decoration: none; white-space: nowrap; }
        .dsh-card-head .dsh-alllink:hover { text-decoration: underline; }
        .dsh-tx-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 13px 18px; border-top: 1px solid var(--d-line); }
        .dsh-tx-row:first-child { border-top: none; }
        .dsh-tx-name { font-weight: 700; color: var(--d-ink); }
        .dsh-tx-date { font-size: 12px; color: var(--d-muted); margin-top: 2px; }
        .dsh-tx-date i { color: var(--d-accent); }
        .dsh-tx-right { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; justify-content: flex-end; }
        .dsh-tx-amount { font-weight: 800; color: var(--d-ink); }
        .dsh-pill i { font-size: 11px; }
        .dsh-tx-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 30px 16px; color: var(--d-muted); }
        .dsh-tx-empty i { font-size: 30px; opacity: .5; }

        /* Bérlet státusz */
        .dsh-mem-ticket { font-size: 18px; font-weight: 800; color: var(--d-ink); }
        .dsh-mem-sub { font-size: 12px; color: var(--d-muted); margin-top: 2px; }
        .dsh-progress { height: 10px; background: #eef2f7; border-radius: 999px; overflow: hidden; margin: 14px 0 10px; }
        .dsh-progress > span { display: block; height: 100%; background: linear-gradient(90deg, #0950dc, #2f73f0); border-radius: 999px; }
        .dsh-mem-row { display: flex; align-items: center; justify-content: space-between; font-size: 13px; padding: 6px 0; border-top: 1px solid var(--d-line); }
        .dsh-mem-row:first-of-type { border-top: none; }
        .dsh-mem-row .k { color: var(--d-muted); }
        .dsh-mem-row .v { font-weight: 700; color: var(--d-ink); }
        .dsh-mem-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 24px 0; color: var(--d-muted); text-align: center; }
        .dsh-mem-empty i { font-size: 30px; opacity: .5; }

        /* Edzés összegzés */
        .dsh-wsum { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .dsh-wsum .wbox { background: #f8fafc; border: 1px solid var(--d-line); border-radius: 14px; padding: 14px; text-align: center; }
        .dsh-wsum .wbox.full { grid-column: 1 / -1; }
        .dsh-wsum .wbox i { color: var(--d-accent); font-size: 18px; margin-bottom: 4px; display: block; }
        .dsh-wsum .wval { font-size: 22px; font-weight: 800; color: var(--d-ink); }
        .dsh-wsum .wlbl { font-size: 11px; color: var(--d-muted); font-weight: 600; margin-top: 2px; }
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
                <a class="navbar-brand" href=""><img width="70px" src="../assets/img/logo.png" alt="Logo"></a>
            </div>
            <div class="collapse navbar-collapse" id="myNavbar">
                <ul class="nav navbar-nav">
                    <li class="active"><a href=""><i class="bi bi-house"></i>
                            <?php echo $translations["mainpage"]; ?></a></li>
                    <li><a href="stats/"><i class="bi bi-graph-up"></i> <?php echo $translations["statspage"]; ?></a>
                    </li>
                    <li><a href="profile/"><i class="bi bi-person-badge"></i>
                            <?php echo $translations["profilepage"]; ?></a></li>
                    <li><a href="invoices/"><i class="bi bi-receipt"></i>
                            <?php echo $translations["invoicepage"]; ?></a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row content">
            <div class="col-sm-2 sidenav hidden-xs text-center">
                <h2><img src="../assets/img/brand/logo.png" width="105px" alt="Logo"></h2>
                <p class="lead mb-4 fs-4"><?php echo $business_name ?></p>
                <ul class="nav nav-pills nav-stacked">
                    <li class="sidebar-item active">
                        <a class="sidebar-link" href="#">
                            <i class="bi bi-house"></i> <?php echo $translations["mainpage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="stats/">
                            <i class="bi bi-graph-up"></i> <?php echo $translations["statspage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="profile/">
                            <i class="bi bi-person-badge"></i> <?php echo $translations["profilepage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="invoices/">
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
                            <div class="dsh-stat-icon dsh-ic-blue"><i class="bi bi-ticket-perforated-fill"></i></div>
                            <div>
                                <div class="dsh-stat-label"><?php echo $translations["currentticket"]; ?></div>
                                <div class="dsh-stat-value"><?php echo !empty($ticketname) ? htmlspecialchars($ticketname) : '—'; ?></div>
                            </div>
                        </div>
                        <div class="dsh-stat">
                            <div class="dsh-stat-icon dsh-ic-green"><i class="bi bi-calendar-check-fill"></i></div>
                            <div>
                                <div class="dsh-stat-label"><?php echo $translations["lastworkout"]; ?></div>
                                <div class="dsh-stat-value"><?php echo htmlspecialchars($latest_training); ?></div>
                            </div>
                        </div>
                        <div class="dsh-stat">
                            <div class="dsh-stat-icon dsh-ic-amber"><i class="bi bi-hourglass-split"></i></div>
                            <div>
                                <div class="dsh-stat-label"><?php echo $translations["remainingdays"]; ?></div>
                                <div class="dsh-stat-value"><?php echo $daysRemaining; ?><span class="dsh-unit"><?php echo $translations["day"]; ?></span></div>
                            </div>
                        </div>
                        <div class="dsh-stat">
                            <div class="dsh-stat-icon dsh-ic-violet"><i class="bi bi-wallet2"></i></div>
                            <div>
                                <div class="dsh-stat-label"><?php echo $translations["profilebalance"]; ?></div>
                                <div class="dsh-stat-value"><?php echo number_format((float) $profile_balance, 0, ',', '.'); ?><span class="dsh-unit"><?php echo $currency; ?></span></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- QR belépőkártya -->
                        <div class="col-sm-4">
                            <div class="dsh-card">
                                <div class="dsh-card-head">
                                    <i class="bi bi-qr-code"></i>
                                    <h4><?php echo $translations["dashboard"] ?? 'Belépőkártya'; ?></h4>
                                </div>
                                <div class="dsh-card-body dsh-qr">
                                    <?php
                                    if (file_exists($filename)) {
                                        echo "<img class='dsh-qr-img' src='../assets/img/logincard/{$userid}.png' alt='{$firstname}-{$lastname}-{$userid}'>";
                                    } else {
                                        echo "<h2 class='lead'>{$translations["qrgenerateing"]}</h2>";
                                    }
                                    ?>
                                    <div>
                                        <a href="pkpass.php" target="_blank">
                                            <img src="../assets/img/brand/wallet/<?php echo $lang_code; ?>_add_to_google_wallet_add-wallet-badge.png"
                                                alt="<?= $lang_code; ?>_add_to_google_wallet_add-wallet-badge"
                                                class="img img-fluid google-wallet">
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Nyitvatartás -->
                        <div class="col-sm-5">
                            <?php if (!empty($days)): ?>
                                <div class="dsh-card">
                                    <div class="dsh-card-head">
                                        <i class="bi bi-clock"></i>
                                        <h4><?= $translations["openhourspage"] ?? "Nyitvatartás"; ?></h4>
                                    </div>
                                    <div>
                                        <?php foreach ($days as $day): ?>
                                            <div class="dsh-hours-row">
                                                <strong><?= htmlspecialchars($dayNames[$day['day']]) ?></strong>
                                                <?php if (is_null($day['open_time']) && is_null($day['close_time'])): ?>
                                                    <span class="dsh-pill dsh-pill-closed"><?= $translations["closed"]; ?></span>
                                                <?php else: ?>
                                                    <span class="dsh-pill dsh-pill-open">
                                                        <?= date('H:i', strtotime($day['open_time'])) ?> - <?= date('H:i', strtotime($day['close_time'])) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php if (!empty($exceptions)): ?>
                                            <div class="dsh-hours-sub"><?= $translations["special-opentime"]; ?></div>
                                            <?php foreach ($exceptions as $ex): ?>
                                                <?php
                                                $date = new DateTime($ex['date']);
                                                $monthName = $months[(int) $date->format('n')];
                                                $day = $date->format('j');
                                                ?>
                                                <div class="dsh-hours-row">
                                                    <span class="dsh-exc-label"><i class="bi bi-calendar-event"></i><strong><?= $monthName . ' ' . $day . '.' ?></strong></span>
                                                    <?php if ($ex['is_closed']): ?>
                                                        <span class="dsh-pill dsh-pill-closed"><?= $translations["closed"]; ?></span>
                                                    <?php else: ?>
                                                        <span class="dsh-pill dsh-pill-exc">
                                                            <?= date('H:i', strtotime($ex['open_time'])) ?> - <?= date('H:i', strtotime($ex['close_time'])) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Bérlet státusz -->
                        <div class="col-sm-3">
                            <div class="dsh-card">
                                <div class="dsh-card-head">
                                    <i class="bi bi-card-checklist"></i>
                                    <h4><?php echo $translations["currentticket"] ?? 'Bérlet'; ?></h4>
                                </div>
                                <div class="dsh-card-body">
                                    <?php if ($validTicketFound && !empty($ticketname)): ?>
                                        <div class="dsh-mem-ticket"><?php echo htmlspecialchars($ticketname); ?></div>
                                        <div class="dsh-mem-sub"><?php echo $translations["remainingdays"]; ?>: <strong><?php echo $daysRemaining; ?></strong> <?php echo $translations["day"]; ?></div>
                                        <div class="dsh-progress"><span style="width: <?php echo (int) $ticketPercent; ?>%"></span></div>
                                        <div class="dsh-mem-row">
                                            <span class="k"><?php echo $translations["buytime"] ?? 'Vásárolva'; ?>:</span>
                                            <span class="v"><?php echo htmlspecialchars($buydate); ?></span>
                                        </div>
                                        <div class="dsh-mem-row">
                                            <span class="k"><?php echo $translations["expiredate"] ?? 'Lejár'; ?></span>
                                            <span class="v"><?php echo htmlspecialchars($expiredate); ?></span>
                                        </div>
                                        <?php if (!is_null($opportunities)): ?>
                                            <div class="dsh-mem-row">
                                                <span class="k"><?php echo $translations["occasions"] ?? 'Alkalmak'; ?></span>
                                                <span class="v"><?php echo (int) $opportunities; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="dsh-mem-empty">
                                            <i class="bi bi-emoji-neutral"></i>
                                            <span><?php echo $translations["notickets"] ?? 'Nincs aktív bérlet'; ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Legutóbbi tranzakciók -->
                        <div class="col-sm-9">
                            <div class="dsh-card">
                                <div class="dsh-card-head">
                                    <i class="bi bi-receipt"></i>
                                    <h4><?php echo $translations["transactions"]; ?></h4>
                                    <a href="invoices/" class="dsh-alllink"><?php echo $translations["all"]; ?> <i class="bi bi-arrow-right"></i></a>
                                </div>
                                <div>
                                    <?php if (!empty($transactions)): ?>
                                        <?php foreach ($transactions as $t):
                                            $paid = (strtolower($t['status']) === 'paid');
                                            $tdate = !empty($t['created_at']) ? date('Y-m-d H:i', strtotime($t['created_at'])) : '—';
                                        ?>
                                            <div class="dsh-tx-row">
                                                <div class="dsh-tx-main">
                                                    <div class="dsh-tx-name"><?php echo htmlspecialchars($t['name']); ?></div>
                                                    <div class="dsh-tx-date"><i class="bi bi-clock-history"></i> <?php echo $tdate; ?></div>
                                                </div>
                                                <div class="dsh-tx-right">
                                                    <span class="dsh-tx-amount"><?php echo number_format((float) $t['price'], 0, ',', '.'); ?> <?php echo $currency; ?></span>
                                                    <?php if ($paid): ?>
                                                        <span class="dsh-pill dsh-pill-open"><i class="bi bi-check-circle"></i> <?php echo $translations["paid"] ?? 'Fizetve'; ?></span>
                                                    <?php else: ?>
                                                        <span class="dsh-pill dsh-pill-closed"><i class="bi bi-exclamation-circle"></i> <?php echo $translations["unpaid"] ?? 'Fizetetlen'; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="dsh-tx-empty">
                                            <i class="bi bi-inbox"></i>
                                            <span><?php echo $translations["notransactions"]; ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-3">
                            <div class="dsh-card">
                                <div class="dsh-card-head">
                                    <i class="bi bi-activity"></i>
                                    <h4><?php echo $translations["workoutsummary"]; ?></h4>
                                </div>
                                <div class="dsh-card-body">
                                    <div class="dsh-wsum">
                                        <div class="wbox">
                                            <i class="bi bi-trophy"></i>
                                            <div class="wval"><?php echo (int) $workout_count; ?></div>
                                            <div class="wlbl"><?php echo $translations["totalworkouts"]; ?></div>
                                        </div>
                                        <div class="wbox">
                                            <i class="bi bi-stopwatch"></i>
                                            <div class="wval"><?php echo (int) $workout_total_min; ?></div>
                                            <div class="wlbl"><?php echo $translations["totalminutes"]; ?></div>
                                        </div>
                                        <div class="wbox full">
                                            <i class="bi bi-calendar-month"></i>
                                            <div class="wval"><?php echo (int) $workout_month; ?></div>
                                            <div class="wlbl"><?php echo $translations["thismonth"]; ?></div>
                                        </div>
                                    </div>
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

                            <a href="logout.php" type="button" class="btn btn-danger" style="padding: 8px 25px;">
                                <i class="bi bi-check-circle" style="margin-right: 5px;"></i>
                                <?php echo $translations["confirm"]; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPTS! -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>

</html>