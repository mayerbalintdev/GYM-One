<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userid = $_POST['userid'];
    $amount = $_POST['amount'];
    
    header("Location: ../payment/balance/?userid=$userid&balance=$amount");
    exit();
}
?>
