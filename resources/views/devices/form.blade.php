@extends('layouts.app')
@section('title', $device->exists ? 'Editar equipamento' : 'Novo equipamento')
@section('content')
<h1>{{ $device->exists ? 'Editar equipamento' : 'Novo equipamento' }}</h1>
<p class="sub">Dados de acesso ao equipamento. A senha é guardada criptografada e nunca é enviada ao provedor de IA.</p>

<div class="panel">
    <form method="post" action="{{ $device->exists ? route('devices.update', $device) : route('devices.store') }}">
        @csrf
        @if($device->exists) @method('PUT') @endif

        <label for="name">Nome *</label>
        <input id="name" type="text" name="name" value="{{ old('name', $device->name) }}" required>
        @error('name')<div class="err-msg">{{ $message }}</div>@enderror

        <div class="grid2">
            <div>
                <label for="module">Módulo</label>
                <select id="module" name="module">
                    <option value="">—</option>
                    @foreach(\App\Models\Device::MODULES as $k => $label)
                        <option value="{{ $k }}" @selected(old('module', $device->module) === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="access_method">Protocolo de acesso *</label>
                <select id="access_method" name="access_method" required>
                    @foreach(\App\Models\Device::ACCESS_METHODS as $k => $label)
                        <option value="{{ $k }}" @selected(old('access_method', $device->access_method) === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid2">
            <div>
                <label for="manufacturer">Fabricante</label>
                <input id="manufacturer" type="text" name="manufacturer" value="{{ old('manufacturer', $device->manufacturer) }}" placeholder="MikroTik, Ubiquiti, Intelbras, Fiberhome, Huawei...">
            </div>
            <div>
                <label for="model">Modelo</label>
                <input id="model" type="text" name="model" value="{{ old('model', $device->model) }}">
            </div>
        </div>

        <div class="grid2">
            <div>
                <label for="firmware">Firmware</label>
                <input id="firmware" type="text" name="firmware" value="{{ old('firmware', $device->firmware) }}">
            </div>
            <div>
                <label for="os">Sistema embarcado</label>
                <input id="os" type="text" name="os" value="{{ old('os', $device->os) }}" placeholder="RouterOS, airOS, WiFiber...">
            </div>
        </div>

        <div class="grid2">
            <div>
                <label for="host">Endereço (IP ou host)</label>
                <input id="host" type="text" name="host" value="{{ old('host', $device->host) }}" placeholder="192.168.0.1">
            </div>
            <div>
                <label for="port">Porta</label>
                <input id="port" type="number" name="port" value="{{ old('port', $device->port) }}" min="1" max="65535">
            </div>
        </div>

        <div class="grid2">
            <div>
                <label for="username">Usuário</label>
                <input id="username" type="text" name="username" value="{{ old('username', $device->username) }}" autocomplete="off">
            </div>
            <div>
                <label for="password">Senha</label>
                <input id="password" type="password" name="password" value="" autocomplete="new-password" placeholder="{{ $device->exists && $device->password ? '•••••••• (deixe em branco para manter)' : '' }}">
                @if($device->exists && $device->password)
                    <label style="font-weight:400;margin-top:6px;"><input type="checkbox" name="clear_password" value="1" style="width:auto;"> Remover a senha guardada</label>
                @endif
            </div>
        </div>

        <label for="snmp_community">Comunidade SNMP</label>
        <input id="snmp_community" type="text" name="snmp_community" value="" autocomplete="off" placeholder="{{ $device->exists && $device->snmp_community ? '•••••••• (deixe em branco para manter)' : 'public' }}">

        <label for="notes">Observações</label>
        <textarea id="notes" name="notes" rows="3">{{ old('notes', $device->notes) }}</textarea>

        <label style="font-weight:400;margin-top:12px;"><input type="checkbox" name="active" value="1" @checked(old('active', $device->active ?? true)) style="width:auto;"> Equipamento ativo</label>

        <div class="row" style="margin-top:20px;">
            <button class="btn">{{ $device->exists ? 'Salvar' : 'Cadastrar' }}</button>
            <a href="{{ route('devices.index') }}" class="btn secondary">Cancelar</a>
            @if($device->exists)
                <form method="post" action="{{ route('devices.destroy', $device) }}" style="margin-left:auto;" onsubmit="return confirm('Excluir este equipamento?')">
                    @csrf @method('DELETE')
                    <button class="btn danger">Excluir</button>
                </form>
            @endif
        </div>
    </form>
</div>
@endsection
