<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    @include('layouts.head', ['title' => trim($__env->yieldContent('title')) ?: ($title ?? null)])
</head>
<body>
@auth
    @php
        $u = auth()->user();
        $nav = [
            ['route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'home', 'label' => 'Visão geral'],
            ['route' => 'devices.index', 'match' => 'devices.*', 'icon' => 'router', 'label' => 'Equipamentos'],
            ['route' => 'commands.index', 'match' => ['commands.*', 'discovery.*'], 'icon' => 'terminal', 'label' => 'Comandos'],
            ['route' => 'library.index', 'match' => 'library.*', 'icon' => 'book', 'label' => 'Biblioteca'],
            ['route' => 'assistant.index', 'match' => 'assistant.*', 'icon' => 'sparkles', 'label' => 'Assistente IA'],
        ];
    @endphp
    <div class="app">
        <aside class="sidebar" id="sidebar">
            <a href="{{ route('dashboard') }}" class="brand">
                <x-logo />
                <div>
                    <div class="brand-name">MAP<span>DEVICE</span></div>
                    <div class="brand-tag">Diagnóstico de rede</div>
                </div>
            </a>
            <nav class="nav">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['match']) ? 'active' : '' }}">
                        <x-icon :name="$item['icon']" /> {{ $item['label'] }}
                    </a>
                @endforeach

                <div class="nav-label">Sistema</div>
                @if ($u->isAdmin())
                    <a href="{{ route('ai-providers.index') }}" class="{{ request()->routeIs('ai-providers.*') ? 'active' : '' }}"><x-icon name="plug" /> Provedores de IA</a>
                @endif
            </nav>
            <div class="sidebar-foot">mapdevice · v1.0</div>
        </aside>

        <div class="main">
            <header class="topbar">
                <button class="icon-btn menu-toggle" data-nav-toggle aria-label="Abrir menu"><x-icon name="menu" /></button>
                <form class="search-box" action="{{ route('devices.index') }}" method="get">
                    <x-icon name="search" class="sm" />
                    <input type="search" name="q" value="{{ request()->routeIs('devices.*') ? request('q') : '' }}" placeholder="Buscar equipamentos...">
                </form>
                <div class="spacer"></div>
                <button class="icon-btn" data-theme-toggle title="Alternar tema claro/escuro"><x-icon name="sun" /></button>
                <div class="dropdown">
                    <button class="user-chip" data-dropdown-toggle>
                        <span class="avatar">{{ mb_strtoupper(mb_substr($u->first_name ?: $u->name, 0, 1)) }}</span>
                        <span class="hide-sm">{{ $u->first_name }}</span>
                        <x-icon name="chevron-down" class="sm" />
                    </button>
                    <div class="dropdown-menu">
                        <div class="small muted" style="padding: 6px 10px">{{ $u->email }}<br>{{ $u->role_label }}</div>
                        <div class="sep"></div>
                        @if ($u->isAdmin())
                            <a href="{{ route('ai-providers.index') }}"><x-icon name="plug" /> Provedores de IA</a>
                        @endif
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"><x-icon name="logout" /> Sair</button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="content">
                @if (session('success'))
                    <div class="alert success" data-autohide><x-icon name="check" /> {{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert error"><x-icon name="alert" /> {{ session('error') }}</div>
                @endif
                @if ($errors->any() && ! isset($hideErrorSummary))
                    <div class="alert error"><x-icon name="alert" /> Verifique os campos destacados no formulário.</div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
@else
    @yield('content')
@endauth
<script src="{{ asset('js/app.js') }}?v=1"></script>
@stack('scripts')
</body>
</html>
