<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Gym Opening Hours</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>

<body>
    <div class="container">
        <h2 class="my-4">Gym Opening Hours</h2>

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

        $sql = "SELECT * FROM opening_hours";
        $result = $conn->query($sql);
        $opening_hours = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $opening_hours[$row["day_of_week"]] = [
                    "open_time" => $row["open_time"],
                    "close_time" => $row["close_time"]
                ];
            }
        }

        $days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
        ?>

        <table class="table">
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Open Time</th>
                    <th>Close Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($days as $key => $day): ?>
                    <tr>
                        <td><?php echo $day; ?></td>
                        <td><?php echo isset($opening_hours[$key]['open_time']) ? $opening_hours[$key]['open_time'] : 'Closed'; ?>
                        </td>
                        <td><?php echo isset($opening_hours[$key]['close_time']) ? $opening_hours[$key]['close_time'] : 'Closed'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>
</body>

</html>

<?php
$conn->close();
?>