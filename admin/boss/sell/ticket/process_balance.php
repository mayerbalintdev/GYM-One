<?php
session_start();

if (!isset($_SESSION['adminuser'])) {
    header("Location: ../");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userid = isset($_POST['userid']) ? trim($_POST['userid']) : '';

    // Magyar tizedesvessző -> pont, majd numerikus ellenőrzés
    $raw = isset($_POST['amount']) ? trim($_POST['amount']) : '';
    $raw = str_replace([' ', "\xc2\xa0"], '', $raw); // szóköz / nbsp ezreselválasztó
    $raw = str_replace(',', '.', $raw);
    $amount = is_numeric($raw) ? (float) $raw : 0;

    // Érvényes vásárló és pozitív összeg kell
    if ($userid === '' || $userid === 'N/A' || !ctype_digit($userid) || $amount <= 0) {
        header("Location: ../ticket/?userid=" . urlencode($userid));
        exit();
    }

    header("Location: ../payment/balance/?userid=" . urlencode($userid) . "&balance=" . urlencode($amount));
    exit();
}

header("Location: ../");
exit();
?>
