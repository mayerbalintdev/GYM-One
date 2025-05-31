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

$env_data = read_env_file('../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

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

// API!
$file_path = 'https://api.gymoneglobal.com/latest/version.txt';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $file_path);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$latest_version = curl_exec($ch);
curl_close($ch);

$current_version = $version;

$is_new_version_available = version_compare($latest_version, $current_version) > 0;

$conn->close();
?>
<?php

/**
 * GitHub Updater Script
 *
 * This script will download the latest version from a GitHub repository,
 * unpacks and then updates the current installation, keeping the images.
 * This script is located in the www.domain.com\admin\updater folder,
 * but updates the main directory (www.domain.com).
 */

if (isset($_POST['action']) && $_POST['action'] == 'run_update') {
    header('Content-Type: application/json');

    $result = runUpdateProcess();
    echo json_encode($result);
    exit;
}

function runUpdateProcess()
{
    ob_start();
    $success = false;
    $message = '';
    $log = '';
    $version = '';

    try {
        // Konfiguráció
        $config = [
            'github_username' => 'mayerbalintdev',
            'github_repository' => 'GYM-One',
            'github_branch' => 'main',
            'zip_file' => 'update.zip',
            'extract_folder' => 'update_extract',
            'image_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'],
            'image_folders' => ['images', 'img', 'uploads', 'media'],
            'protected_files' => ['.env.example', '.env.backup'],
            'root_directory' => '../../',
        ];

        $current_dir = __DIR__;

        $env_data = read_env_file('../../.env');

        $lang_code = $env_data['LANG_CODE'] ?? '';

        $lang = $lang_code;

        $langDir = __DIR__ . "/../../assets/lang/";

        $langFile = $langDir . "$lang.json";

        if (!file_exists($langFile)) {
            die("A nyelvi fájl nem található: $langFile");
        }

        $translations = json_decode(file_get_contents($langFile), true);

        $root_dir = realpath($current_dir . DIRECTORY_SEPARATOR . $config['root_directory']);

        if (!$root_dir) {
            throw new Exception("The library of the main website cannot be found: " . $current_dir . DIRECTORY_SEPARATOR . $config['root_directory']);
        }

        $zip_file_path = $current_dir . DIRECTORY_SEPARATOR . $config['zip_file'];
        $extract_folder_path = $current_dir . DIRECTORY_SEPARATOR . $config['extract_folder'];

        date_default_timezone_set(date_default_timezone_get());

        cleanup($zip_file_path, $extract_folder_path);

        $download_url = "https://github.com/{$config['github_username']}/{$config['github_repository']}/archive/refs/heads/{$config['github_branch']}.zip";
        logMessage($translations["downloadpage"] . ": $download_url");

        $version = getLatestVersion($config);
        logMessage($translations["updatenewversion"] . " $version");



        logMessage($translations["updatedownloadgithub"]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $download_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $zip_content = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception($translations["unexpected-error"] . curl_error($ch));
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code != 200) {
            throw new Exception("Download error: HTTP code $http_code");
        }

        curl_close($ch);

        file_put_contents($zip_file_path, $zip_content);
        LogMessage($translations["updatezipdownload"] . " $zip_file_path");

        logMessage($translations["updatezipunpack"]);
        $zip = new ZipArchive;
        if ($zip->open($zip_file_path) !== true) {
            throw new Exception("The ZIP file could not be opened.");
        }

        if (!is_dir($extract_folder_path)) {
            mkdir($extract_folder_path, 0755, true);
        }

        $zip->extractTo($extract_folder_path);
        $zip->close();
        logMessage($translations["updatezipupdate"] . "$extract_folder_path");

        $extracted_files = scandir($extract_folder_path);
        $main_folder = null;

        foreach ($extracted_files as $file) {
            if ($file != '.' && $file != '..' && is_dir($extract_folder_path . DIRECTORY_SEPARATOR . $file)) {
                $main_folder = $file;
                break;
            }
        }

        if (!$main_folder) {
            throw new Exception("There is no main folder in the unpacked files.");
        }

        $source_dir = $extract_folder_path . DIRECTORY_SEPARATOR . $main_folder;
        logMessage($translations["updatesourcefolder"] . " $source_dir");

        logMessage($translations["updateroot"]);
        logMessage($translations["updatesecuredfiles"]);

        copyFilesRecursively($source_dir, $root_dir, $config);

        updateEnvVersion($root_dir, $version);

        $success = true;
        $message = $translations["updateend"] . " $version";
        logMessage($translations["updateend"]);

        cleanup($zip_file_path, $extract_folder_path);
    } catch (Exception $e) {
        $message = "Hiba történt: " . $e->getMessage();
        logMessage("HIBA: " . $e->getMessage());
    }

    $log = ob_get_clean();

    return [
        'success' => $success,
        'message' => $message,
        'log' => $log,
        'version' => $version
    ];
}

function getLatestVersion($config)
{
    $api_url = "https://api.github.com/repos/{$config['github_username']}/{$config['github_repository']}/releases/latest";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP Updater Script');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['tag_name'])) {
            return preg_replace('/^[vV]/', '', $data['tag_name']);
        }
    }

    $api_url = "https://api.github.com/repos/{$config['github_username']}/{$config['github_repository']}/commits/{$config['github_branch']}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP Updater Script');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['sha'])) {
            return substr($data['sha'], 0, 7);
        }
    }

    return date('Ymd.His');
}

function updateEnvVersion($root_dir, $version)
{
    $env_file = $root_dir . DIRECTORY_SEPARATOR . '.env';
    $env_data = read_env_file('../../.env');

    $lang_code = $env_data['LANG_CODE'] ?? '';

    $lang = $lang_code;

    $langDir = __DIR__ . "/../../assets/lang/";

    $langFile = $langDir . "$lang.json";

    if (!file_exists($langFile)) {
        die("A nyelvi fájl nem található: $langFile");
    }

    $translations = json_decode(file_get_contents($langFile), true);

    if (file_exists($env_file)) {
        logMessage($translations["updateenvupdate"]);

        $env_content = file_get_contents($env_file);

        if (preg_match('/^APP_VERSION=.*$/m', $env_content)) {
            $new_env_content = preg_replace('/^APP_VERSION=.*$/m', "APP_VERSION=V$version", $env_content);
            file_put_contents($env_file, $new_env_content);
            logMessage($translations["updateenvupdated"] . " V$version");
        } else {
            file_put_contents($env_file, $env_content . "\nAPP_VERSION=V$version\n");
            logMessage($translations["updateenvupdated"] . " V$version");
        }
    } else {
        logMessage("Unpacked source folder:.env file not found, version update skipped.");
    }
}

function logMessage($message)
{
    $date = date('Y-m-d H:i:s');
    echo "[$date] $message" . PHP_EOL;
}

function cleanup($zip_file_path, $extract_folder_path)
{
    $env_data = read_env_file('../../.env');

    $lang_code = $env_data['LANG_CODE'] ?? '';

    $lang = $lang_code;

    $langDir = __DIR__ . "/../../assets/lang/";

    $langFile = $langDir . "$lang.json";

    if (!file_exists($langFile)) {
        die("A nyelvi fájl nem található: $langFile");
    }

    $translations = json_decode(file_get_contents($langFile), true);

    logMessage($translations["updatedelettemp"]);

    if (file_exists($zip_file_path)) {
        unlink($zip_file_path);
    }

    if (is_dir($extract_folder_path)) {
        deleteDirectory($extract_folder_path);
    }

    logMessage($translations["updatedeleted"]);
}

function deleteDirectory($dir)
{
    if (!is_dir($dir)) {
        return;
    }

    $files = array_diff(scandir($dir), ['.', '..']);

    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;

        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }

    return rmdir($dir);
}

function copyFilesRecursively($source, $destination, $config)
{
    $env_data = read_env_file('../../.env');

    $lang_code = $env_data['LANG_CODE'] ?? '';

    $lang = $lang_code;

    $langDir = __DIR__ . "/../../assets/lang/";

    $langFile = $langDir . "$lang.json";

    if (!file_exists($langFile)) {
        die("A nyelvi fájl nem található: $langFile");
    }

    $translations = json_decode(file_get_contents($langFile), true);

    $dir = opendir($source);

    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    while (($file = readdir($dir)) !== false) {
        if ($file == '.' || $file == '..') {
            continue;
        }

        $source_path = $source . DIRECTORY_SEPARATOR . $file;
        $dest_path = $destination . DIRECTORY_SEPARATOR . $file;

        if (is_dir($source_path)) {
            $is_image_folder = false;
            foreach ($config['image_folders'] as $img_folder) {
                if (
                    strpos($source_path, DIRECTORY_SEPARATOR . $img_folder) !== false ||
                    basename($source_path) == $img_folder
                ) {
                    $is_image_folder = true;
                    break;
                }
            }

            if ($is_image_folder) {
                logMessage($translations["updateskippfolder"] . ": $source_path");
                continue;
            }

            copyFilesRecursively($source_path, $dest_path, $config);
        } else {
            $file_extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $is_image = in_array($file_extension, $config['image_extensions']);
            $is_protected = in_array($file, $config['protected_files']);
            $is_env = ($file === '.env');

            if (!$is_image && !$is_protected && !$is_env) {
                if (!file_exists($dest_path) || filemtime($source_path) > filemtime($dest_path)) {
                    copy($source_path, $dest_path);
                    logMessage($translations["updateupdatedfile"] . ": $dest_path");
                }
            } else {
                if ($is_image) {
                    logMessage($translations["updateskippfile"] . ": $source_path");
                } elseif ($is_protected) {
                    logMessage($translations["updateskippsecured"] . ": $source_path");
                } elseif ($is_env) {
                    logMessage($translations["updateskippenv"] . ": $source_path");
                }
            }
        }
    }

    closedir($dir);
}

if (!extension_loaded('zip')) {
    die("The PHP ZIP extension is not installed.");
}

if (!function_exists('curl_init')) {
    die("The PHP cURL extension is not installed.");
}

?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations["updatepage"]; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="https://gymoneglobal.com/assets/img/logo.png" type="image/x-icon">
    <style>
        :root {
            --primary-color: #0d6efd;
            --primary-hover: #0b5ed7;
            --dark-color: #212529;
            --light-color: #f8f9fa;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
        }

        body {
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
        }

        .updater-container {
            max-width: 900px;
            margin: 2rem auto;
            background-color: #fff;
            border-radius: 1rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            transition: all 0.3s ease;
        }

        @media (max-width: 768px) {
            .updater-container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .header h1 {
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            font-size: 2.2rem;
        }

        @media (max-width: 576px) {
            .header h1 {
                font-size: 1.8rem;
            }
        }

        .header p {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 0;
        }

        .progress {
            height: 1.2rem;
            margin: 1.5rem 0;
            border-radius: 0.75rem;
            overflow: hidden;
            background-color: #e9ecef;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .progress-bar {
            background-color: var(--primary-color);
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .log-container {
            background-color: var(--dark-color);
            color: #ddd;
            border-radius: 0.5rem;
            padding: 1rem;
            height: 300px;
            overflow-y: auto;
            font-family: 'Consolas', 'Monaco', monospace;
            margin-top: 1.5rem;
            white-space: pre-line;
            font-size: 0.9rem;
            scrollbar-width: thin;
            scrollbar-color: #666 var(--dark-color);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .log-container::-webkit-scrollbar {
            width: 8px;
        }

        .log-container::-webkit-scrollbar-track {
            background: var(--dark-color);
        }

        .log-container::-webkit-scrollbar-thumb {
            background-color: #666;
            border-radius: 4px;
        }

        .btn-update {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 600;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08);
            font-size: 1.05rem;
        }

        .btn-update:hover,
        .btn-update:focus {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 7px 14px rgba(50, 50, 93, 0.1), 0 3px 6px rgba(0, 0, 0, 0.08);
        }

        .btn-update:active {
            transform: translateY(1px);
            box-shadow: 0 2px 5px rgba(50, 50, 93, 0.1), 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .btn-update:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .status-message {
            margin: 1.5rem 0;
            padding: 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            border-left: 4px solid transparent;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .alert-primary {
            border-left-color: var(--primary-color);
            background-color: rgba(13, 110, 253, 0.05);
        }

        .alert-success {
            border-left-color: var(--success-color);
            background-color: rgba(25, 135, 84, 0.05);
        }

        .alert-danger {
            border-left-color: var(--danger-color);
            background-color: rgba(220, 53, 69, 0.05);
        }

        .alert-info {
            border-left-color: var(--primary-color);
            background-color: rgba(13, 202, 240, 0.05);
        }

        .countdown {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 1rem 0;
            text-align: center;
            color: var(--dark-color);
        }

        .countdown i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }

        .version-info {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 1.2rem;
            font-weight: 700;
            padding: 0.5rem;
            border-radius: 0.5rem;
            background-color: rgba(13, 110, 253, 0.08);
            color: var(--primary-color);
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }

        .step-indicator::before {
            content: '';
            position: absolute;
            top: 1.5rem;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #e9ecef;
            z-index: 1;
        }

        .step {
            text-align: center;
            position: relative;
            z-index: 2;
            width: 25%;
        }

        .step-circle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 3rem;
            height: 3rem;
            background-color: #e9ecef;
            border-radius: 50%;
            margin: 0 auto 0.75rem;
            color: #6c757d;
            font-weight: 600;
            transition: all 0.3s;
            position: relative;
            z-index: 3;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .step-title {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
            margin-top: 0.5rem;
            transition: all 0.3s;
        }

        @media (max-width: 576px) {
            .step-title {
                font-size: 0.75rem;
            }

            .step-circle {
                width: 2.5rem;
                height: 2.5rem;
            }
        }

        .step.active .step-circle {
            background-color: var(--primary-color);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 4px 10px rgba(13, 110, 253, 0.3);
        }

        .step.active .step-title {
            color: var(--dark-color);
            font-weight: 600;
        }

        .step.completed .step-circle {
            background-color: var(--success-color);
            color: white;
        }

        .step.completed .step-title {
            color: var(--success-color);
        }

        .step.completed .step-circle::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
        }

        .line-progress {
            position: absolute;
            top: 1.5rem;
            left: calc(12.5% + 1.5rem);
            right: calc(12.5% + 1.5rem);
            height: 2px;
            background-color: #e9ecef;
            z-index: 1;
        }

        .line-progress .progress-line {
            height: 100%;
            width: 0%;
            background-color: var(--success-color);
            transition: width 0.5s ease;
        }

        @media (max-width: 767px) {
            .step-indicator {
                flex-direction: column;
                margin-left: 2rem;
                margin-right: 1rem;
                margin-bottom: 2rem;
            }

            .step-indicator::before {
                top: 0;
                bottom: 0;
                left: 1.5rem;
                width: 2px;
                height: auto;
                right: auto;
            }

            .step {
                display: flex;
                align-items: center;
                width: 100%;
                margin-bottom: 1.5rem;
                text-align: left;
            }

            .step-circle {
                margin: 0 1.5rem 0 0;
            }

            .step-title {
                margin-top: 0;
            }

            .line-progress {
                left: 1.5rem;
                top: 0;
                bottom: 0;
                width: 2px;
                height: calc(100% - 3rem);
                right: auto;
            }

            .line-progress .progress-line {
                width: 100%;
                height: 0%;
            }
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="updater-container">
            <div class="header">
                <img src="https://gymoneglobal.com/assets/img/text-color-logo.png" width="270px" alt="" class="img img-fluid">
                <p class="text-muted"><?php echo $translations["readytoupdate"]; ?></p>
            </div>

            <div class="step-indicator" id="step-indicator">
                <div class="line-progress">
                    <div class="progress-line" id="progress-line"></div>
                </div>
                <div class="step" id="step-1">
                    <div class="step-circle">1</div>
                    <div class="step-title"><?php echo $translations["updaterstepone"] ?? "Előkészítés"; ?></div>
                </div>
                <div class="step" id="step-2">
                    <div class="step-circle">2</div>
                    <div class="step-title"><?php echo $translations["downloadbtn"] ?? "Letöltés"; ?></div>
                </div>
                <div class="step" id="step-3">
                    <div class="step-circle">3</div>
                    <div class="step-title"><?php echo $translations["updatepage"] ?? "Frissítés"; ?></div>
                </div>
                <div class="step" id="step-4">
                    <div class="step-circle">4</div>
                    <div class="step-title"><?php echo $translations["updaterstepfour"] ?? "Befejezés"; ?></div>
                </div>
            </div>

            <div id="status-message" class="status-message alert alert-primary" role="alert">
                <i class="fas fa-info-circle me-2"></i> <?php echo $translations["updatetextone"]; ?>
            </div>

            <div class="text-center mb-4">
                <button id="update-button" class="btn btn-update">
                    <i class="fas fa-sync-alt me-2"></i> <?php echo $translations["updatebtn"]; ?>
                </button>
            </div>

            <div id="progress-container" style="display: none;">
                <div class="progress">
                    <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
                </div>
                <div id="countdown" class="countdown">
                    <i class="fas fa-clock"></i> <?php echo $translations["updateback"]; ?> <span id="time-remaining">0:00</span>
                </div>
            </div>

            <div id="version-info" class="version-info" style="display: none;"></div>

            <div class="log-container" id="log-container">
                <?php echo $translations["waittoupdate"]; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            let updateRunning = false;
            let startTime;
            let timerInterval;
            let progressInterval;
            let currentStep = 0;
            const totalSteps = 4;
            const isMobile = window.innerWidth < 768;
            let updateCompleted = false;

            $("#update-button").click(function() {
                if (updateCompleted) {
                    window.location.href = '../../';
                    return;
                }

                if (updateRunning) return;

                updateRunning = true;
                startTime = new Date();

                $("#update-button").prop("disabled", true).html('<i class="fas fa-spinner fa-spin me-2"></i> <?php echo $translations["updateinprogress"]; ?>');
                $("#status-message").removeClass("alert-primary alert-success alert-danger").addClass("alert-info").html('<i class="fas fa-spinner fa-spin me-2"></i> <?php echo $translations["updateinprogress"]; ?>');
                $("#progress-container").show();
                $("#log-container").html("<?php echo $translations["updatestarting"]; ?>\n");

                updateStepIndicator(1);

                startTimers();

                $.ajax({
                    url: window.location.href,
                    type: "POST",
                    data: {
                        action: "run_update"
                    },
                    dataType: "json",
                    success: function(response) {
                        updateRunning = false;
                        updateCompleted = true;
                        clearInterval(timerInterval);
                        clearInterval(progressInterval);

                        updateStepIndicator(4);
                        updateProgressLine(100);

                        updateProgressBar(100);

                        $("#log-container").html(response.log);
                        $("#log-container").scrollTop($("#log-container")[0].scrollHeight);

                        // Change button text to indicate completion and direction
                        $("#update-button").prop("disabled", false).html('<i class="fas fa-arrow-right me-2"></i> <?php echo $translations["gotomainpage"] ?? "Vissza a főoldalra"; ?>');

                        if (response.success) {
                            $("#status-message").removeClass("alert-primary alert-info alert-danger").addClass("alert-success").html('<i class="fas fa-check-circle me-2"></i> ' + response.message);

                            if (response.version) {
                                $("#version-info").show().html('<i class="fas fa-tag me-2"></i> <?php echo $translations["updatenewversion"]; ?> V' + response.version);
                            }
                        } else {
                            $("#status-message").removeClass("alert-primary alert-info alert-success").addClass("alert-danger").html('<i class="fas fa-exclamation-triangle me-2"></i> ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        updateRunning = false;
                        clearInterval(timerInterval);
                        clearInterval(progressInterval);

                        $("#status-message").removeClass("alert-primary alert-info alert-success").addClass("alert-danger").html('<i class="fas fa-exclamation-triangle me-2"></i> <?php echo $translations["updateerror"]; ?>');
                        $("#log-container").append("\n<?php echo $translations["unexpected-error"]; ?>: " + error);
                        $("#update-button").prop("disabled", false).html('<i class="fas fa-sync-alt me-2"></i><?php echo $translations["updatetryagain"]; ?>');
                    }
                });
            });

            function startTimers() {
                timerInterval = setInterval(updateTimer, 1000);

                progressInterval = setInterval(function() {
                    const elapsedTime = (new Date() - startTime) / 1000;
                    let progress = 0;

                    if (elapsedTime < 3) {
                        progress = (elapsedTime / 3) * 15;
                        updateStepIndicator(1);
                    } else if (elapsedTime < 10) {
                        progress = 15 + ((elapsedTime - 3) / 7) * 35;
                        updateStepIndicator(2);
                    } else {
                        progress = 50 + ((elapsedTime - 10) / 15) * 45;
                        if (progress > 95) progress = 95;
                        updateStepIndicator(3);
                    }

                    updateProgressBar(progress);
                    updateProgressLine(progress / 100 * 3);
                }, 500);
            }

            function updateTimer() {
                const elapsedSeconds = Math.floor((new Date() - startTime) / 1000);
                const estimatedTotalSeconds = 60;
                let remainingSeconds = Math.max(0, estimatedTotalSeconds - elapsedSeconds);

                const minutes = Math.floor(remainingSeconds / 60);
                const seconds = remainingSeconds % 60;

                $("#time-remaining").text(minutes + ":" + (seconds < 10 ? "0" : "") + seconds);

                if (remainingSeconds <= 0) {
                    clearInterval(timerInterval);
                }
            }

            function updateProgressBar(percentage) {
                const roundedPercentage = Math.round(percentage);
                $("#progress-bar").css("width", roundedPercentage + "%").text(roundedPercentage + "%");
            }

            function updateProgressLine(completedSteps) {
                if (isMobile) {
                    const heightPercentage = Math.min(100, (completedSteps / (totalSteps - 1)) * 100);
                    $("#progress-line").css("height", heightPercentage + "%");
                } else {
                    const widthPercentage = Math.min(100, (completedSteps / (totalSteps - 1)) * 100);
                    $("#progress-line").css("width", widthPercentage + "%");
                }
            }

            function updateStepIndicator(step) {
                if (currentStep === step) return;

                currentStep = step;

                $(".step").removeClass("active completed");

                for (let i = 1; i <= totalSteps; i++) {
                    if (i < step) {
                        $("#step-" + i).addClass("completed");
                    } else if (i === step) {
                        $("#step-" + i).addClass("active");
                    }
                }
            }

            $(window).resize(function() {
                const newIsMobile = window.innerWidth < 768;
                if (newIsMobile !== isMobile) {
                    location.reload();
                }
            });
        });
    </script>
</body>

</html>