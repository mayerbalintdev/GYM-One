<?php
function read_env_file($file_path)
{
    if (!file_exists($file_path)) return [];
    $env_file = file_get_contents($file_path);
    $env_lines = preg_split("/\r\n|\n|\r/", $env_file);
    $env_data = [];

    foreach ($env_lines as $line) {
        if (trim($line) === '' || strpos($line, '#') === 0) continue;
        $line_parts = explode('=', $line, 2);
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

$lang_code = $env_data['LANG_CODE'] ?? '';
$lang = $lang_code;

$langDir = __DIR__ . "/../../assets/lang/";
$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);

$useHost = $db_host;
$useUser = $db_username;
$usePass = $db_password ;
$useDB   = $db_name;

$conn = new mysqli($useHost, $useUser, $usePass, $useDB);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['search'])) {
    $searchQuery = $_POST['search'];

    $sql = "SELECT * FROM users WHERE CONCAT(firstname, ' ', lastname) LIKE ?";
    $stmt = $conn->prepare($sql);

    $like = "%{$searchQuery}%";
    $stmt->bind_param("s", $like);

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo '<table class="table table-striped">';
        echo '<thead><tr><th>' . htmlspecialchars($translations["firstname"], ENT_QUOTES) . '</th><th>' . htmlspecialchars($translations["lastname"], ENT_QUOTES) . '</th><th>' . htmlspecialchars($translations["email"], ENT_QUOTES) . '</th><th></th></tr></thead><tbody>';
        while ($row = $result->fetch_assoc()) {
            $userid = htmlspecialchars($row['userid'], ENT_QUOTES);
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['firstname'], ENT_QUOTES) . '</td>';
            echo '<td>' . htmlspecialchars($row['lastname'], ENT_QUOTES) . '</td>';
            echo '<td>' . htmlspecialchars($row['email'], ENT_QUOTES) . '</td>';
            // Beléptetés gomb: dinamikus, AJAX-szal kezeljük
            $btnLabel = isset($translations["next"]) ? $translations["next"] : 'Beléptetés';
            echo '<td><button class="btn btn-sm btn-primary loginUser" data-userid="' . $userid . '">' . htmlspecialchars($btnLabel, ENT_QUOTES) . '</button></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>' . htmlspecialchars($translations["user-notexist"], ENT_QUOTES) . '</p>';
    }

    $stmt->close();
    $conn->close();
}
