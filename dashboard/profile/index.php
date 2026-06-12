<?php
session_start();

require_once '../../vendor/autoload.php'; // COMPOSER!


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
$smtp_host = $env_data['MAIL_HOST'] ?? '';
$smtp_port = $env_data['MAIL_PORT'] ?? '';
$smtp_encryption = $env_data['MAIL_ENCRYPTION'] ?? '';
$smtp_username = $env_data['MAIL_USERNAME'] ?? '';
$smtp_password = $env_data['MAIL_PASSWORD'] ?? '';

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

$alerts_html = '';


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profilePicture'])) {
  $file = $_FILES['profilePicture'];

  $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

  $image = null;
  switch ($fileExtension) {
    case 'png':
      $image = imagecreatefrompng($file['tmp_name']);
      break;
    case 'jpg':
    case 'jpeg':
      $image = imagecreatefromjpeg($file['tmp_name']);
      break;
    case 'gif':
      $image = imagecreatefromgif($file['tmp_name']);
      break;
    default:
      echo "<div class='alert alert-danger'>" . $translations["onlypng"] . "</div>";
      exit;
  }

  if ($image !== null) {
    $targetDir = '../../assets/img/profiles/';
    $targetFile = $targetDir . $userid . '.png';

    if (file_exists($targetFile)) {
      unlink($targetFile);
    }

    if (imagepng($image, $targetFile)) {
      $alerts_html .= '<div class="alert alert-success" role="alert">
                                    ' . $translations["success-update"] . '
                                </div>';
      header("Refresh:2");
    } else {
      $alerts_html .= '<div class="alert alert-danger" role="alert">
                                    ' . $translations["unexpected-error"] . '
                                </div>';
      header("Refresh:2");
    }
    imagedestroy($image);
  }
}

$sql = "SELECT firstname, lastname, email FROM users WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userid);
$stmt->execute();
$stmt->bind_result($firstname, $lastname, $mail);
$stmt->fetch();

$stmt->close();
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$domain_url = $protocol . $host;



if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['userid'])) {
  if (isset($_POST['currentPassword'], $_POST['newPassword'], $_POST['confirmPassword'])) {
    $userid = $_SESSION['userid'];
    $change_date = date("Y-m-d H:i:s");
    $currentPassword = $_POST['currentPassword'];
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];

    if ($newPassword !== $confirmPassword) {
      $alerts_html .= '<div class="alert alert-warning" role="alert">
                            ' . $translations["twopasswordnot"] . '
                        </div>';
      header("Refresh:2");
    } else {
      $sql = "SELECT password FROM users WHERE userid = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("i", $userid);
      $stmt->execute();
      $stmt->store_result();
      $stmt->bind_result($hashedPassword);
      $stmt->fetch();

      if (!password_verify($currentPassword, $hashedPassword)) {
        $alerts_html .= '<div class="alert alert-danger" role="alert">
                                    ' . $translations["error-old-password"] . '
                                </div>';
        header("Refresh:2");
      } else {
        $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateSql = "UPDATE users SET password = ? WHERE userid = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("si", $newHashedPassword, $userid);

        $PasswordEmailHeaderTwo_PLACEHOLDER = str_replace("{business_name}", $business_name, $translations["passwordemailheadertwo"]);

        $replacements = [
          "{business_name}" => $business_name,
          "{user_email}" => $mail
        ];
        $PasswordEmailWhy_PLACEHOLDER = strtr($translations["passwordemailwhy"], $replacements);

        if ($updateStmt->execute()) {
          $alerts_html .= '<div class="alert alert-success" role="alert">
                                    ' . $translations["success-new-password"] . '
                                </div>';
          $transport = (new Swift_SmtpTransport($smtp_host, $smtp_port, $smtp_encryption))
            ->setUsername($smtp_username)
            ->setPassword($smtp_password);

          $mailer = new Swift_Mailer($transport);

          $editedcontent = <<<EOD
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!--
    Recommended preheader: Your password was successfully changed on {{change_date}}. If this wasn't you, secure your account immediately.
    -->
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; background-color: #f8f9fa; }
        .email-container { max-width: 680px; margin: 0 auto; background: white; }
        .header { padding: 40px 30px 20px; text-align: center; }
        .logo { max-width: 200px; height: auto; }
        .content { padding: 0 30px 30px; }
        .success-badge { background: #ECFDF5; color: #059669; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; margin-bottom: 20px; }
        .hero-title { color: #222; font-size: 24px; font-weight: 700; margin-bottom: 16px; text-align: center; }
        .subtitle { color: #6B7280; font-size: 16px; text-align: center; margin-bottom: 32px; }
        .user-info { background: #f8f9fa; padding: 16px; border-radius: 8px; margin: 20px 0; }
        .user-email { color: #222; font-weight: 600; margin-bottom: 8px; }
        .change-date { color: #6B7280; font-size: 14px; }
        .security-alert { background: #FEF3C7; border: 1px solid #F59E0B; padding: 20px; border-radius: 8px; margin: 24px 0; }
        .alert-title { color: #92400E; font-weight: 700; font-size: 16px; margin-bottom: 12px; }
        .alert-text { color: #92400E; font-size: 14px; line-height: 1.5; margin-bottom: 16px; }
        .cta-button { 
            display: inline-block; 
            background: linear-gradient(135deg, #0950DC, #0742B8); 
            color: white; 
            text-decoration: none; 
            padding: 14px 28px; 
            border-radius: 8px; 
            font-weight: 600; 
            font-size: 14px; 
            text-align: center; 
            box-shadow: 0 4px 12px rgba(9, 80, 220, 0.3);
        }
        .tips-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 32px 0; }
        .tips-title { color: #222222; font-size: 16px; font-weight: 600; margin-bottom: 16px; }
        .tip-item { color: #6B7280; margin-bottom: 8px; padding-left: 20px; position: relative; font-size: 14px; }
        .tip-item:before { content: "•"; color: #0950DC; font-weight: bold; position: absolute; left: 0; }
        .confirmation-text { background: #ECFDF5; border: 1px solid #10B981; padding: 16px; border-radius: 8px; margin: 20px 0; text-align: center; }
        .confirmation-message { color: #059669; font-weight: 600; }
        .footer { background: #f8f9fa; padding: 24px 30px; text-align: center; color: #6B7280; font-size: 12px; }
        .footer a { color: #0950DC; text-decoration: none; }
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
                            <span class="success-badge">✓ {$translations["passwordemailbadge"]}</span>
                        </div>
                        <h1 class="hero-title">{$translations["passwordemailheaderone"]}</h1>
                        <p class="subtitle">{$PasswordEmailHeaderTwo_PLACEHOLDER}</p>
                        
                        <div class="user-info">
                            <div class="user-email">{$translations["passwordemailaccount"]}: {$mail}</div>
                            <div class="change-date">{$translations["passwordemailchangedate"]} {$change_date}</div>
                        </div>
                        
                        <div class="confirmation-text">
                            <div class="confirmation-message">{$translations["passwordemailconfirmation"]}</div>
                        </div>
                        
                        <div class="security-alert">
                            <div class="alert-title">⚠️ {$translations["passwordemaildidntchange"]}</div>
                            <p class="alert-text">
                              {$translations["passwordemailalert"]}
                            </p>
                        </div>
                        <div class="tips-section">
                            <h2 class="tips-title">{$translations["passwordemailtipheader"]}</h2>
                            <div class="tip-item">{$translations["passwordemailtipone"]}</div>
                        </div>
                    </div>
                    
                    <div class="footer">
                        <p>{$PasswordEmailWhy_PLACEHOLDER}</p>
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


          $message = (new Swift_Message($translations["passwordedited"]))
            ->setFrom(["{$smtp_username}" => "$PasswordEmailHeaderTwo_PLACEHOLDER"])
            ->setTo([$mail => '{$firstname}'])
            ->setBody($editedcontent, 'text/html');

          $result = $mailer->send($message);
          header("Refresh:2");
        } else {
          $alerts_html .= '<div class="alert alert-danger" role="alert">
                                    ' . $translations["error-old-password"] . '
                                </div>';
          header("Refresh:2");
        }

        $updateStmt->close();
      }

      $stmt->close();
    }
  } else {
    $alerts_html .= '<div class="alert alert-danger" role="alert">
                        ' . $translations["unexpected-error"] . '
                    </div>';
    header("Refresh:2");
  }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {

  $sql = "DELETE FROM users WHERE userid = ?";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $userid);

  if ($stmt->execute()) {
    session_unset();
    session_destroy();
    header("Location: ../../");
    exit();
  } else {
    echo "" . $translations["unexpected-error"] . ": " . $stmt->error;
  }
}


$conn->close();

// Profilkép elérési útja + monogram az identitás-fejléchez
$profilePicPath = '../../assets/img/profiles/' . $userid . '.png';
$hasProfilePic = file_exists($profilePicPath);
$initials = strtoupper(mb_substr((string) $lastname, 0, 1) . mb_substr((string) $firstname, 0, 1));
?>


<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
  <meta charset="UTF-8">
  <title><?php echo $business_name; ?> - <?php echo $translations["profilepage"]; ?></title>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../../assets/css/dashboard.css">
  <link rel="shortcut icon" href="../../assets/img/brand/favicon.png" type="image/x-icon">
  <style>
    /* ====== Modern profil (scoped: .dsh) ====== */
    .dsh { --d-accent: #0950dc; --d-accent2: #2f73f0; --d-ink: #0f172a; --d-muted: #64748b; --d-line: rgba(15, 23, 42, .08); }
    .dsh * { box-sizing: border-box; }

    .dsh-welcome {
      display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
      background: linear-gradient(135deg, #0950dc, #2f73f0); color: #fff; border-radius: 20px;
      padding: 22px 26px; margin-bottom: 22px; box-shadow: 0 16px 40px rgba(9, 80, 220, .28);
    }
    .dsh-logout {
      display: inline-flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, .16);
      color: #fff; border: 1px solid rgba(255, 255, 255, .35); border-radius: 12px;
      padding: 9px 18px; font-weight: 700; cursor: pointer; transition: .15s; text-decoration: none;
    }
    .dsh-logout:hover { background: rgba(255, 255, 255, .26); color: #fff; }

    .dsh-welcome-hi { font-size: 13px; text-transform: uppercase; letter-spacing: .08em; opacity: .85; }
    .dsh-welcome-name { font-size: 24px; font-weight: 800; margin-top: 2px; }

    .dsh-card { background: #fff; border: 1px solid var(--d-line); border-radius: 18px; box-shadow: 0 10px 28px rgba(15, 23, 42, .06); overflow: hidden; margin-bottom: 16px; }
    .dsh-card-head { display: flex; align-items: center; gap: 10px; padding: 16px 18px; border-bottom: 1px solid var(--d-line); }
    .dsh-card-head i { color: var(--d-accent); font-size: 18px; }
    .dsh-card-head h4 { margin: 0; font-size: 16px; font-weight: 800; color: var(--d-ink); }
    .dsh-card-body { padding: 18px; }
    .dsh-card.dsh-danger { border-color: #fecaca; }
    .dsh-card.dsh-danger .dsh-card-head { border-bottom-color: #fecaca; }
    .dsh-card.dsh-danger .dsh-card-head i { color: #dc2626; }

    .dsh label, .dsh .form-label { font-size: 13px; font-weight: 600; color: var(--d-muted); margin-bottom: .35rem; display: block; }
    .dsh .form-control { width: 100%; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 10px 14px; font-size: 14px; background: #f8fafc; color: var(--d-ink); height: auto; box-shadow: none; transition: border-color .15s, box-shadow .15s, background .15s; }
    .dsh .form-control:focus { border-color: var(--d-accent); background: #fff; box-shadow: 0 0 0 4px rgba(9, 80, 220, .12); outline: none; }
    .dsh .form-group { margin-bottom: 14px; }
    .dsh .form-text { color: var(--d-muted); }

    .dsh .btn { border-radius: 12px; padding: 10px 18px; font-weight: 700; font-size: 14px; border: none; display: inline-flex; align-items: center; gap: 7px; transition: background .15s, transform .15s; }
    .dsh .btn-primary { background: var(--d-accent); color: #fff; }
    .dsh .btn-primary:hover, .dsh .btn-primary:focus { background: #0742b8; color: #fff; transform: translateY(-1px); }
    .dsh .btn-danger { background: #dc2626; color: #fff; }
    .dsh .btn-danger:hover, .dsh .btn-danger:focus { background: #b91c1c; color: #fff; transform: translateY(-1px); }
    .dsh .btn-block, .dsh .w-100 { width: 100%; justify-content: center; }

    .dsh-profilepic { display: flex; flex-direction: column; align-items: center; gap: 12px; margin-bottom: 12px; }
    .dsh-profilepic img { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #fff; box-shadow: 0 8px 24px rgba(15, 23, 42, .15); }
    .dsh-profilepic .dsh-pp-empty { width: 120px; height: 120px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #f1f5fb; color: #9bb2e0; font-size: 40px; border: 2px dashed #c3d4f3; }

    .dsh-col { display: flex; flex-direction: column; }
    .dsh-equal { display: flex; flex-direction: column; height: 100%; }
  </style></head>

<body>
  <nav class="navbar navbar-inverse visible-xs">
    <div class="container-fluid">
      <div class="navbar-header">
        <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#myNavbar">
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </button>
        <a class="navbar-brand" href=""><img src="../../assets/img/logo.png" width="70px" alt="Logo"></a>
      </div>
      <div class="collapse navbar-collapse" id="myNavbar">
        <ul class="nav navbar-nav">
          <li><a href="../"><i class="bi bi-house"></i> <?php echo $translations["mainpage"]; ?></a></li>
          <li><a href="../stats/"><i class="bi bi-graph-up"></i> <?php echo $translations["statspage"]; ?></a></li>
          <li class="active"><a href=""><i class="bi bi-person-badge"></i>
              <?php echo $translations["profilepage"]; ?></a></li>
          <li><a href="../invoices/"><i class="bi bi-receipt"></i> <?php echo $translations["invoicepage"]; ?></a></li>
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
          <li class="sidebar-item active">
            <a class="sidebar-link" href="">
              <i class="bi bi-person-badge"></i> <?php echo $translations["profilepage"]; ?>
            </a>
          </li>
          <li class="sidebar-item">
            <a class="sidebar-link" href="../invoices/">
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

          <?php echo $alerts_html; ?>

          <div class="row">
            <!-- Profilkép -->
            <div class="col-sm-4 dsh-col">
              <div class="dsh-card dsh-equal">
                <div class="dsh-card-head">
                  <i class="bi bi-image"></i>
                  <h4><?php echo $translations["select-upload-profile"]; ?></h4>
                </div>
                <div class="dsh-card-body">
                  <form id="uploadForm" action="" method="POST" enctype="multipart/form-data">
                    <div class="dsh-profilepic">
                      <?php if ($hasProfilePic): ?>
                        <img id="ppPreview" src="<?php echo $profilePicPath; ?>" alt="User">
                      <?php else: ?>
                        <div class="dsh-pp-empty" id="ppEmpty"><i class="bi bi-person"></i></div>
                        <img id="ppPreview" src="" alt="" style="display:none;">
                      <?php endif; ?>
                    </div>
                    <div class="form-group">
                      <input type="file" class="form-control" id="profilePicture" name="profilePicture"
                        accept=".png,.jpg,.jpeg,.gif" required>
                      <div id="fileHelp" class="form-text"><small><?php echo $translations["onlypng"]; ?></small></div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block"><i class="bi bi-save"></i>
                      <?php echo $translations["upload"]; ?></button>
                  </form>
                </div>
              </div>
            </div>

            <!-- Jelszó módosítás -->
            <div class="col-sm-4 dsh-col">
              <div class="dsh-card dsh-equal">
                <div class="dsh-card-head">
                  <i class="bi bi-shield-lock"></i>
                  <h4><?php echo $translations["newpassword"]; ?></h4>
                </div>
                <div class="dsh-card-body">
                  <form id="passwordChangeForm" method="POST" action="">
                    <div class="form-group">
                      <label for="currentPassword" class="form-label"><?php echo $translations["curpassword"]; ?></label>
                      <input type="password" class="form-control" id="currentPassword" name="currentPassword" required>
                    </div>
                    <div class="form-group">
                      <label for="newPassword" class="form-label"><?php echo $translations["newpassword"]; ?></label>
                      <input type="password" class="form-control" id="newPassword" name="newPassword" required>
                    </div>
                    <div class="form-group">
                      <label for="confirmPassword" class="form-label"><?php echo $translations["password-confirm"]; ?></label>
                      <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block"><i class="bi bi-save"></i>
                      <?php echo $translations["save"]; ?></button>
                  </form>
                </div>
              </div>
            </div>

            <!-- E-mail módosítás + Fiók törlése -->
            <div class="col-sm-4 dsh-col">
              <div class="dsh-card">
                <div class="dsh-card-head">
                  <i class="bi bi-envelope-at"></i>
                  <h4><?php echo $translations["newemailaddress"]; ?></h4>
                </div>
                <div class="dsh-card-body">
                  <form action="" method="post">
                    <div class="form-group">
                      <label for="newemail" class="form-label"><?php echo $translations["newemailaddress"]; ?></label>
                      <input type="email" class="form-control" id="newemail" name="newemail" required>
                    </div>
                    <div class="form-group">
                      <label for="password" class="form-label"><?php echo $translations["curpassword"]; ?></label>
                      <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block"><i class="bi bi-save"></i>
                      <?php echo $translations["save"]; ?></button>
                  </form>
                </div>
              </div>

              <div class="dsh-card dsh-danger">
                <div class="dsh-card-head">
                  <i class="bi bi-exclamation-octagon"></i>
                  <h4><?php echo $translations["deleteuser"]; ?></h4>
                </div>
                <div class="dsh-card-body">
                  <button type="button" class="btn btn-danger btn-block" data-toggle="modal" data-target="#DeleteModal">
                    <i class="bi bi-trash"></i> <?php echo $translations["deleteuser"]; ?>
                  </button>
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

              <a href="../logout.php" type="button" class="btn btn-danger" style="padding: 8px 25px;">
                <i class="bi bi-check-circle" style="margin-right: 5px;"></i>
                <?php echo $translations["confirm"]; ?>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- DELETE MODAL -->
    <div class="modal fade" id="DeleteModal" tabindex="-1" role="dialog" aria-labelledby="DeleteModalLabel"
      aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <form method="POST">
            <div class="modal-header">
              <h5 class="modal-title lead" id="deleteAccountModalLabel">
                <?php echo $translations["deleteuser"]; ?>
              </h5>
            </div>
            <div class="modal-body">
              <p>
                <?php echo $translations["areyousuredelete"]; ?>
              </p>
              <pre tabindex="0">
<li><?php echo $translations["deletemodalfirst"]; ?></li>
<li><?php echo $translations["deletemodalsecond"]; ?></li>
<li><?php echo $translations["deletemodalthree"]; ?></li>
<li><?php echo $translations["deletemodalfour"]; ?></li>
<li><?php echo $translations["deletemodalfive"]; ?></li>
</pre>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">
                <?php echo $translations["not-yet"]; ?>
              </button>
              <button type="submit" name="delete_user" class="btn btn-danger">
                <?php echo $translations["delete"]; ?>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- SCRIPTS! -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script>
      // Profilkép élő előnézet kiválasztáskor
      (function () {
        var input = document.getElementById('profilePicture');
        if (!input) return;
        input.addEventListener('change', function () {
          var file = input.files && input.files[0];
          if (!file || !/^image\//.test(file.type)) return;
          var url = URL.createObjectURL(file);
          var img = document.getElementById('ppPreview');
          var empty = document.getElementById('ppEmpty');
          if (img) { img.src = url; img.style.display = 'block'; }
          if (empty) { empty.style.display = 'none'; }
        });
      })();
    </script>
</body>

</html>