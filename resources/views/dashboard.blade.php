@extends('layouts.app')
@section('title', 'Visão geral')
@section('content')
<div class="page-head">
    <div>
        <h1>Visão geral</h1>
        <div class="sub">Assistente de diagnóstico dos equipamentos da rede.</div>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="{{ route('assistant.index') }}"><x-icon name="sparkles" class="sm" /> Abrir o assistente</a>
    </div>
</div>

<div class="grid grid-2 mb">
    <a class="card kpi" href="{{ route('devices.index') }}">
        <div class="kpi-icon"><x-icon name="router" /></div>
        <div>
            <div class="value">{{ $deviceCount }}</div>
            <div class="label">Equipamentos cadastrados · {{ $activeDevices }} ativos</div>
        </div>
    </a>
    <a class="card kpi" href="{{ auth()->user()->isAdmin() ? route('ai-providers.index') : route('assistant.index') }}">
        <div class="kpi-icon purple"><x-icon name="plug" /></div>
        <div>
            <div class="value">{{ $providerCount }}</div>
            <div class="label">
                Provedores de IA ativos
                @if($defaultProvider) · padrão: <strong>{{ $defaultProvider->name }}</strong> ({{ $defaultProvider->model }})@endif
            </div>
        </div>
    </a>
</div>

@unless($defaultProvider)
    <div class="alert warning"><x-icon name="alert" /> Nenhum provedor de IA configurado ainda. Cadastre um em <a class="link" href="{{ route('ai-providers.index') }}">Provedores</a> para usar o assistente.</div>
@endunless

<div class="card">
    <div class="card-head">
        <h2><x-icon name="router" /> Equipamentos recentes</h2>
        @if(auth()->user()->isAdmin())
            <a class="btn btn-sm btn-ghost" href="{{ route('devices.create') }}"><x-icon name="plus" class="sm" /> Novo equipamento</a>
        @endif
    </div>
    <div class="card-body flush">
        @if($recentDevices->isEmpty())
            <x-empty icon="router">Nenhum equipamento cadastrado ainda.</x-empty>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Nome</th><th>Fabricante / modelo</th><th>Acesso</th><th></th></tr></thead>
                    <tbody>
                    @foreach($recentDevices as $d)
                        <tr>
                            <td class="title">{{ $d->name }}</td>
                            <td>{{ $d->manufacturer ?: '—' }} {{ $d->model }}</td>
                            <td><span class="badge">{{ $d->accessLabel() }}</span></td>
                            <td class="right"><a href="{{ route('assistant.index', ['equipamento' => $d->id]) }}" class="btn btn-sm"><x-icon name="sparkles" class="sm" /> Diagnosticar</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
