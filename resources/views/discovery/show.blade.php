@extends('layouts.app')
@section('title', 'Descoberta · ' . $device->name)
@section('content')
@php($clueLabels = [
    'ssh_banner' => 'Banner SSH', 'telnet_banner' => 'Banner Telnet',
    'http_server' => 'Servidor HTTP', 'http_title' => 'Título da página',
    'snmp_sysdescr' => 'SNMP sysDescr', 'snmp_sysobjectid' => 'SNMP sysObjectID',
])
<div class="row" style="justify-content:space-between;">
    <div>
        <h1>Descoberta — {{ $device->name }}</h1>
        <p class="sub">Sonda só-leitura: identifica fabricante e sistema embarcado sem alterar o equipamento.</p>
    </div>
    <div style="white-space:nowrap;">
        <a href="{{ route('probe.show', $device) }}" class="btn secondary small">Sondagem</a>
        <a href="{{ route('commands.show', $device) }}" class="btn secondary small">Comandos</a>
    </div>
</div>

<div class="panel">
    <div class="row" style="justify-content:space-between;align-items:center;">
        <p class="hint" style="margin:0;">
            {{ $device->manufacturer ?: 'Fabricante não definido' }} {{ $device->model }} ·
            {{ $device->host ?: 'sem host' }} <span class="hint">({{ $device->accessLabel() }})</span>
        </p>
        <form method="post" action="{{ route('discovery.run', $device) }}">
            @csrf
            <button class="btn">Descobrir agora</button>
        </form>
    </div>
</div>

@if($latest)
    <div class="panel">
        <h2 style="margin-top:0;">Último resultado <span class="hint">· {{ $latest->created_at->format('d/m/Y H:i') }}</span></h2>

        @if($latest->manufacturer)
            <p class="flash ok">
                Palpite: <strong>{{ $latest->manufacturer }}</strong> — {{ $latest->os }} ·
                acesso sugerido <span class="tag">{{ \App\Models\Device::ACCESS_METHODS[$latest->access_method] ?? $latest->access_method }}</span> ·
                confiança {{ $latest->confidence }}%
            </p>
            @if(auth()->user()->isAdmin())
                <form method="post" action="{{ route('discovery.apply', [$device, $latest]) }}" style="margin-bottom:12px;">
                    @csrf
                    <button class="btn secondary small">Aplicar ao cadastro do equipamento</button>
                </form>
            @endif
        @else
            <p class="flash err">Não deu para identificar o fabricante pelas pistas coletadas.</p>
        @endif

        <h3>Portas</h3>
        <p>
            @foreach($latest->ports ?? [] as $label => $open)
                <span class="tag {{ $open ? 'ok' : 'err' }}">{{ $label }}: {{ $open ? 'aberta' : 'fechada' }}</span>
            @endforeach
        </p>

        @if($latest->clues)
            <h3>Pistas coletadas</h3>
            <table>
                <tbody>
                @foreach($latest->clues as $key => $value)
                    <tr><td style="white-space:nowrap;"><strong>{{ $clueLabels[$key] ?? $key }}</strong></td><td><code>{{ $value }}</code></td></tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @foreach($latest->notes ?? [] as $note)
            <p class="hint">• {{ $note }}</p>
        @endforeach
    </div>
@else
    <div class="panel"><p class="hint">Nenhuma descoberta ainda. Clique em “Descobrir agora”.</p></div>
@endif

@if($history->count() > 1)
    <div class="panel">
        <h2 style="margin-top:0;">Histórico</h2>
        <table>
            <thead><tr><th>Quando</th><th>Palpite</th><th>Confiança</th><th>Portas abertas</th></tr></thead>
            <tbody>
            @foreach($history as $h)
                <tr>
                    <td class="hint">{{ $h->created_at->format('d/m H:i') }}</td>
                    <td>{{ $h->manufacturer ? $h->manufacturer.' / '.$h->os : '—' }}</td>
                    <td>{{ $h->confidence }}%</td>
                    <td class="hint">{{ implode(', ', $h->openPorts()) ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
