<?php
session_start();

if (!isset($_SESSION['userid'])) {
    header("Location: ../");
    exit();
}

$userid = $_SESSION['userid'];

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
$currency = $env_data['CURRENCY'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../../../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ticket_type = $_POST['ticket_type'];
    $validity = $_POST['validity'];
    $price = $_POST['price'];
    $expiration = $_POST['expiration'];

    $sql = "INSERT INTO tickets (ticket_type, validity, price, expiration) VALUES ('$ticket_type', '$validity', $price, $expiration)";

    if ($conn->query($sql) === TRUE) {
        echo "New ticket created successfully";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

$sql = "SELECT id, ticket_type, validity, price, expiration FROM tickets";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gym Ticket Management</title>
</head>
<body>
    <h1>Add New Ticket</h1>
    <form method="post" action="">
        <label for="ticket_type">Ticket Type:</label><br>
        <input type="text" id="ticket_type" name="ticket_type" required><br>
        <label for="validity">Validity:</label><br>
        <input type="text" id="validity" name="validity" required><br>
        <label for="price">Price:</label><br>
        <input type="number" id="price" name="price" required><br>
        <label for="expiration">Expiration (days):</label><br>
        <input type="number" id="expiration" name="expiration" required><br><br>
        <input type="submit" value="Add Ticket">
    </form>

    <h1>Available Tickets</h1>
    <table>
        <tr>
            <th>ID</th>
            <th>Ticket Type</th>
            <th>Validity</th>
            <th>Price</th>
            <th>Expiration</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                echo "<tr>
                        <td>".$row["id"]."</td>
                        <td>".$row["ticket_type"]."</td>
                        <td>".$row["validity"]."</td>
                        <td>".$row["price"]."</td>
                        <td>".$row["expiration"]."</td>
                    </tr>";
            }
        } else {
            echo "<tr><td colspan='5'>No tickets found</td></tr>";
        }
        ?>
    </table>
</body>
</html>

<?php
$conn->close();
?>
