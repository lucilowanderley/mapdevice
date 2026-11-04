@extends('layouts.app')
@section('title', 'Visão geral')
@section('content')
<h1>Visão geral</h1>
<p class="sub">Painel do assistente de diagnóstico de equipamentos.</p>

<div class="grid2">
    <div class="panel">
        <div class="row" style="justify-content:space-between;">
            <div>
                <div style="font-size:32px;font-weight:700;">{{ $deviceCount }}</div>
                <div class="hint">equipamentos cadastrados ({{ $activeDevices }} ativos)</div>
            </div>
            <a href="{{ route('devices.index') }}" class="btn secondary small">Ver equipamentos</a>
        </div>
    </div>
    <div class="panel">
        <div class="row" style="justify-content:space-between;">
            <div>
                <div style="font-size:32px;font-weight:700;">{{ $providerCount }}</div>
                <div class="hint">
                    provedores de IA ativos
                    @if($defaultProvider) · padrão: <strong>{{ $defaultProvider->name }}</strong> ({{ $defaultProvider->model }})@endif
                </div>
            </div>
            @auth @if(auth()->user()->isAdmin())
                <a href="{{ route('ai-providers.index') }}" class="btn secondary small">Provedores</a>
            @endif @endauth
        </div>
    </div>
</div>

<div class="panel">
    <div class="row" style="justify-content:space-between;">
        <h2 style="font-size:16px;margin:0;">Começar</h2>
        <a href="{{ route('assistant.index') }}" class="btn">Abrir o assistente</a>
    </div>
    @unless($defaultProvider)
        <p class="hint" style="margin-top:10px;">Nenhum provedor de IA configurado ainda. Cadastre um em <a href="{{ route('ai-providers.index') }}">Provedores</a> para usar o assistente.</p>
    @endunless
</div>

@if($recentDevices->isNotEmpty())
<div class="panel">
    <h2 style="font-size:16px;margin:0 0 10px;">Equipamentos recentes</h2>
    <table>
        <thead><tr><th>Nome</th><th>Fabricante / modelo</th><th>Acesso</th><th></th></tr></thead>
        <tbody>
        @foreach($recentDevices as $d)
            <tr>
                <td><strong>{{ $d->name }}</strong></td>
                <td>{{ $d->manufacturer ?: '—' }} {{ $d->model }}</td>
                <td><span class="tag">{{ $d->accessLabel() }}</span></td>
                <td style="text-align:right;"><a href="{{ route('assistant.index', ['equipamento' => $d->id]) }}" class="btn secondary small">Diagnosticar</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
