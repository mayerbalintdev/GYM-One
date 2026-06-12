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

if (isset($_GET['userid'])) {
    $ticketbuyerid = htmlspecialchars($_GET['userid']);
} else {
    $ticketbuyerid = 'N/A';
}

$env_data = read_env_file('../../../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';
$currency = $env_data['CURRENCY'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';

$lang = $lang_code;

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
$conn->set_charset('utf8mb4');

$sql = "DELETE FROM temp_cart";

$conn->query($sql);

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

/* Vásárló adatai a fejléchez (ID a kiválasztó oldalról érkezik) */
$buyer_firstname = '';
$buyer_lastname = '';
$buyer_found = false;
if ($ticketbuyerid !== 'N/A' && ctype_digit((string) $ticketbuyerid)) {
    $bstmt = $conn->prepare("SELECT firstname, lastname FROM users WHERE userid = ? LIMIT 1");
    $bstmt->bind_param("s", $ticketbuyerid);
    $bstmt->execute();
    $bstmt->bind_result($buyer_firstname, $buyer_lastname);
    if ($bstmt->fetch()) {
        $buyer_found = true;
    }
    $bstmt->close();
}

function tk_initials($fn, $ln)
{
    $a = mb_substr(trim((string) $fn), 0, 1, 'UTF-8');
    $b = mb_substr(trim((string) $ln), 0, 1, 'UTF-8');
    $i = mb_strtoupper($a . $b, 'UTF-8');
    return $i !== '' ? $i : '?';
}

$sql = "SELECT * FROM tickets";
$result = $conn->query($sql);

$product_stmt = $conn->prepare("SELECT * FROM products");
$product_stmt->execute();
$product_result = $product_stmt->get_result();

$message = "";

$file_path = 'https://api.gymoneglobal.com/latest/version.txt';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $file_path);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 4);
$latest_version = curl_exec($ch);
curl_close($ch);

$current_version = $version;

$is_new_version_available = is_string($latest_version)
    && version_compare(trim($latest_version), $current_version) > 0;

?>


<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <title><?php echo $translations["sellpage"]; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                        <a class="sidebar-link" href="../">
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

                <!-- ===================== MODERN JEGYELADÁS ===================== -->
                <div class="tk">

                    <!-- Vásárló-fejléc -->
                    <div class="tk-buyer">
                        <div class="tk-buyer-left">
                            <div class="tk-avatar">
                                <span class="tk-ava-ini"><?php echo htmlspecialchars(tk_initials($buyer_firstname, $buyer_lastname)); ?></span>
                                <?php if ($buyer_found): ?>
                                    <img class="tk-ava-img" src="../../../../assets/img/profiles/<?php echo htmlspecialchars($ticketbuyerid); ?>.png" alt="" onerror="this.remove()">
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="tk-buyer-cap"><?php echo $translations['selected-member'] ?? 'Kiválasztott vásárló'; ?></div>
                                <div class="tk-buyer-name">
                                    <?php echo $buyer_found ? htmlspecialchars($buyer_firstname . ' ' . $buyer_lastname) : '—'; ?>
                                </div>
                                <div class="tk-buyer-id"><i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($ticketbuyerid); ?></div>
                            </div>
                        </div>
                        <a href="../" class="tk-btn tk-btn-ghost">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            <?php echo $translations['sell-change'] ?? 'Másik vásárló'; ?>
                        </a>
                    </div>

                    <?php if (!$buyer_found): ?>
                        <div class="tk-warn">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span><?php echo $translations['sell-pick-hint'] ?? 'Nincs kiválasztott vásárló. Először válassz vásárlót a kiválasztó oldalon.'; ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Fülek -->
                    <div class="tk-tabs" role="tablist">
                        <button class="tk-tab is-active" data-tab="passes" type="button">
                            <i class="bi bi-ticket-perforated"></i>
                            <span><?php echo $translations['ticketspage'] ?? 'Bérletek'; ?></span>
                        </button>
                        <button class="tk-tab" data-tab="balance" type="button">
                            <i class="bi bi-wallet2"></i>
                            <span><?php echo $translations['customaddmoneyheader'] ?? 'Egyenleg'; ?></span>
                        </button>
                        <button class="tk-tab" data-tab="products" type="button">
                            <i class="bi bi-bag"></i>
                            <span><?php echo $translations['shopcategory'] ?? 'Termékek'; ?></span>
                        </button>
                    </div>

                    <!-- ====== BÉRLETEK / JEGYEK ====== -->
                    <section class="tk-panel is-active" id="tab-passes">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <div class="tk-grid">
                                <?php while ($row = $result->fetch_assoc()):
                                    $unlimited = !$row['expire_days'];
                                    $occ = $row['occasions'];
                                ?>
                                    <div class="tk-pass">
                                        <div class="tk-pass-top">
                                            <span class="tk-pass-icon"><i class="bi bi-ticket-perforated-fill"></i></span>
                                            <?php if ($unlimited): ?>
                                                <span class="tk-chip tk-chip-gold"><i class="bi bi-infinity"></i> <?php echo $translations["unlimited"]; ?></span>
                                            <?php else: ?>
                                                <span class="tk-chip"><i class="bi bi-calendar3"></i> <?php echo htmlspecialchars($row['expire_days']) . ' ' . $translations["day"]; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <h5 class="tk-pass-name"><?php echo htmlspecialchars($row['name']); ?></h5>
                                        <div class="tk-pass-meta">
                                            <span><i class="bi bi-repeat"></i> <?php echo $translations["tickettableoccassion"]; ?>:
                                                <b><?php echo $occ ? htmlspecialchars($occ) : '—'; ?></b></span>
                                        </div>
                                        <div class="tk-pass-price">
                                            <?php echo number_format($row['price'], 0, ',', '.'); ?>
                                            <span class="tk-cur"><?php echo $currency; ?></span>
                                        </div>
                                        <a href="../payment/?userid=<?php echo urlencode($ticketbuyerid); ?>&ticketid=<?php echo (int) $row['id']; ?>"
                                            class="tk-btn tk-btn-primary tk-pass-btn <?php echo $buyer_found ? '' : 'tk-disabled'; ?>"
                                            <?php echo $buyer_found ? '' : 'tabindex="-1" aria-disabled="true"'; ?>>
                                            <i class="bi bi-box-arrow-in-right"></i> <?php echo $translations["choose"]; ?>
                                        </a>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="tk-empty">
                                <i class="bi bi-ticket-perforated"></i>
                                <p><?php echo $translations["notickets"]; ?></p>
                            </div>
                        <?php endif; ?>
                    </section>

                    <!-- ====== EGYENLEG FELTÖLTÉS ====== -->
                    <section class="tk-panel" id="tab-balance">
                        <div class="tk-card tk-balance">
                            <div class="tk-card-head">
                                <span class="tk-card-icon"><i class="bi bi-wallet2"></i></span>
                                <h5><?php echo $translations["customaddmoneyheader"]; ?></h5>
                            </div>
                            <form method="post" action="process_balance.php" id="tk-balance-form">
                                <label class="tk-label" for="amount"><?php echo $translations["price"]; ?></label>
                                <div class="tk-amount">
                                    <input type="number" inputmode="decimal" step="1" min="1" id="amount" name="amount"
                                        class="tk-input" placeholder="<?php echo $translations['balancegiveadd']; ?>" required
                                        <?php echo $buyer_found ? '' : 'disabled'; ?>>
                                    <span class="tk-amount-cur"><?php echo $currency; ?></span>
                                </div>
                                <div class="tk-quick">
                                    <?php foreach ([1000, 2000, 5000, 10000] as $q): ?>
                                        <button type="button" class="tk-quick-btn" data-amount="<?php echo $q; ?>">+<?php echo number_format($q, 0, ',', '.'); ?></button>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" id="userid" name="userid" value="<?php echo htmlspecialchars($ticketbuyerid); ?>">
                                <button type="submit" class="tk-btn tk-btn-primary tk-block <?php echo $buyer_found ? '' : 'tk-disabled'; ?>"
                                    <?php echo $buyer_found ? '' : 'disabled'; ?>>
                                    <i class="bi bi-plus-circle"></i> <?php echo $translations["add"]; ?>
                                </button>
                                <p class="tk-note"><i class="bi bi-info-circle"></i> <?php echo $translations["profilebalanceattencion"]; ?></p>
                            </form>
                        </div>
                    </section>

                    <!-- ====== TERMÉKEK ====== -->
                    <section class="tk-panel" id="tab-products">
                        <?php if ($product_result && $product_result->num_rows > 0): ?>
                            <div class="tk-search">
                                <i class="bi bi-search"></i>
                                <input type="text" id="searchInput" class="tk-input" placeholder="<?= $translations["search"]; ?>..." oninput="searchProducts()">
                            </div>

                            <form action="cart_process.php?userid=<?php echo urlencode($ticketbuyerid); ?>" method="post" id="tk-product-form">
                                <div id="productList" class="tk-grid">
                                    <?php while ($row = $product_result->fetch_assoc()):
                                        $out = (int) $row['stock'] <= 0;
                                    ?>
                                        <div class="tk-product product-item <?php echo $out ? 'is-out' : ''; ?>"
                                            data-price="<?php echo (float) $row['price']; ?>">
                                            <div class="tk-product-img">
                                                <img src="../../../../assets/img/packageimg/<?php echo htmlspecialchars($row['barcode']); ?>.png"
                                                    alt="<?php echo htmlspecialchars($row['name']); ?>"
                                                    onerror="this.src='../../../../assets/img/logo.png';this.classList.add('tk-img-fallback')">
                                                <?php if ($out): ?><span class="tk-out-badge"><?php echo $translations['piece'] ?? 'Elfogyott'; ?>: 0</span><?php endif; ?>
                                            </div>
                                            <div class="tk-product-body">
                                                <h5 class="card-title tk-product-name"><?php echo htmlspecialchars($row['name']); ?></h5>
                                                <p class="tk-product-desc"><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                                                <div class="tk-product-foot">
                                                    <div>
                                                        <div class="tk-product-price"><?php echo number_format($row['price'], 0, ',', '.'); ?> <?php echo $currency; ?></div>
                                                        <div class="tk-product-stock"><i class="bi bi-box"></i> <?= $translations["piece"]; ?>: <b><?php echo (int) $row['stock']; ?></b></div>
                                                    </div>
                                                    <div class="tk-stepper" data-stock="<?php echo (int) $row['stock']; ?>">
                                                        <button type="button" class="tk-step tk-step-minus" aria-label="-">&minus;</button>
                                                        <input type="number" id="quantity_<?php echo (int) $row['id']; ?>"
                                                            name="quantities[<?php echo (int) $row['id']; ?>]"
                                                            class="tk-step-input" value="0" min="0" max="<?php echo (int) $row['stock']; ?>"
                                                            <?php echo $out ? 'disabled' : ''; ?>>
                                                        <button type="button" class="tk-step tk-step-plus" aria-label="+">+</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                                <div id="noResult" class="tk-empty" style="display:none;">
                                    <i class="bi bi-search"></i>
                                    <p><?php echo $translations['notickets'] ?? 'Nincs találat'; ?></p>
                                </div>

                                <input type="hidden" id="userid_p" name="userid" value="<?php echo htmlspecialchars($ticketbuyerid); ?>">

                                <!-- Lebegő összegző sáv -->
                                <div class="tk-cartbar" id="tk-cartbar">
                                    <div class="tk-cartbar-info">
                                        <span class="tk-cartbar-count" id="tk-cart-count">0 <?= $translations["piece"]; ?></span>
                                        <span class="tk-cartbar-total" id="tk-cart-total">0 <?php echo $currency; ?></span>
                                    </div>
                                    <button type="submit" class="tk-btn tk-btn-success" id="tk-cart-next" disabled>
                                        <i class="bi bi-box-arrow-in-right"></i> <?= $translations["next"]; ?>
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="tk-empty">
                                <i class="bi bi-bag-x"></i>
                                <p><?= $translations["packagepage"]; ?></p>
                            </div>
                        <?php endif; ?>
                    </section>

                </div>
                <!-- =================== /MODERN JEGYELADÁS ==================== -->

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

                            <a href="../../../logout.php" type="button" class="btn btn-danger" style="padding: 8px 25px;">
                                <i class="bi bi-check-circle" style="margin-right: 5px;"></i>
                                <?php echo $translations["confirm"]; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== STÍLUS (tk, kék GYM One akcent) ===================== -->
    <style>
        .tk {
            --tk-accent: #0950dc;
            --tk-accent2: #096ed2;
            --tk-ink: #0f172a;
            --tk-muted: #64748b;
            --tk-line: rgba(15, 23, 42, .08);
            margin-top: 10px;
            padding-bottom: 90px;
        }
        .tk * { box-sizing: border-box; }

        /* Vásárló fejléc */
        .tk-buyer {
            display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
            background: linear-gradient(135deg, #eff6ff, #ffffff);
            border: 1.5px solid #93c5fd; border-radius: 18px; padding: 16px 20px; margin-bottom: 16px;
            box-shadow: 0 12px 30px rgba(9, 80, 220, .10);
        }
        .tk-buyer-left { display: flex; align-items: center; gap: 14px; }
        .tk-avatar {
            width: 56px; height: 56px; flex: 0 0 56px; border-radius: 50%; position: relative;
            background: linear-gradient(135deg, var(--tk-accent), var(--tk-accent2)); color: #fff;
            display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 800;
            box-shadow: 0 8px 20px rgba(9, 80, 220, .3);
        }
        .tk-avatar .tk-ava-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        .tk-buyer-cap { font-size: 12px; text-transform: uppercase; letter-spacing: .06em; color: var(--tk-muted); }
        .tk-buyer-name { font-size: 20px; font-weight: 800; color: var(--tk-ink); }
        .tk-buyer-id { font-size: 13px; color: var(--tk-muted); margin-top: 2px; }
        .tk-buyer-id i { color: var(--tk-accent); }

        .tk-warn {
            display: flex; align-items: center; gap: 10px; background: #fffbeb; border: 1px solid #fde68a;
            color: #92400e; border-radius: 14px; padding: 12px 16px; margin-bottom: 16px; font-size: 14px;
        }
        .tk-warn i { font-size: 18px; }

        /* Fülek */
        .tk-tabs {
            display: flex; gap: 8px; background: #f1f5f9; padding: 6px; border-radius: 16px;
            margin-bottom: 20px; overflow-x: auto;
        }
        .tk-tab {
            flex: 1; min-width: 120px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            border: none; background: transparent; color: var(--tk-muted); font-weight: 700; font-size: 14px;
            padding: 11px 14px; border-radius: 12px; cursor: pointer; transition: .15s; white-space: nowrap;
        }
        .tk-tab:hover { color: var(--tk-ink); }
        .tk-tab.is-active { background: #fff; color: var(--tk-accent); box-shadow: 0 6px 18px rgba(15, 23, 42, .08); }

        .tk-panel { display: none; animation: tk-fade .2s ease; }
        .tk-panel.is-active { display: block; }
        @keyframes tk-fade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

        .tk-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 16px; }

        /* Bérlet kártya */
        .tk-pass {
            background: #fff; border: 1px solid var(--tk-line); border-radius: 18px; padding: 20px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .06); display: flex; flex-direction: column;
            transition: .15s;
        }
        .tk-pass:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(9, 80, 220, .14); border-color: #bfdbfe; }
        .tk-pass-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
        .tk-pass-icon {
            width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: var(--tk-accent2); font-size: 20px;
        }
        .tk-chip {
            display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 700;
            background: #eff6ff; color: var(--tk-accent); padding: 5px 10px; border-radius: 999px;
        }
        .tk-chip-gold { background: #fef3c7; color: #b45309; }
        .tk-pass-name { font-size: 18px; font-weight: 800; color: var(--tk-ink); margin: 0 0 8px; }
        .tk-pass-meta { font-size: 13px; color: var(--tk-muted); margin-bottom: 14px; }
        .tk-pass-meta i { color: var(--tk-accent); }
        .tk-pass-price { font-size: 26px; font-weight: 800; color: var(--tk-ink); margin-bottom: 16px; }
        .tk-pass-price .tk-cur { font-size: 15px; font-weight: 700; color: var(--tk-muted); }
        .tk-pass-btn { margin-top: auto; justify-content: center; }

        /* Gombok */
        .tk-btn {
            display: inline-flex; align-items: center; gap: 8px; border: none; border-radius: 13px;
            padding: 11px 20px; font-weight: 700; font-size: 14px; cursor: pointer; text-decoration: none; transition: .15s;
        }
        .tk-btn-primary { background: linear-gradient(135deg, var(--tk-accent), var(--tk-accent2)); color: #fff; box-shadow: 0 8px 20px rgba(9, 80, 220, .3); }
        .tk-btn-primary:hover { filter: brightness(1.06); transform: translateY(-1px); color: #fff; }
        .tk-btn-success { background: linear-gradient(135deg, #16a34a, #15803d); color: #fff; box-shadow: 0 8px 20px rgba(22, 163, 74, .3); }
        .tk-btn-success:hover { filter: brightness(1.06); transform: translateY(-1px); color: #fff; }
        .tk-btn-ghost { background: #f1f5f9; color: #475569; }
        .tk-btn-ghost:hover { background: #e2e8f0; color: #475569; }
        .tk-btn.tk-disabled, .tk-btn:disabled { opacity: .5; cursor: not-allowed; box-shadow: none; pointer-events: none; }
        .tk-block { width: 100%; justify-content: center; }

        /* Egyenleg */
        .tk-card { background: #fff; border: 1px solid var(--tk-line); border-radius: 18px; padding: 22px; box-shadow: 0 10px 30px rgba(15, 23, 42, .06); }
        .tk-balance { max-width: 460px; }
        .tk-card-head { display: flex; align-items: center; gap: 11px; margin-bottom: 18px; }
        .tk-card-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: #f8fafc; color: var(--tk-accent2); font-size: 19px; }
        .tk-card-head h5 { margin: 0; font-weight: 800; }
        .tk-label { font-size: 13px; font-weight: 700; color: var(--tk-muted); display: block; margin-bottom: 6px; }
        .tk-input {
            width: 100%; padding: 12px 16px; border-radius: 13px; border: 1.5px solid var(--tk-line);
            background: #f8fafc; font-size: 15px; outline: none; transition: .15s;
        }
        .tk-input:focus { border-color: var(--tk-accent); background: #fff; box-shadow: 0 0 0 4px rgba(9, 80, 220, .12); }
        .tk-amount { position: relative; }
        .tk-amount .tk-input { padding-right: 56px; }
        .tk-amount-cur { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--tk-muted); }
        .tk-quick { display: flex; gap: 8px; flex-wrap: wrap; margin: 12px 0 18px; }
        .tk-quick-btn { border: 1px solid var(--tk-line); background: #fff; color: var(--tk-accent); font-weight: 700; font-size: 13px; padding: 7px 12px; border-radius: 999px; cursor: pointer; transition: .13s; }
        .tk-quick-btn:hover { background: #eff6ff; border-color: var(--tk-accent); }
        .tk-note { margin-top: 14px; font-size: 13px; color: var(--tk-muted); display: flex; gap: 7px; align-items: flex-start; }
        .tk-note i { color: var(--tk-accent); margin-top: 2px; }

        /* Termék kereső */
        .tk-search { position: relative; max-width: 420px; margin-bottom: 18px; }
        .tk-search i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .tk-search .tk-input { padding-left: 44px; }

        /* Termék kártya */
        .tk-product { background: #fff; border: 1px solid var(--tk-line); border-radius: 18px; overflow: hidden; box-shadow: 0 10px 30px rgba(15, 23, 42, .06); display: flex; flex-direction: column; transition: .15s; }
        .tk-product:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(9, 80, 220, .12); }
        .tk-product.is-out { opacity: .65; }
        .tk-product-img { position: relative; height: 150px; background: #f8fafc; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid var(--tk-line); }
        .tk-product-img img { max-height: 130px; max-width: 80%; width: auto; object-fit: contain; }
        .tk-product-img .tk-img-fallback { opacity: .25; max-height: 80px; }
        .tk-out-badge { position: absolute; top: 10px; right: 10px; background: #fee2e2; color: #b91c1c; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 999px; }
        .tk-product-body { padding: 16px; display: flex; flex-direction: column; flex: 1; }
        .tk-product-name { font-size: 16px; font-weight: 800; color: var(--tk-ink); margin: 0 0 6px; }
        .tk-product-desc { font-size: 13px; color: var(--tk-muted); margin-bottom: 14px; flex: 1; }
        .tk-product-foot { display: flex; align-items: flex-end; justify-content: space-between; gap: 10px; }
        .tk-product-price { font-size: 18px; font-weight: 800; color: var(--tk-ink); }
        .tk-product-stock { font-size: 12px; color: var(--tk-muted); margin-top: 2px; }
        .tk-product-stock i { color: var(--tk-accent); }

        /* Stepper */
        .tk-stepper { display: inline-flex; align-items: center; border: 1.5px solid var(--tk-line); border-radius: 12px; overflow: hidden; background: #f8fafc; }
        .tk-step { width: 34px; height: 38px; border: none; background: transparent; font-size: 18px; font-weight: 700; color: var(--tk-accent); cursor: pointer; transition: .13s; }
        .tk-step:hover { background: #eff6ff; }
        .tk-step-input { width: 44px; height: 38px; border: none; border-left: 1px solid var(--tk-line); border-right: 1px solid var(--tk-line); background: #fff; text-align: center; font-weight: 700; font-size: 15px; outline: none; -moz-appearance: textfield; }
        .tk-step-input::-webkit-outer-spin-button, .tk-step-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

        /* Üres állapot */
        .tk-empty { text-align: center; padding: 50px 20px; color: var(--tk-muted); }
        .tk-empty i { font-size: 46px; opacity: .4; display: block; margin-bottom: 12px; }
        .tk-empty p { font-size: 15px; margin: 0; }

        /* Lebegő kosár-sáv */
        .tk-cartbar {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 1500;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            background: #fff; border-top: 1px solid var(--tk-line); box-shadow: 0 -8px 30px rgba(15, 23, 42, .1);
            padding: 12px 24px; transform: translateY(120%); transition: transform .25s ease;
        }
        .tk-cartbar.is-visible { transform: translateY(0); }
        .tk-cartbar-info { display: flex; flex-direction: column; }
        .tk-cartbar-count { font-size: 13px; color: var(--tk-muted); }
        .tk-cartbar-total { font-size: 22px; font-weight: 800; color: var(--tk-ink); }

        @media (max-width: 767px) {
            .tk-buyer { flex-direction: column; align-items: flex-start; }
            .tk-cartbar { padding: 10px 16px; }
            .tk-cartbar-total { font-size: 18px; }
        }
    </style>

    <?php
    $conn->close();
    ?>
    <!-- SCRIPTS! -->
    <script>
        (function () {
            'use strict';
            var CURRENCY = <?php echo json_encode($currency); ?>;
            var PIECE = <?php echo json_encode($translations["piece"] ?? 'db'); ?>;

            function fmt(n) {
                return new Intl.NumberFormat('hu-HU').format(Math.round(n));
            }

            /* ---- Fülek ---- */
            var tabs = document.querySelectorAll('.tk-tab');
            var panels = { passes: 'tab-passes', balance: 'tab-balance', products: 'tab-products' };
            tabs.forEach(function (t) {
                t.addEventListener('click', function () {
                    tabs.forEach(function (x) { x.classList.remove('is-active'); });
                    t.classList.add('is-active');
                    Object.keys(panels).forEach(function (k) {
                        document.getElementById(panels[k]).classList.toggle('is-active', k === t.dataset.tab);
                    });
                    var bar = document.getElementById('tk-cartbar');
                    if (bar) bar.classList.toggle('is-visible', t.dataset.tab === 'products' && cartHasItems());
                });
            });

            /* ---- Egyenleg gyorsgombok ---- */
            var amount = document.getElementById('amount');
            document.querySelectorAll('.tk-quick-btn').forEach(function (b) {
                b.addEventListener('click', function () {
                    if (!amount || amount.disabled) return;
                    var cur = parseInt(amount.value, 10) || 0;
                    amount.value = cur + parseInt(b.dataset.amount, 10);
                    amount.focus();
                });
            });

            /* ---- Termék stepper + összegző ---- */
            var cartbar = document.getElementById('tk-cartbar');
            var cartCount = document.getElementById('tk-cart-count');
            var cartTotal = document.getElementById('tk-cart-total');
            var cartNext = document.getElementById('tk-cart-next');

            function cartHasItems() {
                var sum = 0;
                document.querySelectorAll('.tk-step-input').forEach(function (i) { sum += (parseInt(i.value, 10) || 0); });
                return sum > 0;
            }

            function recalc() {
                var total = 0, count = 0;
                document.querySelectorAll('.product-item').forEach(function (card) {
                    var inp = card.querySelector('.tk-step-input');
                    if (!inp) return;
                    var q = parseInt(inp.value, 10) || 0;
                    var price = parseFloat(card.dataset.price) || 0;
                    total += q * price;
                    count += q;
                });
                if (cartCount) cartCount.textContent = count + ' ' + PIECE;
                if (cartTotal) cartTotal.textContent = fmt(total) + ' ' + CURRENCY;
                if (cartNext) cartNext.disabled = count === 0;
                var onProducts = document.getElementById('tab-products').classList.contains('is-active');
                if (cartbar) cartbar.classList.toggle('is-visible', count > 0 && onProducts);
            }

            function clamp(inp) {
                var max = parseInt(inp.getAttribute('max'), 10);
                var v = parseInt(inp.value, 10) || 0;
                if (v < 0) v = 0;
                if (!isNaN(max) && v > max) v = max;
                inp.value = v;
            }

            document.querySelectorAll('.tk-stepper').forEach(function (st) {
                var inp = st.querySelector('.tk-step-input');
                st.querySelector('.tk-step-minus').addEventListener('click', function () {
                    if (inp.disabled) return;
                    inp.value = (parseInt(inp.value, 10) || 0) - 1; clamp(inp); recalc();
                });
                st.querySelector('.tk-step-plus').addEventListener('click', function () {
                    if (inp.disabled) return;
                    inp.value = (parseInt(inp.value, 10) || 0) + 1; clamp(inp); recalc();
                });
                inp.addEventListener('input', function () { clamp(inp); recalc(); });
            });
            recalc();

            /* ---- Termék kereső ---- */
            window.searchProducts = function () {
                var input = document.getElementById("searchInput");
                if (!input) return;
                var filter = input.value.toLowerCase();
                var products = document.querySelectorAll(".product-item");
                var visible = 0;
                products.forEach(function (p) {
                    var name = p.querySelector(".tk-product-name").innerText.toLowerCase();
                    var show = name.indexOf(filter) > -1;
                    p.style.display = show ? "" : "none";
                    if (show) visible++;
                });
                var nr = document.getElementById('noResult');
                if (nr) nr.style.display = visible === 0 ? 'block' : 'none';
            };
        })();
    </script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script src="../../../../assets/js/date-time.js"></script>
</body>

</html>
