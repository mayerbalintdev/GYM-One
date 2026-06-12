/**
 * GYM One – beléptető (check-in) frontend
 *
 * A fájl az index.php MELLÉ kerül (ugyanaz a mappa).
 * Az index.php-ben, ebben a sorrendben:
 *   <script src="https://unpkg.com/@zxing/browser@0.1.5"></script>
 *   <script>window.translations = <?php echo json_encode($translations); ?>;</script>
 *   <script src="checkin.js"></script>
 *
 * window.translations KELL (nem const). QR-hez @zxing/browser (global: ZXingBrowser).
 */
(function () {
    'use strict';

    const t = window.translations || {};
    // Az endpointokat a checkin.js helyéhez kötjük (a php-k ugyanabban a mappában),
    // így mindegy, hogy az oldal /dashboard/ vagy /dashboard/index.php URL-en fut.
    const BASE = (document.currentScript && document.currentScript.src)
        ? document.currentScript.src.replace(/[^/]*$/, '')
        : '';
    const ENDPOINT = BASE + 'process.php';
    const SEARCH_ENDPOINT = BASE + 'search.php';
    // Profilképek: assets/img/profiles/<userid>.png — a dashboardról ../../assets/...
    const PROFILE_BASE = BASE + '../../assets/img/profiles/';

    let scanCompleted = false;
    let scanning = false;
    let userData = {};
    let verified = false;   // KÖTELEZŐ: a "Tovább" csak a személy megerősítése után aktív
    let codeReader = null;
    let scanControls = null;
    let searchTimer = null;
    let searchAbort = null;

    // --- segédek -----------------------------------------------------------

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    let els = {};
    function cacheEls() {
        els = {
            video:          document.getElementById('video'),
            result:         document.getElementById('result'),
            checkmark:      document.getElementById('checkmark'),
            error:          document.getElementById('error'),
            qrCodeContent:  document.getElementById('qrcodeContent'),
            continueButton: document.getElementById('continueButton'),
            userDetails:    document.getElementById('userDetails'),
            nextButton:     document.getElementById('nextButton'),
        };
    }

    function initials(d) {
        const a = ((d.firstname || '').trim()[0] || '');
        const b = ((d.lastname || '').trim()[0] || '');
        return (a + b).toUpperCase() || '?';
    }

    function statusBadge(status) {
        const ok = status === t.valid;
        return `<span class="cm-badge ${ok ? 'ok' : 'bad'}">` +
               `<i class="bi ${ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill'}"></i>` +
               `${escapeHtml(status)}</span>`;
    }

    function row(icon, label, valueHtml) {
        return `<div class="cm-row">
                    <span class="cm-row-label"><i class="bi ${icon}"></i>${escapeHtml(label)}</span>
                    <span class="cm-row-value">${valueHtml}</span>
                </div>`;
    }

    function avatarHtml(d) {
        const ini = escapeHtml(initials(d));
        const hint = escapeHtml(t['verify-btn'] || 'Profil ellenőrzése');
        const img = d.userid
            ? `<img class="cm-avatar-img" src="${escapeHtml(PROFILE_BASE + encodeURIComponent(d.userid) + '.png')}" alt="" onerror="this.remove()">`
            : '';
        return `<div class="cm-avatar cm-avatar--zoom" title="${hint}">
                    <span class="cm-avatar-ini">${ini}</span>
                    ${img}
                    <span class="cm-avatar-zoom"><i class="bi bi-zoom-in"></i></span>
                </div>`;
    }

    function updateNextState() {
        // Tovább csak ha a bérlet érvényes ÉS a személyt megerősítették
        els.nextButton.disabled = !(userData.ticket_status === t.valid && verified);
    }

    function renderUserDetails(d) {
        els.userDetails.classList.remove('cm-verified');
        els.userDetails.innerHTML =
            `<div class="cm-profile">
                ${avatarHtml(d)}
                <div class="cm-profile-name">${escapeHtml(d.firstname)} ${escapeHtml(d.lastname)}</div>
                <div class="cm-profile-status">${statusBadge(d.ticket_status)}</div>
                <div class="cm-verify">
                    <button type="button" class="cm-verify-cta">
                        <i class="bi bi-hand-index-thumb-fill"></i>
                        <span>${escapeHtml(t['verify-cta'] || 'Kattints a profilképre az ellenőrzéshez')}</span>
                    </button>
                    <div class="cm-verify-done"><i class="bi bi-patch-check-fill"></i> ${escapeHtml(t['verify-done'] || 'Személy ellenőrizve')}</div>
                </div>
             </div>
             <div class="cm-card">
                ${row('bi-calendar-event', t.birthday, escapeHtml(d.birthdate))}
             </div>`;
    }

    function showSuccessUI() {
        els.video.classList.remove('error');
        els.video.classList.add('scanned');
        els.checkmark.style.display = 'block';
        els.error.style.display = 'none';
        els.continueButton.style.display = 'inline-flex';
    }

    function showErrorUI(message) {
        els.result.textContent = message || t['qr-error'] || 'Hiba';
        els.video.classList.remove('scanned');
        els.video.classList.add('error');
        els.error.style.display = 'block';
        els.checkmark.style.display = 'none';
        els.continueButton.style.display = 'none';
    }

    // --- KÖZÖS beléptető logika (QR + manuális) ----------------------------

    async function checkIn(userId) {
        els.qrCodeContent.value = userId;
        els.result.innerHTML = `<span class="cm-spin"></span> ${escapeHtml(t['checking'] || 'Ellenőrzés...')}`;
        try {
            const res = await fetch(ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'lookup', qrcode: userId }).toString()
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);

            const data = await res.json();

            if (!data.success) {
                showErrorUI(data.error || t['qr-error']);
                els.nextButton.disabled = true;
                return;
            }

            userData = {
                userid: userId,
                firstname: data.firstname,
                lastname: data.lastname,
                birthdate: data.birthdate,
                ticket_status: data.ticket_status,
                remaining_opportunities: data.remaining_opportunities,
                expiredate: data.expiredate,
                assigned_locker: data.assigned_locker
            };

            els.result.innerHTML =
                `<i class="bi bi-check-circle-fill" style="color:#16a34a"></i> ` +
                `${escapeHtml(data.firstname)} ${escapeHtml(data.lastname)}`;

            renderUserDetails(userData);
            verified = false;
            updateNextState();
            showSuccessUI();
        } catch (err) {
            console.error('Check-in error:', err);
            showErrorUI(t['qr-error']);
            els.nextButton.disabled = true;
        }
    }

    // --- QR scanner (@zxing/browser) ---------------------------------------

    async function startScanning() {
        if (scanCompleted || scanning) return;
        scanning = true;
        try {
            if (!window.ZXingBrowser || !ZXingBrowser.BrowserQRCodeReader) {
                throw new Error('ZXingBrowser not loaded');
            }
            if (!codeReader) codeReader = new ZXingBrowser.BrowserQRCodeReader();

            scanControls = await codeReader.decodeFromVideoDevice(undefined, els.video, (result) => {
                if (result && !scanCompleted) {
                    scanCompleted = true;
                    checkIn(result.getText());
                }
            });
        } catch (e) {
            scanning = false;
            console.error('Kamera hiba:', e);
            showErrorUI(t['camera-error'] || 'A kamera nem indítható. Használd a kereső mezőt lent.');
        }
    }

    function stopScanning() {
        if (scanControls) {
            try { scanControls.stop(); } catch (_) {}
            scanControls = null;
        }
        if (els.video) els.video.srcObject = null;
        scanning = false;
        scanCompleted = false;
        if (els.result) els.result.textContent = t.qrscann || '';
        if (els.checkmark) els.checkmark.style.display = 'none';
        if (els.error) els.error.style.display = 'none';
        if (els.video) els.video.classList.remove('scanned', 'error');
        if (els.continueButton) els.continueButton.style.display = 'none';
    }

    // --- init --------------------------------------------------------------

    $(function () {
        cacheEls();

        $(document).on('click', '.loginUser', function (e) {
            e.preventDefault();
            scanCompleted = true;
            checkIn($(this).data('userid'));
        });

        $('#search').on('input', function () {
            const query = this.value.trim();
            clearTimeout(searchTimer);
            if (query.length <= 2) { $('#results').html(''); return; }

            searchTimer = setTimeout(function () {
                if (searchAbort) searchAbort.abort();
                searchAbort = new AbortController();
                fetch(SEARCH_ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ search: query }).toString(),
                    signal: searchAbort.signal
                })
                    .then((r) => {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.text();
                    })
                    .then((html) => $('#results').html(html))
                    .catch((err) => {
                        if (err.name === 'AbortError') return;
                        console.error('Search error:', err);
                        $('#results').html('<div class="cm-empty">' +
                            escapeHtml(t['search-unavailable'] || 'A keresés most nem elérhető (search.php?).') +
                            '</div>');
                    });
            }, 250);
        });

        $('#continueButton').on('click', function () {
            $('#Logginer_MODAL').modal('hide');
            stopScanning();
            renderUserDetails(userData);
            $('#UserDetails_MODAL').modal('show');
        });

        function renderTicketDetails() {
            const occ = (userData.remaining_opportunities === null || userData.remaining_opportunities === undefined)
                ? '∞' : userData.remaining_opportunities;
            $('#ticketDetails').html(
                `<div class="cm-stats">
                    <div class="cm-stat">
                        <div class="cm-stat-ico"><i class="bi bi-repeat"></i></div>
                        <div class="cm-stat-val">${escapeHtml(occ)}</div>
                        <div class="cm-stat-cap">${escapeHtml(t.tickettableoccassion)}</div>
                    </div>
                    <div class="cm-stat">
                        <div class="cm-stat-ico"><i class="bi bi-hourglass-split"></i></div>
                        <div class="cm-stat-val">${escapeHtml(userData.expiredate)}</div>
                        <div class="cm-stat-cap">${escapeHtml(t.expiredate)}</div>
                    </div>
                 </div>
                 <div class="cm-locker">
                    <div class="cm-locker-cap"><i class="bi bi-key-fill"></i> ${escapeHtml(t.randomlockerselected)}</div>
                    <div class="cm-locker-num">${escapeHtml(userData.assigned_locker)}</div>
                 </div>`
            );
        }

        // A tényleges beléptetés CSAK itt történik (commit) – a megerősítés után.
        async function commitCheckin() {
            els.nextButton.disabled = true;
            try {
                const res = await fetch(ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'commit', qrcode: userData.userid }).toString()
                });
                const d = await res.json();
                if (!d.success) {
                    alert(d.error || t['qr-error'] || 'Hiba');
                    els.nextButton.disabled = false;
                    return;
                }
                userData.assigned_locker = d.assigned_locker;
                if (d.remaining_opportunities !== undefined) userData.remaining_opportunities = d.remaining_opportunities;
                if (d.expiredate) userData.expiredate = d.expiredate;

                renderTicketDetails();
                $('#UserDetails_MODAL').modal('hide');
                $('#TicketDetails_MODAL').modal('show');
            } catch (e) {
                console.error('Commit error:', e);
                alert(t['qr-error'] || 'Hiba');
                els.nextButton.disabled = false;
            }
        }

        $('#nextButton').on('click', function () {
            if (els.nextButton.disabled) return;
            if (userData.ticket_status !== t.valid || !verified) {
                openVerify();
                return;
            }
            commitCheckin();
        });

        $('#Logginer_MODAL').on('shown.bs.modal', startScanning);
        $('#Logginer_MODAL').on('hidden.bs.modal', stopScanning);

        // --- Profilkép nagyítás (lightbox) ---
        // --- Profilkép ellenőrzés (lightbox + KÖTELEZŐ megerősítés) ---
        const lb = document.createElement('div');
        lb.className = 'cm-lightbox';
        lb.innerHTML =
            '<button type="button" class="cm-lightbox-close" aria-label="Close"><i class="bi bi-x-lg"></i></button>' +
            '<figure class="cm-lightbox-fig">' +
            '<div class="cm-lightbox-imgwrap" id="cmLbWrap"></div>' +
            '<figcaption class="cm-lightbox-name" id="cmLbName"></figcaption>' +
            '<div class="cm-lightbox-hint">' + escapeHtml(t['verify-hint'] || 'Hasonlítsd össze a fotót a vendéggel.') + '</div>' +
            '<button type="button" class="cm-btn cm-btn-primary cm-lightbox-confirm" id="cmLbConfirm">' +
            '<i class="bi bi-check-lg"></i> ' + escapeHtml(t['verify-confirm'] || 'Megerősítem, hogy ő az') +
            '</button>' +
            '</figure>';
        document.body.appendChild(lb);
        const lbWrap = lb.querySelector('#cmLbWrap');
        const lbName = lb.querySelector('#cmLbName');

        function openVerify() {
            lbName.textContent = ((userData.firstname || '') + ' ' + (userData.lastname || '')).trim();
            lbWrap.innerHTML = '<div class="cm-lb-ini">' + escapeHtml(initials(userData)) + '</div>';
            if (userData.userid) {
                const src = PROFILE_BASE + encodeURIComponent(userData.userid) + '.png';
                const probe = new Image();
                probe.onload = function () { lbWrap.innerHTML = '<img class="cm-lb-img" src="' + src.replace(/"/g, '&quot;') + '" alt="">'; };
                probe.src = src;
            }
            lb.classList.add('open');
        }
        function closeVerify() { lb.classList.remove('open'); }

        lb.addEventListener('click', function (e) {
            if (e.target === lb || e.target.closest('.cm-lightbox-close')) closeVerify();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && lb.classList.contains('open')) closeVerify();
        });
        document.getElementById('cmLbConfirm').addEventListener('click', function () {
            verified = true;
            els.userDetails.classList.add('cm-verified');
            updateNextState();
            closeVerify();
        });

        // avatar kattintás -> ellenőrzés
        $(document).on('click', '.cm-avatar--zoom', openVerify);
        // a figyelemfelhívó gomb is ugyanazt nyitja
        $(document).on('click', '.cm-verify-cta', openVerify);
    });

    // --- modern design-rendszer (csak a beléptetőre szabva) ----------------

    $('<style>').prop('type', 'text/css').html(`
        .checkin-modal{ --cm-accent:#0950dc; --cm-accent2:#096ed2; --cm-ink:#0f172a; --cm-muted:#64748b; --cm-line:rgba(15,23,42,.08); }
        .checkin-modal *{ box-sizing:border-box; }
        .checkin-modal .modal-dialog{ max-width:460px; margin:6vh auto; }
        .checkin-modal .modal-content{
            border:none; border-radius:24px; overflow:hidden;
            box-shadow:0 30px 80px rgba(15,23,42,.32);
            font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; color:var(--cm-ink);
            animation:cm-pop .22s cubic-bezier(.2,.9,.3,1.2);
        }
        @keyframes cm-pop{ from{ transform:translateY(14px) scale(.97); opacity:0 } to{ transform:none; opacity:1 } }

        .checkin-modal .modal-header{
            display:flex; align-items:center; justify-content:space-between;
            padding:18px 22px; border-bottom:1px solid var(--cm-line);
        }
        .checkin-modal .cm-head{ display:flex; align-items:center; gap:13px; }
        .checkin-modal .cm-head-icon{
            width:42px; height:42px; border-radius:13px; display:inline-flex; align-items:center; justify-content:center;
            background:linear-gradient(135deg,#dbeafe,#bfdbfe); color:var(--cm-accent2); font-size:20px;
        }
        .checkin-modal .modal-title{ font-size:18px; font-weight:700; margin:0; }
        .checkin-modal .cm-close{
            width:36px; height:36px; border:none; border-radius:10px; background:#f1f5f9; color:#475569;
            font-size:15px; cursor:pointer; transition:.15s; display:inline-flex; align-items:center; justify-content:center;
        }
        .checkin-modal .cm-close:hover{ background:#e2e8f0; color:#0f172a; }

        .checkin-modal .modal-body{ padding:22px; }

        /* --- kamera --- */
        .checkin-modal #video-container{
            position:relative; width:100%; height:auto; aspect-ratio:4/3;
            border-radius:18px; overflow:hidden; background:#0b1020; border:1px solid var(--cm-line);
        }
        .checkin-modal #video{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
        .checkin-modal .scan-frame{ position:absolute; inset:0; pointer-events:none; }
        .checkin-modal .scan-frame span{ position:absolute; width:36px; height:36px; border:3px solid rgba(9,80,220,.95); }
        .checkin-modal .scan-frame span:nth-child(1){ top:18%; left:18%; border-right:none; border-bottom:none; border-radius:8px 0 0 0; }
        .checkin-modal .scan-frame span:nth-child(2){ top:18%; right:18%; border-left:none; border-bottom:none; border-radius:0 8px 0 0; }
        .checkin-modal .scan-frame span:nth-child(3){ bottom:18%; left:18%; border-right:none; border-top:none; border-radius:0 0 0 8px; }
        .checkin-modal .scan-frame span:nth-child(4){ bottom:18%; right:18%; border-left:none; border-top:none; border-radius:0 0 8px 0; }
        .checkin-modal #checkmark,.checkin-modal #error{ font-size:64px; z-index:3; text-shadow:0 4px 16px rgba(0,0,0,.4); }
        .checkin-modal #result{ margin:14px 0 0; text-align:center; color:var(--cm-muted); font-size:14px; min-height:21px; }

        .cm-spin{ display:inline-block; width:14px; height:14px; border:2px solid #cbd5e1; border-top-color:var(--cm-accent); border-radius:50%; vertical-align:-2px; animation:cm-rot .7s linear infinite; }
        @keyframes cm-rot{ to{ transform:rotate(360deg) } }

        /* --- "vagy" elválasztó --- */
        .checkin-modal .cm-divider{ display:flex; align-items:center; gap:14px; margin:20px 0; }
        .checkin-modal .cm-divider::before,.checkin-modal .cm-divider::after{ content:""; flex:1; height:1px; background:var(--cm-line); }
        .checkin-modal .cm-divider span{ font-size:11px; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:#94a3b8; }

        /* --- kereső --- */
        .checkin-modal .cm-search{ position:relative; margin:0; }
        .checkin-modal .cm-search i{ position:absolute; left:18px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:16px; }
        .checkin-modal #search{
            width:100%; height:auto; padding:13px 18px 13px 46px; border-radius:14px;
            border:1.5px solid var(--cm-line); background:#f8fafc; font-size:15px; outline:none; transition:.15s; color:var(--cm-ink);
        }
        .checkin-modal #search:focus{ border-color:var(--cm-accent); background:#fff; box-shadow:0 0 0 4px rgba(9,80,220,.12); }

        /* --- találatok --- */
        .checkin-modal #results{ margin-top:10px; }
        .checkin-modal #results .table{ margin:0; }
        .checkin-modal #results .table thead{ display:none; }
        .checkin-modal #results .table,.checkin-modal #results .table>tbody>tr>td{ border:none; }
        .checkin-modal #results .table>tbody>tr{ display:flex; align-items:center; gap:8px; padding:8px 6px; border-radius:12px; }
        .checkin-modal #results .table>tbody>tr:hover{ background:#f8fafc; }
        .checkin-modal #results .table>tbody>tr>td{ padding:4px 6px; vertical-align:middle; }
        .checkin-modal #results .table>tbody>tr>td:first-child{ font-weight:600; }
        .checkin-modal #results .table>tbody>tr>td:last-child{ margin-left:auto; }
        .checkin-modal #results .loginUser{
            border:none; border-radius:10px; padding:7px 16px; font-weight:600; font-size:13px; color:#fff;
            background:linear-gradient(135deg,var(--cm-accent),var(--cm-accent2)); cursor:pointer; transition:.15s;
        }
        .checkin-modal #results .loginUser:hover{ filter:brightness(1.05); transform:translateY(-1px); }
        .checkin-modal .cm-empty{ padding:14px; text-align:center; color:var(--cm-muted); font-size:14px; background:#f8fafc; border:1px dashed var(--cm-line); border-radius:12px; }

        /* --- profil (user modal) --- */
        .checkin-modal .cm-profile{ text-align:center; margin-bottom:18px; }
        .checkin-modal .cm-avatar{
            width:84px; height:84px; margin:0 auto 12px; border-radius:50%;
            background:linear-gradient(135deg,var(--cm-accent),var(--cm-accent2)); color:#fff;
            display:flex; align-items:center; justify-content:center; font-size:32px; font-weight:800;
            box-shadow:0 12px 30px rgba(9,80,220,.35); position:relative;
        }
        .checkin-modal .cm-avatar-img{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover; border-radius:50%; }
        .checkin-modal .cm-avatar-zoom{ display:flex; position:absolute; right:0; bottom:0; width:28px; height:28px; border-radius:50%; background:var(--cm-accent); color:#fff; align-items:center; justify-content:center; font-size:13px; z-index:2; border:2px solid #fff; box-shadow:0 2px 8px rgba(15,23,42,.3); }
        .checkin-modal .cm-avatar--zoom{ cursor:zoom-in; }
        .checkin-modal .cm-avatar--zoom:hover{ transform:scale(1.03); transition:transform .15s; }

        /* ellenőrzés-állapot */
        .checkin-modal .cm-verify{ margin-top:12px; font-size:14px; font-weight:700; }
        .checkin-modal .cm-verify-cta{ display:inline-flex; align-items:center; gap:8px; border:none; cursor:pointer;
            background:linear-gradient(135deg,var(--cm-accent),var(--cm-accent2)); color:#fff;
            padding:10px 18px; border-radius:999px; box-shadow:0 8px 20px rgba(9,80,220,.3);
            animation:cm-cta-pulse 1.5s ease-in-out infinite; }
        .checkin-modal .cm-verify-cta i{ font-size:16px; }
        .checkin-modal .cm-verify-cta:hover{ filter:brightness(1.07); transform:translateY(-1px); }
        .checkin-modal .cm-verify-done{ display:none; align-items:center; gap:7px; color:#15803d; }
        .checkin-modal .cm-verified .cm-verify-cta{ display:none; }
        .checkin-modal .cm-verified .cm-verify-done{ display:inline-flex; }
        @keyframes cm-cta-pulse{ 0%,100%{ box-shadow:0 8px 20px rgba(9,80,220,.28) } 50%{ box-shadow:0 10px 30px rgba(9,80,220,.6) } }
        /* lüktető gyűrű az avatar körül, amíg nincs ellenőrizve */
        .checkin-modal #userDetails:not(.cm-verified) .cm-avatar::after{ content:""; position:absolute; inset:-6px; border-radius:50%; border:3px solid var(--cm-accent); animation:cm-ring 1.5s ease-out infinite; pointer-events:none; }
        @keyframes cm-ring{ 0%{ transform:scale(1); opacity:.75 } 100%{ transform:scale(1.3); opacity:0 } }

        /* lightbox (a body-ra kerül, ezért nem .checkin-modal scope) */
        .cm-lightbox{ position:fixed; inset:0; z-index:20000; display:none; align-items:center; justify-content:center;
            background:rgba(2,6,23,.86); backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); padding:24px; }
        .cm-lightbox.open{ display:flex; animation:cm-fade .18s ease; }
        @keyframes cm-fade{ from{ opacity:0 } to{ opacity:1 } }
        .cm-lightbox-fig{ margin:0; text-align:center; max-width:92vw; }
        .cm-lightbox-imgwrap{ display:flex; align-items:center; justify-content:center; }
        .cm-lb-img{ max-width:86vw; max-height:62vh; border-radius:18px; border:3px solid #fff; box-shadow:0 30px 80px rgba(0,0,0,.6); background:#0b1020; }
        .cm-lb-ini{ width:200px; height:200px; border-radius:50%; background:linear-gradient(135deg,var(--cm-accent),var(--cm-accent2)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:84px; font-weight:800; border:3px solid #fff; box-shadow:0 30px 80px rgba(0,0,0,.5); }
        .cm-lightbox-name{ margin-top:16px; color:#fff; font-size:22px; font-weight:800; text-shadow:0 2px 10px rgba(0,0,0,.5); }
        .cm-lightbox-hint{ margin-top:6px; color:#cbd5e1; font-size:14px; }
        .cm-lightbox-confirm{ margin-top:18px; padding:13px 26px; font-size:15px; }
        .cm-lightbox-close{ position:fixed; top:20px; right:22px; width:46px; height:46px; border:none; border-radius:50%;
            background:rgba(255,255,255,.16); color:#fff; font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:.15s; }
        .cm-lightbox-close:hover{ background:rgba(255,255,255,.3); }
        .checkin-modal .cm-profile-name{ font-size:21px; font-weight:700; margin-bottom:10px; }

        /* --- adat-kártya / sorok --- */
        .checkin-modal .cm-card{ border:1px solid var(--cm-line); border-radius:14px; overflow:hidden; background:#fff; }
        .checkin-modal .cm-row{ display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; border-bottom:1px solid var(--cm-line); }
        .checkin-modal .cm-row:last-child{ border-bottom:none; }
        .checkin-modal .cm-row-label{ display:flex; align-items:center; gap:10px; color:var(--cm-muted); font-size:14px; }
        .checkin-modal .cm-row-label i{ font-size:17px; opacity:.8; }
        .checkin-modal .cm-row-value{ font-weight:700; }

        /* --- badge --- */
        .checkin-modal .cm-badge{ display:inline-flex; align-items:center; gap:7px; padding:6px 16px; border-radius:999px; font-weight:700; font-size:14px; }
        .checkin-modal .cm-badge.ok{ background:#dcfce7; color:#166534; }
        .checkin-modal .cm-badge.bad{ background:#fee2e2; color:#991b1b; }

        /* --- stat csempék (jegy modal) --- */
        .checkin-modal .cm-stats{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .checkin-modal .cm-stat{ background:#f8fafc; border:1px solid var(--cm-line); border-radius:16px; padding:18px 12px; text-align:center; }
        .checkin-modal .cm-stat-ico{ width:38px; height:38px; margin:0 auto 8px; border-radius:11px; display:flex; align-items:center; justify-content:center; background:#fff; color:var(--cm-accent2); font-size:18px; box-shadow:0 2px 6px rgba(15,23,42,.06); }
        .checkin-modal .cm-stat-val{ font-size:20px; font-weight:800; }
        .checkin-modal .cm-stat-cap{ font-size:12px; color:var(--cm-muted); margin-top:3px; }

        /* --- szekrény --- */
        .checkin-modal .cm-locker{ margin-top:18px; text-align:center; }
        .checkin-modal .cm-locker-cap{ font-size:12px; text-transform:uppercase; letter-spacing:.07em; color:var(--cm-muted); margin-bottom:12px; }
        .checkin-modal .cm-locker-num{
            display:inline-flex; align-items:center; justify-content:center; min-width:120px; height:120px; padding:0 20px;
            border-radius:28px; background:linear-gradient(135deg,var(--cm-accent),var(--cm-accent2)); color:#fff;
            font-size:52px; font-weight:800; box-shadow:0 16px 36px rgba(9,80,220,.4);
            animation:cm-breathe 2.2s ease-in-out infinite;
        }
        @keyframes cm-breathe{ 0%,100%{ transform:scale(1) } 50%{ transform:scale(1.04) } }

        /* --- footer + gombok --- */
        .checkin-modal .modal-footer{ display:flex; gap:10px; justify-content:flex-end; padding:16px 22px; border-top:1px solid var(--cm-line); }
        .checkin-modal .cm-btn{ display:inline-flex; align-items:center; gap:8px; border:none; border-radius:13px; padding:11px 20px; font-weight:700; font-size:14px; cursor:pointer; transition:.15s; text-decoration:none; }
        .checkin-modal .cm-btn-primary{ background:linear-gradient(135deg,var(--cm-accent),var(--cm-accent2)); color:#fff; box-shadow:0 8px 20px rgba(9,80,220,.3); }
        .checkin-modal .cm-btn-primary:hover{ filter:brightness(1.05); transform:translateY(-1px); color:#fff; }
        .checkin-modal .cm-btn-primary[disabled]{ opacity:.45; cursor:not-allowed; box-shadow:none; transform:none; }
        .checkin-modal .cm-btn-ghost{ background:#f1f5f9; color:#475569; }
        .checkin-modal .cm-btn-ghost:hover{ background:#e2e8f0; }
    `).appendTo('head');
})();