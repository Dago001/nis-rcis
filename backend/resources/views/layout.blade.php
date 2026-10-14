<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') — Nigeria Immigration Service</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/nis-logo.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/nis-logo.png') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/nis-logo.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-touch-icon.png') }}">
    <style>
        :root {
            --primary:#2b892b; --primary-dark:#1f6b1f; --deep:#073f1c; --mint:#eef7f1; --orange:#ea7317;
            --ink:#1e293b; --muted:#64748b; --line:#e2e8f0; --danger:#b91c1c;
        }
        * { box-sizing:border-box; }
        html { height:100%; }
        body { min-height:100%; margin:0; color:var(--ink); background:#fff; display:flex; flex-direction:column;
               font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif; }
        /* Header: same as the website's landing page header */
        .site-header { flex-shrink:0; position:sticky; top:0; z-index:5; background:#f6f6f6; border-bottom:1px solid var(--line); box-shadow:0 1px 3px rgba(15,23,42,.08); }
        .site-header .inner { display:flex; align-items:center; justify-content:space-between; gap:16px; height:80px; padding:0 5%; }
        .brand { display:flex; align-items:center; gap:12px; text-decoration:none; }
        .brand img { width:48px; height:48px; }
        .brand strong { display:block; font-size:.9rem; letter-spacing:.02em; color:var(--primary-dark); }
        .brand span { display:block; font-size:.78rem; color:var(--muted); }
        .site-header nav { display:flex; gap:10px; align-items:center; }
        .site-header nav a { text-decoration:none; font-size:.92rem; color:#334155; padding:8px 12px; border-radius:6px; }
        .site-header nav a:hover { color:var(--primary); }
        .site-header nav a.cta { background:var(--primary); color:#fff; padding:9px 20px; }
        .site-header nav a.cta:hover { background:var(--primary-dark); color:#fff; }

        /* Full-page background photograph behind the sign-in card */
        .stage { position:relative; flex:1 0 auto; min-height:calc(100vh - 80px - 48px); display:flex; align-items:center; justify-content:center; gap:48px;
                 padding:20px 5%; overflow:hidden; background:var(--deep); }
        .stage img.bg { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
        .stage::after { content:""; position:absolute; inset:0; background:linear-gradient(120deg, rgba(0,0,0,.72) 0%, rgba(7,63,28,.55) 55%, rgba(0,0,0,.45) 100%); }
        .caption { position:relative; z-index:1; color:#fff; max-width:28rem; }
        .caption .eyebrow { font-size:.78rem; letter-spacing:.2em; text-transform:uppercase; color:rgba(255,255,255,.8); font-weight:600; }
        .caption h2 { margin:.4rem 0 0; font-size:2.15rem; line-height:1.2; font-weight:600; }
        .caption h2 span { color:var(--orange); }
        .caption p { margin:.75rem 0 0; color:rgba(255,255,255,.88); line-height:1.5; font-size:.92rem; }
        .panel { position:relative; z-index:1; width:100%; max-width:440px; background:#fff; border-radius:16px; padding:24px 30px 20px;
                 box-shadow:0 25px 50px -12px rgba(0,0,0,.45); }
        .site-footer { flex-shrink:0; padding:14px 16px; text-align:center; font-size:.82rem; color:#475569; background:#fff; border-top:1px solid var(--line); }
        .qr { display:flex; justify-content:center; align-items:center; margin:12px auto; padding:10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; width:fit-content; box-shadow:0 1px 3px rgba(0,0,0,0.05); }
        .qr svg { width:130px; height:130px; max-width:130px; max-height:130px; display:block; }
        code.secret { display:inline-block; margin-top:4px; padding:4px 10px; background:var(--mint); border:1px solid rgba(43,137,43,.2); border-radius:6px; font-size:.9rem; font-weight:600; letter-spacing:.08em; word-break:break-all; color:var(--deep); }
        .pw { position:relative; width:100%; display:block; }
        .pw input { padding-right:44px; width:100%; }
        .pw .eye { position:absolute; right:6px; top:50%; transform:translateY(-50%); width:34px; height:34px; margin:0 !important; padding:0 !important;
                   display:flex; align-items:center; justify-content:center; background:transparent !important; color:var(--muted); border:0 !important; border-radius:6px; cursor:pointer; }
        .pw .eye:hover { background:var(--mint) !important; color:var(--primary); }
        .pw .eye svg { width:18px; height:18px; pointer-events:none; }
        .pw .eye .eye-off { display:none; }
        .pw .eye.showing .eye-on { display:none; }
        .pw .eye.showing .eye-off { display:block; }
        h1 { font-size:1.45rem; margin:0 0 4px; color:#0f172a; }
        .sub { color:var(--muted); font-size:.88rem; margin:0 0 16px; line-height:1.4; }
        label { display:block; font-size:.82rem; font-weight:600; margin:12px 0 5px; color:#334155; }
        input { width:100%; padding:10px 13px; border:1px solid #cbd5e1; border-radius:8px; font-size:.95rem; background:#fff; }
        input:focus { outline:3px solid rgba(43,137,43,.25); border-color:var(--primary); }
        button { width:100%; margin-top:18px; padding:11px; border:0; border-radius:8px; background:var(--primary); color:#fff;
                 font-size:.95rem; font-weight:600; cursor:pointer; transition:background .15s; }
        button:hover { background:var(--primary-dark); }
        button.secondary { background:#fff; color:var(--ink); border:1px solid #cbd5e1; margin-top:8px; }
        .error { background:#fef2f2; border:1px solid #fecaca; color:var(--danger); padding:10px 12px; border-radius:8px; font-size:.85rem; margin-bottom:6px; line-height:1.4; }
        .links { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-top:14px; font-size:.86rem; }
        a { color:var(--primary); }
        .hint { font-size:.78rem; color:var(--muted); margin-top:5px; }
        .notice { display:flex; gap:10px; align-items:flex-start; margin-top:18px; padding:10px 12px; background:var(--mint);
                  border-radius:8px; font-size:.8rem; color:#335; line-height:1.4; }
        .back { margin-top:16px; font-size:.82rem; }
        .back a { color:var(--muted); text-decoration:none; }
        .back a:hover { color:var(--primary); }
        ul.scopes { padding-left:18px; font-size:.88rem; }
        @media (max-height: 740px) {
            .site-header .inner { height:64px; }
            .brand img { width:40px; height:40px; }
            .stage { padding:12px 5%; gap:32px; min-height:calc(100vh - 64px - 42px); }
            .panel { padding:18px 24px 16px; }
            .sub { margin-bottom:12px; }
            label { margin:10px 0 4px; }
            button { margin-top:14px; padding:10px; }
            .links { margin-top:10px; }
            .back { margin-top:10px; }
            .site-footer { padding:10px 16px; }
        }
        @media (max-width: 900px) {
            .caption { display:none; }
            .stage { padding:20px 16px; }
            .panel { padding:22px 20px 18px; }
            .site-header nav a:not(.cta) { display:none; }
        }
        .env-banner { background: #ea7317; color: #fff; text-align: center; font: 600 13px/1.4 system-ui, sans-serif; padding: 6px 12px; letter-spacing: .04em; }
    </style>
</head>
<body>
@if (filled(config('nis.environment_label')))
    <div class="env-banner" role="note">{{ strtoupper(config('nis.environment_label')) }} — test system, not the live service. Do not enter real personal data.</div>
@endif
<header class="site-header">
    <div class="inner">
        <a class="brand" href="{{ config('nis.frontend_url') }}">
            <img src="{{ asset('images/nis-logo.png') }}" alt="">
            <div><strong>NIGERIA IMMIGRATION SERVICE</strong><span>ECOWAS Residence Card Portal</span></div>
        </a>
        <nav>
            <a href="{{ config('nis.frontend_url') }}">Home</a>
            <a href="{{ config('nis.frontend_url') }}/faq">FAQ</a>
            <a href="{{ config('nis.frontend_url') }}/track">Track</a>
            <a class="cta" href="{{ config('nis.frontend_url') }}/register">Create account</a>
        </nav>
    </div>
</header>
<main class="stage">
    <img class="bg" src="{{ asset('images/'.trim($__env->yieldContent('image', 'hq-dusk.jpg'))) }}" alt="">
    <div class="caption" aria-hidden="true">
        <div class="eyebrow">Nigeria Immigration Service</div>
        <h2>@yield('headline', 'ECOWAS Residence Card Issuance Portal')</h2>
        <p>@yield('tagline', 'Directorate of Visa and Residency.')</p>
    </div>
    <section class="panel">
        @yield('content')
        <div class="back"><a href="{{ config('nis.frontend_url') }}">← Back to the ECOWAS Residence Card Portal</a></div>
    </section>
</main>
<footer class="site-footer">Nigeria Immigration Service · All rights reserved © {{ date('Y') }}</footer>
<script src="{{ asset('auth.js') }}?v={{ file_exists(public_path('auth.js')) ? filemtime(public_path('auth.js')) : '1' }}"></script>
</body>
</html>
