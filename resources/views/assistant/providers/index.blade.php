@extends('layouts.app')
@section('title', 'Provedores de IA')
@section('content')
<div class="row" style="justify-content:space-between;">
    <div>
        <h1>Provedores de IA</h1>
        <p class="sub">Conexões com os modelos de linguagem. Você cadastra as chaves e os modelos aqui.</p>
    </div>
</div>

<div class="panel">
    <div style="font-size:13px;color:var(--muted);font-weight:600;margin-bottom:10px;">ADICIONAR PROVEDOR</div>
    <div class="row">
        @foreach(\App\Support\Ai\Drivers::ALL as $key => $d)
            @php($p = \App\Support\Ai\Drivers::PRICING[$d['pricing']] ?? ['label' => $d['pricing'], 'color' => ''])
            <a class="btn btn-sm" href="{{ route('ai-providers.create', ['tipo' => $key]) }}" title="{{ $d['pricing_note'] }}">
                {{ $d['label'] }}
                <span class="tag {{ $d['pricing'] === 'gratis' ? 'ok' : ($d['pricing'] === 'comercial' ? '' : 'brand') }}" style="margin-left:4px;">{{ $p['label'] }}</span>
            </a>
        @endforeach
    </div>
</div>

<div class="panel">
    @if($providers->isEmpty())
        <p class="hint">Nenhum provedor cadastrado. Escolha um acima para começar — há opções gratuitas (Ollama local) e pagas.</p>
    @else
    <table>
        <thead><tr><th>Nome</th><th>Tipo</th><th>Modelo</th><th>Consumo (mês)</th><th>Situação</th><th></th></tr></thead>
        <tbody>
        @foreach($providers as $p)
            @php($u = $usage[$p->name] ?? null)
            <tr>
                <td><strong>{{ $p->name }}</strong>@if($p->is_default) <span class="tag brand">padrão</span>@endif</td>
                <td>{{ $p->driver_label }}<br><span class="tag {{ $p->pricing === 'gratis' ? 'ok' : '' }}">{{ $p->pricing_label }}</span></td>
                <td>{{ $p->model }}</td>
                <td>@if($u)<span class="hint">{{ $u->replies }} respostas · {{ number_format($u->input + $u->output) }} tokens</span>@else<span class="hint">—</span>@endif</td>
                <td>
                    @if($p->active)<span class="tag ok">ativo</span>@else<span class="tag err">inativo</span>@endif
                    @if($p->last_tested_at)
                        <br><span class="tag {{ $p->last_test_ok ? 'ok' : 'err' }}">teste {{ $p->last_test_ok ? 'OK' : 'falhou' }}</span>
                    @endif
                </td>
                <td style="text-align:right;white-space:nowrap;">
                    <form method="post" action="{{ route('ai-providers.test', $p) }}" style="display:inline;">@csrf<button class="btn btn-sm">Testar</button></form>
                    @unless($p->is_default)
                        <form method="post" action="{{ route('ai-providers.default', $p) }}" style="display:inline;">@csrf<button class="btn btn-sm">Tornar padrão</button></form>
                    @endunless
                    <a href="{{ route('ai-providers.edit', $p) }}" class="btn btn-sm">Editar</a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
