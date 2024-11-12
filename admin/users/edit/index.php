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

// API!
$file_path = 'https://api.gymoneglobal.com/latest/version.txt';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $file_path);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$latest_version = curl_exec($ch);
curl_close($ch);

$current_version = $version;

$alerts_html = "";

$is_new_version_available = version_compare($latest_version, $current_version) > 0;

if (isset($_GET['user']) && is_numeric($_GET['user'])) {
  $useridgymuser = $_GET['user'];

  $sql = "SELECT * FROM users WHERE userid = $useridgymuser";
  $result = $conn->query($sql);

  if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $firstname = $row['firstname'];
    $lastname = $row['lastname'];
    $email = $row['email'];
    $regdate = $row['registration_date'];
    $lastlogin = $row['lastlogin'];
    $verify = $row['confirmed'];
    $lastip = $row['lastip'];
  } else {
    echo "The user does not exist!";
    exit;
  }
} else {
  echo "Incorrect request received!";
  exit;
}


if (isset($_POST['save'])) {
  $new_firstname = $_POST['firstname'];
  $new_lastname = $_POST['lastname'];
  $new_email = $_POST['email'];

  if (empty($new_firstname) || empty($new_lastname) || empty($new_email)) {
    echo "Minden mező kitöltése kötelező.";
  } else {
    $sql_update = "UPDATE users SET firstname = '$new_firstname', lastname = '$new_lastname', email = '$new_email' WHERE userid = $useridgymuser";

    if ($conn->query($sql_update) === TRUE) {
      $alerts_html .= '<div class="alert alert-success" role="alert">
                                    ' . $translations["success-update"] . '
                                </div>';
      $action = $translations['success-edit-user'] . ' ID: ' . $useridgymuser . ' Mail: ' . $new_email;
      $actioncolor = 'warning';
      $sql = "INSERT INTO logs (userid, action, actioncolor, time) 
                            VALUES (?, ?, ?, NOW())";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("iss", $userid, $action, $actioncolor);
      $stmt->execute();
      header("Refresh:2");
      exit;
    } else {
      $alerts_html .= '<div class="alert alert-danger" role="alert">Unexpected error: ' . $conn->error . '</div>';
    }
  }
}

if (isset($_POST['delete_user'])) {
  $useridgymuser = $_POST['userid'];

  $sql = "DELETE FROM users WHERE userid = ?";

  if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $useridgymuser);
    if ($stmt->execute()) {
      $action = $translations['success-delete-user'] . ' ID: ' . $useridgymuser . ' ' . $firstname . ' ' . $lastname;
      $actioncolor = 'danger';
      $sql = "INSERT INTO logs (userid, action, actioncolor, time) 
                            VALUES (?, ?, ?, NOW())";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("iss", $userid, $action, $actioncolor);
      $stmt->execute();
      header("Location: ../");
    } else {
      $alerts_html .= '<div class="alert alert-danger" role="alert">
                                    ' . $translations["deletefail"] . '
                                </div>';
    }
    $stmt->close();
  }

  $conn->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['userid'])) {
  $useridgymuser = $_POST['userid'];

  $sql = "UPDATE users SET confirmed = 'Yes' WHERE userid = $useridgymuser";

  if ($conn->query($sql) === TRUE) {
    $alerts_html .= '<div class="alert alert-success" role="alert">
    ' . $translations["regconfirm"] . '
</div>';
    header("Refresh:2");
  } else {
    echo "Error updating record: " . $conn->error;
  }

  $conn->close();
}

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
        <a class="navbar-brand" href="#"><img src="../../../assets/img/logo.png" width="105px" alt="Logo"></a>
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
          <li class="sidebar-item">
            <a class="sidebar-link" href="#">
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
              <a class="sidebar-link" href="../boss/mainsettings">
                <i class="bi bi-gear"></i>
                <span><?php echo $translations["businesspage"]; ?></span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="../boss/workers">
                <i class="bi bi-people"></i>
                <span><?php echo $translations["workers"]; ?></span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="../boss/packages">
                <i class="bi bi-box-seam"></i>
                <span><?php echo $translations["packagepage"]; ?></span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="../boss/hours">
                <i class="bi bi-clock"></i>
                <span><?php echo $translations["openhourspage"]; ?></span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="../boss/smtp">
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
          <li class="sidebar-item">
            <a class="sidebar-link" href="../boss/smtp">
              <i class="bi bi-envelope-at"></i>
              <span>felhasznalok kezelese</span>
            </a>
          </li>
          <li><a href="#section3">Geo</a></li>
          <li class="sidebar-header"><?php echo $translations["other-header"]; ?></li>
          <?php
          if ($is_boss == 1) {
          ?>
            <li class="sidebar-item active">
              <a class="sidebar-ling" href="../updater">
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
            <a class="sidebar-ling" href="../log">
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
          <div class="col-sm-12">
            <?php echo $alerts_html; ?>
          </div>
        </div>
        <div class="row">
          <div class="col-sm-6">
            <div class="card shadow">
              <div class="card-heading">
                <h5 class="card-title"><?php echo $translations["editprofile"]; ?></h5>
              </div>
              <form method="POST">
                <div class="row">
                  <div class="col-md-9">
                    <div class="mb-3">
                      <div class="form-group">
                        <label for="firstname"><?php echo $translations["firstname"]; ?></label>
                        <input type="text" class="form-control" id="firstname" name="firstname" value="<?php echo $firstname; ?>" required>
                      </div>
                    </div>
                    <div class="mb-3">
                      <div class="form-group">
                        <label for="lastname"><?php echo $translations["lastname"]; ?></label>
                        <input type="text" class="form-control" id="lastname" name="lastname" value="<?php echo $lastname; ?>" required>
                      </div>
                    </div>
                  </div>

                  <div class="col-md-3 text-center">
                    <img src="../../../assets/img/profiles/<?php echo $useridgymuser; ?>.png" alt="User" class="img img-rounded img-fluid mb-3" height="150">
                  </div>
                </div>
                <div class="mb-3">
                  <div class="form-group">
                    <label for="email"><?php echo $translations["email"]; ?></label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required>
                  </div>

                </div>
                <button type="submit" name="save" class="btn btn-primary"><i class="bi bi-save"></i>
                  <?php echo $translations["save"]; ?></button>
                <?php
                if ($is_boss == 1) {
                ?>
                  <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#deleteModal" data-userid="1">
                    <i class="bi bi-trash"></i>
                    <?php echo $translations["deleteuserbtn"]; ?>
                  </button> <?php
                          }
                            ?>

              </form>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card card-default">
              <div class="card-heading">
                <h5 class="card-title"><?php echo $translations["userinfo"]; ?></h5>
              </div>
              <div class="card-body">
                <div class="form-group">
                  <label for="registerInput"><?php echo $translations["reg-date"]; ?></label>
                  <input type="text" class="form-control" id="registerInput" value="<?php echo $regdate; ?>" disabled>
                </div>
                <div class="form-group">
                  <label for="lastLoginInput"><?php echo $translations["last-login"]; ?></label>
                  <input type="text" class="form-control" id="lastLoginInput" value="<?php echo $lastlogin; ?>" disabled>
                </div>
                <div class="form-group">
                  <label for="emailVerifiedInput"><?php echo $translations["regconfirm"]; ?></label>
                  <form method="post">
                    <div class="input-group">
                      <input type="text" class="form-control text-danger" id="emailVerifiedInput" value="<?php echo ($verify == "Yes") ? $translations["yes"] : $translations["no"]; ?>" disabled>
                      <span class="input-group-btn">
                        <button class="btn btn-success" type="submit" <?php if ($verify == "Yes") {
                                                                        echo "disabled";
                                                                      } ?>>
                          <?php echo $translations["forceregconf"]; ?>
                        </button>
                        <input type="hidden" name="userid" value="<?php echo $useridgymuser; ?>">
                      </span>
                    </div>
                  </form>
                </div>
                <div class="form-group">
                  <label for="addressInput"><?php echo $translations["lastip"]; ?></label>
                  <input type="text" class="form-control" id="addressInput" value="<?php echo $lastip; ?>" disabled>
                </div>
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
          <a href="../../logout.php" type="button" class="btn btn-danger"><?php echo $translations["confirm"]; ?></a>
        </div>
      </div>
    </div>
  </div>
  <!-- DELETE USER MODAL -->

  <!-- Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteModalLabel"><?php echo $translations["deleteuserbtn"]; ?></h5>
        </div>
        <div class="modal-body">
          <p><?php echo $translations["undoallert"]; ?></p>
          <code><?php echo $firstname; ?> <?php echo $lastname; ?> <?php echo $translations["identifier"]; ?> <?php echo $useridgymuser; ?></code>
        </div>
        <div class="modal-footer">
          <form method="post" action="">
            <input type="hidden" name="userid" id="userid" value="<?php echo $useridgymuser; ?>">
            <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="bi bi-x-lg"></i>
              <?php echo $translations["not-yet"]; ?></button>
            <button type="submit" name="delete_user" class="btn btn-danger"><i class="bi bi-exclamation-triangle"></i>
              <?php echo $translations["delete"]; ?></button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- SCRIPTS! -->
  <script src="../../assets/js/date-time.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>

</html>