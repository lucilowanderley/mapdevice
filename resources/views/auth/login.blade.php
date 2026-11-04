@extends('layouts.app')
@section('title', 'Entrar')
@section('content')
<div class="auth">
    <section class="auth-hero">
        <div class="brand" style="padding: 0">
            <x-logo style="width: 44px; height: 44px" />
            <div>
                <div class="brand-name" style="font-size: 20px">MAP<span>DEVICE</span></div>
                <div class="brand-tag">Diagnóstico de rede</div>
            </div>
        </div>
        <div>
            <h1>O suporte da sua rede, com inteligência.</h1>
            <p>Descubra o equipamento, deixe a IA sugerir o diagnóstico e execute os comandos com confirmação e verificação — tudo em um só lugar.</p>
        </div>
        <div class="auth-features">
            <div><x-icon name="radar" /> Descoberta de fabricante/SO</div>
            <div><x-icon name="terminal" /> Biblioteca de comandos</div>
            <div><x-icon name="sparkles" /> Assistente com IA</div>
            <div><x-icon name="router" /> OLT, PPPoE, rádios e CPEs</div>
            <div><x-icon name="shield" /> Escrita confirmada + read-back</div>
            <div><x-icon name="globe" /> Sondagem da interface web</div>
        </div>
    </section>
    <section class="auth-form">
        <div class="box">
            <h2 style="font-size: 22px">Entrar</h2>
            <p class="muted" style="margin-top: 6px">Acesse com seu e-mail e senha.</p>
            <form method="post" action="{{ route('login.attempt') }}" style="margin-top: 22px">
                @csrf
                <div class="field">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                    @error('email')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <div class="field">
                    <label class="check"><input type="checkbox" name="remember" value="1"> Manter conectado</label>
                </div>
                <button class="btn btn-primary" style="width: 100%; padding: 11px">Entrar</button>
            </form>
        </div>
    </section>
</div>
@endsection
