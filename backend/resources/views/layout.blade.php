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
        .split { display:grid; grid-template-columns: 1.1fr 1fr; min-height:100vh; }
        .visual { position:relative; overflow:hidden; background:var(--deep); }
        .visual img.bg { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
        .visual::after { content:""; position:absolute; inset:0;
            background:linear-gradient(to top, rgba(7,63,28,.92) 0%, rgba(7,63,28,.55) 45%, rgba(0,0,0,.25) 100%); }
        .visual .caption { position:absolute; z-index:1; left:0; right:0; bottom:0; padding:48px; color:#fff; }
        .visual .eyebrow { font-size:.78rem; letter-spacing:.2em; text-transform:uppercase; color:rgba(255,255,255,.8); font-weight:600; }
        .visual h2 { margin:.6rem 0 0; font-size:2rem; line-height:1.25; font-weight:600; max-width:30rem; }
        .visual h2 span { color:#fbbf6b; }
        .visual p { margin:.8rem 0 0; color:rgba(255,255,255,.85); max-width:30rem; line-height:1.55; }
        .panel { display:flex; flex-direction:column; justify-content:center; padding:48px clamp(24px,6vw,88px); background:#fff; }
        .brand { display:flex; align-items:center; gap:12px; margin-bottom:36px; text-decoration:none; }
        .brand img { width:52px; height:52px; }
        .brand strong { display:block; font-size:.82rem; letter-spacing:.04em; color:var(--primary-dark); }
        .brand span { display:block; font-size:.78rem; color:var(--muted); }
        .form-wrap { width:100%; max-width:400px; }
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
            .split { grid-template-columns: 1fr; }
            .visual { min-height:220px; }
            .visual .caption { padding:24px; }
            .visual h2 { font-size:1.4rem; }
            .visual p { display:none; }
            .panel { padding:32px 20px 48px; }
        }
    </style>
</head>
<body>
<div class="split">
    <aside class="visual" aria-hidden="true">
        <img class="bg" src="/images/@yield('image', 'hq-dusk.jpg')" alt="">
        <div class="caption">
            <div class="eyebrow">Nigeria Immigration Service</div>
            <h2>@yield('headline', 'Residence Card Issuance System')</h2>
            <p>@yield('tagline', 'Directorate of Visa and Residency.')</p>
        </div>
    </aside>
    <main class="panel">
        <div class="form-wrap">
            <a class="brand" href="{{ config('nis.frontend_url') }}">
                <img src="/images/nis-logo.png" alt="">
                <div><strong>NIGERIA IMMIGRATION SERVICE</strong><span>Residence Card Portal</span></div>
            </a>
            @yield('content')
            <div class="back"><a href="{{ config('nis.frontend_url') }}">← Back to the Residence Card Portal</a></div>
        </div>
    </main>
</div>
</body>
</html>
