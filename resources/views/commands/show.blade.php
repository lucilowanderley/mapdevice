@extends('layouts.app')
@section('title', 'Comandos · ' . $device->name)
@section('content')
@php($userIsAdmin = auth()->user()->isAdmin())
<div class="row" style="justify-content:space-between;">
    <div>
        <h1>{{ $device->name }}</h1>
        <p class="sub">
            <span class="tag">{{ $device->moduleLabel() }}</span>
            {{ $device->manufacturer }} {{ $device->model }} ·
            {{ $device->host }} <span class="hint">({{ $device->accessLabel() }}{{ $device->port ? ':'.$device->port : '' }})</span>
        </p>
    </div>
    <div style="white-space:nowrap;">
        <a href="{{ route('discovery.show', $device) }}" class="btn btn-sm">Descoberta</a>
        <a href="{{ route('commands.index') }}" class="btn btn-sm">Voltar</a>
    </div>
</div>

@isset($result)
    <div class="panel" style="border-left:4px solid {{ $result->success ? 'var(--green)' : 'var(--red)' }};">
        <h2 style="margin-top:0;">Resultado — {{ $ranAction->name }}</h2>
        @if($result->success)
            @if($ranAction->isWrite())
                @if($result->verified === true)
                    <p class="flash ok" style="margin-top:0;">✓ Alteração aplicada e <strong>verificada</strong>: o equipamento devolveu o valor esperado{{ $result->expected ? ' ("'.$result->expected.'")' : '' }}.</p>
                @elseif($result->verified === false)
                    <p class="flash err" style="margin-top:0;">A alteração foi enviada, mas a verificação não encontrou o valor esperado{{ $result->expected ? ' ("'.$result->expected.'")' : '' }}. Confira a saída abaixo.</p>
                @else
                    <p class="hint">Alteração enviada. Esta ação não define verificação automática de leitura.</p>
                @endif
            @endif
            <pre class="output">{{ $result->output() }}</pre>
            @if($result->verifyOutput)
                <h3>Leitura de verificação</h3>
                <pre class="output">{{ $result->verifyOutput }}</pre>
            @endif
        @else
            <p class="flash err" style="margin-top:0;">{{ $result->error }}</p>
        @endif
    </div>
@endisset

@if(! $profile)
    <div class="panel">
        <p class="hint">Nenhum perfil da Biblioteca atende a este equipamento (fabricante <strong>{{ $device->manufacturer ?: '—' }}</strong>, acesso {{ $device->accessLabel() }}).</p>
        <p class="hint">Peça ao assistente para gerar um rascunho de perfil e importe-o, ou cadastre o fabricante/modelo do equipamento.</p>
    </div>
@else
    <div class="panel">
        <div class="row" style="justify-content:space-between;">
            <h2 style="margin:0;">{{ $profile->name }}</h2>
            <span class="tag">{{ $profile->protocolLabel() }}</span>
        </div>
        @if($profile->description)<p class="hint">{{ $profile->description }}</p>@endif

        @foreach($profile->actions as $action)
            <div class="action" style="border:1px solid var(--border-soft);border-radius:8px;padding:14px;margin-top:14px;">
                <div class="row" style="justify-content:space-between;">
                    <div>
                        <strong>{{ $action->name }}</strong>
                        <span class="hint">{{ $action->key }}</span>
                    </div>
                    @if($action->isWrite())
                        <span class="tag err">escrita</span>
                    @else
                        <span class="tag ok">consulta</span>
                    @endif
                </div>
                @if($action->description)<p class="hint" style="margin:6px 0;">{{ $action->description }}</p>@endif

                <form method="post" action="{{ route('commands.run', $device) }}">
                    @csrf
                    <input type="hidden" name="action" value="{{ $action->key }}">
                    @foreach($action->params ?? [] as $p)
                        <div style="margin:8px 0;">
                            <label style="display:block;font-size:13px;">
                                {{ $p['label'] ?? $p['name'] }}@if($p['required'] ?? true) <span style="color:var(--red);">*</span>@endif
                            </label>
                            <input type="{{ ($p['secret'] ?? false) ? 'password' : 'text' }}"
                                   name="params[{{ $p['name'] }}]"
                                   value="{{ old('params.'.$p['name']) }}"
                                   placeholder="{{ $p['pattern'] ?? '' }}"
                                   style="max-width:360px;">
                        </div>
                    @endforeach

                    @if($action->isWrite())
                        <label class="hint" style="display:block;margin:8px 0;color:var(--red);">
                            <input type="checkbox" name="confirm" value="1" required>
                            Confirmo executar esta <strong>alteração</strong> no equipamento{{ $action->verify['action'] ?? null ? ' (o sistema vai ler o valor de volta para conferir)' : '' }}.
                        </label>
                        <button class="btn" style="background:var(--red);border-color:var(--red);color:#fff;font-weight:600;">Executar alteração</button>
                    @else
                        <button class="btn btn-sm">Executar consulta</button>
                    @endif
                </form>
            </div>
        @endforeach
    </div>
@endif

<div class="panel">
    <h2 style="margin-top:0;">Últimas execuções</h2>
    @if($history->isEmpty())
        <p class="hint">Nenhuma execução registrada ainda.</p>
    @else
    <table>
        <thead><tr><th>Quando</th><th>Ação</th><th>Tipo</th><th>Resultado</th></tr></thead>
        <tbody>
        @foreach($history as $h)
            <tr>
                <td class="hint">{{ $h->created_at->format('d/m H:i') }}</td>
                <td>{{ $h->action_key }}</td>
                <td>@if($h->isWrite())<span class="tag err">escrita</span>@else<span class="tag ok">consulta</span>@endif</td>
                <td>
                    @if(! $h->success)<span class="tag err">falhou</span>
                    @elseif($h->verified === true)<span class="tag ok">verificada</span>
                    @elseif($h->verified === false)<span class="tag err">não conferiu</span>
                    @else<span class="tag ok">ok</span>@endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

<style>
    pre.output { background:#0f172a; color:#e2e8f0; padding:12px 14px; border-radius:8px; overflow:auto; font-size:13px; white-space:pre-wrap; word-break:break-word; }
</style>
@endsection
