<?php
header('Content-Type: application/json');

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

$lang_code = $env_data['LANG_CODE'] ?? '';
$lang = $lang_code;

$langDir = __DIR__ . "/../../assets/lang/";
$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

$qrCode = isset($_POST['qrcode']) ? $conn->real_escape_string($_POST['qrcode']) : '';

$sql = "SELECT firstname, lastname, birthdate, gender FROM users WHERE userid = '$qrCode'";
$result = $conn->query($sql);

$response = ['success' => false];

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $response['success'] = true;
    $response['firstname'] = $row['firstname'];
    $response['lastname'] = $row['lastname'];
    $response['birthdate'] = $row['birthdate'];
    $response['gender'] = $row["gender"];

    $ticketSql = "SELECT opportunities, expiredate 
                  FROM current_tickets 
                  WHERE userid = '$qrCode' 
                    AND (opportunities > 0 OR opportunities IS NULL)
                    AND expiredate >= CURDATE()
                  ORDER BY expiredate ASC 
                  LIMIT 1";

    $ticketResult = $conn->query($ticketSql);

    if ($ticketResult && $ticketResult->num_rows > 0) {
        $ticketRow = $ticketResult->fetch_assoc();
        $opportunities = $ticketRow['opportunities'];
        $expiredate = $ticketRow['expiredate'];

        $currentDate = date('Y-m-d');

        $response['ticket_status'] = $translations["valid"];
        $response['remaining_opportunities'] = $opportunities;
        $response['expiredate'] = $expiredate;

        if ($expiredate == $currentDate) {
            $response['expiredate_message'] = $translations["todayexpire"];
        } else {
            $interval = date_diff(date_create($currentDate), date_create($expiredate));
            $response['remaining_days'] = $interval->days;
        }

        $gender = $row['gender'];
        $lockerSql = "SELECT lockernum FROM lockers WHERE gender = '$gender' AND user_id IS NULL"; 
        $lockerResult = $conn->query($lockerSql);

        if ($lockerResult && $lockerResult->num_rows > 0) {
            $lockers = [];
            while ($lockerRow = $lockerResult->fetch_assoc()) {
                $lockers[] = $lockerRow['lockernum'];
            }

            $randomLocker = $lockers[array_rand($lockers)];
            $response['assigned_locker'] = $randomLocker;

            $assignLockerSql = "UPDATE lockers SET user_id = '$qrCode' WHERE lockernum = '$randomLocker'";
            $conn->query($assignLockerSql);

            if (!is_null($opportunities) && $opportunities > 0) {
                $newOpportunities = $opportunities - 1;
                $updateTicketSql = "UPDATE current_tickets 
                                    SET opportunities = '$newOpportunities' 
                                    WHERE userid = '$qrCode' 
                                      AND expiredate = '$expiredate'";
                $conn->query($updateTicketSql);
                $response['remaining_opportunities'] = $newOpportunities;
            }

            $logUserSql = "INSERT INTO temp_loggeduser (name, userid, login_date, lockerid) 
                           VALUES ('{$row['firstname']} {$row['lastname']}', '$qrCode', NOW(), '$randomLocker')";
            $conn->query($logUserSql);
        } else {
            $response['assigned_locker'] = $translations["locker_notavilable"]; 
        }
    } else {
        $response['ticket_status'] = $translations["expired"];
    }
} else {
    $response['error'] = 'User not found';
}

$conn->close();
echo json_encode($response);
?>
