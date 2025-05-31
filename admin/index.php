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

$env_data = read_env_file('../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);

$alerts_html = '';

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    $alerts_html .= '<div class="alert alert-danger" role="alert">
                        Adatbázis kapcsolat sikertelen: ' . $conn->connect_error . '
                    </div>';
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT userid, password_hash FROM workers WHERE username = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        $alerts_html .= '<div class="alert alert-danger" role="alert">
                            SQL lekérdezés előkészítése sikertelen: ' . $conn->error . '
                        </div>';
        exit();
    }

    $stmt->bind_param("s", $username);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password_hash'])) {
                $_SESSION['adminuser'] = $row['userid'];
                header("Location: dashboard/");
                exit();
            } else {
                $alerts_html .= '<div class="alert alert-danger" role="alert">
                                    ' . $translations["incorrect-pass"] . '
                                </div>';
            }
        } else {
            $alerts_html .= '<div class="alert alert-danger" role="alert">
                                ' . $translations["user-notexist"] . '
                            </div>';
        }
    } else {
        $alerts_html .= '<div class="alert alert-danger" role="alert">
                            Hiba történt: ' . $conn->error . '
                        </div>';
    }

    $stmt->close();
}


$conn->close();
?>


<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $business_name; ?> - <?php echo $translations["login"]; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="shortcut icon" href="https://gymoneglobal.com/assets/img/logo.png" type="image/x-icon">
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-5 mx-auto text-center mt-5">
                <img src="../assets/img/logo.png" class="img img-fluid" width="205px" alt="Logo">
                <h3 class="fw-semibold"><?php echo $business_name; ?> - <?php echo $translations["login"]; ?></h3>

                <div class="col-12">
                    <?php echo $alerts_html; ?>
                </div>
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-group">
                        <label for="username"><?php echo $translations["username"]; ?>:</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password"><?php echo $translations["password"]; ?>:</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mt-3"></div>
                    <button type="submit" class="btn btn-primary"><?php echo $translations["login"]; ?></button>
                </form>
            </div>
        </div>
    </div>
</body>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
    integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
    crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"
    integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy"
    crossorigin="anonymous"></script>

</html>