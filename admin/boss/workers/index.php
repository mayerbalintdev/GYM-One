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

$alerts_html = '';

$sql = "SELECT userid, Firstname, Lastname, username, is_boss FROM workers";
$result = $conn->query($sql);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_user"])) {
    $firstname = $_POST["firstname"];
    $lastname = $_POST["lastname"];
    $username = $_POST["username"];
    $password = $_POST["password"];
    $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Jelszó hash-elése
    $is_boss = isset($_POST["is_boss"]) ? 1 : 0;

    $userid = mt_rand(1000000000, 9999999999);

    $sql = "INSERT INTO workers (userid, Firstname, Lastname, username, password_hash, is_boss)
            VALUES ($userid, '$firstname', '$lastname', '$username', '$hashed_password', $is_boss)";

    if ($conn->query($sql) === TRUE) {
        $alerts_html .= "<div class='alert alert-success'>{$translations["success-add"]}</div>";
        header("Refresh:2");
    } else {
        $alerts_html .= "<div class='alert alert-danger'>Hiba történt a felhasználó hozzáadása közben: " . $conn->error . "</div>";
    }
}

// FELHASZNÁLÓ TÖRLÉSE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_user"])) {
    $userid = $_POST["userid"];

    if ($userid != 9999999999) {
        $sql = "DELETE FROM workers WHERE userid = $userid";

        if ($conn->query($sql) === TRUE) {
            $alerts_html .= "<div class='alert alert-success'>{$translations["success-delete"]}</div>";
            header("Refresh:2");
        } else {
            $alerts_html .= "<div class='alert alert-danger'>Hiba történt a felhasználó törlése közben: " . $conn->error . "</div>";
        }
    } else {
        $alerts_html .= "<div class='alert alert-warning'> {$translations["cant-delete-main"]}</div>";
        header("Refresh:2");

    }
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
                    <li class="active"><a href="#"><?php echo $translations["mainpage"]; ?></a></li>
                    <li><a href="#section2">Age</a></li>
                    <li><a href="#section3">Gender</a></li>
                    <li><a href="#section3">Geo</a></li>
                    <li><a href="../global"><?php echo $translations["businessedit"]; ?></a></li>
                </ul><br>
            </div>
            <br>
            <div class="col-sm-10">
                <div class="d-none topnav d-sm-inline-block">
                    <a href="https://gymoneglobal.com/discord" class="btn btn-primary mx-1" target="_blank"
                        rel="noopener noreferrer">
                        <i class="bi bi-question-circle"></i>
                        <?php echo $translations["support"];?>
                    </a>

                    <a href="https://gymoneglobal.com/docs" class="btn btn-danger" target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-journals"></i>
                        <?php echo $translations["docs"];?>
                    </a>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#logoutModal">
                    <?php echo $translations["logout"];?>
                    </button>
                </div>
                <div class="row">
                    <div class="col-sm-12">
                        <?php echo $alerts_html; ?>
                        <div class="card shadow">
                            <div class="card-body">

                                <?php
                                if ($stmt->num_rows > 0) {
                                    $stmt->bind_result($is_boss);
                                    $stmt->fetch();

                                    if ($is_boss == 1) {
                                        ?>
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th scope="col"><?php echo $translations["firstname"]; ?></th>
                                                    <th scope="col"><?php echo $translations["lastname"]; ?></th>
                                                    <th scope="col"><?php echo $translations["username"]; ?></th>
                                                    <th scope="col"><?php echo $translations["position"];?></th>
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
                                        <?php
                                    } else {
                                        echo $translations["dont-access"];
                                    }
                                } else {
                                    echo "Users do not exist!";
                                }
                                ?>

                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="card">
                                    <div class="card-body">
                                        <?php
                                        if ($stmt->num_rows > 0) {
                                            $stmt->bind_result($is_boss);
                                            $stmt->fetch();

                                            if ($is_boss == 1) {
                                                ?>
                                                <form method="post"
                                                    action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <input type="text" class="form-control" name="firstname"
                                                                placeholder="<?php echo $translations["firstname"]; ?>" required>
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
                                                        <label class="form-check-label" for="is_boss"><?php echo $translations["isboss-or-not"];?></label>
                                                    </div>
                                                    <button type="submit" class="btn btn-primary" name="add_user"><?php echo $translations["register"]; ?></button>
                                                </form>
                                                <?php
                                            } else {
                                                echo $translations["dont-access"];
                                            }
                                        } else {
                                            echo "Users do not exist!";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <footer class="footer">
                <div class="container-fluid">
                    <p class="mb-0 py-2 text-center text-body-secondary">
                        Powered by <a href="https://azuriom.com" target="_blank" rel="noopener noreferrer">Azuriom</a> © 2019-2024. Panel designed by <a href="https://adminkit.io/" target="_blank" rel="noopener noreferrer">AdminKit</a>.                    </p>
                </div>
            </footer>
            </div>
        </div>
    </div>

    <!-- EXIT MODAL -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <p class="lead"><?php echo $translations["exit-modal"]; ?></p>
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
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>

</html>