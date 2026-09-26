<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') — Nigeria Immigration Service</title>
    <link rel="icon" href="/favicon.ico">
    <style>
        :root {
            --primary:#2b892b; --primary-dark:#1f6b1f; --deep:#073f1c; --mint:#eef7f1; --orange:#ea7317;
            --ink:#1e293b; --muted:#64748b; --line:#e2e8f0; --danger:#b91c1c;
        }
        * { box-sizing:border-box; }
        html, body { height:100%; }
        body { margin:0; color:var(--ink); background:#fff;
               font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif; }
        /* Header: same as the website's landing page header */
        .site-header { position:sticky; top:0; z-index:5; background:#f6f6f6; border-bottom:1px solid var(--line); box-shadow:0 1px 3px rgba(15,23,42,.08); }
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
        .stage { position:relative; min-height:calc(100vh - 80px - 58px); display:flex; align-items:center; justify-content:center; gap:56px;
                 padding:48px 5%; overflow:hidden; background:var(--deep); }
        .stage img.bg { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
        .stage::after { content:""; position:absolute; inset:0; background:linear-gradient(120deg, rgba(0,0,0,.72) 0%, rgba(7,63,28,.55) 55%, rgba(0,0,0,.45) 100%); }
        .caption { position:relative; z-index:1; color:#fff; max-width:30rem; }
        .caption .eyebrow { font-size:.78rem; letter-spacing:.2em; text-transform:uppercase; color:rgba(255,255,255,.8); font-weight:600; }
        .caption h2 { margin:.6rem 0 0; font-size:2.4rem; line-height:1.2; font-weight:600; }
        .caption h2 span { color:var(--orange); }
        .caption p { margin:1rem 0 0; color:rgba(255,255,255,.88); line-height:1.6; }
        .panel { position:relative; z-index:1; width:100%; max-width:460px; background:#fff; border-radius:16px; padding:36px 36px 28px;
                 box-shadow:0 25px 50px -12px rgba(0,0,0,.45); }
        .site-footer { padding:18px 16px; text-align:center; font-size:.875rem; color:#475569; background:#fff; border-top:1px solid var(--line); }
        .qr { display:flex; justify-content:center; margin:6px 0 10px; }
        .qr svg { width:200px; height:200px; }
        code.secret { display:inline-block; margin-top:6px; padding:6px 10px; background:var(--mint); border-radius:6px; font-size:.95rem; letter-spacing:.08em; word-break:break-all; }
        .pw { position:relative; }
        .pw input { padding-right:48px; }
        .pw .eye { position:absolute; right:6px; top:50%; transform:translateY(-50%); width:38px; height:38px; margin:0; padding:0;
                   display:flex; align-items:center; justify-content:center; background:transparent; color:var(--muted); border-radius:6px; }
        .pw .eye:hover { background:var(--mint); color:var(--primary); }
        .pw .eye svg { width:20px; height:20px; }
        .pw .eye .off { display:none; }
        .pw .eye[aria-pressed="true"] .on { display:none; }
        .pw .eye[aria-pressed="true"] .off { display:block; }
        h1 { font-size:1.6rem; margin:0 0 6px; color:#0f172a; }
        .sub { color:var(--muted); font-size:.92rem; margin:0 0 26px; line-height:1.5; }
        label { display:block; font-size:.85rem; font-weight:600; margin:18px 0 6px; color:#334155; }
        input { width:100%; padding:12px 14px; border:1px solid #cbd5e1; border-radius:8px; font-size:1rem; background:#fff; }
        input:focus { outline:3px solid rgba(43,137,43,.25); border-color:var(--primary); }
        button { width:100%; margin-top:26px; padding:13px; border:0; border-radius:8px; background:var(--primary); color:#fff;
                 font-size:1rem; font-weight:600; cursor:pointer; transition:background .15s; }
        button:hover { background:var(--primary-dark); }
        button.secondary { background:#fff; color:var(--ink); border:1px solid #cbd5e1; margin-top:10px; }
        .error { background:#fef2f2; border:1px solid #fecaca; color:var(--danger); padding:11px 13px; border-radius:8px; font-size:.88rem; margin-bottom:6px; line-height:1.45; }
        .links { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-top:20px; font-size:.9rem; }
        a { color:var(--primary); }
        .hint { font-size:.8rem; color:var(--muted); margin-top:6px; }
        .notice { display:flex; gap:10px; align-items:flex-start; margin-top:28px; padding:12px 14px; background:var(--mint);
                  border-radius:8px; font-size:.82rem; color:#335; line-height:1.45; }
        .back { margin-top:28px; font-size:.85rem; }
        .back a { color:var(--muted); text-decoration:none; }
        .back a:hover { color:var(--primary); }
        ul.scopes { padding-left:18px; font-size:.92rem; }
        @media (max-width: 900px) {
            .caption { display:none; }
            .stage { padding:28px 16px; }
            .panel { padding:28px 22px 22px; }
            .site-header nav a:not(.cta) { display:none; }
        }
    </style>
</head>
<body>
<header class="site-header">
    <div class="inner">
        <a class="brand" href="{{ config('nis.frontend_url') }}">
            <img src="/images/nis-logo.png" alt="">
            <div><strong>NIGERIA IMMIGRATION SERVICE</strong><span>Residence Card Portal</span></div>
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
    <img class="bg" src="/images/@yield('image', 'hq-dusk.jpg')" alt="">
    <div class="caption" aria-hidden="true">
        <div class="eyebrow">Nigeria Immigration Service</div>
        <h2>@yield('headline', 'Residence Card Issuance System')</h2>
        <p>@yield('tagline', 'Directorate of Visa and Residency.')</p>
    </div>
    <section class="panel">
        @yield('content')
        <div class="back"><a href="{{ config('nis.frontend_url') }}">← Back to the Residence Card Portal</a></div>
    </section>
</main>
<footer class="site-footer">Nigeria Immigration Service · All rights reserved © {{ date('Y') }}</footer>
<script src="/js/password-toggle.js" defer></script>
</body>
</html>
