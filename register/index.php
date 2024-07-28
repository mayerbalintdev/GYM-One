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
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $userid = rand(pow(10, 9), pow(10, 10) - 1);

        $confirmed = 'No';

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

        if ($stmt->execute()) {
            $alerts_html .= '<div class="alert alert-success">Sikeres regisztráció!</div>';
            header("Refresh: 5");
            $transport = (new Swift_SmtpTransport('smtp.gmail.com', 587, 'tls'))
                ->setUsername('12345@gmail.com')
                ->setPassword('HAHA YOU FEEL ME?');

            $mailer = new Swift_Mailer($transport);

            $successEmailContent = <<<EOD
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
      <img class="max-width" border="0" style="display:block; color:#000000; text-decoration:none; font-family:Helvetica, arial, sans-serif; font-size:16px; max-width:20% !important; width:20%; height:auto !important;" width="116" alt="" data-proportionally-constrained="true" data-responsive="true" src="https://gymoneglobal.com/assets/img/text-color-logo.png">
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
<div style="font-family: inherit; text-align: center">{$translations["confiremailbody"]}</div><div></div></div></td>
  </tr>
</tbody>
</table><table border="0" cellpadding="0" cellspacing="0" class="module" data-role="module-button" data-type="button" role="module" style="table-layout:fixed;" width="100%" data-muid="871ebc25-ef86-4b75-a5c9-79c7924d0c94">
  <tbody>
    <tr>
      <td align="center" bgcolor="" class="outer-td" style="padding:0px 0px 10px 0px;">
        <table border="0" cellpadding="0" cellspacing="0" class="wrapper-mobile" style="text-align:center;">
          <tbody>
            <tr>
            <td align="center" bgcolor="#36d91b" class="inner-td" style="border-radius:6px; font-size:16px; text-align:center; background-color:inherit;">
              <a href="{$domain_url}/register/confirm.php?userid={$userid}" style="background-color:#36d91b; border:0px solid #333333; border-color:#333333; border-radius:25px; border-width:0px; color:#ffffff; display:inline-block; font-size:14px; font-weight:normal; letter-spacing:0px; line-height:normal; padding:12px 18px 12px 18px; text-align:center; text-decoration:none; border-style:solid;" target="_blank">{$translations["regconfirmbtn"]}</a>
            </td>
            </tr>
          </tbody>
        </table>
      </td>
    </tr>
  </tbody>
</table></td>
    </tr>
  </tbody>
</table></td>
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


            $recipientEmail = $email;
            $subject = $translations["confirmemailmailsub"];

            $isRegistrationSuccessful = true;

            if ($isRegistrationSuccessful) {
                $message = (new Swift_Message($subject))
                    ->setFrom(['12345@gmail.com' => "{$business_name} - {$translations['confirmemailpage']}"])
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
</head>

<body>
    <div id="register">
        <div class="container">
            <div class="row justify-content-center pt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-center">
                                <h2><?php echo $translations["login"]; ?></h2>
                                <img class="img mb-3 mt-3 img-fluid" src="../assets/img/brand/logo.png" title="<?php echo $business_name; ?>" alt="<?php echo $business_name; ?>">
                            </div>
                            <?php if (!empty($login_error)) : ?>
                                <div class="alert alert-danger"><?php echo $login_error; ?></div>
                            <?php endif; ?>
                            <?php if (!empty($alerts_html)) : ?>
                                <?php echo $alerts_html; ?>
                            <?php endif; ?>

                            <form method="POST">
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
                                        <option value="Famale"><?php echo $translations["girl"]; ?></option>
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
                                <iframe src="../admin/boss/rule/rule.html" width="100%" height="200px" frameborder="0"></iframe>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault" required>
                                    <label class="form-check-label" for="flexCheckDefault">
                                        <?php echo $translations["acceptrules"]; ?>
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-primary"><?php echo $translations["register"]; ?></button>
                            </form>
                            <div class="text-start mt-1">
                                <small><?php echo $translations["doyouhaveaccount"]; ?> <span><a href="../login/"><?php echo $translations["login"]; ?></a></span></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 319">
        <path fill="whitesmoke" fill-opacity="1" d="M0,64L1440,192L1440,320L0,320Z"></path>
    </svg>
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</body>

</html>