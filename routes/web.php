<?php

use App\Http\Controllers\AiProviderController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CommandController;
use App\Http\Controllers\CommandProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\WebProbeController;
use Illuminate\Support\Facades\Route;

// Autenticação
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Equipamentos da rede (técnico consulta; administrador cadastra e exclui)
    Route::middleware('role:admin,tecnico')->group(function () {
        Route::get('/equipamentos', [DeviceController::class, 'index'])->name('devices.index');
    });
    Route::middleware('role:admin')->group(function () {
        Route::get('/equipamentos/novo', [DeviceController::class, 'create'])->name('devices.create');
        Route::post('/equipamentos', [DeviceController::class, 'store'])->name('devices.store');
        Route::get('/equipamentos/{device}/editar', [DeviceController::class, 'edit'])->name('devices.edit');
        Route::put('/equipamentos/{device}', [DeviceController::class, 'update'])->name('devices.update');
        Route::delete('/equipamentos/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
    });

    // Comandos: executa as ações da Biblioteca no equipamento (técnico e administrador)
    Route::middleware('role:admin,tecnico')->group(function () {
        Route::get('/comandos', [CommandController::class, 'index'])->name('commands.index');
        Route::get('/comandos/{device}', [CommandController::class, 'show'])->name('commands.show');
        Route::post('/comandos/{device}/executar', [CommandController::class, 'run'])->middleware('throttle:60,1')->name('commands.run');

        // Descoberta (sonda só-leitura) de fabricante/SO
        Route::get('/equipamentos/{device}/descoberta', [DiscoveryController::class, 'show'])->name('discovery.show');
        Route::post('/equipamentos/{device}/descoberta', [DiscoveryController::class, 'run'])->middleware('throttle:20,1')->name('discovery.run');

        // Biblioteca de comandos (consulta dos perfis e exportação)
        Route::get('/biblioteca', [CommandProfileController::class, 'index'])->name('library.index');
        Route::get('/biblioteca/{profile}/exportar', [CommandProfileController::class, 'export'])->name('library.export');

        // Sondagem da interface web (mapeamento só-leitura)
        Route::get('/equipamentos/{device}/sondagem', [WebProbeController::class, 'show'])->name('probe.show');
        Route::post('/equipamentos/{device}/sondagem', [WebProbeController::class, 'run'])->middleware('throttle:20,1')->name('probe.run');
        Route::get('/equipamentos/{device}/sondagem/{probe}/rascunho', [WebProbeController::class, 'draft'])->name('probe.draft');
    });

    // Aplicar o resultado da descoberta e importar/remover perfis: só administrador
    Route::middleware('role:admin')->group(function () {
        Route::post('/equipamentos/{device}/descoberta/{discovery}/aplicar', [DiscoveryController::class, 'apply'])->name('discovery.apply');
        Route::post('/equipamentos/{device}/sondagem/{probe}/importar', [WebProbeController::class, 'import'])->name('probe.import');
        Route::post('/biblioteca/importar', [CommandProfileController::class, 'import'])->name('library.import');
        Route::delete('/biblioteca/{profile}', [CommandProfileController::class, 'destroy'])->name('library.destroy');
    });

    // Assistente de IA (chat)
    Route::get('/assistente', [AssistantController::class, 'index'])->name('assistant.index');
    Route::post('/assistente', [AssistantController::class, 'store'])->middleware('throttle:30,1')->name('assistant.store');
    Route::get('/assistente/{conversation}', [AssistantController::class, 'show'])->name('assistant.show');
    Route::post('/assistente/{conversation}/mensagem', [AssistantController::class, 'send'])->middleware('throttle:30,1')->name('assistant.send');
    Route::put('/assistente/{conversation}', [AssistantController::class, 'update'])->name('assistant.update');
    Route::post('/assistente/{conversation}/repetir', [AssistantController::class, 'retry'])->middleware('throttle:30,1')->name('assistant.retry');
    Route::delete('/assistente/{conversation}', [AssistantController::class, 'destroy'])->name('assistant.destroy');

    // Provedores de IA (somente administradores)
    Route::middleware('role:admin')->group(function () {
        Route::get('/assistente-provedores', [AiProviderController::class, 'index'])->name('ai-providers.index');
        Route::get('/assistente-provedores/novo', [AiProviderController::class, 'create'])->name('ai-providers.create');
        Route::post('/assistente-provedores', [AiProviderController::class, 'store'])->name('ai-providers.store');
        Route::post('/assistente-provedores/modelos', [AiProviderController::class, 'models'])->middleware('throttle:20,1')->name('ai-providers.models');
        Route::get('/assistente-provedores/{aiProvider}/editar', [AiProviderController::class, 'edit'])->name('ai-providers.edit');
        Route::put('/assistente-provedores/{aiProvider}', [AiProviderController::class, 'update'])->name('ai-providers.update');
        Route::delete('/assistente-provedores/{aiProvider}', [AiProviderController::class, 'destroy'])->name('ai-providers.destroy');
        Route::post('/assistente-provedores/{aiProvider}/padrao', [AiProviderController::class, 'makeDefault'])->name('ai-providers.default');
        Route::post('/assistente-provedores/{aiProvider}/testar', [AiProviderController::class, 'test'])->middleware('throttle:10,1')->name('ai-providers.test');
    });
});
