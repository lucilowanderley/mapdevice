@extends('layouts.app')
@section('title', 'Comandos')
@section('content')
<div>
    <h1>Comandos</h1>
    <p class="sub">Rode as ações da Biblioteca no equipamento. As ações de consulta são seguras; as de configuração (escrita) pedem confirmação do técnico e o sistema confere o resultado.</p>
</div>

<div class="panel">
    @if($devices->isEmpty())
        <p class="hint">Nenhum equipamento ativo. Cadastre em <a href="{{ route('devices.index') }}">Equipamentos</a>.</p>
    @else
    <table>
        <thead><tr><th>Nome</th><th>Módulo</th><th>Fabricante / modelo</th><th>Host</th><th>Acesso</th><th></th></tr></thead>
        <tbody>
        @foreach($devices as $d)
            <tr>
                <td><strong>{{ $d->name }}</strong></td>
                <td><span class="tag">{{ $d->moduleLabel() }}</span></td>
                <td>{{ $d->manufacturer ?: '—' }} {{ $d->model }}</td>
                <td>{{ $d->host ?: '—' }}</td>
                <td><span class="tag">{{ $d->accessLabel() }}</span></td>
                <td style="text-align:right;"><a href="{{ route('commands.show', $d) }}" class="btn btn-sm">Abrir</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
