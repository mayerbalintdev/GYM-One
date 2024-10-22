<?php function read_env_file($file_path)
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
$copyright_year = date("Y");
$alerts_html = '';


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
$mailadress = $env_data['MAIL_USERNAME'] ?? '';
$phoneno = $env_data['PHONE_NO'] ?? '';
$smtp_host = $env_data['MAIL_HOST'] ?? '';
$smtp_port = $env_data['MAIL_PORT'] ?? '';
$smtp_encryption = $env_data['MAIL_ENCRYPTION'] ?? '';
$smtp_username = $env_data['MAIL_USERNAME'] ?? '';
$smtp_password = $env_data['MAIL_PASSWORD'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../assets/lang/";

$langFile = $langDir . "$lang.json";

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$domain_url = $protocol . $host;

if (!file_exists($langFile)) {
  die("A nyelvi fájl nem található: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
  die("Kapcsolódási hiba: " . $conn->connect_error);
}

$dayNames = [
  1 => $translations["Mon"],
  2 => $translations["Tue"],
  3 => $translations["Wed"],
  4 => $translations["Thu"],
  5 => $translations["Fri"],
  6 => $translations["Sat"],
  7 => $translations["Sun"]
];

$sql = "SELECT * FROM opening_hours ORDER BY day ASC";
$result = $conn->query($sql);

$days = [];
if ($result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $days[] = $row;
  }
}

require_once '../vendor/autoload.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $name = $_POST['name'];
  $userEmail = $_POST['email'];
  $userMessage = $_POST['message'];

  $transport = (new Swift_SmtpTransport($smtp_host, $smtp_port, $smtp_encryption))
    ->setUsername($smtp_username)
    ->setPassword($smtp_password);

  $mailer = new Swift_Mailer($transport);

  $adminMessage = (new Swift_Message($translations["newmessagefromwebsite"]))
    ->setFrom([$userEmail => $name])
    ->setTo([$smtp_username])
    ->setBody(
      $translations["fullname"] . ": " . $name . "\n" .
        $translations["email"] . ": " . $userEmail . "\n" .
        $translations["message"] . ": " . $userMessage . "\n"
    );

  $result = $mailer->send($adminMessage);
  $editedcontent = <<<EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html data-editor-version="2" class="sg-campaigns" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">
  <!--[if !mso]><!-->
  <meta http-equiv="X-UA-Compatible" content="IE=Edge">
  <!--<![endif]-->
  <!--[if (gte mso 9)|(IE)]>
  <xml>
    <o:OfficeDocumentSettings>
      <o:AllowPNG/>
      <o:PixelsPerInch>96</o:PixelsPerInch>
    </o:OfficeDocumentSettings>
  </xml>
  <![endif]-->
  <!--[if (gte mso 9)|(IE)]>
<style type="text/css">
body {width: 600px;margin: 0 auto;}
table {border-collapse: collapse;}
table, td {mso-table-lspace: 0pt;mso-table-rspace: 0pt;}
img {-ms-interpolation-mode: bicubic;}
</style>
<![endif]-->
  <style type="text/css">
body, p, div {
  font-family: arial,helvetica,sans-serif;
  font-size: 14px;
}
body {
  color: #000000;
}
body a {
  color: #1188E6;
  text-decoration: none;
}
p { margin: 0; padding: 0; }
table.wrapper {
  width:100% !important;
  table-layout: fixed;
  -webkit-font-smoothing: antialiased;
  -webkit-text-size-adjust: 100%;
  -moz-text-size-adjust: 100%;
  -ms-text-size-adjust: 100%;
}
img.max-width {
  max-width: 100% !important;
}
.column.of-2 {
  width: 50%;
}
.column.of-3 {
  width: 33.333%;
}
.column.of-4 {
  width: 25%;
}
ul ul ul ul  {
  list-style-type: disc !important;
}
ol ol {
  list-style-type: lower-roman !important;
}
ol ol ol {
  list-style-type: lower-latin !important;
}
ol ol ol ol {
  list-style-type: decimal !important;
}
@media screen and (max-width:480px) {
  .preheader .rightColumnContent,
  .footer .rightColumnContent {
    text-align: left !important;
  }
  .preheader .rightColumnContent div,
  .preheader .rightColumnContent span,
  .footer .rightColumnContent div,
  .footer .rightColumnContent span {
    text-align: left !important;
  }
  .preheader .rightColumnContent,
  .preheader .leftColumnContent {
    font-size: 80% !important;
    padding: 5px 0;
  }
  table.wrapper-mobile {
    width: 100% !important;
    table-layout: fixed;
  }
  img.max-width {
    height: auto !important;
    max-width: 100% !important;
  }
  a.bulletproof-button {
    display: block !important;
    width: auto !important;
    font-size: 80%;
    padding-left: 0 !important;
    padding-right: 0 !important;
  }
  .columns {
    width: 100% !important;
  }
  .column {
    display: block !important;
    width: 100% !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
  }
  .social-icon-column {
    display: inline-block !important;
  }
}
</style>
  <!--user entered Head Start--><!--End Head user entered-->
</head>
<body>
  <center class="wrapper" data-link-color="#1188E6" data-body-style="font-size:14px; font-family:arial,helvetica,sans-serif; color:#000000; background-color:#FFFFFF;">
    <div class="webkit">
      <table cellpadding="0" cellspacing="0" border="0" width="100%" class="wrapper" bgcolor="#FFFFFF">
        <tr>
          <td valign="top" bgcolor="#FFFFFF" width="100%">
            <table width="100%" role="content-container" class="outer" align="center" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td width="100%">
                  <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td>
                        <!--[if mso]>
<center>
<table><tr><td width="600">
<![endif]-->
                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px;" align="center">
                                  <tr>
                                    <td role="modules-container" style="padding:0px 0px 0px 0px; color:#000000; text-align:left;" bgcolor="#FFFFFF" width="100%" align="left"><table class="module preheader preheader-hide" role="module" data-type="preheader" border="0" cellpadding="0" cellspacing="0" width="100%" style="display: none !important; mso-hide: all; visibility: hidden; opacity: 0; color: transparent; height: 0; width: 0;">
<tr>
  <td role="module-content">
    <p></p>
  </td>
</tr>
</table><table border="0" cellpadding="0" cellspacing="0" align="center" width="100%" role="module" data-type="columns" style="padding:0px 0px 0px 0px;" bgcolor="#FFFFFF" data-distribution="1">
<tbody>
  <tr role="module-content">
    <td height="100%" valign="top"><table width="580" style="width:580px; border-spacing:0; border-collapse:collapse; margin:0px 10px 0px 10px;" cellpadding="0" cellspacing="0" align="left" border="0" bgcolor="" class="column column-0">
  <tbody>
    <tr>
      <td style="padding:0px;margin:0px;border-spacing:0;"><table class="wrapper" role="module" data-type="image" border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed;" data-muid="dae5891b-ceee-40f7-9315-02ea0b72592e">
<tbody>
  <tr>
    <td style="font-size:6px; line-height:10px; padding:0px 0px 0px 0px;" valign="top" align="center">
      <img class="max-width" border="0" style="display:block; color:#000000; text-decoration:none; font-family:Helvetica, arial, sans-serif; font-size:16px; max-width:20% !important; width:20%; height:auto !important;" width="116" alt="" data-proportionally-constrained="true" data-responsive="true" src="{$domain_url}/assets/img/brand/logo.png">
    </td>
  </tr>
</tbody>
</table></td>
    </tr>
  </tbody>
</table></td>
  </tr>
</tbody>
</table><table border="0" cellpadding="0" cellspacing="0" align="center" width="100%" role="module" data-type="columns" style="padding:10px 0px 10px 0px;" bgcolor="#FFFFFF" data-distribution="1">
<tbody>
  <tr role="module-content">
    <td height="100%" valign="top"><table width="580" style="width:580px; border-spacing:0; border-collapse:collapse; margin:0px 10px 0px 10px;" cellpadding="0" cellspacing="0" align="left" border="0" bgcolor="" class="column column-0">
  <tbody>
    <tr>
      <td style="padding:0px;margin:0px;border-spacing:0;"><table class="module" role="module" data-type="text" border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed;" data-muid="c09ad11e-b6f0-426b-bfb5-e854fb1d6b4e">
<tbody>
  <tr>
    <td style="padding:18px 0px 18px 0px; line-height:30px; text-align:inherit;" height="100%" valign="top" bgcolor="" role="module-content"><div><h2 style="text-align: center">{$business_name}</h2>
<div style="font-family: inherit; text-align: center">{$translations["dear"]} {$name}</div>
<div style="font-family: inherit; text-align: center">{$translations["smtpcontactcontent"]}</div>
<div style="font-family: inherit; text-align: center">{$userMessage}</div>
<div></div></div>
</td>
  </tr>
</tbody>
</table><table border="0" cellpadding="0" cellspacing="0" align="center" width="100%" role="module" data-type="columns" style="padding:0px 0px 0px 0px;" bgcolor="#252525" data-distribution="1">
<tbody>
  <tr role="module-content">
    <td height="100%" valign="top"><table width="580" style="width:580px; border-spacing:0; border-collapse:collapse; margin:0px 10px 0px 10px;" cellpadding="0" cellspacing="0" align="left" border="0" bgcolor="" class="column column-0">
  <tbody>
    <tr>
      <td style="padding:0px;margin:0px;border-spacing:0;"><table class="wrapper" role="module" data-type="image" border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed;" data-muid="70667641-28f8-4e30-850f-c1783cac6e0b">
<tbody>
  <tr>
    <td style="font-size:6px; line-height:10px; padding:0px 0px 0px 0px;" valign="top" align="center">
      <img class="max-width" border="0" style="display:block; color:#000000; text-decoration:none; font-family:Helvetica, arial, sans-serif; font-size:16px; max-width:10% !important; width:10%; height:auto !important;" width="58" alt="" data-proportionally-constrained="true" data-responsive="true" src="https://gymoneglobal.com/assets/img/text-color-logo.png">
    </td>
  </tr>
</tbody>
</table></td>
    </tr>
  </tbody>
</table></td>
  </tr>
</tbody>
</table><div data-role="module-unsubscribe" class="module" role="module" data-type="unsubscribe" style="background-color:#252525; color:#444444; font-size:12px; line-height:20px; padding:0px 0px 0px 0px; text-align:Center;" data-muid="4e838cf3-9892-4a6d-94d6-170e474d21e5"><div class="Unsubscribe--addressLine"></div><p style="font-size:12px; line-height:20px;"><a class="Unsubscribe--unsubscribeLink" href="https://gymoneglobal.com/" target="_blank" style="">Gymoneglobal.com</a></p></div></td>
                                  </tr>
                                </table>
                                <!--[if mso]>
                              </td>
                            </tr>
                          </table>
                        </center>
                        <![endif]-->
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </div>
  </center>
</body>
</html>
EOD;

  $userConfirmationMessage = (new Swift_Message($translations["thankyouforyouremail"]))
    ->setFrom([$smtp_username => $business_name])
    ->setTo([$userEmail])
    ->setBody($editedcontent, 'text/html');

  $resultUser = $mailer->send($userConfirmationMessage);

  if ($result && $resultUser) {
    $alerts_html .= '<div class="alert alert-success" role="alert">
                            ' . $translations["successsndedemail"] . '
                        </div>';
    header("Refresh:2");
  } else {
    $alerts_html .= '<div class="alert alert-danger" role="alert">
                            ' . $translations["unexpected-error"] . '
                        </div>';
    header("Refresh:2");
  }
}
?>




<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $business_name; ?> - <?php echo $translations["contactpage"]; ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- CUSTOM STYLE INSERT HERE! -->
  <link rel="stylesheet" href="../assets/css/default.css">
  <!-- CUSTOM STYLE INSERT HERE! -->
  <link rel="shortcut icon" href="../assets/img/brand/favicon.png" type="image/x-icon">
  <meta name="title" content="<?php echo $business_name; ?> - <?php echo $translations["contactpage"]; ?>">
  <meta name="description" content="<?php echo $description; ?>">
  <meta name="keywords" content="<?php echo $metakey; ?>">
  <meta name="robots" content="index, follow">
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <meta name="author" content="<?php echo $business_name; ?>">

</head>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $gkey; ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];

  function gtag() {
    dataLayer.push(arguments);
  }
  gtag('js', new Date());

  gtag('config', '<?php echo $gkey; ?>');
</script>

<body>
  <div class="container">
    <nav class="navbar navbar-expand-lg navbar-light">
      <img class="img" src="../assets/img/brand/logo.png" width="148px" alt="<?php echo $business_name; ?> Logo">
      <button class="navbar-toggler custom-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link" href="../"><?php echo $translations["mainpage"]; ?></a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="../trainers/"><?php echo $translations["trainerspage"]; ?></a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="../prices/"><?php echo $translations["pricespage"]; ?></a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href=""><?php echo $translations["contactpage"]; ?></a>
          </li>
        </ul>
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a href="../login/" rel="noopener noreferrer" title="Login" class="nav-link ps-0 ps-lg-3 pe-3">
              <i class="bi bi-person-circle"></i>
            </a>
          </li>
        </ul>
      </div>
    </nav>
  </div>
  <div class="container-fluid">
    <div class="row">
      <div class="col bg-imageback">
      </div>
    </div>
    <div class="row text-center ">
      <div class="col mt-2">
        <h1><?php echo $translations["contactpage"]; ?></h1>
      </div>
    </div>
    <div class="row text-center ">
      <div class="col-4">
        <div class="d-inline-block fs-1 lh-1 text-primary bg-primary bg-opacity-25 p-4 rounded-pill">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-geo-alt-fill" viewBox="0 0 16 16">
            <path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10m0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6" />
          </svg>
        </div>
        <h3 class="mt-1"><?php echo $translations["location"]; ?></h3>
        <p class="lead"></p>
        <p class="lead"><?php echo $city; ?>, <?php echo $street; ?> <?php echo $hause_no; ?></p>
      </div>
      <div class="col-4">
        <div class="d-inline-block fs-1 lh-1 text-primary bg-primary bg-opacity-25 p-4 rounded-pill">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-envelope-at-fill" viewBox="0 0 16 16">
            <path d="M2 2A2 2 0 0 0 .05 3.555L8 8.414l7.95-4.859A2 2 0 0 0 14 2zm-2 9.8V4.698l5.803 3.546zm6.761-2.97-6.57 4.026A2 2 0 0 0 2 14h6.256A4.5 4.5 0 0 1 8 12.5a4.49 4.49 0 0 1 1.606-3.446l-.367-.225L8 9.586zM16 9.671V4.697l-5.803 3.546.338.208A4.5 4.5 0 0 1 12.5 8c1.414 0 2.675.652 3.5 1.671" />
            <path d="M15.834 12.244c0 1.168-.577 2.025-1.587 2.025-.503 0-1.002-.228-1.12-.648h-.043c-.118.416-.543.643-1.015.643-.77 0-1.259-.542-1.259-1.434v-.529c0-.844.481-1.4 1.26-1.4.585 0 .87.333.953.63h.03v-.568h.905v2.19c0 .272.18.42.411.42.315 0 .639-.415.639-1.39v-.118c0-1.277-.95-2.326-2.484-2.326h-.04c-1.582 0-2.64 1.067-2.64 2.724v.157c0 1.867 1.237 2.654 2.57 2.654h.045c.507 0 .935-.07 1.18-.18v.731c-.219.1-.643.175-1.237.175h-.044C10.438 16 9 14.82 9 12.646v-.214C9 10.36 10.421 9 12.485 9h.035c2.12 0 3.314 1.43 3.314 3.034zm-4.04.21v.227c0 .586.227.8.581.8.31 0 .564-.17.564-.743v-.367c0-.516-.275-.708-.572-.708-.346 0-.573.245-.573.791" />
          </svg>
        </div>
        <h3 class="mt-1"><?php echo $translations["email"]; ?></h3>
        <p class="lead"><?php echo $mailadress; ?></p>
      </div>
      <div class="col-4">
        <div class="d-inline-block fs-1 lh-1 text-primary bg-primary bg-opacity-25 p-4 rounded-pill">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-telephone-forward-fill" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M1.885.511a1.745 1.745 0 0 1 2.61.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.68.68 0 0 0 .178.643l2.457 2.457a.68.68 0 0 0 .644.178l2.189-.547a1.75 1.75 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877zm10.761.135a.5.5 0 0 1 .708 0l2.5 2.5a.5.5 0 0 1 0 .708l-2.5 2.5a.5.5 0 0 1-.708-.708L14.293 4H9.5a.5.5 0 0 1 0-1h4.793l-1.647-1.646a.5.5 0 0 1 0-.708" />
          </svg>
        </div>
        <h3 class="mt-1"><?php echo $translations["fno"]; ?></h3>
        <p class="lead"><?php echo $phoneno; ?></p>
      </div>
    </div>
    <div id="contact" class="row text-center justify-content-center">
      <div class="col-sm-4 py-3">
        <h1><?php echo $translations["contactform"]; ?></h1>
        <?php echo $alerts_html; ?>
        <form method="post">
          <div class="mb-3">
            <label for="name" class="form-label"><?php echo $translations["fullname"]; ?>:</label>
            <input type="text" class="form-control" id="name" name="name" required>
          </div>
          <div class="mb-3">
            <label for="email" class="form-label"><?php echo $translations["email"]; ?>:</label>
            <input type="email" class="form-control" id="email" name="email" required>
          </div>
          <div class="mb-3">
            <label for="message" class="form-label"><?php echo $translations["message"]; ?>:</label>
            <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
          </div>
          <button type="submit" class="btn btn-primary"><?php echo $translations["send"]; ?></button>
        </form>
      </div>
    </div>
  </div>

  <div class="footer">
    <div class="container">
      <div class="row gy-4">
        <div class="mt-3"></div>
        <div class="col-md-4 mb-1">
          <h2 class="mb-4">
            <img src="../assets/img/brand/logo.png" alt="<?php echo $business_name; ?> - Logo" height="105">
          </h2>

          <p><?php echo $city; ?></p>
          <p><?php echo $street; ?> <?php echo $hause_no; ?></p>
        </div>
        <div class="col-md-3 offset-md-1">
          <?php if (!empty($days)): ?>
            <div class="list-group">
              <?php foreach ($days as $day): ?>
                <div class="list-group-itemcustom d-flex justify-content-between align-items-center">
                  <span><strong><?= htmlspecialchars($dayNames[$day['day']]) ?></strong></span>
                  <span class="text-center justify-content-center">
                    <?php if (is_null($day['open_time']) && is_null($day['close_time'])): ?>
                      <span class="badge bg-danger"><?= $translations["closed"]; ?></span>
                    <?php else: ?>
                      <span class="badge bg-success">
                        <?= date('H:i', strtotime($day['open_time'])) ?> - <?= date('H:i', strtotime($day['close_time'])) ?>
                      </span>
                    <?php endif; ?>
                  </span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="col-md-2 offset-md-1">
          <h5 class="text-light mb-4"></h5>

        </div>
      </div>

      <div class="border-top border-secondary pt-3 mt-3">
        <p class="small text-center mb-0">
          Copyright © <?php echo $copyright_year; ?> <?php echo $business_name; ?> - <?php echo $translations["copyright"]; ?>
          &nbsp;<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="red" class="bi bi-heart-fill" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M8 1.314C12.438-3.248 23.534 4.735 8 15-7.534 4.736 3.562-3.248 8 1.314">
            </path>
          </svg>
          <a href="https://www.gymoneglobal.com/?lang=<?php echo $lang_code; ?>">GYM One</a>
        </p>
      </div>
    </div>
  </div>
</body>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

</html>