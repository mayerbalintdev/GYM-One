<?php
function createPayPalButton($email, $amount) {
    $paypalUrl = "https://www.paypal.com/cgi-bin/webscr";
    $businessEmail = htmlspecialchars($email);
    $paymentAmount = number_format($amount, 2, '.', '');

    $button = '
    <form action="' . $paypalUrl . '" method="post" target="_top">
        <input type="hidden" name="cmd" value="_xclick">
        <input type="hidden" name="business" value="' . $businessEmail . '">
        <input type="hidden" name="lc" value="US">
        <input type="hidden" name="item_name" value="Geciputtony">
        <input type="hidden" name="amount" value="' . $paymentAmount . '">
        <input type="hidden" name="currency_code" value="USD">
        <input type="hidden" name="button_subtype" value="services">
        <input type="hidden" name="no_note" value="1">
        <input type="hidden" name="cn" value="Add special instructions to the seller">
        <input type="hidden" name="no_shipping" value="1">
        <input type="hidden" name="rm" value="1">
        <input type="hidden" name="return" value="http://yourwebsite.com/success.php">
        <input type="hidden" name="cancel_return" value="http://yourwebsite.com/cancel.php">
        <input type="hidden" name="notify_url" value="http://yourwebsite.com/ipn.php">
        <input type="hidden" name="custom" value="custom_data">
        <input type="hidden" name="quantity" value="1">
        <input type="hidden" name="item_number" value="unique-item-id">
        <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_paynow_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
        <img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
    </form>';

    return $button;
}

$email = "bimbibubu@gmail.com";
$amount = 1.0; // Példa összeg

echo createPayPalButton($email, $amount);
?>
