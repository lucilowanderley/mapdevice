<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Painel') · {{ config('app.name') }}</title>
    <style>
        :root { --bg:#0f172a; --panel:#fff; --ink:#1e293b; --muted:#64748b; --line:#e2e8f0;
            --brand:#0ea5e9; --brand-ink:#0369a1; --ok:#16a34a; --err:#dc2626; --soft:#f1f5f9; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif; color:var(--ink); background:var(--soft); }
        a { color:var(--brand-ink); text-decoration:none; }
        a:hover { text-decoration:underline; }
        header.top { background:linear-gradient(90deg,#0c4a6e,#0ea5e9); color:#fff; padding:14px 20px; display:flex; align-items:center; gap:24px; flex-wrap:wrap; }
        header.top .brand { font-weight:700; font-size:18px; letter-spacing:.5px; }
        header.top nav { display:flex; gap:18px; flex-wrap:wrap; }
        header.top nav a { color:#e0f2fe; font-weight:500; }
        header.top nav a.active, header.top nav a:hover { color:#fff; text-decoration:none; border-bottom:2px solid #fff; padding-bottom:2px; }
        header.top .spacer { margin-left:auto; }
        header.top form { margin:0; }
        .logout { background:transparent; border:1px solid rgba(255,255,255,.5); color:#fff; padding:6px 12px; border-radius:6px; cursor:pointer; }
        main { max-width:1100px; margin:24px auto; padding:0 16px; }
        h1 { font-size:22px; margin:0 0 4px; }
        .sub { color:var(--muted); margin:0 0 20px; }
        .panel { background:var(--panel); border:1px solid var(--line); border-radius:10px; padding:20px; margin-bottom:18px; }
        .flash { padding:12px 16px; border-radius:8px; margin-bottom:16px; }
        .flash.ok { background:#dcfce7; color:#14532d; border:1px solid #86efac; }
        .flash.err { background:#fee2e2; color:#7f1d1d; border:1px solid #fca5a5; }
        table { width:100%; border-collapse:collapse; }
        th,td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--line); font-size:14px; }
        th { color:var(--muted); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:.4px; }
        .btn { display:inline-block; background:var(--brand); color:#fff; border:none; padding:9px 16px; border-radius:7px; font-size:14px; cursor:pointer; font-weight:600; }
        .btn:hover { background:#0284c7; text-decoration:none; }
        .btn.secondary { background:#fff; color:var(--ink); border:1px solid var(--line); }
        .btn.danger { background:var(--err); }
        .btn.small { padding:5px 10px; font-size:13px; }
        label { display:block; font-weight:600; font-size:13px; margin:14px 0 5px; }
        input[type=text],input[type=email],input[type=password],input[type=number],select,textarea {
            width:100%; padding:9px 11px; border:1px solid var(--line); border-radius:7px; font-size:14px; font-family:inherit; }
        textarea { resize:vertical; }
        .hint { color:var(--muted); font-size:12px; margin-top:4px; }
        .err-msg { color:var(--err); font-size:13px; margin-top:4px; }
        .grid2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .row { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
        .tag { display:inline-block; font-size:12px; padding:2px 9px; border-radius:999px; background:var(--soft); color:var(--muted); }
        .tag.ok { background:#dcfce7; color:#14532d; } .tag.err { background:#fee2e2; color:#7f1d1d; }
        .tag.brand { background:#e0f2fe; color:var(--brand-ink); }
        @media (max-width:640px){ .grid2{grid-template-columns:1fr;} }
    </style>
    @stack('head')
</head>
<body>
@auth
    @php($u = auth()->user())
    <header class="top">
        <span class="brand">{{ config('app.name') }}</span>
        <nav>
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Visão geral</a>
            <a href="{{ route('devices.index') }}" class="{{ request()->routeIs('devices.*') ? 'active' : '' }}">Equipamentos</a>
            <a href="{{ route('assistant.index') }}" class="{{ request()->routeIs('assistant.*') ? 'active' : '' }}">Assistente</a>
            @if($u->isAdmin())
                <a href="{{ route('ai-providers.index') }}" class="{{ request()->routeIs('ai-providers.*') ? 'active' : '' }}">Provedores</a>
            @endif
        </nav>
        <div class="spacer"></div>
        <span style="color:#e0f2fe;font-size:13px;">{{ $u->first_name }} · {{ $u->role_label }}</span>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="logout">Sair</button></form>
    </header>
@endauth

<main>
    @if(session('success'))<div class="flash ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="flash err">{{ session('error') }}</div>@endif
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
