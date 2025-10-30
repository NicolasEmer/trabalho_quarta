<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>@yield('title','Eventos')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { --bg:#0f172a; --card:#111827; --muted:#9ca3af; --text:#e5e7eb; --brand:#22c55e; --danger:#ef4444; }
        *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--text);font:16px/1.4 system-ui,Segoe UI,Roboto}
        a{color:#60a5fa;text-decoration:none} a:hover{text-decoration:underline}
        .wrap{max-width:960px;margin:24px auto;padding:0 16px}
        .card{background:var(--card);border:1px solid #1f2937;border-radius:14px;padding:16px;box-shadow:0 6px 20px rgba(0,0,0,.25)}
        .row{display:flex;gap:12px;flex-wrap:wrap} .grow{flex:1}
        .btn{display:inline-block;border-radius:10px;padding:10px 14px;border:1px solid #374151}
        .btn-primary{background:var(--brand);color:#052e16;border:1px solid #16a34a}
        .btn-danger{background:var(--danger);color:#fff;border:1px solid #b91c1c}
        .btn-outline{background:transparent;color:#e5e7eb}
        input,textarea{width:100%;background:#0b1220;color:#e5e7eb;border:1px solid #1f2937;border-radius:10px;padding:10px}
        label{font-size:14px;color:var(--muted)} .muted{color:var(--muted);font-size:14px}
        table{width:100%;border-collapse:collapse} th,td{padding:10px;border-bottom:1px solid #1f2937}
        th{text-align:left;color:#a1a1aa}
        .badge{font-size:12px;border-radius:999px;padding:4px 8px;border:1px solid #374151}
        .right{text-align:right}
        .flash{padding:10px 12px;border-radius:10px;border:1px solid #14532d;background:#052e16;margin-bottom:12px}
        .actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        form.inline{display:inline}
    </style>
    @stack('head')
</head>
<body>
<div class="wrap">
    <div class="card" style="margin-bottom:16px">
        <div class="row" style="align-items:center">
            <div class="grow">
                <h1 style="margin:0;font-size:22px">📅 @yield('title','Eventos')</h1>
                <div class="muted">CRUD de eventos</div>
            </div>
            <div>
                <a class="btn btn-primary" href="{{ route('events.create') }}">+ Novo evento</a>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="flash card">{{ session('success') }}</div>
    @endif

    <div class="card">
        @yield('content')
    </div>
</div>
@stack('scripts')
</body>
</html>
