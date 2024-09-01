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

function get_db_connection()
{
    global $env_data;

    $db_host = $env_data['DB_SERVER'] ?? '';
    $db_username = $env_data['DB_USERNAME'] ?? '';
    $db_password = $env_data['DB_PASSWORD'] ?? '';
    $db_name = $env_data['DB_NAME'] ?? '';

    $conn = new mysqli($db_host, $db_username, $db_password, $db_name);

    if ($conn->connect_error) {
        die("Kapcsolódási hiba: " . $conn->connect_error);
    }

    return $conn;
}

$env_data = read_env_file('../.env');

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);


$login_error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $conn = get_db_connection();

    $stmt = $conn->prepare("SELECT userid, password, confirmed FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($userid, $hashed_password, $confirmed);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            if ($confirmed == 'Yes') {
                $current_datetime = date('Y-m-d H:i:s');
                $user_ip = $_SERVER['REMOTE_ADDR'];
                $update_stmt = $conn->prepare("UPDATE users SET lastlogin = ?, lastip = ? WHERE userid = ?");
                $update_stmt->bind_param("ssi", $current_datetime, $user_ip, $userid);
                $update_stmt->execute();
                $update_stmt->close();
                session_start();
                $_SESSION['userid'] = $userid;
                header("Location: ../dashboard");
                exit();
            } else {
                $login_error = $translations["acceptemailplease"];
            }
        } else {
            $login_error = $translations["notcorrectlogin"];
        }
    } else {
        $login_error = $translations["notcorrectlogin"];
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $translations["login"]; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/login-register.css">
    <link rel="shortcut icon" href="../assets/img/brand/favicon.png" type="image/x-icon">
</head>

<body>
    <div id="login">
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
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                <div class="form-group">
                                    <label for="email"><?php echo $translations["email"]; ?>:</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="form-group">
                                    <label for="password"><?php echo $translations["password"]; ?>:</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-lg btn-block btn-primary"><?php echo $translations["next"]; ?></button>
                            </form>
                            <div class="text-start mt-1">
                                <small><?php echo $translations["youdonthaveaccount"];?> <span><a href="../register/"><?php echo $translations["registerbtn"];?></a></span></small>
                            </div>
                        </div>
                    </div>
                    <small><?php echo $translations["adminaccountlogin"];?> <span><a href="../admin/"><?php echo $translations["login"];?></a></span></small>
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