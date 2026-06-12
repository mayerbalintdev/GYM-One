<?php
session_start();

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

function get_db_connection()
{
    global $env_data;

    $db_host = $env_data['DB_SERVER'] ?? '';
    $db_username = $env_data['DB_USERNAME'] ?? '';
    $db_password = $env_data['DB_PASSWORD'] ?? '';
    $db_name = $env_data['DB_NAME'] ?? '';

    $conn = new mysqli($db_host, $db_username, $db_password, $db_name);

    if ($conn->connect_error) {
        die("Kapcsolódási hiba: " . $conn->connect_error);
    }

    return $conn;
}

$env_data = read_env_file('../.env');

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);


$login_error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $conn = get_db_connection();

    $stmt = $conn->prepare("SELECT userid, password, confirmed FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($userid, $hashed_password, $confirmed);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            if ($confirmed == 'Yes') {
                $current_datetime = date('Y-m-d H:i:s');
                $user_ip = $_SERVER['REMOTE_ADDR'];
                $update_stmt = $conn->prepare("UPDATE users SET lastlogin = ?, lastip = ? WHERE userid = ?");
                $update_stmt->bind_param("ssi", $current_datetime, $user_ip, $userid);
                $update_stmt->execute();
                $update_stmt->close();
                session_start();
                $_SESSION['userid'] = $userid;
                header("Location: ../dashboard");
                exit();
            } else {
                $login_error = $translations["acceptemailplease"];
            }
        } else {
            $login_error = $translations["notcorrectlogin"];
        }
    } else {
        $login_error = $translations["notcorrectlogin"];
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $translations["login"]; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/login-register.css">
    <link rel="shortcut icon" href="../assets/img/brand/favicon.png" type="image/x-icon">
    <style>
        /* ====== MINIMÁL DESIGN + visszafogott háttér (mint a regisztrációnál) ====== */
        html, body { background: #f4f6fb !important; }
        body::before, body::after { display: none !important; }

        #login {
            position: relative !important;
            display: block !important;
            align-items: flex-start !important;
            justify-content: flex-start !important;
            height: auto !important;
            min-height: 100vh;
            padding: 48px 0 0 !important;
            overflow: hidden;
            background:
                radial-gradient(circle, rgba(15, 23, 42, .05) 1px, transparent 1.6px) 0 0 / 26px 26px,
                #f4f6fb !important;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        #login::before, #login::after { display: none !important; }
        #login .container { margin-top: 0 !important; position: relative; z-index: 2; }
        #login .row.pt-4 { padding-top: 0 !important; }

        /* Halvány, lassan lélegző fényfoltok */
        .lg-bg { position: absolute; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
        .lg-blob { position: absolute; border-radius: 50%; filter: blur(90px); }
        .lg-blob-1 { width: 380px; height: 380px; background: #0950dc; opacity: .10; top: -130px; left: -90px; animation: lgDrift 22s ease-in-out infinite; }
        .lg-blob-2 { width: 340px; height: 340px; background: #6d28d9; opacity: .08; bottom: -120px; right: -90px; animation: lgDrift 26s ease-in-out infinite reverse; }
        .lg-blob-3 { width: 260px; height: 260px; background: #22d3ee; opacity: .06; top: 40%; right: 8%; animation: lgDrift 30s ease-in-out infinite; }
        @keyframes lgDrift {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(26px, -22px) scale(1.06); }
        }

        /* Letisztult kártya + beúszó animáció */
        #login .reg-card,
        #login .card {
            border: 1px solid #e9eef6 !important;
            border-radius: 18px !important;
            background: #ffffff !important;
            box-shadow: 0 8px 30px rgba(15, 23, 42, .07) !important;
            opacity: 0;
            transform: translateY(18px) scale(.99);
            animation: lgCardIn .6s cubic-bezier(.2, .7, .2, 1) forwards;
        }
        @keyframes lgCardIn { to { opacity: 1; transform: none; } }

        /* Fejléc */
        #login .lg-head { text-align: center; margin-bottom: 1.4rem; }
        #login .lg-logo { max-width: 150px; height: auto; margin-bottom: .9rem; }
        #login .lg-title { font-weight: 800; color: #0f172a; font-size: 1.7rem; letter-spacing: -.3px; margin: 0; }
        #login .lg-sub { color: #94a3b8; font-size: .92rem; margin-top: .3rem; margin-bottom: 0; }

        /* Lépcsőzetes belépő-animáció a tartalomra */
        #login .lg-anim { opacity: 0; transform: translateY(12px); animation: lgRise .55s cubic-bezier(.2, .7, .2, 1) forwards; }
        #login .lg-d1 { animation-delay: .12s; }
        #login .lg-d2 { animation-delay: .20s; }
        #login .lg-d3 { animation-delay: .28s; }
        #login .lg-d4 { animation-delay: .36s; }
        #login .lg-d5 { animation-delay: .44s; }
        @keyframes lgRise { to { opacity: 1; transform: none; } }

        /* Mezők */
        #login label { font-size: .85rem; font-weight: 600; color: #475569; margin-bottom: .3rem; }
        #login .form-control {
            border: 1.5px solid #e2e8f0;
            border-radius: 13px;
            padding: .7rem .9rem;
            font-size: .98rem;
            background: #f8fafc;
            color: #0f172a;
            height: auto;
            box-shadow: none;
            transition: border-color .15s, box-shadow .15s, background .15s;
        }
        #login .form-control:focus {
            border-color: #0950dc;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(9, 80, 220, .12);
            outline: none;
        }
        #login .form-group { margin-bottom: 1rem; }

        /* Gomb */
        #login .btn-primary {
            width: 100%;
            background: #0950dc !important;
            border: none !important;
            border-radius: 14px !important;
            padding: .8rem 1rem !important;
            font-weight: 700;
            font-size: 1.02rem;
            box-shadow: none !important;
            transition: background .15s, transform .15s;
        }
        #login .btn-primary:hover { background: #0742b8 !important; transform: translateY(-2px); }
        #login .btn-primary:active { transform: translateY(0); }

        /* Linkek */
        #login .lg-links { text-align: center; margin-top: 1rem; }
        #login .lg-links small { color: #64748b; font-size: .9rem; display: block; }
        #login .lg-links small + small { margin-top: .35rem; }
        #login .lg-links a { color: #0950dc; font-weight: 700; text-decoration: none; }
        #login .lg-links a:hover { text-decoration: underline; }

        /* GYM One copyright legalul */
        .lg-copyright {
            position: relative; z-index: 2;
            text-align: center; padding: 28px 16px 24px; margin-top: 36px;
            color: #94a3b8; font-size: .85rem;
        }
        .lg-copyright a { color: #0950dc; font-weight: 700; text-decoration: none; }
        .lg-copyright a:hover { text-decoration: underline; }
        .lg-copyright .lg-heart { color: #ef4444; }

        /* Mozgáscsökkentés igény esetén */
        @media (prefers-reduced-motion: reduce) {
            #login .card, #login .lg-anim, .lg-blob { animation: none !important; opacity: 1 !important; transform: none !important; }
        }
    </style>
</head>

<body>
    <div id="login">
        <div class="lg-bg">
            <span class="lg-blob lg-blob-1"></span>
            <span class="lg-blob lg-blob-2"></span>
            <span class="lg-blob lg-blob-3"></span>
        </div>
        <div class="container">
            <div class="row justify-content-center pt-4">
                <div class="col-lg-5 col-md-7">
                    <div class="card reg-card">
                        <div class="card-body">
                            <div class="lg-head lg-anim lg-d1">
                                <img class="lg-logo" src="../assets/img/brand/logo.png" title="<?php echo $business_name; ?>" alt="<?php echo $business_name; ?>">
                                <h1 class="lg-title"><?php echo $translations["login"]; ?></h1>
                                <p class="lg-sub"><?php echo htmlspecialchars($business_name); ?></p>
                            </div>
                            <?php if (!empty($login_error)) : ?>
                                <div class="alert alert-danger lg-anim"><?php echo $login_error; ?></div>
                            <?php endif; ?>
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                <div class="form-group lg-anim lg-d2">
                                    <label for="email"><?php echo $translations["email"]; ?></label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="form-group lg-anim lg-d3">
                                    <label for="password"><?php echo $translations["password"]; ?></label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-primary lg-anim lg-d4"><?php echo $translations["next"]; ?></button>
                            </form>
                            <div class="lg-links lg-anim lg-d5">
                                <small><?php echo $translations["youdonthaveaccount"]; ?> <span><a href="../register/"><?php echo $translations["registerbtn"]; ?></a></span></small>
                                <small><?php echo $translations["adminaccountlogin"]; ?> <span><a href="../admin/"><?php echo $translations["login"]; ?></a></span></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="lg-copyright">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($business_name); ?> &middot;
            <span><?php echo $translations["copyright"];?> <span class="lg-heart">&hearts;</span></span>
            <a href="https://gymoneglobal.com/?lang=<?php echo $lang_code; ?>" target="_blank" rel="noopener noreferrer">GYM One</a>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</body>

</html>