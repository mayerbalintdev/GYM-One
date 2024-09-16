<?php
session_start();

if (!isset($_SESSION['adminuser'])) {
    header("Location: ../");
    exit();
}

$userid = $_SESSION['adminuser'];

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
$currency_env = $env_data["CURRENCY"] ?? '';
$smtp_username = $env_data['MAIL_USERNAME'] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../../../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

$alerts_html = "";

$translations = json_decode(file_get_contents($langFile), true);

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}

$sql = "SELECT is_boss FROM workers WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userid);
$stmt->execute();
$stmt->store_result();

$is_boss = null;

if ($stmt->num_rows > 0) {
    $stmt->bind_result($is_boss);
    $stmt->fetch();
}
$stmt->close();

$file_path = 'https://api.gymoneglobal.com/latest/version.txt';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $file_path);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$latest_version = curl_exec($ch);
curl_close($ch);

$current_version = $version;

$is_new_version_available = version_compare($latest_version, $current_version) > 0;

// Új bérlet/jegy hozzáadása
if (isset($_POST['add_ticket'])) {
    $name = $_POST['name'];
    $expire_days = $_POST['expire_days'] === 'unlimited' ? 'NULL' : $_POST['expire_days'];
    $price = $_POST['price'];
    $occasions = $_POST['occasions'] === '' ? 'NULL' : $_POST['occasions'];

    $sql = "INSERT INTO tickets (name, expire_days, price, occasions) 
            VALUES ('$name', $expire_days, $price, $occasions)";
    mysqli_query($conn, $sql);
}

// Bérlet/jegy törlése
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM tickets WHERE id = $id";
    mysqli_query($conn, $sql);
}

// Bérletek/jegyek lekérdezése
$sql = "SELECT * FROM tickets";
$result = mysqli_query($conn, $sql);

$conn->close();
?>
<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use Mpdf\Mpdf;

// A számlázási adatok
$userid = 12345;
$invoiceNumber = bin2hex(random_bytes(8));
$date = date('Y-m-d');
$dueDate = date('Y-m-d', strtotime('+14 days'));
$clientName = 'Teszt Ügyfél';
$clientTel = '123456789';
$clientCity = 'Budapest';
$clientAddress = 'Fő utca 1.';
$clientEmail = 'teszt@ugyfel.hu';

// Képek base64 kódolása
$logoPath = __DIR__ . '/../../../assets/img/brand/logo.png';
$logoData = base64_encode(file_get_contents($logoPath));
$logoSrc = 'data:image/png;base64,' . $logoData;

$partnerLogoPath = __DIR__ . '/../../../assets/img/logo.png';
$partnerLogoData = base64_encode(file_get_contents($partnerLogoPath));
$partnerLogoSrc = 'data:image/png;base64,' . $partnerLogoData;

// Számla HTML tartalom táblázatos elrendezéssel
$invoiceHtml = "
<!doctype html>
<html lang='hu'>
<head>
    <meta charset='utf-8'>
    <title>Számla</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { width: 100%; max-width: 800px; margin: auto; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table, .table th, .table td { border: 1px solid black; }
        .table th, .table td { padding: 8px; text-align: center; }
        .hr { border-top: 1px solid black; margin-top: 20px; margin-bottom: 20px; }
        .img-fluid { max-width: 100%; height: auto; }
        .row { width: 100%; display: table; }
        .col-6 { display: table-cell; width: 50%; vertical-align: middle; }
        .col-12 { width: 100%; }
        .align-center { vertical-align: middle; }
        .mb-0 { margin-bottom: 0; }
        .mt-2 { margin-top: 20px; }
        .me-2 { margin-right: 8px; }
        .d-flex { display: flex; }
        .justify-content-center { justify-content: center; }
        .align-items-center { align-items: center; }
        .blue{color:#0950dc;}
    </style>
</head>

<body>
    <div class='container mt-2'>
        <!-- Fejléc -->
        <table class='row text-center'>
            <tr>
                <td class='col-6 align-center'>
                    <img src='$logoSrc' class='img-fluid' alt='Logo'>
                </td>
                <td class='col-6 align-center'>
                    <h1 class='blue'>" . $translations['invoice'] . "</h1>
                </td>
            </tr>
        </table>
        <hr class='hr' />
        <!-- Cég és számla adatok -->
        <table class='row text-left'>
            <tr>
                <td class='col-6 align-center'>
                    <h4 class='blue'>" . $business_name . "</h4>
                    <small>" . $smtp_username . "</small>
                </td>
                <td class='col-6 align-center text-right'>
                    <p><b class='blue'>" . $translations["date-log"] . ":</b> $date</p>
                    <p><b class='blue'>" . $translations["invoiceid"] . "</b> $invoiceNumber</p>
                    <p><b class='blue'>" . $translations["userid"] . "</b> $userid</p>
                </td>
            </tr>
        </table>
        <hr class='hr' />
        <div class='text-left'>
            <p><strong>" . $translations["adressedinvoice"] . "</strong></p>
            <p>&emsp;<strong>$clientName</strong></p>
            <p>&emsp;$clientTel</p>
            <p>&emsp;$clientCity</p>
            <p>&emsp;$clientAddress</p>
            <p>&emsp;$clientEmail</p>
        </div>
        <hr class='hr' />
        <table class='table'>
            <thead>
                <tr>
                    <th>" . $translations["workerinvoice"] . "</th>
                    <th>" . $translations["paymenttype"] . "</th>
                    <th>" . $translations["date-log"] . "</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Sajt32</td>
                    <td>Készpénz</td>
                    <td>$date</td>
                </tr>
            </tbody>
        </table>
        <hr class='hr' />
        <table class='table'>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>" . $translations["invoicedescription"] . "</th>
                    <th>" . $translations["unitprice"] . "</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>333</td>
                    <td>Szolgáltatás</td>
                    <td>1000 HUF</td>
                </tr>
                <tr>
                    <td colspan='2' class='text-right'><strong>" . $translations["invoiceamount"] . "</strong></td>
                    <td><strong>1000 HUF</strong></td>
                </tr>
            </tbody>
        </table>
        <!-- Lábléc -->
        <table class='row' style='margin-top: 20px;'>
            <tr>
                <td class='col-6 align-center'>
                    <img src='$partnerLogoSrc' width='100' class='img-fluid' alt='GYM ONE Logo COPYRIGHT DO NOT REMOVE'>
                </td>
                <td class='col-6 align-center text-left'>
                    <p class='mb-0'>Partner Program - © 2024 GYM One</p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
";

$mpdf = new Mpdf();
$mpdf->WriteHTML($invoiceHtml);

$invoicePath = __DIR__ . "/../../../assets/docs/invoices/{$userid}-{$invoiceNumber}.pdf";
$mpdf->Output($invoicePath, \Mpdf\Output\Destination::FILE);

echo "A számla sikeresen létrehozva: $invoicePath";
