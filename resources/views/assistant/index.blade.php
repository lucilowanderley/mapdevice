@extends('layouts.app')
@section('title', 'Assistente IA')
@php
    $isNew = ! $conversation->exists;
    $shortcuts = \App\Support\Ai\Assistant::actionsFor($conversation);
    $actionIcons = ['diagnostico' => 'activity', 'comandos' => 'terminal', 'fabricante' => 'radar', 'perfil' => 'book', 'sondagem' => 'globe', 'explicar' => 'message'];
    $sendAction = $conversation->exists ? route('assistant.send', $conversation) : route('assistant.store');
@endphp

@section('content')
<div class="page-head">
    <div>
        <h1>Assistente IA</h1>
        <div class="sub">
            @if($conversation->device)
                Equipamento: <strong>{{ $conversation->device->name }}</strong>
                <span class="faint">({{ $conversation->device->manufacturer }} {{ $conversation->device->model }})</span>
            @else
                Diagnóstico de equipamentos de rede com inteligência artificial.
            @endif
        </div>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="{{ route('assistant.index') }}"><x-icon name="plus" class="sm" /> Nova conversa</a>
        @if(auth()->user()->isAdmin())<a class="btn" href="{{ route('ai-providers.index') }}"><x-icon name="plug" class="sm" /> Provedores de IA</a>@endif
    </div>
</div>

@unless($defaultProvider)
    <div class="alert warning"><x-icon name="alert" /> Nenhum provedor de IA ativo. Cadastre um em <a class="link" href="{{ route('ai-providers.index') }}">Provedores</a> antes de conversar.</div>
@endunless

<div class="ai-layout">
    <aside class="card ai-history">
        <div class="card-head"><h3><x-icon name="history" /> Conversas</h3></div>
        @if ($conversations->isEmpty())
            <x-empty icon="message">Nenhuma conversa ainda.</x-empty>
        @else
            <ul class="list ai-conv-list">
                @foreach ($conversations as $c)
                    <li class="{{ $conversation->exists && $conversation->id === $c->id ? 'active' : '' }}">
                        <a href="{{ route('assistant.show', $c) }}" class="grow">
                            <span class="title">{{ \Illuminate\Support\Str::limit($c->title, 30) }}</span>
                            <span class="small faint">
                                @if ($c->device)<x-icon name="router" class="sm" /> {{ \Illuminate\Support\Str::limit($c->device->name, 24) }} · @endif
                                {{ $c->updated_at->diffForHumans() }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </aside>

    <section class="card ai-chat">
        <div class="card-head">
            <div class="ai-chat-title">
                <h3><x-icon name="sparkles" /> {{ $isNew ? 'Nova conversa' : $conversation->title }}</h3>
                @if ($conversation->device)
                    <div class="row wrap small">
                        <a class="badge gold" href="{{ route('assistant.index', ['equipamento' => $conversation->device_id]) }}"><x-icon name="router" class="sm" /> {{ $conversation->device->name }}</a>
                    </div>
                @endif
            </div>
        </div>

        <div class="ai-messages" data-chat-log>
            @forelse ($messages as $message)
                @include('assistant._message', ['message' => $message])
            @empty
                <div class="ai-welcome" data-chat-welcome>
                    <div class="ai-hero-icon"><x-icon name="sparkles" class="lg" /></div>
                    <h2>Como posso ajudar, {{ auth()->user()->first_name }}?</h2>
                    <p class="muted small">
                        @if ($conversation->device)
                            Os dados do equipamento (fabricante, modelo, firmware e pistas da descoberta) já estão no contexto da conversa.
                        @else
                            Dica: abra um equipamento e clique em <strong>Diagnosticar</strong> para conversar com os dados dele.
                        @endif
                    </p>
                </div>
            @endforelse
            <div id="fim"></div>
        </div>

        @if ($shortcuts)
            <div class="ai-actions">
                @foreach ($shortcuts as $key => $a)
                    <button type="button" class="chip-btn" data-prompt="{{ $a['prompt'] }}"><x-icon :name="$actionIcons[$key] ?? 'sparkles'" class="sm" /> {{ $a['label'] }}</button>
                @endforeach
            </div>
        @endif

        <form class="ai-composer" method="post" data-chat-form action="{{ $sendAction }}">
            @csrf
            @unless($conversation->exists)
                <input type="hidden" name="device_id" value="{{ $conversation->device_id }}">
                @if($defaultProvider)<input type="hidden" name="ai_provider_id" value="{{ $defaultProvider->id }}">@endif
            @endunless
            <div class="ai-input">
                <textarea name="message" rows="3" required maxlength="30000" placeholder="Descreva o problema do equipamento... (Ctrl+Enter envia)" data-chat-input @disabled(!$defaultProvider)>{{ old('message') }}</textarea>
                <button class="btn btn-primary" data-chat-send @disabled(!$defaultProvider)><x-icon name="arrow-up" class="sm" /> Enviar</button>
            </div>
            @error('message')<div class="error small">{{ $message }}</div>@enderror
            <div class="faint small ai-disclaimer">
                <x-icon name="alert" class="sm" />
                <span>@if($defaultProvider)As respostas são geradas por IA ({{ $defaultProvider->name }} · {{ $defaultProvider->model }}) e podem conter erros: confira antes de executar no equipamento. @else Configure um provedor de IA para conversar. @endif Segredos dos equipamentos nunca são enviados ao provedor.</span>
            </div>
        </form>
    </section>
</div>
@endsection
