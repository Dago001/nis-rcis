<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') — Nigeria Immigration Service</title>
    <link rel="icon" href="/favicon.ico">
    <style>
        :root { --green:#0b5d2a; --green-dark:#073f1c; --gold:#c9a227; --ink:#1e293b; --muted:#64748b; --line:#e2e8f0; --bg:#f1f5f4; --danger:#b91c1c; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:flex; flex-direction:column; background:var(--bg); color:var(--ink);
               font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif; }
        header { background:var(--green-dark); color:#fff; border-bottom:4px solid var(--gold); }
        header .inner { max-width:960px; margin:0 auto; padding:14px 16px; display:flex; align-items:center; gap:12px; }
        header img { width:44px; height:44px; }
        header strong { display:block; font-size:1rem; letter-spacing:.02em; }
        header span { font-size:.8rem; opacity:.85; }
        main { flex:1; display:flex; align-items:flex-start; justify-content:center; padding:48px 16px; }
        .card { width:100%; max-width:420px; background:#fff; border:1px solid var(--line); border-radius:12px; padding:32px 28px;
                box-shadow:0 10px 30px rgba(15,23,42,.06); }
        h1 { font-size:1.35rem; margin:0 0 4px; }
        .sub { color:var(--muted); font-size:.9rem; margin:0 0 24px; }
        label { display:block; font-size:.85rem; font-weight:600; margin:16px 0 6px; }
        input { width:100%; padding:11px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:1rem; }
        input:focus { outline:2px solid var(--green); outline-offset:1px; border-color:var(--green); }
        button { width:100%; margin-top:24px; padding:12px; border:0; border-radius:8px; background:var(--green); color:#fff;
                 font-size:1rem; font-weight:600; cursor:pointer; }
        button:hover { background:var(--green-dark); }
        button.secondary { background:#fff; color:var(--ink); border:1px solid #cbd5e1; margin-top:10px; }
        .error { background:#fef2f2; border:1px solid #fecaca; color:var(--danger); padding:10px 12px; border-radius:8px; font-size:.88rem; margin-bottom:8px; }
        .links { display:flex; justify-content:space-between; margin-top:18px; font-size:.88rem; }
        a { color:var(--green); }
        .hint { font-size:.8rem; color:var(--muted); margin-top:6px; }
        footer { text-align:center; font-size:.78rem; color:var(--muted); padding:20px 16px; }
        ul.scopes { padding-left:18px; font-size:.9rem; }
    </style>
</head>
<body>
<header>
    <div class="inner">
        <img src="/images/nis-crest.png" alt="">
        <div>
            <strong>NIGERIA IMMIGRATION SERVICE</strong>
            <span>Residence Card Issuance System</span>
        </div>
    </div>
</header>
<main>
    <div class="card">
        @yield('content')
    </div>
</main>
<footer>&copy; {{ date('Y') }} Nigeria Immigration Service · Directorate of Visa and Residency</footer>
</body>
</html>
