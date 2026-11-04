<?php

namespace App\Http\Controllers;

use App\CommandLibrary\CommandLibrary;
use App\CommandLibrary\CommandLibraryException;
use App\Models\CommandProfile;
use Illuminate\Http\Request;

/** Biblioteca de comandos: lista os perfis e importa/exporta os arquivos (o assistente gera o JSON; aqui ele vira perfil). */
class CommandProfileController extends Controller
{
    public function __construct(private readonly CommandLibrary $library) {}

    public function index()
    {
        return view('library.index', [
            'profiles' => CommandProfile::with('actions')->orderBy('manufacturer')->orderBy('name')->get(),
        ]);
    }

    /** Importa o arquivo JSON (o que o assistente gera no atalho "Gerar perfil da Biblioteca"). */
    public function import(Request $request)
    {
        $data = $request->validate([
            'json' => ['required', 'string', 'max:200000'],
        ]);

        try {
            $profile = $this->library->import($data['json'], 'importado');
        } catch (CommandLibraryException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('library.index')->with('success', "Perfil \"{$profile->name}\" importado com {$profile->actions->count()} ação(ões).");
    }

    /** Baixa o arquivo JSON de um perfil (formato mapdevice-commands v1). */
    public function export(CommandProfile $profile)
    {
        return response($this->library->exportJson($profile), 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$profile->slug.'.json"',
        ]);
    }

    public function destroy(CommandProfile $profile)
    {
        $profile->delete();

        return redirect()->route('library.index')->with('success', 'Perfil removido da Biblioteca.');
    }
}
