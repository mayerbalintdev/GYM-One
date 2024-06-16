<?php
session_start();

function get_db_connection() {
    $env_data = read_env_file('../.env');
    
    $db_host = $env_data['DB_SERVER'] ?? '';
    $db_username = $env_data['DB_USERNAME'] ?? '';
    $db_password = $env_data['DB_PASSWORD'] ?? '';
    $db_name = $env_data['DB_NAME'] ?? '';
    
    $conn = new mysqli($db_host, $db_username, $db_password, $db_name);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

function read_env_file($file_path) {
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
        
        $stmt = $conn->prepare("INSERT INTO users (userid, firstname, lastname, email, password, gender, birthdate, city, street, house_number, allergy, registration_date, confirmed)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("issssssssssss", $userid, $firstname, $lastname, $email, $hashed_password, $gender, $birthdate, $city, $street, $house_number, $allergy, $registration_date, $confirmed);
        
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
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Regisztráció</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container">
    <h2>Regisztráció</h2>
    <?php echo isset($alerts_html) ? $alerts_html : ''; ?>
    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <div class="form-group">
            <label for="firstname">Vezetéknév</label>
            <input type="text" class="form-control" id="firstname" name="firstname" required>
        </div>
        <div class="form-group">
            <label for="lastname">Keresztnév</label>
            <input type="text" class="form-control" id="lastname" name="lastname" required>
        </div>
        <div class="form-group">
            <label for="email">Email cím</label>
            <input type="email" class="form-control" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Jelszó</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label for="confirm_password">Jelszó megerősítése</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
        </div>
        <div class="form-group">
            <label for="gender">Neme</label>
            <select class="form-control" id="gender" name="gender" required>
                <option value="Male">Férfi</option>
                <option value="Famale">Nő</option>
            </select>
        </div>
        <div class="form-group">
            <label for="birthdate">Születésnap</label>
            <input type="date" class="form-control" id="birthdate" name="birthdate" required>
        </div>
        <div class="form-group">
            <label for="city">Város</label>
            <input type="text" class="form-control" id="city" name="city" required>
        </div>
        <div class="form-group">
            <label for="street">Utca</label>
            <input type="text" class="form-control" id="street" name="street" required>
        </div>
        <div class="form-group">
            <label for="house_number">Házszám</label>
            <input type="text" class="form-control" id="house_number" name="house_number" required>
        </div>
        <div class="form-group">
            <label for="allergy">Allergia valamire</label>
            <input type="text" class="form-control" id="allergy" name="allergy">
        </div>
        <button type="submit" class="btn btn-primary">Regisztráció</button>
    </form>
</div>
</body>
</html>
