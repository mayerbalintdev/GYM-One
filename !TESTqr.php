<!-- The code is not definitive! Do not use it if you open the project! It will result in errors! -->
<?php

require __DIR__ . '/vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

$userid = '4294967295';

$filename = __DIR__ . "/assets/img/logincard/{$userid}.png";

if (file_exists($filename)) {
    echo "<h2>QR kód a következőhöz: {$userid}</h2>";
    echo "<img src='assets/img/logincard/{$userid}.png' alt='QR kód'>";
} else {
    echo "<h2>Létrehozás folyamatban...</h2>";

    try {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($userid)
            ->size(300)
            ->margin(10)
            ->build();

        $result->saveToFile($filename);

        header("Refresh:0");

    } catch (Exception $e) {
        echo 'Hiba történt: ' . $e->getMessage();
    }
}

?>
