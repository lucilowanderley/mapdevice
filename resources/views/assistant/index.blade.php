@extends('layouts.app')
@section('title', 'Assistente')

@push('head')
<style>
    .chat { display:grid; grid-template-columns:260px 1fr; gap:16px; align-items:start; }
    .side { background:#fff; border:1px solid var(--line); border-radius:10px; padding:12px; max-height:72vh; overflow:auto; }
    .side a.conv { display:block; padding:8px 10px; border-radius:7px; font-size:13px; color:var(--ink); }
    .side a.conv:hover, .side a.conv.active { background:var(--soft); text-decoration:none; }
    .side a.conv small { color:var(--muted); display:block; }
    .msgs { background:#fff; border:1px solid var(--line); border-radius:10px; padding:16px; min-height:40vh; }
    .msg { margin-bottom:16px; }
    .msg .who { font-size:12px; color:var(--muted); font-weight:600; margin-bottom:4px; }
    .msg .body { padding:10px 14px; border-radius:10px; font-size:14px; line-height:1.55; }
    .msg.user .body { background:#e0f2fe; }
    .msg.ai .body { background:var(--soft); }
    .msg.ai.err .body { background:#fee2e2; color:#7f1d1d; }
    .msg .body pre { background:#0f172a; color:#e2e8f0; padding:12px; border-radius:8px; overflow:auto; font-size:13px; }
    .msg .body code { font-family:ui-monospace,Menlo,Consolas,monospace; }
    .msg .body p:first-child { margin-top:0; } .msg .body p:last-child { margin-bottom:0; }
    .actions { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
    .actions button { background:#fff; border:1px solid var(--line); border-radius:999px; padding:6px 13px; font-size:13px; cursor:pointer; }
    .actions button:hover { background:var(--soft); }
    @media (max-width:760px){ .chat{grid-template-columns:1fr;} }
</style>
@endpush

@section('content')
<div class="row" style="justify-content:space-between;">
    <div>
        <h1>Assistente</h1>
        <p class="sub">
            @if($conversation->device)
                Equipamento: <strong>{{ $conversation->device->name }}</strong>
                <span class="hint">({{ $conversation->device->manufacturer }} {{ $conversation->device->model }})</span>
            @else
                Diagnóstico de equipamentos de rede com IA.
            @endif
        </p>
    </div>
    <a href="{{ route('assistant.index') }}" class="btn secondary small">Nova conversa</a>
</div>

@unless($defaultProvider)
    <div class="flash err">Nenhum provedor de IA ativo. Cadastre um em <a href="{{ route('ai-providers.index') }}">Provedores</a> antes de conversar.</div>
@endunless

<div class="chat">
    <aside class="side">
        <div style="font-size:12px;color:var(--muted);font-weight:600;margin-bottom:8px;">CONVERSAS</div>
        @forelse($conversations as $c)
            <a class="conv {{ $conversation->id === $c->id ? 'active' : '' }}" href="{{ route('assistant.show', $c) }}">
                {{ \Illuminate\Support\Str::limit($c->title, 38) }}
                @if($c->device)<small>{{ $c->device->name }}</small>@endif
            </a>
        @empty
            <p class="hint">Nenhuma conversa ainda.</p>
        @endforelse
    </aside>

    <section>
        <div class="msgs" id="msgs">
            @forelse($messages as $m)
                @include('assistant._message', ['message' => $m])
            @empty
                <p class="hint">Escreva uma mensagem ou use um atalho abaixo para começar.</p>
            @endforelse
            <span id="fim"></span>
        </div>

        @php($shortcuts = \App\Support\Ai\Assistant::actionsFor($conversation))
        @if($shortcuts)
        <div class="actions" style="margin-top:12px;">
            @foreach($shortcuts as $key => $a)
                <form method="post" action="{{ $conversation->exists ? route('assistant.send', $conversation) : route('assistant.store') }}">
                    @csrf
                    @unless($conversation->exists)
                        <input type="hidden" name="device_id" value="{{ $conversation->device_id }}">
                        @if($defaultProvider)<input type="hidden" name="ai_provider_id" value="{{ $defaultProvider->id }}">@endif
                    @endunless
                    <input type="hidden" name="message" value="{{ $a['prompt'] }}">
                    <button type="submit">{{ $a['label'] }}</button>
                </form>
            @endforeach
        </div>
        @endif

        <form method="post" action="{{ $conversation->exists ? route('assistant.send', $conversation) : route('assistant.store') }}" style="margin-top:8px;">
            @csrf
            @unless($conversation->exists)
                <input type="hidden" name="device_id" value="{{ $conversation->device_id }}">
                @if($defaultProvider)<input type="hidden" name="ai_provider_id" value="{{ $defaultProvider->id }}">@endif
            @endunless
            <textarea name="message" rows="3" placeholder="Descreva o problema do equipamento..." required @disabled(!$defaultProvider)></textarea>
            <div class="row" style="margin-top:10px;justify-content:space-between;">
                <span class="hint">@if($defaultProvider)Provedor: {{ $defaultProvider->name }} ({{ $defaultProvider->model }})@endif</span>
                <button class="btn" @disabled(!$defaultProvider)>Enviar</button>
            </div>
        </form>
    </section>
</div>

@push('scripts')
<script>document.getElementById('fim')?.scrollIntoView();</script>
@endpush
@endsection
