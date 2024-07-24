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
    $allergy = $_POST['allergy'];

    if ($password !== $confirm_password) {
        $alerts_html = '<div class="alert alert-danger">A két jelszó nem egyezik meg.</div>';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $userid = rand(pow(10, 9), pow(10, 10) - 1);

        $confirmed = 'No';

        $registration_date = date('Y-m-d H:i:s');

        $conn = get_db_connection();

        $stmt = $conn->prepare("INSERT INTO users (userid, firstname, lastname, email, password, gender, birthdate, city, street, house_number, registration_date, confirmed)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("issssssssssss", $userid, $firstname, $lastname, $email, $hashed_password, $gender, $birthdate, $city, $street, $house_number, $registration_date, $confirmed);

        if ($stmt->execute()) {
            $alerts_html = '<div class="alert alert-success">Sikeres regisztráció!</div>';
        } else {
            $alerts_html = '<div class="alert alert-danger">Hiba történt a regisztráció során.</div>';
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
    <div class="container">
        <h2>Regisztráció</h2>
        <?php echo isset($alerts_html) ? $alerts_html : ''; ?>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
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
    </div>
</body>

</html>