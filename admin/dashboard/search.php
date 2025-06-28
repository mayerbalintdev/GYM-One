<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gymone";

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
$capacity = $env_data["CAPACITY"] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
if (isset($_POST['search'])) {
    $searchQuery = $_POST['search'];

    $sql = "SELECT * FROM users WHERE CONCAT(firstname, ' ', lastname) LIKE ?";
    $stmt = $conn->prepare($sql);

    $searchQuery = "%$searchQuery%";
    $stmt->bind_param("s", $searchQuery);

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo '<table class="table table-striped">';
        echo '<thead><tr><th>' . $translations["firstname"] . '</th><th>' . $translations["lastname"] . '</th><th>' . $translations["email"] . '</th></tr></thead><tbody>';
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $row['firstname'] . '</td>';
            echo '<td>' . $row['lastname'] . '</td>';
            echo '<td>' . $row['email'] . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>' . $translations["user-notexist"] . '</p>';
    }

    $stmt->close();
    $conn->close();
}
