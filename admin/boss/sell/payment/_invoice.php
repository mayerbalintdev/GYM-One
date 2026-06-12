<?php
/**
 * GYM One – közös, modern számla-sablon (mPDF-kompatibilis).
 * A vázat, fejlécet, metaadatokat, vásárló-blokkot, dolgozó/fizetés sort és láblécet
 * adja; a tételsorokat ($itemsHtml) az adott oldal állítja elő és adja át.
 *
 * mPDF korlátok miatt minden elrendezés táblázat-alapú (nincs flexbox/grid).
 */

if (!function_exists('gymone_invoice_shell')) {

    /**
     * Logó forrás biztonságos feloldása mPDF-hez.
     * Elfogad: data: URI-t, http(s) URL-t, vagy fájlrendszeri útvonalat.
     * A képet GD-vel ÚJRAKÓDOLJA tiszta, szabványos PNG-vé (átlátszóságot megtartva),
     * mert az mPDF egyes PNG-formátumokat (pl. paletta/áttetszőség) nem tud beágyazni
     * nyersen → így a logó biztosan megjelenik.
     * Ha a forrás üres / nem létezik / nem érvényes kép → üres stringet ad vissza,
     * hogy a hívó szövegre essen vissza törött kép helyett.
     */
    function gymone_invoice_logo_src(string $logo): string
    {
        $logo = trim($logo);
        if ($logo === '') {
            return '';
        }

        // http(s) URL: hagyjuk, az mPDF letölti
        if (preg_match('#^https?://#i', $logo)) {
            return $logo;
        }

        // Nyers képbájtok megszerzése (data: URI vagy fájlrendszeri útvonal)
        $bytes = '';
        if (preg_match('#^data:[^;,]*;base64,(.*)$#is', $logo, $m)) {
            $bytes = base64_decode($m[1], true);
            $bytes = ($bytes === false) ? '' : $bytes;
        } elseif (is_file($logo) && is_readable($logo)) {
            $bytes = (string) @file_get_contents($logo);
        }
        if ($bytes === '') {
            return '';
        }

        // GD-vel újrakódoljuk tiszta PNG-vé (mPDF-barát)
        if (function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring($bytes);
            if ($img !== false) {
                imagealphablending($img, false);
                imagesavealpha($img, true);
                ob_start();
                imagepng($img);
                $clean = ob_get_clean();
                imagedestroy($img);
                if ($clean !== '' && $clean !== false) {
                    return 'data:image/png;base64,' . base64_encode($clean);
                }
            }
            // GD nem tudta dekódolni → nem érvényes kép
            return '';
        }

        // GD nélkül: csak ha a magic byte alapján valódi kép
        if (substr($bytes, 0, 8) === "\x89PNG\r\n\x1a\n") {
            return 'data:image/png;base64,' . base64_encode($bytes);
        }
        if (substr($bytes, 0, 2) === "\xFF\xD8") {
            return 'data:image/jpeg;base64,' . base64_encode($bytes);
        }
        if (substr($bytes, 0, 6) === 'GIF87a' || substr($bytes, 0, 6) === 'GIF89a') {
            return 'data:image/gif;base64,' . base64_encode($bytes);
        }
        return '';
    }

    function gymone_invoice_styles(): string
    {
        return <<<CSS
<style>
    body { font-family: dejavusans, sans-serif; color: #1f2937; font-size: 12px; }
    .inv-wrap { width: 100%; }

    /* Fejléc-sáv */
    .inv-head { width: 100%; margin-bottom: 6px; }
    .inv-head td { vertical-align: middle; }
    .inv-logo { max-height: 64px; max-width: 230px; }
    .inv-head-title { text-align: right; }
    .inv-badge {
        display: inline-block; background: #0950dc; color: #ffffff;
        font-size: 22px; font-weight: bold; letter-spacing: 1px;
        padding: 9px 20px; border-radius: 10px;
    }
    .inv-num { color: #6b7280; font-size: 12px; margin-top: 6px; }

    .inv-rule { height: 3px; background: #0950dc; border-radius: 3px; margin: 10px 0 16px; }

    /* Meta */
    .inv-meta { width: 100%; margin-bottom: 4px; }
    .inv-meta td { vertical-align: top; }
    .inv-meta-r { text-align: right; }
    .inv-biz { color: #0950dc; font-size: 16px; font-weight: bold; margin-bottom: 4px; }
    .inv-line { color: #6b7280; font-size: 11px; line-height: 1.5; }
    .inv-k { color: #9ca3af; font-size: 11px; }
    .inv-v { color: #111827; font-weight: bold; font-size: 12px; }
    .inv-meta-r div { margin-bottom: 3px; }

    /* Vásárló-blokk */
    .inv-client {
        background: #f3f6fc; border: 1px solid #dbe5fb; border-radius: 12px;
        padding: 14px 16px; margin: 14px 0;
    }
    .inv-client-cap { color: #6b7280; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
    .inv-client-name { color: #0f172a; font-size: 14px; font-weight: bold; margin-bottom: 2px; }
    .inv-client-line { color: #374151; font-size: 11px; line-height: 1.6; }

    /* Táblázatok */
    .inv-table { width: 100%; border-collapse: collapse; margin: 6px 0 14px; }
    .inv-table th, .inv-table td { padding: 9px 12px; font-size: 11px; }
    .inv-th { background: #0950dc; color: #ffffff; text-align: left; font-weight: bold; }
    .inv-th:first-child { border-radius: 8px 0 0 0; }
    .inv-th:last-child { border-radius: 0 8px 0 0; }
    .inv-table tbody td { border-bottom: 1px solid #e5e7eb; color: #1f2937; }
    .inv-table tbody tr:nth-child(even) td { background: #f9fafb; }
    .inv-r { text-align: right; }
    .inv-total-row td { background: #eef4ff; font-weight: bold; color: #0f172a; font-size: 12px; border-bottom: none; }

    .inv-section-cap { color: #9ca3af; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; margin: 14px 0 4px; }

    /* Lábléc */
    .inv-foot { width: 100%; margin-top: 26px; border-top: 1px solid #e5e7eb; padding-top: 12px; }
    .inv-foot td { vertical-align: middle; }
    .inv-foot-logo img { max-width: 90px; }
    .inv-foot-txt { text-align: right; color: #9ca3af; font-size: 10px; }
</style>
CSS;
    }

    function gymone_invoice_shell(array $c, string $itemsHtml): string
    {
        $t = $c['t'];
        $esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $styles = gymone_invoice_styles();

        $title          = $esc($c['title'] ?? ($t['invoice'] ?? 'Invoice'));
        $logoSrc        = gymone_invoice_logo_src($c['logoSrc'] ?? ($c['logoPath'] ?? ''));
        $partnerLogoSrc = gymone_invoice_logo_src($c['partnerLogoSrc'] ?? ($c['partnerLogoPath'] ?? ''));
        $year           = $esc($c['year'] ?? date('Y'));

        $businessName  = $esc($c['businessName'] ?? '');
        $businessEmail = $esc($c['businessEmail'] ?? '');
        $businessPhone = $esc($c['businessPhone'] ?? '');

        $date          = $esc($c['date'] ?? '');
        $invoiceNumber = $esc($c['invoiceNumber'] ?? '');
        $userid        = $esc($c['userid'] ?? '');

        $clientName    = $esc($c['clientName'] ?? '');
        $clientCity    = $esc($c['clientCity'] ?? '');
        $clientAddress = $esc($c['clientAddress'] ?? '');
        $clientEmail   = $esc($c['clientEmail'] ?? '');

        $workerName    = $esc($c['workerName'] ?? '');
        $paymentType   = $esc($c['paymentType'] ?? '');

        $lDate    = $esc($t['date-log'] ?? 'Date');
        $lInvId   = $esc($t['invoiceid'] ?? 'Invoice ID');
        $lUserId  = $esc($t['userid'] ?? 'User ID');
        $lAddr    = $esc($t['adressedinvoice'] ?? 'Billed to');
        $lWorker  = $esc($t['workerinvoice'] ?? 'Worker');
        $lPayType = $esc($t['paymenttype'] ?? 'Payment');

        // Logók: kép, ha van; különben szöveges tartalék (nincs törött kép)
        $topLogoHtml = $logoSrc !== ''
            ? "<img src='{$logoSrc}' class='inv-logo' alt='Logo'>"
            : "<span style='font-size:20px; font-weight:bold; color:#0950dc;'>{$businessName}</span>";

        $partnerLogoHtml = $partnerLogoSrc !== ''
            ? "<img src='{$partnerLogoSrc}' width='90' alt='GYM ONE Logo COPYRIGHT DO NOT REMOVE'>"
            : "<span style='font-size:13px; font-weight:bold; color:#0950dc;'>GYM One</span>";

        return "
<!doctype html>
<html lang='hu'>
<head>
    <meta charset='utf-8'>
    <title>{$title} - {$invoiceNumber}</title>
    {$styles}
</head>
<body>
    <div class='inv-wrap'>

        <table class='inv-head'>
            <tr>
                <td>{$topLogoHtml}</td>
                <td class='inv-head-title'>
                    <span class='inv-badge'>{$title}</span>
                    <div class='inv-num'>#{$invoiceNumber}</div>
                </td>
            </tr>
        </table>

        <div class='inv-rule'></div>

        <table class='inv-meta'>
            <tr>
                <td>
                    <div class='inv-biz'>{$businessName}</div>
                    <div class='inv-line'>{$businessEmail}</div>
                    <div class='inv-line'>{$businessPhone}</div>
                </td>
                <td class='inv-meta-r'>
                    <div><span class='inv-k'>{$lDate}:</span> <span class='inv-v'>{$date}</span></div>
                    <div><span class='inv-k'>{$lInvId}:</span> <span class='inv-v'>{$invoiceNumber}</span></div>
                    <div><span class='inv-k'>{$lUserId}:</span> <span class='inv-v'>{$userid}</span></div>
                </td>
            </tr>
        </table>

        <div class='inv-client'>
            <div class='inv-client-cap'>{$lAddr}</div>
            <div class='inv-client-name'>{$clientName}</div>
            <div class='inv-client-line'>{$clientCity}</div>
            <div class='inv-client-line'>{$clientAddress}</div>
            <div class='inv-client-line'>{$clientEmail}</div>
        </div>

        <table class='inv-table'>
            <thead>
                <tr>
                    <th class='inv-th'>{$lWorker}</th>
                    <th class='inv-th'>{$lPayType}</th>
                    <th class='inv-th inv-r'>{$lDate}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{$workerName}</td>
                    <td>{$paymentType}</td>
                    <td class='inv-r'>{$date}</td>
                </tr>
            </tbody>
        </table>

        {$itemsHtml}

        <table class='inv-foot'>
            <tr>
                <td class='inv-foot-logo'>{$partnerLogoHtml}</td>
                <td class='inv-foot-txt'>Partner Program &middot; &copy; {$year} GYM One</td>
            </tr>
        </table>

    </div>
</body>
</html>";
    }
}