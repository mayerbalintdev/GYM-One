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

$env_data = read_env_file('../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';
$currency = $env_data['CURRENCY'] ?? '';


$lang = $lang_code;

$langDir = __DIR__ . "/../../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}

$sql = "SELECT firstname, lastname FROM users WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userid);
$stmt->execute();
$stmt->bind_result($firstname, $lastname);
$stmt->fetch();

$stmt->close();

$sql = "SELECT id, name, price, created_at, status, route FROM invoices WHERE userid = $userid";
$result = $conn->query($sql);

$conn->close();
?>


<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <title><?php echo $business_name; ?> - <?php echo $translations["invoicepage"]; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="shortcut icon" href="../../assets/img/brand/favicon.png" type="image/x-icon">
    <style>
        /* ====== Modern számlák (scoped: .dsh) ====== */
        .dsh { --d-accent: #0950dc; --d-ink: #0f172a; --d-muted: #64748b; --d-line: rgba(15, 23, 42, .08); }
        .dsh * { box-sizing: border-box; }

        .dsh-welcome {
            display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
            background: linear-gradient(135deg, #0950dc, #2f73f0); color: #fff; border-radius: 20px;
            padding: 22px 26px; margin-bottom: 22px; box-shadow: 0 16px 40px rgba(9, 80, 220, .28);
        }
        .dsh-welcome-hi { font-size: 13px; text-transform: uppercase; letter-spacing: .08em; opacity: .85; }
        .dsh-welcome-name { font-size: 24px; font-weight: 800; margin-top: 2px; }
        .dsh-logout {
            display: inline-flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, .16);
            color: #fff; border: 1px solid rgba(255, 255, 255, .35); border-radius: 12px;
            padding: 9px 18px; font-weight: 700; cursor: pointer; transition: .15s; text-decoration: none;
        }
        .dsh-logout:hover { background: rgba(255, 255, 255, .26); color: #fff; }

        .dsh-card { background: #fff; border: 1px solid var(--d-line); border-radius: 18px; box-shadow: 0 10px 28px rgba(15, 23, 42, .06); overflow: hidden; }
        .dsh-card-head { display: flex; align-items: center; gap: 10px; padding: 16px 18px; border-bottom: 1px solid var(--d-line); }
        .dsh-card-head i { color: var(--d-accent); font-size: 18px; }
        .dsh-card-head h4 { margin: 0; font-size: 16px; font-weight: 800; color: var(--d-ink); }
        .dsh-card-head .dsh-count { margin-left: auto; font-size: 12px; font-weight: 700; color: var(--d-accent); background: #eef4ff; padding: 4px 12px; border-radius: 999px; }

        /* Táblázat */
        .dsh-table-wrap { overflow-x: auto; }
        .dsh-table { width: 100%; border-collapse: collapse; }
        .dsh-table thead th {
            text-align: left; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em;
            color: var(--d-muted); padding: 12px 18px; background: #f8fafc; border-bottom: 1px solid var(--d-line); white-space: nowrap;
        }
        .dsh-table tbody td { padding: 14px 18px; border-bottom: 1px solid var(--d-line); font-size: 14px; color: var(--d-ink); vertical-align: middle; }
        .dsh-table tbody tr:last-child td { border-bottom: none; }
        .dsh-table tbody tr:hover { background: #f9fbff; }
        .dsh-table .dsh-id { color: var(--d-muted); font-weight: 700; }
        .dsh-table .dsh-name { font-weight: 700; }
        .dsh-table .dsh-price { font-weight: 800; white-space: nowrap; }
        .dsh-table .dsh-date { color: var(--d-muted); white-space: nowrap; }

        .dsh-pill { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 999px; }
        .dsh-pill i { font-size: 11px; }
        .dsh-pill-paid { background: #dcfce7; color: #15803d; }
        .dsh-pill-unpaid { background: #fee2e2; color: #b91c1c; }

        .dsh-actions { display: flex; align-items: center; gap: 8px; }
        .dsh-iconbtn {
            width: 38px; height: 38px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;
            font-size: 16px; text-decoration: none; transition: .15s; border: 1px solid transparent;
        }
        .dsh-iconbtn-view { background: #e6efff; color: #0950dc; }
        .dsh-iconbtn-view:hover { background: #0950dc; color: #fff; }
        .dsh-iconbtn-dl { background: #f1f5f9; color: #475569; }
        .dsh-iconbtn-dl:hover { background: #475569; color: #fff; }

        .dsh-empty { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 48px 16px; color: var(--d-muted); }
        .dsh-empty i { font-size: 38px; opacity: .5; }
    </style>
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
                <a class="navbar-brand" href="#"><img src="../../assets/img/logo.png" width="70px" alt="Logo"></a>
            </div>
            <div class="collapse navbar-collapse" id="myNavbar">
                <ul class="nav navbar-nav">
                    <li><a href="../"><i class="bi bi-house"></i> <?php echo $translations["mainpage"]; ?></a></li>
                    <li><a href="../stats/"><i class="bi bi-graph-up"></i> <?php echo $translations["statspage"]; ?></a>
                    </li>
                    <li><a href="../profile/"><i class="bi bi-person-badge"></i>
                            <?php echo $translations["profilepage"]; ?></a></li>
                    <li class="active"><a href=""><i class="bi bi-receipt"></i>
                            <?php echo $translations["invoicepage"]; ?></a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row content">
            <div class="col-sm-2 sidenav hidden-xs text-center">
                <h2><img src="../../assets/img/brand/logo.png" width="105px" alt="Logo"></h2>
                <p class="lead mb-4 fs-4"><?php echo $business_name ?></p>
                <ul class="nav nav-pills nav-stacked">
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../">
                            <i class="bi bi-house"></i> <?php echo $translations["mainpage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../stats/">
                            <i class="bi bi-graph-up"></i> <?php echo $translations["statspage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../profile/">
                            <i class="bi bi-person-badge"></i> <?php echo $translations["profilepage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item active">
                        <a class="sidebar-link" href="">
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

                    <!-- Számlák -->
                    <div class="dsh-card">
                        <div class="dsh-card-head">
                            <i class="bi bi-receipt"></i>
                            <h4><?php echo $translations["invoicepage"]; ?></h4>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <span class="dsh-count"><?php echo (int) $result->num_rows; ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($result && $result->num_rows > 0): ?>
                            <div class="dsh-table-wrap">
                                <table class="dsh-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th><?php echo $translations["fullname"]; ?></th>
                                            <th><?php echo $translations["invoiceprice"]; ?></th>
                                            <th><?php echo $translations["date-log"]; ?></th>
                                            <th><?php echo $translations["status"]; ?></th>
                                            <th><?php echo $translations["interact"]; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $result->fetch_assoc()):
                                            $paid = ($row["status"] === 'paid');
                                            $route = '../../assets/docs/invoices/' . $row["route"];
                                        ?>
                                            <tr>
                                                <td class="dsh-id">#<?php echo (int) $row["id"]; ?></td>
                                                <td class="dsh-name"><?php echo htmlspecialchars($row["name"]); ?></td>
                                                <td class="dsh-price"><?php echo number_format((float) $row["price"], 0, ',', '.'); ?> <?php echo $currency; ?></td>
                                                <td class="dsh-date"><?php echo htmlspecialchars($row["created_at"]); ?></td>
                                                <td>
                                                    <?php if ($paid): ?>
                                                        <span class="dsh-pill dsh-pill-paid"><i class="bi bi-check-circle"></i> <?php echo ucfirst($row["status"]); ?></span>
                                                    <?php else: ?>
                                                        <span class="dsh-pill dsh-pill-unpaid"><i class="bi bi-exclamation-circle"></i> <?php echo ucfirst($row["status"]); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="dsh-actions">
                                                        <a target="_blank" href="<?php echo htmlspecialchars($route); ?>" class="dsh-iconbtn dsh-iconbtn-view" title="<?php echo $translations["interact"]; ?>"><i class="bi bi-eye"></i></a>
                                                        <a href="<?php echo htmlspecialchars($route); ?>" class="dsh-iconbtn dsh-iconbtn-dl" download title="PDF"><i class="bi bi-download"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="dsh-empty">
                                <i class="bi bi-inbox"></i>
                                <span><?php echo $translations["youdonthaveinvoices"]; ?></span>
                            </div>
                        <?php endif; ?>
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

                            <a href="../logout.php" type="button" class="btn btn-danger" style="padding: 8px 25px;">
                                <i class="bi bi-check-circle" style="margin-right: 5px;"></i>
                                <?php echo $translations["confirm"]; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SCRIPTS! -->
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>

</html>