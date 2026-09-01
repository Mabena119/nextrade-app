<?php
/** NexTradeAI affiliate portal — shared brand styles */
?>
<link rel="stylesheet" href="/assets/css/platform.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<style>
    :root {
        --eat-bg: var(--aura-navy-deep);
        --eat-bg-elevated: #0A1628;
        --eat-bg-card: linear-gradient(135deg, #0A1628 0%, #252525 100%);
        --eat-border: #333;
        --eat-border-soft: rgba(255, 255, 255, 0.08);
        --eat-text: #ffffff;
        --eat-muted: #aaaaaa;
        --eat-muted-dim: #71717a;
        --eat-blue: #00A8FF;
        --eat-blue-dark: #0077CC;
        --eat-blue-glow: rgba(94, 246, 255, 0.38);
        --eat-green: #22c55e;
        --eat-radius: 16px;
        --eat-radius-sm: 12px;
        --eat-shadow: 0 20px 60px rgba(0, 0, 0, 0.45);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    html { background-color: var(--eat-bg); }

    body {
        background: linear-gradient(135deg, var(--eat-bg) 0%, var(--eat-bg-elevated) 100%);
        background-color: var(--eat-bg);
        color: var(--eat-text);
        font-family: 'Manrope', 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        min-height: 100vh;
        line-height: 1.6;
        position: relative;
    }

    body::before {
        content: '';
        position: fixed;
        inset: 0;
        background:
            radial-gradient(circle at 15% 20%, rgba(0, 229, 255, 0.12) 0%, transparent 45%),
            radial-gradient(circle at 85% 75%, rgba(0, 229, 255, 0.06) 0%, transparent 50%);
        pointer-events: none;
        z-index: 0;
    }

    body.auth-page {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px 16px;
    }

    .page { position: relative; z-index: 1; }

    /* ── Nav ── */
    .affiliate-nav {
        position: sticky;
        top: 0;
        z-index: 100;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 14px 24px;
        background: rgba(26, 26, 26, 0.92);
        border-bottom: 1px solid var(--eat-border);
        backdrop-filter: blur(12px);
    }

    .nav-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        color: var(--eat-text);
        font-weight: 700;
        font-size: 1.1rem;
        letter-spacing: -0.02em;
    }

    .nav-brand img {
        width: auto;
        height: 40px;
        border-radius: 0;
        filter: drop-shadow(0 2px 12px var(--eat-blue-glow));
    }

    .nav-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: linear-gradient(135deg, var(--eat-blue) 0%, var(--eat-blue-dark) 100%);
        color: #fff;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        padding: 6px 14px;
        border-radius: 999px;
    }

    .nav-actions { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }

    /* ── Layout ── */
    .wrap { max-width: 1140px; margin: 0 auto; padding: 28px 20px 56px; }

    .card {
        background: var(--eat-bg-card);
        border: 1px solid var(--eat-border);
        border-radius: var(--eat-radius);
        padding: 28px;
        box-shadow: var(--eat-shadow);
    }

    .auth-card {
        width: 100%;
        max-width: 460px;
        animation: fadeUp 0.5s ease-out;
    }

    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* ── Auth logo ── */
    .logo { text-align: center; margin-bottom: 24px; }

    .logo img {
        width: 72px;
        border-radius: 14px;
        margin-bottom: 14px;
        filter: drop-shadow(0 4px 14px var(--eat-blue-glow));
    }

    .logo h1 {
        font-size: 1.55rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        margin-bottom: 6px;
    }

    .logo .tagline {
        display: inline-block;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: var(--eat-blue);
        margin-bottom: 8px;
    }

    h2 {
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 12px;
        letter-spacing: -0.02em;
    }

    p.muted { color: var(--eat-muted); line-height: 1.65; margin-bottom: 18px; font-size: 0.95rem; }

    /* ── Dashboard hero ── */
    .dash-hero {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
        margin-bottom: 24px;
        padding: 28px 32px;
        background: var(--eat-bg-card);
        border: 1px solid var(--eat-border);
        border-radius: var(--eat-radius);
        box-shadow: var(--eat-shadow);
        position: relative;
        overflow: hidden;
    }

    .dash-hero::after {
        content: '';
        position: absolute;
        top: -40%;
        right: -10%;
        width: 280px;
        height: 280px;
        background: radial-gradient(circle, rgba(0, 229, 255, 0.15) 0%, transparent 70%);
        pointer-events: none;
    }

    .dash-hero-main { position: relative; z-index: 1; flex: 1; min-width: 240px; }

    .dash-hero-main h1 {
        font-size: 1.75rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        margin-bottom: 8px;
    }

    .dash-hero-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 14px;
    }

    .meta-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--eat-border-soft);
        border-radius: 999px;
        padding: 6px 14px;
        font-size: 0.82rem;
        color: var(--eat-muted);
    }

    .meta-pill strong { color: var(--eat-text); font-weight: 600; }

    .meta-pill i { color: var(--eat-blue); font-size: 0.95rem; }

    .commission-badge {
        position: relative;
        z-index: 1;
        flex-shrink: 0;
        text-align: center;
        background: rgba(0, 229, 255, 0.1);
        border: 1px solid rgba(0, 229, 255, 0.35);
        border-radius: var(--eat-radius-sm);
        padding: 18px 24px;
        min-width: 140px;
    }

    .commission-badge .rate {
        font-size: 2rem;
        font-weight: 800;
        color: var(--eat-blue);
        line-height: 1;
        letter-spacing: -0.03em;
    }

    .commission-badge .label {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--eat-muted);
        margin-top: 4px;
    }

    .commission-badge .rate-range {
        font-size: 0.75rem;
        color: var(--eat-muted-dim);
        margin-top: 6px;
    }

    .tier-card { margin-bottom: 24px; }

    .tier-sales-count {
        font-size: 0.9rem;
        color: var(--eat-muted);
        margin-bottom: 14px;
    }

    .tier-sales-count strong { color: var(--eat-blue); font-size: 1.1rem; }

    .tier-track {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }

    .tier-min, .tier-max {
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--eat-muted);
        flex-shrink: 0;
        width: 36px;
    }

    .tier-max { text-align: right; }

    .tier-bar {
        flex: 1;
        height: 8px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 999px;
        overflow: hidden;
    }

    .tier-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--eat-blue) 0%, #60a5fa 100%);
        border-radius: 999px;
        transition: width 0.4s ease;
    }

    .tier-note {
        font-size: 0.9rem;
        color: var(--eat-muted);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .tier-note i { color: var(--eat-blue); }

    .tier-note-max { color: #86efac; }
    .tier-note-max i { color: #86efac; }

    /* ── Stats ── */
    .stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .stats-secondary { grid-template-columns: repeat(2, 1fr); }

    .stat {
        background: var(--eat-bg-card);
        border: 1px solid var(--eat-border);
        border-radius: var(--eat-radius-sm);
        padding: 20px 22px;
        transition: border-color 0.2s, transform 0.2s;
    }

    .stat:hover {
        border-color: rgba(0, 229, 255, 0.4);
        transform: translateY(-2px);
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 229, 255, 0.12);
        color: var(--eat-blue);
        font-size: 1.2rem;
        margin-bottom: 12px;
    }

    .stat-label {
        color: var(--eat-muted);
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .stat-value {
        font-size: 1.65rem;
        font-weight: 800;
        margin-top: 4px;
        letter-spacing: -0.02em;
    }

    .stat-value.highlight { color: var(--eat-blue); }

    /* ── Referral link ── */
    .ref-card { margin-bottom: 24px; }

    .ref-card-primary {
        border-color: rgba(0, 229, 255, 0.45);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(0, 229, 255, 0.12);
    }

    .ref-card-primary h2 { font-size: 1.35rem; }

    .ref-card h2 {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ref-card h2 i { color: var(--eat-blue); }

    .link-row {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: stretch;
    }

    .link-box {
        flex: 1;
        min-width: 200px;
        background: rgba(0, 0, 0, 0.35);
        border: 1px solid rgba(0, 229, 255, 0.35);
        border-radius: var(--eat-radius-sm);
        padding: 14px 16px;
        word-break: break-all;
        font-family: ui-monospace, 'SF Mono', Menlo, monospace;
        font-size: 0.88rem;
        color: #93c5fd;
        line-height: 1.5;
    }

    .copy-btn { white-space: nowrap; }

    .copy-btn.copied {
        background: linear-gradient(135deg, #22c55e, #16a34a);
    }

    /* ── How it works ── */
    .steps {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--eat-border-soft);
    }

    .step {
        display: flex;
        gap: 12px;
        align-items: flex-start;
    }

    .step-num {
        flex-shrink: 0;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        background: rgba(0, 229, 255, 0.15);
        color: var(--eat-blue);
        font-size: 0.8rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .step-text { font-size: 0.85rem; color: var(--eat-muted); line-height: 1.5; }
    .step-text strong { display: block; color: var(--eat-text); font-size: 0.9rem; margin-bottom: 2px; }

    /* ── Table ── */
    .table-wrap { overflow-x: auto; margin-top: 8px; }

    table { width: 100%; border-collapse: collapse; font-size: 0.88rem; min-width: 640px; }

    th, td { padding: 12px 10px; border-bottom: 1px solid var(--eat-border-soft); text-align: left; }

    th {
        color: var(--eat-muted-dim);
        font-weight: 600;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    tbody tr:hover td { background: rgba(255, 255, 255, 0.02); }

    td.amount { font-weight: 600; color: var(--eat-blue); }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--eat-muted);
    }

    .empty-state i {
        font-size: 2.5rem;
        color: rgba(0, 229, 255, 0.35);
        margin-bottom: 12px;
        display: block;
    }

    /* ── Forms ── */
    .form-group { margin-bottom: 16px; }

    label {
        display: block;
        color: #ccc;
        font-size: 0.875rem;
        font-weight: 500;
        margin-bottom: 6px;
    }

    input, textarea {
        width: 100%;
        background: var(--eat-bg);
        border: 1px solid var(--eat-border);
        color: var(--eat-text);
        border-radius: 10px;
        padding: 12px 14px;
        font-size: 1rem;
        font-family: inherit;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    input:focus, textarea:focus {
        outline: none;
        border-color: var(--eat-blue);
        box-shadow: 0 0 0 3px rgba(0, 229, 255, 0.15);
    }

    select {
        width: 100%;
        background: var(--eat-bg);
        border: 1px solid var(--eat-border);
        color: var(--eat-text);
        border-radius: 10px;
        padding: 12px 14px;
        font-size: 1rem;
        font-family: inherit;
    }

    select:focus {
        outline: none;
        border-color: var(--eat-blue);
        box-shadow: 0 0 0 3px rgba(0, 229, 255, 0.15);
    }

    .field-hint { display: block; color: var(--eat-muted-dim); font-size: 0.82rem; margin-top: 6px; }

    .empty-inline { font-size: 0.9rem; margin-bottom: 8px; }

    /* ── Payouts ── */
    .payout-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .payout-card { height: 100%; }

    .payout-list { list-style: none; margin: 0 0 8px; padding: 0; }

    .payout-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 0;
        border-bottom: 1px solid var(--eat-border-soft);
    }

    .payout-item:last-child { border-bottom: 0; }

    .payout-item-main { display: flex; align-items: flex-start; gap: 12px; min-width: 0; }

    .payout-type-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: rgba(0, 229, 255, 0.12);
        color: var(--eat-blue);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1.2rem;
    }

    .payout-item-detail { color: var(--eat-muted); font-size: 0.85rem; margin-top: 2px; }

    .payout-form-wrap summary { list-style: none; }
    .payout-form-wrap summary::-webkit-details-marker { display: none; }

    .payout-form { margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--eat-border-soft); }

    .checkbox-row {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--eat-muted);
        font-size: 0.9rem;
        margin-bottom: 16px;
        cursor: pointer;
    }

    .checkbox-row input { width: auto; }

    .btn-sm { padding: 8px 12px; font-size: 0.85rem; }

    .btn:disabled { opacity: 0.45; cursor: not-allowed; transform: none; box-shadow: none; }

    /* ── Buttons ── */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        background: linear-gradient(135deg, var(--eat-blue) 0%, var(--eat-blue-dark) 100%);
        color: #fff;
        border: 0;
        border-radius: 10px;
        padding: 12px 20px;
        font-weight: 600;
        font-size: 0.95rem;
        font-family: inherit;
        cursor: pointer;
        text-decoration: none;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 20px var(--eat-blue-glow);
        color: #fff;
    }

    .btn-block { width: 100%; }

    .btn-secondary {
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid var(--eat-border);
        box-shadow: none;
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.1);
        box-shadow: none;
        color: #fff;
    }

    .btn-ghost {
        background: transparent;
        border: 1px solid var(--eat-border);
        box-shadow: none;
        padding: 10px 16px;
        font-size: 0.88rem;
    }

    .btn-ghost:hover {
        border-color: var(--eat-blue);
        color: var(--eat-blue);
        box-shadow: none;
        transform: none;
    }

    /* ── Alerts & badges ── */
    .alert {
        border-radius: 10px;
        padding: 12px 16px;
        margin-bottom: 18px;
        font-size: 0.9rem;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    .alert-success {
        background: rgba(34, 197, 94, 0.12);
        border: 1px solid rgba(34, 197, 94, 0.35);
        color: #86efac;
    }

    .alert-danger {
        background: rgba(239, 68, 68, 0.12);
        border: 1px solid rgba(239, 68, 68, 0.35);
        color: #fca5a5;
    }

    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: capitalize;
    }

    .badge-confirmed, .badge-paid {
        background: rgba(34, 197, 94, 0.15);
        color: #86efac;
    }

    .badge-pending {
        background: rgba(234, 179, 8, 0.15);
        color: #fde047;
    }

    .badge-rejected {
        background: rgba(239, 68, 68, 0.15);
        color: #fca5a5;
    }

    .footer-link { text-align: center; margin-top: 20px; }

    .footer-link a {
        color: var(--eat-blue);
        text-decoration: none;
        font-weight: 500;
        font-size: 0.9rem;
    }

    .footer-link a:hover { color: var(--eat-blue-dark); }

    .site-footer {
        text-align: center;
        padding: 24px 20px;
        color: var(--eat-muted-dim);
        font-size: 0.8rem;
        border-top: 1px solid var(--eat-border-soft);
        margin-top: 16px;
    }

    .site-footer a { color: var(--eat-muted); text-decoration: none; }
    .site-footer a:hover { color: var(--eat-blue); }

    /* ── Responsive ── */
    @media (max-width: 768px) {
        .affiliate-nav { padding: 12px 16px; }
        .nav-badge { display: none; }
        .nav-brand span { font-size: 1rem; }
        .stats, .stats-secondary, .steps, .payout-grid { grid-template-columns: 1fr; }
        .dash-hero { padding: 22px 20px; }
        .commission-badge { width: 100%; }
        .nav-actions .btn-ghost { display: none; }
    }
</style>
