<?php
session_start();

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

/**
 * Feltöltött profilkép mentése négyzetre vágva, PNG-ként a megadott útvonalra.
 * A rendszer a profilképeket fájlként tárolja: assets/img/profiles/{userid}.png
 * (nincs DB-mező hozzá). Hiba esetén false, a regisztrációt nem akasztja meg.
 */
function save_profile_photo($fileKey, $destPath)
{
  if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
    return false;
  }

  $tmp = $_FILES[$fileKey]['tmp_name'];
  if (!is_uploaded_file($tmp)) {
    return false;
  }

  // Max 8 MB
  if (($_FILES[$fileKey]['size'] ?? 0) > 8 * 1024 * 1024) {
    return false;
  }

  // Valódi kép-e?
  $info = @getimagesize($tmp);
  if ($info === false) {
    return false;
  }
  $mime = $info['mime'] ?? '';

  $dir = dirname($destPath);
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }

  // GD nélküli tartalék: csak PNG-t fogadunk el, azt egy az egyben átmozgatjuk.
  if (!function_exists('imagecreatetruecolor')) {
    if ($mime === 'image/png') {
      return @move_uploaded_file($tmp, $destPath);
    }
    return false;
  }

  switch ($mime) {
    case 'image/jpeg':
      $src = @imagecreatefromjpeg($tmp);
      break;
    case 'image/png':
      $src = @imagecreatefrompng($tmp);
      break;
    case 'image/webp':
      $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false;
      break;
    case 'image/gif':
      $src = @imagecreatefromgif($tmp);
      break;
    default:
      return false;
  }

  if (!$src) {
    return false;
  }

  $w = imagesx($src);
  $h = imagesy($src);

  // Középre igazított négyzet-kivágás
  $side = min($w, $h);
  $sx = (int) (($w - $side) / 2);
  $sy = (int) (($h - $side) / 2);

  $target = 512;
  $dst = imagecreatetruecolor($target, $target);

  // Átlátszóság megőrzése (PNG)
  imagealphablending($dst, false);
  imagesavealpha($dst, true);
  $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
  imagefilledrectangle($dst, 0, 0, $target, $target, $transparent);

  imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $target, $target, $side, $side);

  $ok = imagepng($dst, $destPath);

  imagedestroy($src);
  imagedestroy($dst);

  return $ok;
}

/**
 * Ellenőrzi, hogy érvényes képet töltöttek-e fel (mentés nélkül).
 * A profilkép kötelező, ezért a regisztráció előtt ezzel validálunk.
 */
function is_valid_uploaded_image($fileKey)
{
  if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
    return false;
  }
  $tmp = $_FILES[$fileKey]['tmp_name'];
  if (!is_uploaded_file($tmp)) {
    return false;
  }
  if (($_FILES[$fileKey]['size'] ?? 0) > 8 * 1024 * 1024) {
    return false;
  }
  $info = @getimagesize($tmp);
  if ($info === false) {
    return false;
  }
  $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
  return in_array($info['mime'] ?? '', $allowed, true);
}

$alerts_html = "";

require_once '../vendor/autoload.php'; // COMPOSER!

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

$host = $_SERVER['HTTP_HOST'];

$domain_url = $protocol . $host;

$env_data = read_env_file('../.env');

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
$smtp_password = $env_data['MAIL_PASSWORD'] ?? '';
$smtp_port = $env_data['MAIL_PORT'] ?? '';
$smtp_username = $env_data["MAIL_USERNAME"] ?? '';
$smtp_encryption = $env_data['MAIL_ENCRYPTION'] ?? '';
$smtp_host = $env_data['MAIL_HOST'] ?? '';
$autoaccept = $env_data['AUTOACCEPT'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
  die("A nyelvi fájl nem található: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $firstname = $_POST['firstname'];
  $lastname = $_POST['lastname'];
  $email = $_POST['email'];
  $password = $_POST['password'];
  $confirm_password = $_POST['confirm_password'];
  $gender = $_POST['gender'];
  $birthdate = $_POST['birthdate'];
  $city = $_POST['city'];
  $street = $_POST['street'];
  $house_number = $_POST['house_number'];
  if ($password !== $confirm_password) {
    $alerts_html .= '<div class="alert alert-danger">' . $translations["twopasswordnot"] . '</div>';
    header("Refresh: 5");
  } elseif (!is_valid_uploaded_image('profile_photo')) {
    $alerts_html .= '<div class="alert alert-danger">' . ($translations["profilepicturerequired"] ?? 'A profilkép feltöltése kötelező!') . '</div>';
    header("Refresh: 5");
  } else {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $userid = rand(pow(10, 9), pow(10, 10) - 1);

    if ($autoaccept === "TRUE") {
      $confirmed = 'YES';
    } else {
      $confirmed = 'NO';
    }


    $registration_date = date('Y-m-d H:i:s');

    $conn = new mysqli($db_host, $db_username, $db_password, $db_name);

    if ($conn->connect_error) {
      die("Kapcsolódási hiba: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("INSERT INTO users (userid, firstname, lastname, email, password, gender, birthdate, city, street, house_number, registration_date, confirmed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($stmt === false) {
      die("Hiba az előkészített állítás létrehozása során: " . $conn->error);
    }

    $stmt->bind_param("isssssssssss", $userid, $firstname, $lastname, $email, $hashed_password, $gender, $birthdate, $city, $street, $house_number, $registration_date, $confirmed);

    $ConfirmEmailPage_PLACEHOLDER = str_replace("{business_name}", $business_name, $translations["confirmemailpage"]);
    $replacements = [
      "{business_name}" => $business_name,
      "{first_name}" => $firstname
    ];
    $ConfirmEmailHeader_PLACEHOLDER = strtr($translations["confirmemailheader"], $replacements);
    $ConfirmEmailFooterWhy_PLACEHOLDER = str_replace("{business_name}", $business_name, $translations["confirmemailfooterwhy"]);


    if ($stmt->execute()) {
      // Profilkép mentése (opcionális) – assets/img/profiles/{userid}.png
      $photo_saved = save_profile_photo('profile_photo', __DIR__ . '/../assets/img/profiles/' . $userid . '.png');

      $alerts_html .= '<div class="alert alert-success">Sikeres regisztráció!</div>';
      header("Refresh: 5");
      $transport = (new Swift_SmtpTransport($smtp_host, $smtp_port, $smtp_encryption))
        ->setUsername($smtp_username)
        ->setPassword($smtp_password);

      $mailer = new Swift_Mailer($transport);

      $successEmailContent = <<<EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <style type="text/css">
    body, p, div { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; }
    body { color: #222222; background-color: #f8f9fa; margin: 0; padding: 0; }
    a { color: #0950DC; text-decoration: none; }
    .cta-button { display: inline-block; background: linear-gradient(135deg, #0950DC, #0742B8); color: white; text-decoration: none; padding: 16px 32px; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 12px rgba(9,80,220,0.3); }
    .tips-section { background: #f8f9fa; padding: 24px; border-radius: 8px; margin: 32px 0; }
    .tip-item { color: #6B7280; margin-bottom: 8px; padding-left: 20px; position: relative; }
    .tip-item:before { content: "•"; color: #0950DC; font-weight: bold; position: absolute; left: 0; }
    .footer { background: #f8f9fa; padding: 24px 30px; text-align: center; color: #6B7280; font-size: 12px; }
  </style>
</head>
<body>
  <center>
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
      <tr>
        <td>
          <table width="680" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#FFFFFF">
            <tr>
              <td align="center" style="padding:40px 30px 20px;">
                <img src="{$domain_url}/assets/img/brand/logo.png" alt="GYM Logo" style="max-width:200px; height:auto;" />
              </td>
            </tr>
            <tr>
              <td style="padding:0 30px 30px; text-align:center;">
                <h1 style="color:#333333; font-size:28px; font-weight:700; margin-bottom:16px;">{$ConfirmEmailHeader_PLACEHOLDER}</h1>
                <p style="color:#6B7280; font-size:16px; margin-bottom:32px;">{$translations["confirmemailheadertext"]}</p>
                <a href="{$domain_url}/register/confirm.php?userid={$userid}" class="cta-button">{$translations["regconfirmbtn"]}</a>
                <div style="margin:20px 0;">
                  <a href="{$domain_url}" style="color:#0950DC; font-size:14px;">{$translations["confirmemailorlogin"]} →</a>
                </div>
                <div class="tips-section">
                  <h2 style="color:#333333; font-size:18px; font-weight:600; margin-bottom:16px;">{$translations["confirmemailfirst"]}</h2>
                  <div class="tip-item">{$translations["confirmemailtipone"]}</div>
                  <div class="tip-item">{$translations["confirmemailtiptwo"]}</div>
                </div>
              </td>
            </tr>
            <tr>
              <td class="footer">
                <p>{$ConfirmEmailFooterWhy_PLACEHOLDER}</p>
                <p style="font-size:10px; color:#D1D5DB; display:flex; align-items:center; justify-content:center; gap:8px;">
                  <span>⚡</span>
                  <span>Engineered with <span style="color:#ef4444;">♥</span> by <a href="https://gymoneglobal.com" style="color:#0950DC;">GYM One</a></span>
                  <span>⚡</span>
                </p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </center>
</body>
</html>
EOD;


      $recipientEmail = $email;
      $subject = $translations["confirmemailmailsub"];

      $isRegistrationSuccessful = true;

      if ($isRegistrationSuccessful) {
        $message = (new Swift_Message($subject))
          ->setFrom(["{$smtp_username}" => "{$ConfirmEmailPage_PLACEHOLDER}"])
          ->setTo([$recipientEmail])
          ->setBody($successEmailContent, 'text/html');
      }
      $result = $mailer->send($message);
      header("Refresh: 5");
    }

    $stmt->close();
    $conn->close();
  }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
  <meta charset="UTF-8">
  <title><?php echo $business_name; ?> - <?php echo $translations["register"]; ?></title>
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/css/login-register.css">
  <link rel="shortcut icon" href="../assets/img/brand/favicon.png" type="image/x-icon">
  <style>
    /* Profilkép feltöltő – scoped */
    .reg-avatar-wrap { display: flex; flex-direction: column; align-items: center; margin-bottom: 1.25rem; }
    .reg-avatar-input { display: none; }
    .reg-avatar {
      position: relative; width: 118px; height: 118px; border-radius: 50%;
      background: #f1f5fb; border: 2px dashed #b9cdf5; cursor: pointer;
      display: flex; align-items: center; justify-content: center; overflow: hidden;
      transition: border-color .15s, box-shadow .15s, transform .15s; margin: 0;
    }
    .reg-avatar:hover { border-color: #0950dc; box-shadow: 0 10px 26px rgba(9, 80, 220, .18); transform: translateY(-2px); }
    .reg-avatar.has-img { border-style: solid; border-color: #0950dc; background: #fff; }
    .reg-avatar-img { width: 100%; height: 100%; object-fit: cover; display: none; }
    .reg-avatar.has-img .reg-avatar-img { display: block; }
    .reg-avatar.has-img .reg-avatar-placeholder { display: none; }
    .reg-avatar-placeholder { color: #6f8bc4; display: flex; flex-direction: column; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; }
    .reg-avatar-placeholder svg { width: 30px; height: 30px; }
    .reg-avatar-edit {
      position: absolute; right: 6px; bottom: 6px; width: 30px; height: 30px; border-radius: 50%;
      background: linear-gradient(135deg, #0950dc, #2f73f0); color: #fff;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 6px 14px rgba(9, 80, 220, .35); opacity: 0; transition: opacity .15s;
    }
    .reg-avatar:hover .reg-avatar-edit, .reg-avatar.has-img .reg-avatar-edit { opacity: 1; }
    .reg-avatar-edit svg { width: 14px; height: 14px; }
    .reg-avatar-hint { margin-top: .55rem; font-size: 12px; color: #8a93a3; }
    .reg-req { color: #ef4444; font-weight: 700; }
    .reg-avatar.is-invalid { border-style: solid; border-color: #ef4444; background: #fef2f2; }
    .reg-avatar-error { color: #ef4444; font-size: 12px; margin-top: .35rem; display: none; }
    .reg-avatar-remove {
      margin-top: .35rem; font-size: 12px; color: #ef4444; background: none; border: none;
      cursor: pointer; display: none; padding: 0;
    }
    .reg-avatar.has-img ~ .reg-avatar-remove { display: inline-block; }

    /* ===== Teljes oldal modern kinézet (scoped: #register) ===== */
    #register {
      min-height: 100vh;
      background: linear-gradient(160deg, #eef3fc 0%, #f7f9fd 45%, #ffffff 100%);
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      padding-bottom: 60px;
    }
    #register .reg-card,
    #register .card {
      border: none;
      border-radius: 24px;
      box-shadow: 0 30px 70px rgba(15, 23, 42, .12);
      overflow: hidden;
      background: #fff;
    }
    #register .card-body { padding: 2.4rem 2.2rem; }
    @media (max-width: 575px) { #register .card-body { padding: 1.6rem 1.3rem; } }

    /* Fejléc */
    #register .reg-head { text-align: center; margin-bottom: 1.4rem; }
    #register .reg-logo { max-width: 150px; height: auto; margin-bottom: .9rem; }
    #register .reg-title { font-weight: 800; color: #0f172a; font-size: 1.7rem; margin: 0; }
    #register .reg-sub { color: #64748b; font-size: .98rem; margin-top: .3rem; margin-bottom: 0; }

    /* Mezők */
    #register label { font-size: .85rem; font-weight: 600; color: #475569; margin-bottom: .3rem; }
    #register .form-control {
      border: 1.5px solid #e2e8f0;
      border-radius: 13px;
      padding: .65rem .9rem;
      font-size: .98rem;
      background: #f8fafc;
      color: #0f172a;
      transition: border-color .15s, box-shadow .15s, background .15s;
      height: auto;
      box-shadow: none;
    }
    #register .form-control:focus {
      border-color: #0950dc;
      background: #fff;
      box-shadow: 0 0 0 4px rgba(9, 80, 220, .12);
      outline: none;
    }
    #register select.form-control {
      appearance: none; -webkit-appearance: none; -moz-appearance: none;
      background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%2364748b' stroke-width='1.6'%3E%3Cpath d='M4 6l4 4 4-4'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right .8rem center; background-size: 16px; padding-right: 2.2rem;
    }

    #register .form-row { margin-left: -8px; margin-right: -8px; }
    #register .form-row > [class*="col-"] { padding-left: 8px; padding-right: 8px; }
    #register .form-group { margin-bottom: 1rem; }

    /* Szabályzat doboz */
    #register .reg-rules-cap { font-size: .85rem; font-weight: 600; color: #475569; margin-bottom: .35rem; display: block; }
    #register .reg-rules {
      border: 1.5px solid #e2e8f0; border-radius: 14px; overflow: hidden;
      background: #f8fafc; margin-bottom: 1rem;
    }
    #register .reg-rules iframe { display: block; width: 100%; height: 180px; border: 0; background: #fff; }

    /* Checkbox */
    #register .form-check { padding-left: 0; display: flex; align-items: center; gap: .6rem; margin-bottom: 1.3rem; }
    #register .form-check-input { width: 20px; height: 20px; margin: 0; accent-color: #0950dc; flex: 0 0 auto; cursor: pointer; position: static; }
    #register .form-check-label { font-size: .9rem; color: #334155; margin: 0; cursor: pointer; }

    /* Gomb */
    #register .btn-primary {
      width: 100%;
      background: linear-gradient(135deg, #0950dc, #2f73f0);
      border: none;
      border-radius: 14px;
      padding: .8rem 1rem;
      font-weight: 700;
      font-size: 1.02rem;
      box-shadow: 0 12px 28px rgba(9, 80, 220, .32);
      transition: transform .15s, filter .15s;
    }
    #register .btn-primary:hover { filter: brightness(1.07); transform: translateY(-2px); }
    #register .btn-primary:active { transform: translateY(0); }

    /* Alsó link */
    #register .reg-foot { text-align: center; margin-top: 1.1rem; }
    #register .reg-foot small { color: #64748b; font-size: .9rem; }
    #register .reg-foot a { color: #0950dc; font-weight: 700; text-decoration: none; }
    #register .reg-foot a:hover { text-decoration: underline; }

    /* Alsó hullám kikapcsolva */
    #register-wave { display: none !important; }

    /* ====== MINIMÁL DESIGN + visszafogott háttér ====== */
    html, body { background: #f4f6fb !important; }
    body::before, body::after { display: none !important; }

    #register {
      position: relative !important;
      display: block !important;
      align-items: flex-start !important;
      justify-content: flex-start !important;
      height: auto !important;
      min-height: 100vh;
      padding: 48px 0 0 !important;
      overflow: hidden;
      background:
        radial-gradient(circle, rgba(15, 23, 42, .05) 1px, transparent 1.6px) 0 0 / 26px 26px,
        #f4f6fb !important;
    }
    #register::before, #register::after { display: none !important; }
    #register .container { margin-top: 0 !important; position: relative; z-index: 2; }
    #register .row.pt-4 { padding-top: 0 !important; }

    /* Halvány, statikus fényfoltok a háttérben */
    .reg-bg { position: absolute; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
    .reg-blob { position: absolute; border-radius: 50%; filter: blur(90px); }
    .reg-blob-1 { width: 380px; height: 380px; background: #0950dc; opacity: .10; top: -130px; left: -90px; }
    .reg-blob-2 { width: 340px; height: 340px; background: #6d28d9; opacity: .08; bottom: -120px; right: -90px; }
    .reg-blob-3 { width: 260px; height: 260px; background: #22d3ee; opacity: .06; top: 38%; right: 8%; }

    /* Letisztult kártya */
    #register .reg-card,
    #register .card {
      border: 1px solid #e9eef6 !important;
      border-radius: 18px !important;
      background: #ffffff !important;
      box-shadow: 0 8px 30px rgba(15, 23, 42, .07) !important;
    }

    /* Cím – egyszerű, tömör */
    #register .reg-title {
      color: #0f172a;
      font-size: 1.7rem;
      letter-spacing: -.3px;
    }
    #register .reg-sub { color: #94a3b8; font-size: .92rem; }

    /* Gomb – egyszínű márkakék */
    #register .btn-primary {
      background: #0950dc !important;
      box-shadow: none !important;
    }
    #register .btn-primary:hover { background: #0742b8 !important; transform: none; }

    /* GYM One copyright legalul */
    .reg-copyright {
      position: relative; z-index: 2;
      text-align: center; padding: 28px 16px 24px; margin-top: 36px;
      color: #94a3b8; font-size: .85rem;
    }
    .reg-copyright a { color: #0950dc; font-weight: 700; text-decoration: none; }
    .reg-copyright a:hover { text-decoration: underline; }
    .reg-copyright .reg-heart { color: #ef4444; }
  </style>
</head>

<body>
  <div id="register">
    <div class="reg-bg">
      <span class="reg-blob reg-blob-1"></span>
      <span class="reg-blob reg-blob-2"></span>
      <span class="reg-blob reg-blob-3"></span>
    </div>
    <div class="container">
      <div class="row justify-content-center pt-4">
        <div class="col-lg-7 col-md-9">
          <div class="card reg-card">
            <div class="card-body">
              <div class="reg-head">
                <img class="reg-logo" src="../assets/img/brand/logo.png" title="<?php echo $business_name; ?>" alt="<?php echo $business_name; ?>">
                <h1 class="reg-title"><?php echo $translations["register"]; ?></h1>
                <p class="reg-sub"><?php echo $translations["registersubtitle"] ?? ($business_name); ?></p>
              </div>
              <?php if (!empty($login_error)) : ?>
                <div class="alert alert-danger"><?php echo $login_error; ?></div>
              <?php endif; ?>
              <?php if (!empty($alerts_html)) : ?>
                <?php echo $alerts_html; ?>
              <?php endif; ?>

              <form method="POST" enctype="multipart/form-data">

                <!-- Profilkép feltöltő -->
                <div class="reg-avatar-wrap">
                  <label class="reg-avatar" id="regAvatar" for="profile_photo" title="<?php echo $translations["profilepicture"] ?? 'Profilkép'; ?>">
                    <img id="regAvatarImg" class="reg-avatar-img" src="" alt="">
                    <span class="reg-avatar-placeholder">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M10.5 8.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z" />
                        <path d="M2 4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1.172a2 2 0 0 1-1.414-.586l-.828-.828A2 2 0 0 0 9.172 2H6.828a2 2 0 0 0-1.414.586l-.828.828A2 2 0 0 1 3.172 4H2zm6 2.5a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7z" />
                      </svg>
                      <span><?php echo $translations["profilepictureadd"] ?? 'Kép hozzáadása'; ?></span>
                    </span>
                    <span class="reg-avatar-edit">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5z" />
                      </svg>
                    </span>
                  </label>
                  <input type="file" class="reg-avatar-input" id="profile_photo" name="profile_photo" accept="image/png,image/jpeg,image/webp,image/gif">
                  <button type="button" class="reg-avatar-remove" id="regAvatarRemove"><?php echo $translations["delete"] ?? 'Eltávolítás'; ?></button>
                  <div class="reg-avatar-hint"><?php echo $translations["profilepicturehintrequired"] ?? 'Profilkép (kötelező, max. 8 MB)'; ?> <span class="reg-req">*</span></div>
                  <div class="reg-avatar-error" id="regAvatarError"><?php echo $translations["profilepicturerequired"] ?? 'A profilkép feltöltése kötelező!'; ?></div>
                </div>

                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label for="firstname"><?php echo $translations["firstname"]; ?></label>
                    <input type="text" class="form-control" id="firstname" name="firstname" required>
                  </div>
                  <div class="form-group col-md-6">
                    <label for="lastname"><?php echo $translations["lastname"]; ?></label>
                    <input type="text" class="form-control" id="lastname" name="lastname" required>
                  </div>
                </div>
                <div class="form-group">
                  <label for="email"><?php echo $translations["email"]; ?></label>
                  <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label for="password"><?php echo $translations["password"]; ?></label>
                    <input type="password" class="form-control" id="password" name="password" required>
                  </div>
                  <div class="form-group col-md-6">
                    <label for="confirm_password"><?php echo $translations["password-confirm"]; ?></label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                  </div>
                </div>
                <div class="form-group">
                  <label for="gender"><?php echo $translations["gender"]; ?></label>
                  <select class="form-control" id="gender" name="gender" required>
                    <option value="Male"><?php echo $translations["boy"]; ?></option>
                    <option value="Female"><?php echo $translations["girl"]; ?></option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="birthdate"><?php echo $translations["birthday"]; ?></label>
                  <input type="date" class="form-control" id="birthdate" name="birthdate" required>
                </div>
                <div class="form-row">
                  <div class="form-group col-md-5">
                    <label for="city"><?php echo $translations["city"]; ?></label>
                    <input type="text" class="form-control" id="city" name="city" required>
                  </div>
                  <div class="form-group col-md-5">
                    <label for="street"><?php echo $translations["street"]; ?></label>
                    <input type="text" class="form-control" id="street" name="street" required>
                  </div>
                  <div class="form-group col-md-2">
                    <label for="house_number"><?php echo $translations["hause-no"]; ?></label>
                    <input type="number" class="form-control" id="house_number" name="house_number" required>
                  </div>
                </div>
                <span class="reg-rules-cap"><?php echo $translations["rulepage"] ?? 'Szabályzat'; ?></span>
                <div class="reg-rules">
                  <iframe src="../admin/boss/rule/rule.html" frameborder="0"></iframe>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault" required>
                  <label class="form-check-label" for="flexCheckDefault">
                    <?php echo $translations["acceptrules"]; ?>
                  </label>
                </div>
                <button type="submit" class="btn btn-primary"><?php echo $translations["register"]; ?></button>
              </form>
              <div class="reg-foot mt-1">
                <small><?php echo $translations["doyouhaveaccount"]; ?> <span><a href="../login/"><?php echo $translations["login"]; ?></a></span></small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="reg-copyright">
      &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($business_name); ?> &middot;
      <span><?php echo $translations["copyright"];?> <span class="reg-heart">&hearts;</span></span>
      <a href="https://gymoneglobal.com/?lang=<?php echo $lang_code; ?>" target="_blank" rel="noopener noreferrer">GYM One</a>
    </div>
  </div>
  <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
  <script>
    (function () {
      var input = document.getElementById('profile_photo');
      var avatar = document.getElementById('regAvatar');
      var img = document.getElementById('regAvatarImg');
      var removeBtn = document.getElementById('regAvatarRemove');
      var errorEl = document.getElementById('regAvatarError');
      var form = input ? input.closest('form') : null;
      var MAX = 8 * 1024 * 1024;
      var objectUrl = null;

      if (!input) return;

      input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) { reset(); return; }

        if (!/^image\//.test(file.type)) { alert('Csak képfájl tölthető fel.'); reset(); return; }
        if (file.size > MAX) { alert('A kép túl nagy (max. 8 MB).'); reset(); return; }

        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);
        img.src = objectUrl;
        avatar.classList.add('has-img');
        avatar.classList.remove('is-invalid');
        if (errorEl) errorEl.style.display = 'none';
      });

      if (removeBtn) {
        removeBtn.addEventListener('click', function () { reset(); });
      }

      // Kötelező profilkép: submit blokkolása, ha nincs kiválasztva
      if (form) {
        form.addEventListener('submit', function (e) {
          if (!input.files || input.files.length === 0) {
            e.preventDefault();
            avatar.classList.add('is-invalid');
            if (errorEl) errorEl.style.display = 'block';
            avatar.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        });
      }

      function reset() {
        input.value = '';
        if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
        img.src = '';
        avatar.classList.remove('has-img');
      }
    })();
  </script>
</body>

</html>