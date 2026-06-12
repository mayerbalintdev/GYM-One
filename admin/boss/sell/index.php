<?php
session_start();

if (!isset($_SESSION['adminuser'])) {
    header("Location: ../");
    exit();
}

$userid = $_SESSION['adminuser'];

/** .env beolvasása (\r\n + kommentek). */
function read_env_file($file_path)
{
    if (!is_readable($file_path)) return [];
    $env_data = [];
    foreach (preg_split("/\r\n|\n|\r/", (string) file_get_contents($file_path)) as $line) {
        if (trim($line) === '' || strpos(ltrim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) $env_data[trim($parts[0])] = trim($parts[1]);
    }
    return $env_data;
}

/** Külső HTTP GET timeout-tal. */
function http_get($url, $timeout = 4)
{
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true]]);
    $data = @file_get_contents($url, false, $ctx);
    return $data === false ? null : $data;
}

$env_data = read_env_file('../../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';
$currency = $env_data['CURRENCY'] ?? '';
$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';
$lang = $lang_code;

$langFile = __DIR__ . "/../../../assets/lang/{$lang}.json";
if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}
$translations = json_decode(file_get_contents($langFile), true);

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

/** Kezdőbetűk avatarhoz. */
function sp_initials($fn, $ln)
{
    $a = mb_substr(trim((string) $fn), 0, 1, 'UTF-8');
    $b = mb_substr(trim((string) $ln), 0, 1, 'UTF-8');
    $i = mb_strtoupper($a . $b, 'UTF-8');
    return $i !== '' ? $i : '?';
}

/* ============================================================
   AJAX VÉGPONTOK (HTML kimenet előtt!)
   - q      : élő névkeresés -> találati lista (HTML)
   - lookup : QR-userid ellenőrzés -> JSON
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['q'])) {
    header('Content-Type: text/html; charset=utf-8');
    $q = trim($_POST['q']);
    if (mb_strlen($q, 'UTF-8') < 1) {
        $conn->close();
        exit;
    }
    $stmt = $conn->prepare(
        "SELECT userid, firstname, lastname
           FROM users
          WHERE CONCAT(firstname, ' ', lastname) LIKE ?
          ORDER BY firstname, lastname
          LIMIT 12"
    );
    $like = '%' . $q . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        while ($r = $res->fetch_assoc()) {
            $uid = htmlspecialchars($r['userid'], ENT_QUOTES);
            $fn  = htmlspecialchars($r['firstname'], ENT_QUOTES);
            $ln  = htmlspecialchars($r['lastname'], ENT_QUOTES);
            $ini = htmlspecialchars(sp_initials($r['firstname'], $r['lastname']), ENT_QUOTES);
            echo '<button type="button" class="sp-item" data-userid="' . $uid . '" data-firstname="' . $fn . '" data-lastname="' . $ln . '">'
                . '<span class="sp-item-ava">' . $ini . '</span>'
                . '<span class="sp-item-name">' . $fn . ' ' . $ln . '</span>'
                . '<i class="bi bi-chevron-right"></i>'
                . '</button>';
        }
    } else {
        echo '<div class="sp-empty">' . htmlspecialchars($translations['user-notexist'] ?? 'Nincs találat', ENT_QUOTES) . '</div>';
    }
    $stmt->close();
    $conn->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup'])) {
    header('Content-Type: application/json; charset=utf-8');
    $id = trim($_POST['lookup']);
    if ($id === '' || !ctype_digit($id)) {
        echo json_encode(['found' => false]);
        $conn->close();
        exit;
    }
    $stmt = $conn->prepare("SELECT firstname, lastname FROM users WHERE userid = ? LIMIT 1");
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo json_encode($u
        ? ['found' => true, 'userid' => $id, 'firstname' => $u['firstname'], 'lastname' => $u['lastname']]
        : ['found' => false]);
    $conn->close();
    exit;
}

/* --- normál oldal --- */
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

$latest_version = http_get('https://api.gymoneglobal.com/latest/version.txt', 4);
$current_version = $version;
$is_new_version_available = is_string($latest_version)
    && version_compare(trim($latest_version), $current_version) > 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <title><?php echo $translations["sellpage"]; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <script src="https://unpkg.com/@zxing/browser@0.1.5"></script>
    <link rel="shortcut icon" href="https://gymoneglobal.com/assets/img/logo.png" type="image/x-icon">
</head>

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
                    <li><a href="../../dashboard"><i class="bi bi-speedometer"></i>
                            <?php echo $translations["mainpage"]; ?></a></li>
                    <li><a href="../../users"><i class="bi bi-people"></i> <?php echo $translations["users"]; ?></a></li>
                    <li><a href="../../statistics"><i class="bi bi-bar-chart"></i>
                            <?php echo $translations["statspage"]; ?></a></li>
                    <li class="active"><a href="#"><i class="bi bi-shop"></i>
                            <?php echo $translations["sellpage"]; ?></a></li>
                    <li><a href="../../invoices"><i class="bi bi-receipt"></i>
                            <?php echo $translations["invoicepage"]; ?></a></li>
                    <li><a href="../../log"><i class="bi bi-clock-history"></i>
                            <?php echo $translations["logpage"]; ?></a></li>
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
                        <a class="sidebar-link" href="../../users/">
                            <i class="bi bi-people"></i> <?php echo $translations["users"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../../statistics">
                            <i class="bi bi-bar-chart"></i> <?php echo $translations["statspage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item active">
                        <a class="sidebar-link" href="#">
                            <i class="bi bi-shop"></i> <?php echo $translations["sellpage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../../invoices/" class="sidebar-link">
                            <i class="bi bi-receipt"></i> <?php echo $translations["invoicepage"]; ?>
                        </a>
                    </li>
                    <?php if ($is_boss === 1) { ?>
                        <li class="sidebar-header"><?php echo $translations["settings"]; ?></li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../boss/mainsettings"><i class="bi bi-gear"></i>
                                <span><?php echo $translations["businesspage"]; ?></span></a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../boss/workers"><i class="bi bi-people"></i>
                                <span><?php echo $translations["workers"]; ?></span></a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../boss/packages"><i class="bi bi-box-seam"></i>
                                <span><?php echo $translations["packagepage"]; ?></span></a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../boss/hours"><i class="bi bi-clock"></i>
                                <span><?php echo $translations["openhourspage"]; ?></span></a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../boss/smtp"><i class="bi bi-envelope-at"></i>
                                <span><?php echo $translations["mailpage"]; ?></span></a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../boss/chroom"><i class="bi bi-duffle"></i>
                                <span><?php echo $translations["chroompage"]; ?></span></a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="../../boss/rule"><i class="bi bi-file-ruled"></i>
                                <span><?php echo $translations["rulepage"]; ?></span></a>
                        </li>
                    <?php } ?>
                    <li class="sidebar-header"><?php echo $translations["shopcategory"]; ?></li>
                    <li class="sidebar-item">
                        <a class="sidebar-ling" href="../../shop/tickets">
                            <i class="bi bi-ticket"></i> <span><?php echo $translations["ticketspage"]; ?></span>
                        </a>
                    </li>
                    <li class="sidebar-header"><?php echo $translations["trainersclass"]; ?></li>
                    <li><a class="sidebar-link" href="../../trainers/timetable">
                            <i class="bi bi-calendar-event"></i> <span><?php echo $translations["timetable"]; ?></span></a>
                    </li>
                    <li><a class="sidebar-link" href="../../trainers/personal">
                            <i class="bi bi-award"></i> <span><?php echo $translations["trainers"]; ?></span></a></li>
                    <li class="sidebar-header"><?php echo $translations["other-header"]; ?></li>
                    <?php if ($is_boss === 1) { ?>
                        <li class="sidebar-item">
                            <a class="sidebar-ling" href="../../updater"><i class="bi bi-cloud-download"></i>
                                <span><?php echo $translations["updatepage"]; ?></span>
                                <?php if ($is_new_version_available): ?>
                                    <span class="sidebar-badge badge"><i class="bi bi-exclamation-circle"></i></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php } ?>
                    <li class="sidebar-item">
                        <a class="sidebar-ling" href="../../log"><i class="bi bi-clock-history"></i>
                            <span><?php echo $translations["logpage"]; ?></span></a>
                    </li>
                </ul><br>
            </div>
            <br>
            <div class="col-sm-10">
                <div class="d-none topnav d-sm-inline-block">
                    <a href="https://gymoneglobal.com/discord" class="btn btn-primary mx-1" target="_blank"
                        rel="noopener noreferrer"><i class="bi bi-question-circle"></i>
                        <?php echo $translations["support"]; ?></a>
                    <a href="https://gymoneglobal.com/docs" class="btn btn-danger" target="_blank"
                        rel="noopener noreferrer"><i class="bi bi-journals"></i>
                        <?php echo $translations["docs"]; ?></a>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#logoutModal">
                        <?php echo $translations["logout"]; ?>
                    </button>
                    <h5 id="clock" style="display: inline-block; margin-bottom: 0;"></h5>
                </div>

                <div class="sellpick">
                    <div class="sp-title">
                        <span class="sp-title-icon"><i class="bi bi-shop"></i></span>
                        <div>
                            <h3><?php echo $translations["sellpage"]; ?></h3>
                            <p><?php echo $translations['sell-pick-hint'] ?? 'Válaszd ki a vásárlót QR-kóddal vagy névre keresve.'; ?></p>
                        </div>
                    </div>

                    <!-- Kiválasztott vásárló panel -->
                    <div id="sp-selected" class="sp-selected" style="display:none;">
                        <div class="sp-selected-left">
                            <div class="sp-avatar sp-avatar--clickable" id="sp-sel-ava" title="<?php echo $translations['zoom-photo'] ?? 'Kattints a nagyításhoz'; ?>">?</div>
                            <div>
                                <div class="sp-selected-cap"><?php echo $translations['selected-member'] ?? 'Kiválasztott vásárló'; ?></div>
                                <div class="sp-selected-name" id="sp-sel-name">—</div>
                                <div class="sp-verify">
                                    <button type="button" class="sp-verify-cta" id="sp-verify-cta">
                                        <i class="bi bi-hand-index-thumb-fill"></i>
                                        <?php echo $translations['verify-cta'] ?? 'Kattints a profilképre az ellenőrzéshez'; ?></button>
                                    <span class="sp-verify-done"><i class="bi bi-patch-check-fill"></i>
                                        <?php echo $translations['verify-done'] ?? 'Személy ellenőrizve'; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="sp-selected-actions">
                            <button type="button" class="sp-btn sp-btn-ghost" id="sp-clear">
                                <i class="bi bi-arrow-counterclockwise"></i>
                                <?php echo $translations['sell-change'] ?? 'Másik vásárló'; ?>
                            </button>
                            <button type="button" class="sp-btn sp-btn-check" id="sp-verify-btn">
                                <i class="bi bi-person-bounding-box"></i>
                                <?php echo $translations['verify-btn'] ?? 'Profil ellenőrzése'; ?>
                            </button>
                            <a href="#" class="sp-btn sp-btn-primary sp-disabled" id="sp-start" aria-disabled="true">
                                <i class="bi bi-cart-check"></i>
                                <?php echo $translations['sell-start'] ?? 'Eladás indítása'; ?>
                            </a>
                        </div>
                    </div>

                    <div class="row sp-cols">
                        <!-- QR -->
                        <div class="col-sm-6">
                            <div class="sp-card">
                                <div class="sp-card-head">
                                    <span class="sp-card-icon"><i class="bi bi-qr-code-scan"></i></span>
                                    <h5><?php echo $translations['qrscann'] ?? 'QR-kód beolvasása'; ?></h5>
                                </div>
                                <div id="video-container">
                                    <video id="video" autoplay playsinline muted></video>
                                    <div class="scan-frame"><span></span><span></span><span></span><span></span></div>
                                    <div id="checkmark">✔</div>
                                    <div id="error">✘</div>
                                </div>
                                <p id="result"><?php echo $translations["qrscann"] ?? 'Olvasd be a QR-kódot'; ?></p>
                            </div>
                        </div>
                        <!-- Keresés -->
                        <div class="col-sm-6">
                            <div class="sp-card">
                                <div class="sp-card-head">
                                    <span class="sp-card-icon"><i class="bi bi-search"></i></span>
                                    <h5><?php echo $translations['name-search'] ?? 'Keresés névre'; ?></h5>
                                </div>
                                <form class="sp-search" onsubmit="return false;">
                                    <i class="bi bi-search"></i>
                                    <input id="sp-q" type="search" autocomplete="off"
                                        placeholder="<?php echo $translations['name-search'] ?? 'Név...'; ?>">
                                </form>
                                <div id="sp-results"></div>
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
                                border-radius: 50%; display: flex; align-items: center; justify-content: center;">
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
                        <a href="../../logout.php" type="button" class="btn btn-danger" style="padding: 8px 25px;">
                            <i class="bi bi-check-circle" style="margin-right: 5px;"></i>
                            <?php echo $translations["confirm"]; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== STÍLUS (sellpick, narancs GYM One akcent) ===== -->
    <style>
        .sellpick{ --sp-accent:#0950dc; --sp-accent2:#096ed2; --sp-ink:#0f172a; --sp-muted:#64748b; --sp-line:rgba(15,23,42,.08); margin-top:10px; }
        .sellpick *{ box-sizing:border-box; }
        .sp-title{ display:flex; align-items:center; gap:14px; margin:6px 0 18px; }
        .sp-title-icon{ width:48px; height:48px; border-radius:14px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#dbeafe,#bfdbfe); color:var(--sp-accent2); font-size:22px; }
        .sp-title h3{ margin:0; font-weight:800; }
        .sp-title p{ margin:2px 0 0; color:var(--sp-muted); font-size:14px; }

        .sp-card{ background:#fff; border:1px solid var(--sp-line); border-radius:18px; padding:18px; box-shadow:0 10px 30px rgba(15,23,42,.06); height:100%; }
        .sp-card-head{ display:flex; align-items:center; gap:11px; margin-bottom:14px; }
        .sp-card-icon{ width:38px; height:38px; border-radius:11px; display:flex; align-items:center; justify-content:center; background:#f8fafc; color:var(--sp-accent2); font-size:18px; }
        .sp-card-head h5{ margin:0; font-weight:700; }

        #video-container{ position:relative; width:100%; aspect-ratio:4/3; border-radius:16px; overflow:hidden; background:#0b1020; border:1px solid var(--sp-line); }
        #video{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
        .scan-frame{ position:absolute; inset:0; pointer-events:none; }
        .scan-frame span{ position:absolute; width:34px; height:34px; border:3px solid rgba(9,80,220,.95); }
        .scan-frame span:nth-child(1){ top:16%; left:16%; border-right:none; border-bottom:none; border-radius:8px 0 0 0; }
        .scan-frame span:nth-child(2){ top:16%; right:16%; border-left:none; border-bottom:none; border-radius:0 8px 0 0; }
        .scan-frame span:nth-child(3){ bottom:16%; left:16%; border-right:none; border-top:none; border-radius:0 0 0 8px; }
        .scan-frame span:nth-child(4){ bottom:16%; right:16%; border-left:none; border-top:none; border-radius:0 0 8px 0; }
        #checkmark,#error{ display:none; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); font-size:62px; z-index:3; text-shadow:0 4px 16px rgba(0,0,0,.4); }
        #checkmark{ color:#22c55e; } #error{ color:#ef4444; }
        #video.scanned{ filter:brightness(.6) saturate(1.2); } #video.error{ filter:brightness(.6) sepia(1) hue-rotate(-30deg); }
        #result{ margin:12px 0 0; text-align:center; color:var(--sp-muted); font-size:14px; min-height:21px; }
        .sp-spin{ display:inline-block; width:14px; height:14px; border:2px solid #cbd5e1; border-top-color:var(--sp-accent); border-radius:50%; vertical-align:-2px; animation:sp-rot .7s linear infinite; }
        @keyframes sp-rot{ to{ transform:rotate(360deg) } }

        .sp-search{ position:relative; margin:0 0 12px; }
        .sp-search i{ position:absolute; left:16px; top:50%; transform:translateY(-50%); color:#94a3b8; }
        #sp-q{ width:100%; padding:12px 16px 12px 44px; border-radius:13px; border:1.5px solid var(--sp-line); background:#f8fafc; font-size:15px; outline:none; transition:.15s; }
        #sp-q:focus{ border-color:var(--sp-accent); background:#fff; box-shadow:0 0 0 4px rgba(9,80,220,.12); }

        #sp-results{ display:flex; flex-direction:column; gap:8px; max-height:340px; overflow:auto; }
        .sp-item{ display:flex; align-items:center; gap:12px; width:100%; text-align:left; border:1px solid var(--sp-line); background:#fff; border-radius:13px; padding:9px 12px; cursor:pointer; transition:.13s; }
        .sp-item:hover{ border-color:var(--sp-accent); background:#eff6ff; transform:translateY(-1px); }
        .sp-item-ava{ width:38px; height:38px; flex:0 0 38px; border-radius:50%; background:linear-gradient(135deg,var(--sp-accent),var(--sp-accent2)); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; }
        .sp-item-name{ flex:1; font-weight:600; color:var(--sp-ink); }
        .sp-item i{ color:#94a3b8; }
        .sp-empty{ padding:14px; text-align:center; color:var(--sp-muted); font-size:14px; background:#f8fafc; border:1px dashed var(--sp-line); border-radius:12px; }

        .sp-selected{ display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;
            background:linear-gradient(135deg,#eff6ff,#ffffff); border:1.5px solid #93c5fd; border-radius:18px; padding:16px 20px; margin-bottom:18px; box-shadow:0 12px 30px rgba(9,80,220,.12); }
        .sp-selected-left{ display:flex; align-items:center; gap:14px; }
        .sp-avatar{ width:56px; height:56px; border-radius:50%; background:linear-gradient(135deg,var(--sp-accent),var(--sp-accent2)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:800; box-shadow:0 8px 20px rgba(9,80,220,.3); position:relative; flex:0 0 56px; }
        .sp-avatar .sp-ava-img{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover; border-radius:50%; }
        .sp-avatar--clickable{ cursor:zoom-in; }
        .sp-avatar--clickable:hover{ transform:scale(1.04); transition:transform .15s; }
        .sp-ava-zoom{ position:absolute; right:-2px; bottom:-2px; width:24px; height:24px; border-radius:50%; background:var(--sp-accent); color:#fff; display:flex; align-items:center; justify-content:center; font-size:11px; border:2px solid #fff; box-shadow:0 2px 8px rgba(15,23,42,.3); z-index:2; }
        .sp-selected-cap{ font-size:12px; text-transform:uppercase; letter-spacing:.06em; color:var(--sp-muted); }
        .sp-selected-name{ font-size:20px; font-weight:800; color:var(--sp-ink); }

        /* ellenőrzés-állapot */
        .sp-verify{ margin-top:8px; font-size:14px; font-weight:700; }
        .sp-verify-cta{ display:inline-flex; align-items:center; gap:8px; border:none; cursor:pointer; background:linear-gradient(135deg,var(--sp-accent),var(--sp-accent2)); color:#fff; padding:9px 16px; border-radius:999px; box-shadow:0 8px 20px rgba(9,80,220,.3); animation:sp-cta-pulse 1.5s ease-in-out infinite; }
        .sp-verify-cta:hover{ filter:brightness(1.07); transform:translateY(-1px); }
        .sp-verify-done{ display:none; align-items:center; gap:7px; color:#15803d; }
        .sp-selected.is-verified .sp-verify-cta{ display:none; }
        .sp-selected.is-verified .sp-verify-done{ display:inline-flex; }
        @keyframes sp-cta-pulse{ 0%,100%{ box-shadow:0 8px 20px rgba(9,80,220,.28) } 50%{ box-shadow:0 10px 30px rgba(9,80,220,.6) } }
        .sp-selected:not(.is-verified) .sp-avatar::after{ content:""; position:absolute; inset:-6px; border-radius:50%; border:3px solid var(--sp-accent); animation:sp-ring 1.5s ease-out infinite; pointer-events:none; }
        @keyframes sp-ring{ 0%{ transform:scale(1); opacity:.75 } 100%{ transform:scale(1.3); opacity:0 } }

        .sp-selected-actions{ display:flex; gap:10px; flex-wrap:wrap; }
        .sp-btn{ display:inline-flex; align-items:center; gap:8px; border:none; border-radius:13px; padding:11px 20px; font-weight:700; font-size:14px; cursor:pointer; text-decoration:none; transition:.15s; }
        .sp-btn-primary{ background:linear-gradient(135deg,var(--sp-accent),var(--sp-accent2)); color:#fff; box-shadow:0 8px 20px rgba(9,80,220,.3); }
        .sp-btn-primary:hover{ filter:brightness(1.05); transform:translateY(-1px); color:#fff; }
        .sp-btn-check{ background:#e0ecff; color:var(--sp-accent); }
        .sp-btn-check:hover{ background:#cfe0ff; }
        .sp-btn.sp-disabled{ opacity:.5; cursor:not-allowed; box-shadow:none; pointer-events:auto; }
        .sp-btn.sp-disabled:hover{ transform:none; filter:none; }
        .sp-btn-ghost{ background:#f1f5f9; color:#475569; }
        .sp-btn-ghost:hover{ background:#e2e8f0; }

        /* profilkép-nagyítás (lightbox) */
        .sp-lightbox{ position:fixed; inset:0; z-index:20000; display:none; align-items:center; justify-content:center; background:rgba(2,6,23,.86); backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); padding:24px; }
        .sp-lightbox.open{ display:flex; animation:sp-fade .18s ease; }
        @keyframes sp-fade{ from{ opacity:0 } to{ opacity:1 } }
        .sp-lightbox-fig{ margin:0; text-align:center; max-width:92vw; }
        .sp-lightbox-imgwrap{ display:flex; align-items:center; justify-content:center; }
        .sp-lb-img{ max-width:86vw; max-height:64vh; border-radius:18px; border:3px solid #fff; box-shadow:0 30px 80px rgba(0,0,0,.6); background:#0b1020; }
        .sp-lb-ini{ width:200px; height:200px; border-radius:50%; background:linear-gradient(135deg,var(--sp-accent),var(--sp-accent2)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:84px; font-weight:800; border:3px solid #fff; box-shadow:0 30px 80px rgba(0,0,0,.5); }
        .sp-lightbox-name{ margin-top:16px; color:#fff; font-size:22px; font-weight:800; text-shadow:0 2px 10px rgba(0,0,0,.5); }
        .sp-lightbox-hint{ margin-top:6px; color:#cbd5e1; font-size:14px; }
        .sp-lightbox-confirm{ margin-top:18px; padding:13px 26px; font-size:15px; }
        .sp-lightbox-close{ position:fixed; top:20px; right:22px; width:46px; height:46px; border:none; border-radius:50%; background:rgba(255,255,255,.16); color:#fff; font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:.15s; }
        .sp-lightbox-close:hover{ background:rgba(255,255,255,.3); }
    </style>

    <!-- SCRIPTS! -->
    <script src="../../../assets/js/date-time.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script>
        (function () {
            'use strict';
            const T = <?php echo json_encode($translations); ?>;
            const SELF = window.location.pathname; // ugyanerre az oldalra POST-olunk
            // Profilképek: assets/img/profiles/<userid>.png — a sell oldalról ../../../assets/...
            const PROFILE_BASE = '../../../assets/img/profiles/';

            let codeReader = null, scanControls = null, scanning = false, scanLock = false;
            let searchTimer = null, searchAbort = null;
            let current = null;     // { id, fn, ln }
            let verified = false;   // KÖTELEZŐ: csak ellenőrzés után indítható az eladás

            const $video = document.getElementById('video');
            const $result = document.getElementById('result');
            const $check = document.getElementById('checkmark');
            const $err = document.getElementById('error');
            const $sel = document.getElementById('sp-selected');
            const $selAva = document.getElementById('sp-sel-ava');
            const $selName = document.getElementById('sp-sel-name');
            const $start = document.getElementById('sp-start');

            function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
            function initials(fn, ln){ return ((String(fn||'').trim()[0]||'') + (String(ln||'').trim()[0]||'')).toUpperCase() || '?'; }
            function profileSrc(id){ return PROFILE_BASE + encodeURIComponent(id) + '.png'; }

            function setVerified(v){
                verified = v;
                $sel.classList.toggle('is-verified', v);
                $start.classList.toggle('sp-disabled', !v);
                $start.setAttribute('aria-disabled', v ? 'false' : 'true');
            }

            // Vásárló kiválasztása -> panel; az eladás CSAK ellenőrzés után indítható
            function selectMember(id, fn, ln){
                current = { id: id, fn: fn, ln: ln };
                setVerified(false);
                // avatar: profilkép + kezdőbetűs fallback + nagyító jel
                $selAva.innerHTML =
                    '<span class="sp-ava-ini">' + esc(initials(fn, ln)) + '</span>' +
                    '<img class="sp-ava-img" src="' + esc(profileSrc(id)) + '" alt="" onerror="this.remove()">' +
                    '<span class="sp-ava-zoom"><i class="bi bi-zoom-in"></i></span>';
                $selName.textContent = (fn || '') + ' ' + (ln || '');
                $start.setAttribute('href', 'ticket/?userid=' + encodeURIComponent(id));
                $sel.style.display = 'flex';
                $sel.scrollIntoView({ behavior:'smooth', block:'center' });
            }

            document.getElementById('sp-clear').addEventListener('click', function(){
                $sel.style.display = 'none';
                current = null; setVerified(false);
                $video.classList.remove('scanned','error');
                $check.style.display = $err.style.display = 'none';
                scanLock = false;
                $result.textContent = T['qrscann'] || 'Olvasd be a QR-kódot';
            });

            // --- Profilkép ellenőrzés (lightbox + KÖTELEZŐ megerősítés) ---
            const lb = document.createElement('div');
            lb.className = 'sp-lightbox';
            lb.innerHTML =
                '<button type="button" class="sp-lightbox-close" aria-label="Close"><i class="bi bi-x-lg"></i></button>' +
                '<figure class="sp-lightbox-fig">' +
                  '<div class="sp-lightbox-imgwrap" id="spLbWrap"></div>' +
                  '<figcaption class="sp-lightbox-name" id="spLbName"></figcaption>' +
                  '<div class="sp-lightbox-hint">' + esc(T['verify-hint'] || 'Hasonlítsd össze a fotót a vásárlóval.') + '</div>' +
                  '<button type="button" class="sp-btn sp-btn-primary sp-lightbox-confirm" id="spLbConfirm">' +
                    '<i class="bi bi-check-lg"></i> ' + esc(T['verify-confirm'] || 'Megerősítem, hogy ő az') +
                  '</button>' +
                '</figure>';
            document.body.appendChild(lb);
            const $lbWrap = lb.querySelector('#spLbWrap');
            const $lbName = lb.querySelector('#spLbName');

            function openVerify(){
                if (!current) return;
                $lbName.textContent = (current.fn || '') + ' ' + (current.ln || '');
                // alapból kezdőbetűs helyőrző; ha betölt a kép, lecseréljük a valódi fotóra
                $lbWrap.innerHTML = '<div class="sp-lb-ini">' + esc(initials(current.fn, current.ln)) + '</div>';
                const probe = new Image();
                const src = profileSrc(current.id);
                probe.onload = function(){ $lbWrap.innerHTML = '<img class="sp-lb-img" src="' + esc(src) + '" alt="">'; };
                probe.src = src;
                lb.classList.add('open');
            }
            function closeVerify(){ lb.classList.remove('open'); }

            lb.addEventListener('click', function(e){
                if (e.target === lb || e.target.closest('.sp-lightbox-close')) closeVerify();
            });
            document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && lb.classList.contains('open')) closeVerify(); });
            document.getElementById('spLbConfirm').addEventListener('click', function(){
                setVerified(true);
                closeVerify();
            });

            // avatar és "Profil ellenőrzése" gomb -> lightbox
            $selAva.addEventListener('click', openVerify);
            document.getElementById('sp-verify-btn').addEventListener('click', openVerify);
            document.getElementById('sp-verify-cta').addEventListener('click', openVerify);

            // Eladás indítása: ha még nincs ellenőrizve, ne navigáljon, hanem nyissa az ellenőrzést
            $start.addEventListener('click', function(e){
                if (!verified){
                    e.preventDefault();
                    openVerify();
                }
            });

            // Találat-kattintás (delegált)
            document.getElementById('sp-results').addEventListener('click', function(e){
                const item = e.target.closest('.sp-item');
                if (!item) return;
                selectMember(item.dataset.userid, item.dataset.firstname, item.dataset.lastname);
            });

            // Élő keresés
            document.getElementById('sp-q').addEventListener('input', function(){
                const q = this.value.trim();
                clearTimeout(searchTimer);
                const box = document.getElementById('sp-results');
                if (q.length < 2){ box.innerHTML = ''; return; }
                searchTimer = setTimeout(function(){
                    if (searchAbort) searchAbort.abort();
                    searchAbort = new AbortController();
                    fetch(SELF, {
                        method:'POST',
                        headers:{ 'Content-Type':'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ q }).toString(),
                        signal: searchAbort.signal
                    })
                    .then(r => { if(!r.ok) throw new Error('HTTP '+r.status); return r.text(); })
                    .then(html => box.innerHTML = html)
                    .catch(err => { if (err.name !== 'AbortError') { console.error(err); box.innerHTML = '<div class="sp-empty">'+esc(T['search-unavailable']||'A keresés most nem elérhető.')+'</div>'; } });
                }, 250);
            });

            // QR scan -> lookup -> kiválasztás
            async function onScan(text){
                if (scanLock) return;
                scanLock = true;
                $result.innerHTML = '<span class="sp-spin"></span> ' + esc(T['checking'] || 'Ellenőrzés...');
                try {
                    const r = await fetch(SELF, {
                        method:'POST',
                        headers:{ 'Content-Type':'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ lookup: text }).toString()
                    });
                    const d = await r.json();
                    if (d.found){
                        $video.classList.remove('error'); $video.classList.add('scanned');
                        $check.style.display = 'block'; $err.style.display = 'none';
                        $result.innerHTML = '<i class="bi bi-check-circle-fill" style="color:#16a34a"></i> ' + esc(d.firstname) + ' ' + esc(d.lastname);
                        selectMember(d.userid, d.firstname, d.lastname);
                    } else {
                        $video.classList.remove('scanned'); $video.classList.add('error');
                        $err.style.display = 'block'; $check.style.display = 'none';
                        $result.textContent = T['user-notexist'] || 'Nincs ilyen felhasználó';
                        setTimeout(() => { scanLock = false; }, 1500);
                    }
                } catch(e){
                    console.error(e);
                    $result.textContent = T['qr-error'] || 'Hiba a beolvasáskor';
                    setTimeout(() => { scanLock = false; }, 1500);
                }
            }

            async function startScanning(){
                if (scanning) return;
                scanning = true;
                try {
                    if (!window.ZXingBrowser || !ZXingBrowser.BrowserQRCodeReader) throw new Error('ZXingBrowser not loaded');
                    if (!codeReader) codeReader = new ZXingBrowser.BrowserQRCodeReader();
                    scanControls = await codeReader.decodeFromVideoDevice(undefined, $video, (result) => {
                        if (result && !scanLock) onScan(result.getText());
                    });
                } catch(e){
                    scanning = false;
                    console.error('Kamera hiba:', e);
                    $result.textContent = T['camera-error'] || 'A kamera nem indítható. Használd a keresőt.';
                }
            }

            $(function(){ startScanning(); });
        })();
    </script>
</body>

</html>