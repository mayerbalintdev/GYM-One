<?php
// Indítjuk a session-t, hogy tárolhassuk a kosár tartalmát
session_start();

// Ha még nincs kosár, hozzuk létre
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Ellenőrizzük, hogy a form elküldésre került-e
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['quantities'])) {
    // Bejárjuk a választott mennyiségeket
    foreach ($_POST['quantities'] as $product_id => $quantity) {
        // Ha a mennyiség nagyobb, mint 0 és a termék érvényes, adjuk hozzá a kosárhoz
        if ($quantity > 0) {
            // Ellenőrizzük, hogy már szerepel-e a termék a kosárban
            if (isset($_SESSION['cart'][$product_id])) {
                // Ha már benne van, frissítjük a mennyiséget
                $_SESSION['cart'][$product_id] += $quantity;
            } else {
                // Ha még nincs, hozzáadjuk újként
                $_SESSION['cart'][$product_id] = $quantity;
            }
        }
    }
}

// Átirányítjuk a felhasználót a kosár oldalra
header('Location: ../payment/item/index.php');
exit();
?>
