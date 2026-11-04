<?php

namespace App\CommandLibrary;

use App\Models\CommandAction;
use App\Models\CommandProfile;
use App\Models\Device;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Biblioteca de comandos padronizados por fabricante/modelo/firmware.
 *
 * Fica no banco (consulta rápida pelo motor de execução, edição pela tela) e entra/sai em arquivos JSON no formato
 * "mapdevice-commands" versão 1 — um arquivo por perfil. Os perfis padrão ficam em database/commands/.
 */
class CommandLibrary
{
    public const FORMAT = 'mapdevice-commands';

    public const FORMAT_VERSION = 1;

    /** Perfil mais específico para o equipamento (fabricante + modelo + firmware + método de acesso). */
    public function profileFor(Model $device, string $module): ?CommandProfile
    {
        $firmware = $device->firmware ?: ($device->os ?? null);

        return CommandProfile::with('actions')->where('module', $module)->get()
            ->map(fn (CommandProfile $p) => [$p, $p->score((string) $device->manufacturer, $device->model, $firmware, $device->access_method)])
            ->filter(fn ($pair) => $pair[1] !== null)
            ->sortByDesc(fn ($pair) => $pair[1])
            ->first()[0] ?? null;
    }

    /**
     * Passos de uma ação para o equipamento, já com os parâmetros; $fallback quando a biblioteca não tem o perfil ou a ação.
     */
    public function steps(Model $device, string $module, string $action, array $params = [], array $fallback = []): array
    {
        $found = $this->profileFor($device, $module)?->action($action);

        return $found ? $found->render($params) : $fallback;
    }

    /**
     * Importa um arquivo (conteúdo JSON). Perfil com o mesmo slug é substituído por inteiro (ações incluídas).
     *
     * @throws CommandLibraryException arquivo fora do formato
     */
    public function import(string $json, string $source = 'importado'): CommandProfile
    {
        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new CommandLibraryException('O arquivo não é um JSON válido.');
        }
        $data = $this->validate($data);
        $p = $data['profile'];

        return DB::transaction(function () use ($p, $data, $source) {
            $profile = CommandProfile::updateOrCreate(['slug' => $p['slug']], [
                'name' => $p['name'], 'module' => $p['module'], 'device_type' => $p['device_type'], 'manufacturer' => $p['manufacturer'],
                'models' => array_values($p['models']), 'firmware' => array_values($p['firmware']), 'protocol' => $p['protocol'],
                'access_methods' => array_values($p['access_methods']), 'session' => $p['session'] ?? null, 'version' => $p['version'] ?? '1.0.0',
                'description' => $p['description'] ?? null, 'source' => $source,
            ]);
            $profile->actions()->delete();
            foreach (array_values($data['actions']) as $i => $a) {
                $profile->actions()->create([
                    'key' => $a['key'], 'name' => $a['name'], 'kind' => $a['kind'] ?? 'consulta', 'description' => $a['description'] ?? null,
                    'params' => $a['params'] ?? [], 'steps' => $a['steps'], 'parser' => $a['parser'] ?? null, 'verify' => $a['verify'] ?? null,
                    'validated' => (bool) ($a['validated'] ?? false), 'notes' => $a['notes'] ?? null, 'sort' => $i,
                ]);
            }

            return $profile->load('actions');
        });
    }

    /** Importa todos os *.json de uma pasta. @return CommandProfile[] */
    public function importDirectory(string $dir, string $source = 'padrao'): array
    {
        $files = glob(rtrim($dir, '/\\').DIRECTORY_SEPARATOR.'*.json') ?: [];
        sort($files);

        return array_map(fn ($file) => $this->import((string) file_get_contents($file), $source), $files);
    }

    public function defaultsDirectory(): string
    {
        return database_path('commands');
    }

    /** Perfil no formato de arquivo (o mesmo que a importação lê). */
    public function export(CommandProfile $profile): array
    {
        $profile->loadMissing('actions');

        return [
            'format' => self::FORMAT,
            'format_version' => self::FORMAT_VERSION,
            'profile' => array_filter([
                'slug' => $profile->slug, 'name' => $profile->name, 'module' => $profile->module, 'device_type' => $profile->device_type,
                'manufacturer' => $profile->manufacturer, 'models' => $profile->models, 'firmware' => $profile->firmware,
                'protocol' => $profile->protocol, 'access_methods' => $profile->access_methods, 'session' => $profile->session,
                'version' => $profile->version, 'description' => $profile->description,
            ], fn ($v) => $v !== null),
            'actions' => $profile->actions->map(fn (CommandAction $a) => array_filter([
                'key' => $a->key, 'name' => $a->name, 'kind' => $a->kind, 'description' => $a->description, 'params' => $a->params ?: null,
                'steps' => $a->steps, 'parser' => $a->parser, 'verify' => $a->verify ?: null, 'validated' => $a->validated, 'notes' => $a->notes,
            ], fn ($v) => $v !== null))->values()->all(),
        ];
    }

    public function exportJson(CommandProfile $profile): string
    {
        return json_encode($this->export($profile), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    public function validate(array $data): array
    {
        if (($data['format'] ?? null) !== self::FORMAT || (int) ($data['format_version'] ?? 0) !== self::FORMAT_VERSION) {
            throw new CommandLibraryException('Formato não reconhecido: o arquivo precisa ter "format": "'.self::FORMAT.'" e "format_version": '.self::FORMAT_VERSION.'.');
        }
        $validator = Validator::make($data, [
            'profile' => ['required', 'array'],
            'profile.slug' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9\-]{2,79}$/'],
            'profile.name' => ['required', 'string', 'max:120'],
            'profile.module' => ['required', Rule::in(array_keys(CommandProfile::MODULES))],
            'profile.device_type' => ['required', 'string', 'max:30'],
            'profile.manufacturer' => ['required', 'string', 'max:40'],
            'profile.models' => ['required', 'array', 'min:1'],
            'profile.models.*' => ['required', 'string', 'max:60'],
            'profile.firmware' => ['required', 'array', 'min:1'],
            'profile.firmware.*' => ['required', 'string', 'max:60'],
            'profile.protocol' => ['required', Rule::in(array_keys(CommandProfile::PROTOCOLS))],
            'profile.access_methods' => ['required', 'array', 'min:1'],
            'profile.access_methods.*' => [Rule::in(array_keys(Device::ACCESS_METHODS))],
            'profile.session' => ['nullable', 'array'],
            'profile.version' => ['nullable', 'string', 'max:20'],
            'profile.description' => ['nullable', 'string', 'max:2000'],
            'actions' => ['required', 'array', 'min:1', 'max:500'],
            'actions.*.key' => ['required', 'string', 'regex:/^[a-z0-9_]+(\.[a-z0-9_]+)*$/', 'max:60', 'distinct'],
            'actions.*.name' => ['required', 'string', 'max:120'],
            'actions.*.kind' => ['nullable', Rule::in(array_keys(CommandAction::KINDS))],
            'actions.*.description' => ['nullable', 'string', 'max:2000'],
            'actions.*.params' => ['nullable', 'array', 'max:30'],
            'actions.*.params.*.name' => ['required', 'string', 'regex:/^[a-z_][a-z0-9_]*$/i', 'max:40'],
            'actions.*.params.*.label' => ['nullable', 'string', 'max:80'],
            'actions.*.params.*.required' => ['nullable', 'boolean'],
            'actions.*.params.*.pattern' => ['nullable', 'string', 'max:200'],
            'actions.*.steps' => ['required', 'array', 'min:1', 'max:100'],
            'actions.*.parser' => ['nullable', 'string', 'max:60'],
            'actions.*.verify' => ['nullable', 'array'],
            'actions.*.verify.action' => ['required_with:actions.*.verify', 'string', 'max:60'],
            'actions.*.verify.contains' => ['nullable', 'string', 'max:200'],
            'actions.*.validated' => ['nullable', 'boolean'],
            'actions.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($validator->fails()) {
            throw new CommandLibraryException('Arquivo inválido: '.implode(' ', array_slice($validator->errors()->all(), 0, 5)));
        }
        $protocol = $data['profile']['protocol'];
        foreach (['setup', 'logout'] as $phase) {
            foreach ((array) ($data['profile']['session'][$phase] ?? []) as $step) {
                $this->validateStep($protocol, "sessão ({$phase})", $step);
            }
        }
        foreach ($data['actions'] as $a) {
            foreach ($a['params'] ?? [] as $param) {
                if (isset($param['pattern']) && @preg_match('/'.str_replace('/', '\/', $param['pattern']).'/u', '') === false) {
                    throw new CommandLibraryException("Ação {$a['key']}: padrão do parâmetro {$param['name']} não é uma expressão regular válida.");
                }
            }
            foreach ($a['steps'] as $step) {
                $this->validateStep($protocol, $a['key'], $step);
            }
        }

        return $data;
    }

    private function validateStep(string $protocol, string $action, mixed $step): void
    {
        $fail = fn (string $why) => throw new CommandLibraryException("Ação {$action}: {$why}");
        if (! is_array($step)) {
            $fail('cada passo precisa ser um objeto.');
        }
        if ($protocol === 'routeros_api') {
            if (! is_string($step['command'] ?? null) || ! preg_match('#^/[a-z0-9\-/]+$#', $step['command'])) {
                $fail('passo da API precisa de "command" no formato /caminho/comando.');
            }
            foreach ($step['args'] ?? [] as $k => $v) {
                if (! is_string($k) || ! preg_match('/^[=?][\w.\-]+$/', $k) || ! is_scalar($v)) {
                    $fail('"args" precisa ser um objeto {"=atributo": valor} ou {"?filtro": valor}.');
                }
            }

            return;
        }
        if ($protocol === 'rest') {
            if (! is_string($step['path'] ?? null) || ! preg_match('#^/[A-Za-z0-9_.\-/]*(\?[A-Za-z0-9_.\-=&{}/]*)?$#', $step['path'])) {
                $fail('passo HTTP precisa de "path" no formato /caminho.cgi.');
            }
            if (isset($step['method']) && ! in_array($step['method'], ['GET', 'POST'], true)) {
                $fail('"method" do passo HTTP deve ser GET ou POST.');
            }
            foreach ($step['form'] ?? [] as $k => $v) {
                if (! is_string($k) || ! preg_match('/^[\w.\-]+$/', $k) || ! is_scalar($v)) {
                    $fail('"form" precisa ser um objeto {"campo": valor}.');
                }
            }

            return;
        }
        if ($protocol === 'snmp') {
            if (! is_string($step['oid'] ?? null) || ! preg_match('/^\.?\d+(\.\d+)*$/', $step['oid'])) {
                $fail('passo SNMP precisa de "oid" numérico (ex.: 1.3.6.1.2.1.1.5).');
            }

            return;
        }
        if (! is_string($step['send'] ?? null) || $step['send'] === '' || preg_match('/[\r\n]/', $step['send'])) {
            $fail('passo de CLI precisa de "send" com um único comando (sem quebra de linha).');
        }
        if (isset($step['timeout']) && (! is_int($step['timeout']) || $step['timeout'] < 1 || $step['timeout'] > 600)) {
            $fail('"timeout" do passo deve ficar entre 1 e 600 segundos.');
        }
    }
}
