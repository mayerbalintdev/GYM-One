<?php
session_start();

// Ellenőrizzük, hogy be van-e jelentkezve a felhasználó
if (!isset($_SESSION['userid'])) {
    // Ha nincs bejelentkezve, átirányítjuk a bejelentkezési oldalra vagy hibát jelezhetünk
    header("Location: login.php");
    exit; // Fontos, hogy ne fusson tovább a kód
}

// Sessionból kiolvassuk a userid-t
$userid = $_SESSION['userid'];

// Adatbázis kapcsolódás beállítása
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

$env_data = read_env_file('../../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../../../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

// Nyelvi fájl betöltése és dekódolása
$translations = json_decode(file_get_contents($langFile), true);

// Kapcsolat létrehozása
$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

// Kapcsolat ellenőrzése
if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}

// SQL lekérdezés előkészítése a felhasználó adatainak ellenőrzésére
$sql = "SELECT is_boss FROM workers WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userid);
$stmt->execute();
$stmt->store_result();

// Ellenőrizzük, hogy van-e eredmény a lekérdezésből
if ($stmt->num_rows > 0) {
    // Fetch eredményt és ellenőrizzük, hogy a felhasználó vezető-e
    $stmt->bind_result($is_boss);
    $stmt->fetch();

    if ($is_boss == 1) {
        // Ha a felhasználó vezető, engedjük tovább
        echo "Üdvözöljük, vezető!";
        // Ide jöhet a további kód a vezetők számára
    } else {
        // Ha a felhasználó nem vezető, megakadályozzuk a továbblépést
        echo "Nincs jogosultsága a kért oldal megtekintéséhez!";
        // Lehetőség szerint átirányíthatunk egy hozzáférés megtagadás oldalra vagy hasonlóra
    }
} else {
    // Ha nincs találat a lekérdezésben
    echo "Nincs ilyen felhasználó!";
}

// Lekérdezés lezárása és kapcsolat bezárása
$stmt->close();
?>