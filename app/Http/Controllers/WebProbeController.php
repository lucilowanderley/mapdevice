<?php

namespace App\Http\Controllers;

use App\CommandLibrary\CommandLibrary;
use App\CommandLibrary\CommandLibraryException;
use App\Models\Device;
use App\Models\WebProbe;
use App\Probe\ProfileDraft;
use App\Probe\WebProber;
use Illuminate\Http\Request;

/** Sondagem da interface web: mapeia as páginas do equipamento (só leitura) e gera o rascunho do perfil da Biblioteca. */
class WebProbeController extends Controller
{
    public function __construct(private readonly WebProber $prober, private readonly CommandLibrary $library) {}

    public function show(Device $device)
    {
        return view('probe.show', [
            'device' => $device,
            'latest' => $device->webProbes()->with('pages')->latest()->first(),
            'history' => $device->webProbes()->latest()->limit(10)->get(),
        ]);
    }

    /** Roda a sondagem (só leitura; além do login, só GET). */
    public function run(Request $request, Device $device)
    {
        $data = $request->validate([
            'login_mode' => ['required', 'in:nenhum,basic,formulario'],
            'paths' => ['nullable', 'string', 'max:2000'],
            'login_path' => ['nullable', 'string', 'max:200'],
            'user_field' => ['nullable', 'string', 'max:60'],
            'pass_field' => ['nullable', 'string', 'max:60'],
            'crawl' => ['nullable', 'boolean'],
        ]);

        if (! $device->host) {
            return back()->with('error', 'O equipamento não tem endereço (host) cadastrado.');
        }

        $paths = array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) ($data['paths'] ?? ''))));
        $probe = $this->prober->probe($device, $data['login_mode'], [
            'paths' => $paths ?: ['/'],
            'crawl' => (bool) ($data['crawl'] ?? false),
            'login_path' => $data['login_path'] ?? null,
            'user_field' => $data['user_field'] ?? null,
            'pass_field' => $data['pass_field'] ?? null,
        ], $request->user()?->id);

        $message = $probe->ok
            ? "Sondagem concluída: {$probe->pages_total} página(s) baixada(s)."
            : ($probe->message ?: 'Sondagem não obteve respostas.');

        return redirect()->route('probe.show', $device)->with($probe->ok ? 'success' : 'error', $message);
    }

    /** Baixa o rascunho do perfil (JSON mapdevice-commands) gerado a partir das páginas sondadas. */
    public function draft(Device $device, WebProbe $probe)
    {
        abort_unless($probe->device_id === $device->id, 404);
        $json = ProfileDraft::json($probe);

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="rascunho-sondagem-'.$probe->id.'.json"',
        ]);
    }

    /** Importa o rascunho direto para a Biblioteca (só administrador). */
    public function import(Device $device, WebProbe $probe)
    {
        abort_unless($probe->device_id === $device->id, 404);
        try {
            $profile = $this->library->import(ProfileDraft::json($probe), 'sondagem');
        } catch (CommandLibraryException $e) {
            return back()->with('error', 'Não foi possível importar o rascunho: '.$e->getMessage());
        }

        return redirect()->route('library.index')->with('success', "Rascunho importado como \"{$profile->name}\". Revise e valide as consultas.");
    }
}
