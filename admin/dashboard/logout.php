<?php
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

$lang = $lang_code;

$langDir = __DIR__ . "/../../assets/lang/";

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

// Ellenőrizzük, hogy a 'user' paraméter át van-e adva az URL-ben
if (isset($_GET['user'])) {
    $userid = $_GET['user'];

    // Lekérdezzük a felhasználó bejelentkezési idejét
    $sql = "SELECT login_date FROM temp_loggeduser WHERE userid = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        // Paraméterek bindolása
        $stmt->bind_param("s", $userid);

        // Lekérdezés végrehajtása
        $stmt->execute();
        $stmt->bind_result($login_date);
        $stmt->fetch();
        $stmt->close();

        // Ha van bejelentkezési idő
        if ($login_date) {
            // Jelenlegi idő lekérése
            $current_time = new DateTime();

            // Bejelentkezési idő átalakítása DateTime objektummá
            $login_time = new DateTime($login_date);

            // Különbség kiszámítása
            $interval = $login_time->diff($current_time);

            // Az eltelt idő percekben
            $minutes_spent = $interval->h * 60 + $interval->i;

            // Most, hogy tudjuk, mennyi időt töltött, beírjuk a workout_stats táblába
            $workout_date = $current_time->format('Y-m-d'); // Mai dátum

            // SQL lekérdezés a workout statisztika hozzáadására
            $insert_sql = "INSERT INTO workout_stats (userid, duration, workout_date) VALUES (?, ?, ?)";

            if ($insert_stmt = $conn->prepare($insert_sql)) {
                // Paraméterek bindolása
                $insert_stmt->bind_param("iis", $userid, $minutes_spent, $workout_date);
                if ($insert_stmt->execute()) {
                    echo "Edzésidő sikeresen hozzáadva a statisztikákhoz!";
                } else {
                    echo "Hiba történt az edzés statisztika hozzáadásánál: " . $insert_stmt->error;
                }
                $insert_stmt->close();
            }
        } else {
            echo "Nincs bejelentkezett idő.";
        }

        // Felhasználó törlése a temp_loggeduser táblából
        $delete_sql = "DELETE FROM temp_loggeduser WHERE userid = ?";
        if ($delete_stmt = $conn->prepare($delete_sql)) {
            $delete_stmt->bind_param("s", $userid);
            if ($delete_stmt->execute()) {
                echo "Felhasználó sikeresen törölve!";
            } else {
                echo "Hiba történt a felhasználó törlésénél: " . $delete_stmt->error;
            }
            $delete_stmt->close();
        }

        $update_sql = "UPDATE lockers SET user_id = NULL WHERE user_id = ?";
        if ($update_stmt = $conn->prepare($update_sql)) {
            $update_stmt->bind_param("s", $userid);
            if ($update_stmt->execute()) {
                echo "A felhasználó szekrényéhez rendelt ID sikeresen nullázva!";
            } else {
                echo "Hiba történt a szekrény user_id nullázásánál: " . $update_stmt->error;
            }
            $update_stmt->close();
        }

        header("Location: index.php");
        exit();
    } else {
        echo "Hiba történt a felhasználó lekérdezése során.";
    }
} else {
    echo "Nincs megadva felhasználói ID.";
}

// Kapcsolat lezárása
$conn->close();
?>
