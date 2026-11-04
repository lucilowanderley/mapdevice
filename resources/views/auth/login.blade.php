@extends('layouts.app')
@section('title', 'Entrar')
@section('content')
<div style="max-width:380px;margin:8vh auto;">
    <h1 style="text-align:center;">{{ config('app.name') }}</h1>
    <p class="sub" style="text-align:center;">Assistente de diagnóstico de equipamentos de rede</p>
    <div class="panel">
        <form method="post" action="{{ route('login.attempt') }}">
            @csrf
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')<div class="err-msg">{{ $message }}</div>@enderror
            <label for="password">Senha</label>
            <input id="password" type="password" name="password" required>
            <label style="font-weight:400;margin-top:12px;"><input type="checkbox" name="remember" style="width:auto;"> Manter conectado</label>
            <button class="btn" style="width:100%;margin-top:16px;">Entrar</button>
        </form>
    </div>
</div>
@endsection
