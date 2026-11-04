@extends('layouts.app')
@section('title', 'Sondagem · ' . $device->name)
@section('content')
<div class="row" style="justify-content:space-between;">
    <div>
        <h1>Sondagem web — {{ $device->name }}</h1>
        <p class="sub">Mapeia as páginas da interface web do equipamento (só leitura). Senhas e chaves são mascaradas. Serve para gerar o rascunho do perfil da Biblioteca.</p>
    </div>
    <a href="{{ route('discovery.show', $device) }}" class="btn btn-sm">Descoberta</a>
</div>

<div class="panel">
    <h2 style="margin-top:0;">Rodar sondagem</h2>
    <p class="hint">Endereço base: <code>{{ ($device->access_method === 'http' ? 'http' : 'https') }}://{{ $device->host ?: '—' }}{{ $device->port ? ':'.$device->port : '' }}</code></p>
    <form method="post" action="{{ route('probe.run', $device) }}">
        @csrf
        <div style="margin:8px 0;">
            <label style="display:block;font-size:13px;color:var(--muted);">Login</label>
            <select name="login_mode">
                @foreach(\App\Models\WebProbe::LOGIN_MODES as $k => $label)
                    <option value="{{ $k }}" @selected(old('login_mode')===$k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div style="margin:8px 0;">
            <label style="display:block;font-size:13px;color:var(--muted);">Páginas (uma por linha; padrão <code>/</code>)</label>
            <textarea name="paths" rows="3" style="width:100%;max-width:480px;font-family:monospace;" placeholder="/&#10;/status.cgi&#10;/admin/">{{ old('paths') }}</textarea>
        </div>
        <details style="margin:8px 0;">
            <summary class="hint">Opções do login por formulário</summary>
            <div style="margin-top:8px;">
                <input type="text" name="login_path" value="{{ old('login_path') }}" placeholder="/login.cgi" style="max-width:220px;">
                <input type="text" name="user_field" value="{{ old('user_field') }}" placeholder="username" style="max-width:160px;">
                <input type="text" name="pass_field" value="{{ old('pass_field') }}" placeholder="password" style="max-width:160px;">
            </div>
        </details>
        <label class="hint" style="display:block;margin:8px 0;">
            <input type="checkbox" name="crawl" value="1" @checked(old('crawl'))> Seguir os links da mesma origem (crawl raso, até 15 páginas)
        </label>
        <button class="btn btn-primary">Sondar</button>
    </form>
</div>

@if($latest)
    <div class="panel">
        <div class="row" style="justify-content:space-between;align-items:center;">
            <h2 style="margin:0;">Última sondagem <span class="hint">· {{ $latest->created_at->format('d/m/Y H:i') }} · {{ $latest->loginLabel() }}</span></h2>
            <div style="white-space:nowrap;">
                <a href="{{ route('probe.draft', [$device, $latest]) }}" class="btn btn-sm">Baixar rascunho (JSON)</a>
                @if(auth()->user()->isAdmin())
                    <form method="post" action="{{ route('probe.import', [$device, $latest]) }}" style="display:inline;" onsubmit="return confirm('Importar o rascunho para a Biblioteca?');">
                        @csrf
                        <button class="btn btn-sm">Importar para a Biblioteca</button>
                    </form>
                @endif
            </div>
        </div>
        @if(! $latest->ok)<p class="flash err">{{ $latest->message }}</p>@endif

        @if($latest->pages->isEmpty())
            <p class="hint">Nenhuma página baixada.</p>
        @else
        <table>
            <thead><tr><th>Página</th><th>Status</th><th>Tamanho</th><th>Título</th><th>Origem</th></tr></thead>
            <tbody>
            @foreach($latest->pages as $p)
                <tr>
                    <td><code>{{ $p->path }}</code></td>
                    <td>@if($p->status >= 200 && $p->status < 400)<span class="tag ok">{{ $p->status }}</span>@else<span class="tag err">{{ $p->status ?: 'erro' }}</span>@endif</td>
                    <td class="hint">{{ number_format($p->bytes) }} B</td>
                    <td>{{ $p->title ?: '—' }}</td>
                    <td class="hint">{{ $p->found_via }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p class="hint" style="margin-top:10px;">O rascunho vira uma consulta GET por página (não validada). Importe, revise e confira no equipamento antes de usar.</p>
        @endif
    </div>
@else
    <div class="panel"><p class="hint">Nenhuma sondagem ainda.</p></div>
@endif
@endsection
