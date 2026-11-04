<?php

namespace App\Http\Controllers;

use App\CommandLibrary\CommandLibrary;
use App\CommandLibrary\CommandLibraryException;
use App\Execution\CommandRunner;
use App\Execution\ExecutionException;
use App\Models\CommandExecution;
use App\Models\Device;
use Illuminate\Http\Request;

/** Tela Comandos: roda as ações da Biblioteca no equipamento (consulta e, com confirmação, escrita). */
class CommandController extends Controller
{
    public function __construct(private readonly CommandLibrary $library, private readonly CommandRunner $runner) {}

    /** Lista os equipamentos para escolher em qual rodar comandos. */
    public function index()
    {
        $devices = Device::where('active', true)->orderBy('name')->get();

        return view('commands.index', ['devices' => $devices]);
    }

    /** Ações disponíveis para o equipamento (pelo perfil da Biblioteca) e as últimas execuções. */
    public function show(Device $device)
    {
        $profile = $this->library->profileFor($device, (string) $device->module);
        $history = CommandExecution::where('device_id', $device->id)->latest()->limit(15)->get();

        return view('commands.show', [
            'device' => $device,
            'profile' => $profile,
            'history' => $history,
        ]);
    }

    /** Executa uma ação da Biblioteca no equipamento. Escrita exige confirm=1. */
    public function run(Request $request, Device $device)
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'max:60'],
            'params' => ['nullable', 'array'],
            'confirm' => ['nullable', 'boolean'],
        ]);

        $profile = $this->library->profileFor($device, (string) $device->module);
        $action = $profile?->action($data['action']);
        if (! $profile || ! $action) {
            return back()->with('error', 'Ação não encontrada na Biblioteca para este equipamento.');
        }

        try {
            $result = $this->runner->run(
                $device,
                $profile,
                $action,
                (array) ($data['params'] ?? []),
                (bool) ($data['confirm'] ?? false),
                $request->user(),
            );
        } catch (ExecutionException|CommandLibraryException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return view('commands.show', [
            'device' => $device,
            'profile' => $profile,
            'ranAction' => $action,
            'result' => $result,
            'history' => CommandExecution::where('device_id', $device->id)->latest()->limit(15)->get(),
        ]);
    }
}
