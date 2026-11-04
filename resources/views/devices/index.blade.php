@extends('layouts.app')
@section('title', 'Equipamentos')
@section('content')
<div class="row" style="justify-content:space-between;">
    <div>
        <h1>Equipamentos</h1>
        <p class="sub">Equipamentos da rede que o provedor administra.</p>
    </div>
    @if(auth()->user()->isAdmin())
        <a href="{{ route('devices.create') }}" class="btn">Novo equipamento</a>
    @endif
</div>

<div class="panel">
    <form method="get" class="row" style="margin-bottom:14px;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por nome, fabricante, modelo ou IP" style="max-width:360px;">
        <button class="btn secondary small">Buscar</button>
    </form>

    @if($devices->isEmpty())
        <p class="hint">Nenhum equipamento cadastrado.</p>
    @else
    <table>
        <thead><tr><th>Nome</th><th>Módulo</th><th>Fabricante / modelo</th><th>Host</th><th>Acesso</th><th>Situação</th><th></th></tr></thead>
        <tbody>
        @foreach($devices as $d)
            <tr>
                <td><strong>{{ $d->name }}</strong></td>
                <td><span class="tag">{{ $d->moduleLabel() }}</span></td>
                <td>{{ $d->manufacturer ?: '—' }} {{ $d->model }}<br><span class="hint">{{ $d->firmware }}</span></td>
                <td>{{ $d->host ?: '—' }}</td>
                <td><span class="tag">{{ $d->accessLabel() }}</span>@if($d->port) <span class="hint">:{{ $d->port }}</span>@endif</td>
                <td>@if($d->active)<span class="tag ok">ativo</span>@else<span class="tag err">inativo</span>@endif</td>
                <td style="text-align:right;white-space:nowrap;">
                    <a href="{{ route('assistant.index', ['equipamento' => $d->id]) }}" class="btn secondary small">Diagnosticar</a>
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('devices.edit', $d) }}" class="btn secondary small">Editar</a>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div style="margin-top:14px;">{{ $devices->links() }}</div>
    @endif
</div>
@endsection
