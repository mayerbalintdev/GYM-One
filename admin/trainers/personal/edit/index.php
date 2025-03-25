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

$env_data = read_env_file('../../../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';
$capacity = $env_data["CAPACITY"] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../../../../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}
$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}

$translations = json_decode(file_get_contents($langFile), true);

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

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $sql = "SELECT * FROM trainers WHERE id=$id";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $trainer = $result->fetch_assoc();
    } else {
        echo "Trainer not found.";
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $price_1hour = $_POST['price_1hour'];
        $price_10sessions = $_POST['price_10sessions'];

        if ($_FILES['image']['name']) {
            $target_dir = "../../../../assets/img/trainers/";
            $image_extension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
            $target_file = $target_dir . "trainer_" . $id . "." . $image_extension;

            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $sql = "UPDATE trainers SET name='$name', image='$target_file', description='$description', price_1hour='$price_1hour', price_10sessions='$price_10sessions' WHERE id=$id";
            } else {
                echo "Error uploading file.";
                exit;
            }
        } else {
            $sql = "UPDATE trainers SET name='$name', description='$description', price_1hour='$price_1hour', price_10sessions='$price_10sessions' WHERE id=$id";
        }

        if ($conn->query($sql) === TRUE) {
            header("Location: ../");
        } else {
            echo "Error updating record: " . $conn->error;
        }
    }
} else {
    echo "Invalid request.";
    exit;
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
    <link rel="stylesheet" href="../../../../assets/css/dashboard.css">
    <link rel="shortcut icon" href="https://gymoneglobal.com/assets/img/logo.png" type="image/x-icon">
</head>
<!-- ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="../../../../assets/js/tinymce/js/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    tinymce.init({
        selector: '#description',
        toolbar: 'undo redo | bold italic underline strikethrough | fontsizeselect formatselect | alignleft aligncenter alignright alignjustify | outdent indent |  numlist bullist checklist | forecolor backcolor casechange permanentpen formatpainter removeformat | pagebreak | charmap emoticons | fullscreen  preview save | insertfile image media pageembed template link anchor codesample | a11ycheck ltr rtl',
        height: 400,
        menubar: 'file edit view insert format tools table help',
        content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }'
    });
</script>

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
                    <li><a href="../../../boss/sell"><i class="bi bi-shop"></i> <?php echo $translations["sellpage"]; ?></a></li>
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
                    <li class="active"><a href="../"><i class="bi bi-award"></i> <?php echo $translations["trainers"]; ?></a></li>
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
                        <a class="sidebar-link" href="../../../users">
                            <i class="bi bi-people"></i> <?php echo $translations["users"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../../../statistics">
                            <i class="bi bi-bar-chart"></i> <?php echo $translations["statspage"]; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../../../boss/sell">
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
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../../../boss/sell">
                            <i class="bi bi-ticket"></i>
                            <span><?php echo $translations["ticketspage"]; ?></span>
                        </a>
                    </li>
                    <li class="sidebar-header">
                        <?php echo $translations["trainersclass"]; ?>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="../../../trainers/timetable">
                            <i class="bi bi-calendar-event"></i>
                            <span><?php echo $translations["timetable"]; ?></span>
                        </a>
                    </li>
                    <li class="sidebar-item active">
                        <a class="sidebar-link" href="#">
                            <i class="bi bi-award"></i>
                            <span><?php echo $translations["trainers"]; ?></span>
                        </a>
                    </li>
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
                            <p class="lead"><?php echo $translations["traineredit"]; ?></p>

                            <div class="card-body">

                                <?php
                                if ($is_boss == 1) {
                                ?>
                                    <form action="" method="POST" enctype="multipart/form-data">
                                        <div class="col-md-9">
                                            <div class="form-group">
                                                <div class="mb-3">
                                                    <label for="name" class="form-label"><?php echo $translations["fullname"]; ?></label>
                                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo $trainer['name']; ?>" required>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="mb-3">
                                                    <label for="image" class="form-label"><?php echo $translations["profileimg"]; ?> <?php echo $translations["optional"]; ?></label>
                                                    <input type="file" accept="image/png" class="form-control" id="image" name="image">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <?php if (!empty($trainer['image'])): ?>
                                                <img src="../../../../assets/img/trainers/trainer_<?php echo $trainer["id"]; ?>.png" alt="<?php echo htmlspecialchars($trainer['name']); ?>" class="img-fluid mt-3" style="max-height: 200px;">
                                            <?php endif; ?>
                                        </div>
                                        <div class="form-group">
                                            <div class="col-md-12">
                                                <div class="mb-3">
                                                    <label for="description" class="form-label"><?php echo $translations["description"]; ?></label>
                                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo $trainer['description']; ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="price_1hour" class="form-label"><?php echo $translations["price"]; ?> (1 <?php echo $translations["hour"]; ?>)</label>
                                                    <input type="number" class="form-control" id="price_1hour" name="price_1hour" value="<?php echo $trainer['price_1hour']; ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="price_10sessions" class="form-label"><?php echo $translations["price"]; ?> (10 <?php echo $translations["occasions"]; ?>)</label>
                                                    <input type="number" class="form-control" id="price_10sessions" name="price_10sessions" value="<?php echo $trainer['price_10sessions']; ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn mt-5 btn-primary"><?php echo $translations["traineredit"]; ?></button>
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
                    <a href="../../../logout.php" type="button"
                        class="btn btn-danger"><?php echo $translations["confirm"]; ?></a>
                </div>
            </div>
        </div>
    </div>

    <!-- EMAIL MODAL -->

    <!-- SCRIPTS! -->
    <script src="../../../../assets/js/date-time.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>

</html>