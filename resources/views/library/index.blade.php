@extends('layouts.app')
@section('title', 'Biblioteca de comandos')
@section('content')
<div>
    <h1>Biblioteca de comandos</h1>
    <p class="sub">Perfis de comandos por fabricante/modelo. O assistente gera o arquivo JSON (atalho “Gerar perfil da Biblioteca”); aqui o administrador importa e ele vira um perfil executável na tela Comandos.</p>
</div>

@if(auth()->user()->isAdmin())
<div class="panel">
    <h2 style="margin-top:0;">Importar perfil (JSON)</h2>
    <p class="hint">Cole o arquivo no formato <code>mapdevice-commands</code> v1. Ele é validado antes de ser salvo.</p>
    <form method="post" action="{{ route('library.import') }}">
        @csrf
        <textarea name="json" rows="10" style="width:100%;font-family:monospace;font-size:13px;" placeholder='{ "format": "mapdevice-commands", "format_version": 1, "profile": { ... }, "actions": [ ... ] }'>{{ old('json') }}</textarea>
        <div style="margin-top:10px;"><button class="btn btn-primary">Importar</button></div>
    </form>
</div>
@endif

<div class="panel">
    @if($profiles->isEmpty())
        <p class="hint">Nenhum perfil na Biblioteca ainda.</p>
    @else
    <table>
        <thead><tr><th>Perfil</th><th>Fabricante</th><th>Módulo</th><th>Protocolo</th><th>Ações</th><th>Origem</th><th></th></tr></thead>
        <tbody>
        @foreach($profiles as $p)
            <tr>
                <td><strong>{{ $p->name }}</strong><br><span class="hint">{{ $p->slug }}</span></td>
                <td>{{ $p->manufacturer }}<br><span class="hint">{{ implode(', ', $p->models ?? []) }}</span></td>
                <td><span class="tag">{{ \App\Models\CommandProfile::MODULES[$p->module] ?? $p->module }}</span></td>
                <td>{{ $p->protocolLabel() }}</td>
                <td>
                    @foreach($p->actions as $a)
                        <div><span class="hint">{{ $a->key }}</span> @if($a->isWrite())<span class="tag err">escrita</span>@else<span class="tag ok">consulta</span>@endif</div>
                    @endforeach
                </td>
                <td><span class="tag">{{ $p->source }}</span></td>
                <td style="text-align:right;white-space:nowrap;">
                    <a href="{{ route('library.export', $p) }}" class="btn btn-sm">Exportar</a>
                    @if(auth()->user()->isAdmin())
                        <form method="post" action="{{ route('library.destroy', $p) }}" style="display:inline;" onsubmit="return confirm('Remover o perfil {{ $p->name }}?');">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger">Remover</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
