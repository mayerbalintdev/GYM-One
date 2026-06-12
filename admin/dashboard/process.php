<?php
/**
 * GYM One – beléptető (check-in) backend
 *
 * Két mód (POST 'action'):
 *   - 'lookup' (alapértelmezett): CSAK lekérdez — user + bérlet érvényesség.
 *     Nincs szekrényfoglalás, nincs alkalom-levonás, nincs naplózás.
 *   - 'commit': a tényleges beléptetés (szekrény + alkalom + temp_loggeduser),
 *     amit a kliens csak a dolgozói személyazonosság-megerősítés után hív.
 *
 * Az adatbázis sémája NEM változott — ugyanazok a táblák/oszlopok,
 * prepared statement + tranzakció + sorzárolás (FOR UPDATE).
 */

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

// --- Auth: csak bejelentkezett admin léptethet be ---
if (!isset($_SESSION['adminuser'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// --- Csak POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

/**
 * .env beolvasása (\r\n és kommentek kezelése).
 */
function read_env_file(string $file_path): array
{
    if (!is_readable($file_path)) {
        return [];
    }
    $env_data = [];
    foreach (preg_split("/\r\n|\n|\r/", (string) file_get_contents($file_path)) as $line) {
        if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $env_data[trim($parts[0])] = trim($parts[1]);
        }
    }
    return $env_data;
}

$env_data = read_env_file('../../.env');

$db_host     = $env_data['DB_SERVER']   ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name     = $env_data['DB_NAME']     ?? '';
$lang        = $env_data['LANG_CODE']   ?? '';

// --- Nyelvi fájl ---
$langFile = __DIR__ . "/../../assets/lang/{$lang}.json";
if (!is_readable($langFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Language file not found']);
    exit;
}
$translations = json_decode((string) file_get_contents($langFile), true) ?: [];

// --- Bemenet validálása: a userid bigint, csak számjegy lehet ---
$qrCode = isset($_POST['qrcode']) ? trim((string) $_POST['qrcode']) : '';
if ($qrCode === '' || !ctype_digit($qrCode)) {
    echo json_encode(['success' => false, 'error' => $translations['qr-error'] ?? 'Invalid code']);
    exit;
}

// mysqli dobjon kivételt hiba esetén -> tiszta try/catch
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 'lookup' = csak lekérdezés (NINCS szekrényfoglalás / alkalom-levonás / napló);
// 'commit' = tényleges beléptetés a dolgozói megerősítés után.
// Alapértelmezett a biztonságos 'lookup'.
$action = (($_POST['action'] ?? 'lookup') === 'commit') ? 'commit' : 'lookup';

$response = ['success' => false, 'action' => $action];

try {
    $conn = new mysqli($db_host, $db_username, $db_password, $db_name);
    $conn->set_charset('utf8mb4');

    // 1) User létezik-e?
    $stmt = $conn->prepare(
        "SELECT firstname, lastname, birthdate, gender FROM users WHERE userid = ? LIMIT 1"
    );
    $stmt->bind_param('s', $qrCode);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'error' => $translations['user-notexist'] ?? 'User not found']);
        $conn->close();
        exit;
    }

    $response['success']   = true;
    $response['firstname'] = $user['firstname'];
    $response['lastname']  = $user['lastname'];
    $response['birthdate'] = $user['birthdate'];
    $response['gender']    = $user['gender'];

    // 2) Dupla beléptetés elleni védelem.
    //    (Ha a régi viselkedés kell – minden scan új szekrényt ad –, töröld ezt a blokkot.)
    $stmt = $conn->prepare("SELECT 1 FROM temp_loggeduser WHERE userid = ? LIMIT 1");
    $stmt->bind_param('s', $qrCode);
    $stmt->execute();
    $alreadyIn = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($alreadyIn) {
        $response['ticket_status'] = $translations['expired'] ?? 'Already checked in';
        $response['error'] = $translations['alreadyloggedin'] ?? 'A felhasználó már be van léptetve.';
        $response['success'] = false;
        echo json_encode($response);
        $conn->close();
        exit;
    }

    // 3) Érvényes bérlet keresése (az id-t is lekérjük a pontos UPDATE-hez)
    $stmt = $conn->prepare(
        "SELECT id, opportunities, expiredate
           FROM current_tickets
          WHERE userid = ?
            AND (opportunities > 0 OR opportunities IS NULL)
            AND expiredate >= CURDATE()
          ORDER BY expiredate ASC
          LIMIT 1"
    );
    $stmt->bind_param('s', $qrCode);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ticket) {
        $response['ticket_status'] = $translations['expired'] ?? 'Expired';
        echo json_encode($response);
        $conn->close();
        exit;
    }

    $ticketId      = (int) $ticket['id'];
    $opportunities = $ticket['opportunities']; // lehet NULL (korlátlan)
    $expiredate    = $ticket['expiredate'];
    $currentDate   = date('Y-m-d');

    $response['ticket_status']           = $translations['valid'] ?? 'Valid';
    $response['remaining_opportunities'] = $opportunities;
    $response['expiredate']              = $expiredate;

    if ($expiredate === $currentDate) {
        $response['expiredate_message'] = $translations['todayexpire'] ?? '';
    } else {
        $response['remaining_days'] = date_diff(
            date_create($currentDate),
            date_create($expiredate)
        )->days;
    }

    // LOOKUP: itt megállunk – semmit nem írunk az adatbázisba.
    // A tényleges beléptetés (szekrény + alkalom + napló) csak 'commit'-nál fut,
    // a dolgozói személyazonosság-megerősítés UTÁN.
    if ($action === 'lookup') {
        $conn->close();
        echo json_encode($response);
        exit;
    }

    // 4) COMMIT: szekrény kiosztás + alkalom levonás + napló — egy tranzakcióban,
    //    sorzárolással, hogy két egyidejű beléptetés ne kapja ugyanazt a szekrényt.
    $gender = $user['gender'];
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "SELECT lockernum FROM lockers
              WHERE gender = ? AND user_id IS NULL
              ORDER BY RAND() LIMIT 1 FOR UPDATE"
        );
        $stmt->bind_param('s', $gender);
        $stmt->execute();
        $lockerRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($lockerRow) {
            $lockerNum = (int) $lockerRow['lockernum'];

            // szekrény foglalása
            $stmt = $conn->prepare("UPDATE lockers SET user_id = ? WHERE lockernum = ?");
            $stmt->bind_param('si', $qrCode, $lockerNum);
            $stmt->execute();
            $stmt->close();

            // alkalom levonás (csak ha nem korlátlan)
            if ($opportunities !== null && (int) $opportunities > 0) {
                $newOpportunities = (int) $opportunities - 1;
                $stmt = $conn->prepare("UPDATE current_tickets SET opportunities = ? WHERE id = ?");
                $stmt->bind_param('ii', $newOpportunities, $ticketId);
                $stmt->execute();
                $stmt->close();
                $response['remaining_opportunities'] = $newOpportunities;
            }

            // naplózás
            $fullName = $user['firstname'] . ' ' . $user['lastname'];
            $stmt = $conn->prepare(
                "INSERT INTO temp_loggeduser (name, userid, login_date, lockerid)
                 VALUES (?, ?, NOW(), ?)"
            );
            $stmt->bind_param('ssi', $fullName, $qrCode, $lockerNum);
            $stmt->execute();
            $stmt->close();

            $response['assigned_locker'] = $lockerNum;
        } else {
            $response['assigned_locker'] = $translations['locker_notavilable'] ?? 'No locker available';
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    $conn->close();
    echo json_encode($response);

} catch (Throwable $e) {
    http_response_code(500);
    // A részletes hibát logba, a kliensnek általános üzenet.
    error_log('[checkin] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $translations['qr-error'] ?? 'Server error']);
}