<!-- ===================== GYM One – közös checkout stílus (pc-) ===================== -->
<style>
    .pc {
        --pc-accent: #0950dc;
        --pc-accent2: #096ed2;
        --pc-ink: #0f172a;
        --pc-muted: #64748b;
        --pc-line: rgba(15, 23, 42, .08);
        margin-top: 10px;
    }
    .pc * { box-sizing: border-box; }

    .pc-head { display: flex; align-items: center; gap: 16px; margin: 6px 0 18px; flex-wrap: wrap; }
    .pc-back {
        display: inline-flex; align-items: center; gap: 7px; text-decoration: none;
        background: #f1f5f9; color: #475569; font-weight: 700; font-size: 14px;
        padding: 9px 16px; border-radius: 12px; transition: .15s;
    }
    .pc-back:hover { background: #e2e8f0; color: #475569; }
    .pc-head-title { display: flex; align-items: center; gap: 12px; }
    .pc-head-icon {
        width: 44px; height: 44px; border-radius: 13px; display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: var(--pc-accent2); font-size: 20px;
    }
    .pc-head-title h3 { margin: 0; font-weight: 800; }

    .pc-alerts:empty { display: none; }
    .pc-alerts { margin-bottom: 14px; }

    .pc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; align-items: start; }
    @media (max-width: 991px) { .pc-grid { grid-template-columns: 1fr; } }

    .pc-card {
        background: #fff; border: 1px solid var(--pc-line); border-radius: 18px; padding: 22px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
    }
    .pc-card-head { display: flex; align-items: center; gap: 11px; margin-bottom: 16px; }
    .pc-card-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: #f8fafc; color: var(--pc-accent2); font-size: 19px; }
    .pc-card-head h5 { margin: 0; font-weight: 800; }

    .pc-buyer { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
    .pc-avatar {
        width: 56px; height: 56px; flex: 0 0 56px; border-radius: 50%; position: relative;
        background: linear-gradient(135deg, var(--pc-accent), var(--pc-accent2)); color: #fff;
        display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 800;
        box-shadow: 0 8px 20px rgba(9, 80, 220, .3);
    }
    .pc-avatar .pc-ava-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
    .pc-buyer-name { font-size: 19px; font-weight: 800; color: var(--pc-ink); }
    .pc-buyer-id { font-size: 13px; color: var(--pc-muted); margin-top: 2px; }
    .pc-buyer-id i { color: var(--pc-accent); }

    .pc-meta { list-style: none; padding: 0; margin: 0 0 18px; }
    .pc-meta li { display: flex; align-items: center; gap: 10px; padding: 7px 0; font-size: 14px; color: var(--pc-ink); border-top: 1px solid var(--pc-line); }
    .pc-meta li:first-child { border-top: none; }
    .pc-meta i { color: var(--pc-accent); width: 18px; text-align: center; }
    .pc-meta span { word-break: break-word; }

    .pc-line { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 11px 0; border-top: 1px solid var(--pc-line); font-size: 14px; }
    .pc-line:first-of-type { border-top: none; }
    .pc-line > span { color: var(--pc-muted); }
    .pc-line b { color: var(--pc-ink); text-align: right; }
    .pc-muted { color: var(--pc-muted); font-weight: 600; }

    .pc-chip { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 700; background: #eff6ff; color: var(--pc-accent); padding: 4px 10px; border-radius: 999px; }
    .pc-chip-gold { background: #fef3c7; color: #b45309; }

    .pc-total {
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        margin-top: 16px; padding: 16px 18px; border-radius: 14px;
        background: linear-gradient(135deg, #eff6ff, #f8fafc); border: 1px solid #bfdbfe;
    }
    .pc-total > span { font-size: 14px; font-weight: 700; color: var(--pc-muted); }
    .pc-total-val { font-size: 24px; font-weight: 800; color: var(--pc-ink); }

    /* gombok */
    .pc-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: none; border-radius: 13px; padding: 12px 20px; font-weight: 700; font-size: 14px; cursor: pointer; text-decoration: none; transition: .15s; }
    .pc-btn-primary { background: linear-gradient(135deg, var(--pc-accent), var(--pc-accent2)); color: #fff; box-shadow: 0 8px 20px rgba(9, 80, 220, .3); }
    .pc-btn-primary:hover { filter: brightness(1.06); transform: translateY(-1px); color: #fff; }
    .pc-btn-success { background: linear-gradient(135deg, #16a34a, #15803d); color: #fff; box-shadow: 0 8px 20px rgba(22, 163, 74, .3); }
    .pc-btn-success:hover { filter: brightness(1.06); transform: translateY(-1px); color: #fff; }
    .pc-btn-ghost { background: #f1f5f9; color: #475569; }
    .pc-btn-ghost:hover { background: #e2e8f0; color: #475569; }
    .pc-block { width: 100%; }

    /* kosár tételek (item oldal) */
    .pc-items { display: flex; flex-direction: column; gap: 12px; }
    .pc-item { display: flex; align-items: center; gap: 14px; padding: 12px; border: 1px solid var(--pc-line); border-radius: 14px; background: #f8fafc; }
    .pc-item-main { flex: 1; min-width: 0; }
    .pc-item-name { font-weight: 800; color: var(--pc-ink); }
    .pc-item-desc { font-size: 12px; color: var(--pc-muted); margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pc-item-price { font-size: 13px; color: var(--pc-muted); margin-top: 4px; }
    .pc-item-price b { color: var(--pc-ink); }
    .pc-item-side { display: flex; align-items: center; gap: 8px; }
    .pc-qtyform { display: flex; align-items: center; gap: 6px; }
    .pc-qty { width: 56px; height: 38px; border: 1.5px solid var(--pc-line); border-radius: 10px; text-align: center; font-weight: 700; background: #fff; outline: none; }
    .pc-qty:focus { border-color: var(--pc-accent); box-shadow: 0 0 0 3px rgba(9, 80, 220, .12); }
    .pc-iconbtn { width: 38px; height: 38px; border: none; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 15px; transition: .13s; }
    .pc-iconbtn-save { background: #e0ecff; color: var(--pc-accent); }
    .pc-iconbtn-save:hover { background: #cfe0ff; }
    .pc-iconbtn-del { background: #fee2e2; color: #b91c1c; }
    .pc-iconbtn-del:hover { background: #fecaca; }
    .pc-empty { text-align: center; padding: 40px 20px; color: var(--pc-muted); }
    .pc-empty i { font-size: 42px; opacity: .4; display: block; margin-bottom: 10px; }

    /* modal */
    .pc-modal .modal-content { border: none; border-radius: 20px; box-shadow: 0 30px 80px rgba(2, 6, 23, .35); overflow: hidden; }
    .pc-modal .modal-body { padding: 28px; }
    .pc-modal-top { text-align: center; margin-bottom: 18px; }
    .pc-modal-icon { width: 64px; height: 64px; margin: 0 auto 14px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #0950dc, #096ed2); color: #fff; font-size: 28px; box-shadow: 0 10px 26px rgba(9, 80, 220, .4); }
    .pc-modal-top h4 { margin: 0 0 6px; font-weight: 800; color: #0f172a; }
    .pc-modal-sub { margin: 0; color: #64748b; font-size: 14px; }
    .pc-modal-amount { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-radius: 13px; background: #f8fafc; border: 1px solid rgba(15, 23, 42, .08); margin-bottom: 18px; }
    .pc-modal-amount span { color: #64748b; font-weight: 700; font-size: 13px; }
    .pc-modal-amount b { font-size: 20px; color: #0f172a; }

    .pc-methods { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 10px; margin-bottom: 20px; }
    .pc-method { margin: 0; cursor: pointer; }
    .pc-method input { position: absolute; opacity: 0; pointer-events: none; }
    .pc-method-box {
        display: flex; flex-direction: column; align-items: center; gap: 7px; padding: 16px 10px;
        border: 1.5px solid rgba(15, 23, 42, .1); border-radius: 14px; background: #fff;
        color: #475569; font-weight: 700; font-size: 13px; text-align: center; transition: .15s;
    }
    .pc-method-box i { font-size: 24px; color: #94a3b8; transition: .15s; }
    .pc-method:hover .pc-method-box { border-color: #93c5fd; }
    .pc-method input:checked + .pc-method-box { border-color: #0950dc; background: #eff6ff; color: #0950dc; box-shadow: 0 6px 18px rgba(9, 80, 220, .18); }
    .pc-method input:checked + .pc-method-box i { color: #0950dc; }
    .pc-method input:focus-visible + .pc-method-box { box-shadow: 0 0 0 4px rgba(9, 80, 220, .2); }

    .pc-modal-actions { display: flex; gap: 10px; }
    .pc-modal-actions .pc-btn { flex: 1; }
</style>
