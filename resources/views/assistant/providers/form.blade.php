@extends('layouts.app')
@section('title', $provider->exists ? 'Editar provedor' : 'Novo provedor')
@section('content')
@php($preset = \App\Support\Ai\Drivers::get($provider->driver))
<h1>{{ $provider->exists ? 'Editar provedor de IA' : 'Novo provedor de IA' }}</h1>
<p class="sub">{{ $preset['label'] }} — {{ $preset['pricing_note'] }}</p>

<div class="panel">
    <form method="post" action="{{ $provider->exists ? route('ai-providers.update', $provider) : route('ai-providers.store') }}">
        @csrf
        @if($provider->exists) @method('PUT') @endif
        <input type="hidden" name="driver" value="{{ $provider->driver }}">

        <label for="name">Nome *</label>
        <input id="name" type="text" name="name" value="{{ old('name', $provider->name) }}" required>
        @error('name')<div class="err-msg">{{ $message }}</div>@enderror

        <div class="grid2">
            <div>
                <label for="model">Modelo *</label>
                <input id="model" type="text" name="model" list="models" value="{{ old('model', $provider->model ?: $preset['default_model']) }}" required>
                <datalist id="models">
                    @foreach($preset['models'] as $m)<option value="{{ $m }}">@endforeach
                </datalist>
                @error('model')<div class="err-msg">{{ $message }}</div>@enderror
                @if($preset['hint'])<div class="hint">{{ $preset['hint'] }}</div>@endif
            </div>
            <div>
                <label for="max_tokens">Limite de tokens *</label>
                <input id="max_tokens" type="number" name="max_tokens" value="{{ old('max_tokens', $provider->max_tokens ?: 4096) }}" min="256" max="128000" required>
                @error('max_tokens')<div class="err-msg">{{ $message }}</div>@enderror
            </div>
        </div>

        @if($preset['needs_key'])
        <label for="api_key">Chave de API {{ $provider->exists && $provider->api_key ? '(preenchida — deixe em branco para manter)' : '*' }}</label>
        <input id="api_key" type="password" name="api_key" value="" autocomplete="new-password" placeholder="{{ $provider->exists && $provider->api_key ? '••••••••'.substr($provider->masked_key, -5) : '' }}">
        @error('api_key')<div class="err-msg">{{ $message }}</div>@enderror
        @if($preset['keys_url'])<div class="hint">Obtenha a chave em <a href="{{ $preset['keys_url'] }}" target="_blank" rel="noopener">{{ $preset['keys_url'] }}</a></div>@endif
        @if($provider->exists && $provider->api_key)
            <label style="font-weight:400;margin-top:6px;"><input type="checkbox" name="clear_key" value="1" style="width:auto;"> Remover a chave guardada</label>
        @endif
        @endif

        <label for="base_url">URL base {{ str_contains($preset['base_url'], 'SEU-') || $preset['base_url'] === '' ? '*' : '(opcional)' }}</label>
        <input id="base_url" type="text" name="base_url" value="{{ old('base_url', $provider->base_url) }}" placeholder="{{ $preset['base_url'] ?: 'https://...' }}">
        @error('base_url')<div class="err-msg">{{ $message }}</div>@enderror

        @if($provider->driver === 'azure_openai')
            <div class="grid2">
                <div>
                    <label for="api_version">Versão da API (Azure)</label>
                    <input id="api_version" type="text" name="api_version" value="{{ old('api_version', $provider->option('api_version')) }}" placeholder="2024-10-21">
                </div>
            </div>
        @elseif($provider->driver === 'openai')
            <label for="organization">Organização (opcional)</label>
            <input id="organization" type="text" name="organization" value="{{ old('organization', $provider->option('organization')) }}">
        @endif

        <div class="grid2">
            <div>
                <label for="temperature">Temperatura (opcional)</label>
                <input id="temperature" type="number" step="0.1" min="0" max="2" name="temperature" value="{{ old('temperature', $provider->temperature) }}" placeholder="padrão do modelo">
            </div>
        </div>

        <label for="system_prompt">Instruções do provedor (opcional)</label>
        <textarea id="system_prompt" name="system_prompt" rows="3" placeholder="Instruções extras aplicadas a todas as conversas.">{{ old('system_prompt', $provider->system_prompt) }}</textarea>

        <div class="row" style="margin-top:12px;gap:20px;">
            <label style="font-weight:400;margin:0;"><input type="checkbox" name="active" value="1" @checked(old('active', $provider->active ?? true)) style="width:auto;"> Ativo</label>
            <label style="font-weight:400;margin:0;"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $provider->is_default)) style="width:auto;"> Usar como padrão</label>
        </div>

        <div class="row" style="margin-top:20px;">
            <button class="btn btn-primary">{{ $provider->exists ? 'Salvar' : 'Cadastrar' }}</button>
            <a href="{{ route('ai-providers.index') }}" class="btn">Cancelar</a>
            @if($provider->exists)
                <form method="post" action="{{ route('ai-providers.destroy', $provider) }}" style="margin-left:auto;" onsubmit="return confirm('Excluir este provedor?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger">Excluir</button>
                </form>
            @endif
        </div>
    </form>
</div>
@endsection
