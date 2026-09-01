<?php
/** Shared styles for admin auth pages (login, forgot, reset). NexTradeAI brand. */
?>
<link rel="stylesheet" href="/assets/css/brand.css">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        background: linear-gradient(135deg, var(--aura-navy-deep) 0%, var(--aura-navy) 100%) !important;
        background-color: var(--aura-navy-deep) !important;
        color: var(--aura-text);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    body::before {
        content: '';
        position: fixed;
        inset: 0;
        background:
            radial-gradient(circle at 20% 15%, var(--aura-cyan-soft) 0%, transparent 45%),
            radial-gradient(circle at 80% 85%, rgba(0, 184, 212, 0.08) 0%, transparent 50%);
        pointer-events: none;
        z-index: 0;
    }
    .auth-container { width: 100%; max-width: 480px; position: relative; z-index: 1; }
    .auth-card {
        background: var(--aura-navy-elevated);
        border: 1px solid var(--aura-border-solid);
        border-radius: 16px;
        box-shadow: var(--eat-shadow);
        padding: 2.5rem 2rem;
    }
    .auth-logo { text-align: center; margin-bottom: 1.75rem; }
    .auth-logo img { max-width: 220px; width: 100%; height: auto; margin-bottom: 0.75rem; }
    .auth-logo h1 { color: #fff; font-size: 1.5rem; font-weight: 700; margin-bottom: 0.25rem; }
    .auth-logo p { color: var(--aura-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; }
    .auth-header { margin-bottom: 1.75rem; }
    .auth-header h2 { color: #fff; font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
    .auth-header p { color: var(--aura-muted); font-size: 0.95rem; line-height: 1.6; }
    .form-group { margin-bottom: 1.25rem; }
    .form-group label { color: #cbd5e1; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; display: block; }
    .input-wrapper { position: relative; }
    .input-icon {
        position: absolute; left: 1rem; top: 50%; transform: translateY(-50%);
        color: var(--aura-muted-dim); font-size: 1rem; pointer-events: none;
    }
    .form-control {
        background: var(--aura-navy-deep) !important; border: 1px solid var(--aura-border-solid); color: #fff !important;
        padding: 0.95rem 1rem 0.95rem 2.75rem; border-radius: 10px; font-size: 1rem; width: 100%;
    }
    .form-control:focus {
        border-color: var(--aura-cyan); outline: none;
        box-shadow: 0 0 0 3px var(--aura-cyan-soft);
    }
    .btn-primary-custom {
        background: linear-gradient(135deg, var(--aura-cyan) 0%, var(--aura-cyan-dark) 100%);
        color: var(--aura-navy); border: none; padding: 0.95rem 1.5rem; border-radius: 10px;
        font-size: 1rem; font-weight: 700; width: 100%; cursor: pointer;
    }
    .btn-primary-custom:hover { transform: translateY(-1px); box-shadow: 0 8px 24px var(--aura-cyan-glow); }
    .alert {
        border-radius: 10px; padding: 0.875rem 1rem; margin-bottom: 1.25rem; font-size: 0.9rem; line-height: 1.5;
    }
    .alert-success { background: rgba(34, 197, 94, 0.12); border: 1px solid rgba(34, 197, 94, 0.35); color: #86efac; }
    .alert-danger { background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #fca5a5; }
    .auth-footer { margin-top: 1.5rem; text-align: center; }
    .auth-footer a { color: var(--aura-cyan); text-decoration: none; font-size: 0.9rem; font-weight: 500; }
    .auth-footer a:hover { color: var(--aura-cyan-dark); }
</style>
