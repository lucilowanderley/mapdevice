<?php

namespace App\Probe;

use App\CommandLibrary\CommandLibrary;
use App\Models\WebProbe;
use Illuminate\Support\Str;

/**
 * Rascunho de perfil da Biblioteca a partir de uma sondagem: uma consulta (GET) por página respondida,
 * com validated=false até alguém conferir no equipamento. Sai no formato de importação (mapdevice-commands v1).
 */
class ProfileDraft
{
    private const PATH = '#^/[A-Za-z0-9_.\-/]*(\?[A-Za-z0-9_.\-=&{}/]*)?$#';

    /**
     * @param  int[]|null  $pageIds  páginas escolhidas (null = todas as que responderam 2xx/3xx)
     * @param  array{slug?:string, name?:string, manufacturer?:string, models?:string[], firmware?:string[], description?:?string}  $meta
     * @return array{data: array, skipped: string[]}
     */
    public static function build(WebProbe $probe, ?array $pageIds = null, array $meta = []): array
    {
        $device = $probe->device;
        $pages = $probe->pages()
            ->when($pageIds !== null, fn ($q) => $q->whereIn('id', $pageIds))
            ->get()
            ->filter(fn ($p) => $pageIds !== null || ($p->status >= 200 && $p->status < 400));

        $actions = [];
        $skipped = [];
        $used = [];
        foreach ($pages as $page) {
            if (! preg_match(self::PATH, $page->path)) {
                $skipped[] = $page->path;

                continue;
            }
            $base = Str::of((string) parse_url($page->path, PHP_URL_PATH))->afterLast('/')->beforeLast('.')->snake()->replaceMatches('/[^a-z0-9_]+/', '_')->trim('_')->value() ?: 'inicio';
            $key = 'web.'.$base;
            for ($i = 2; isset($used[$key]); $i++) {
                $key = "web.{$base}_{$i}";
            }
            $used[$key] = true;
            $actions[] = [
                'key' => $key,
                'name' => $page->title ? mb_substr($page->title, 0, 120) : 'Página '.$page->path,
                'kind' => 'consulta',
                'description' => "Página {$page->path} mapeada pela sondagem #{$probe->id}.",
                'steps' => [['method' => 'GET', 'path' => $page->path]],
                'validated' => false,
                'notes' => "Sondagem #{$probe->id}: HTTP {$page->status}, {$page->bytes} bytes. Falta conferir no equipamento e, se precisar, escrever o leitor dos campos.",
            ];
        }

        $tls = str_starts_with($probe->base_url, 'https://');
        $slug = $meta['slug'] ?? Str::slug(($device?->manufacturer ?: 'equipamento').'-'.($device?->model ?: $probe->id).'-web');
        $slug = Str::of($slug)->lower()->replaceMatches('/[^a-z0-9\-]+/', '-')->trim('-')->limit(76, '')->value() ?: 'equipamento-web';

        $data = [
            'format' => CommandLibrary::FORMAT,
            'format_version' => CommandLibrary::FORMAT_VERSION,
            'profile' => array_filter([
                'slug' => $slug,
                'name' => $meta['name'] ?? trim(($device?->manufacturer ?: '').' '.($device?->model ?: '').' — web (rascunho)'),
                'module' => $device?->module ?: 'cpe',
                'device_type' => 'web',
                'manufacturer' => $meta['manufacturer'] ?? ($device?->manufacturer ?: 'Desconhecido'),
                'models' => array_values($meta['models'] ?? array_filter([$device?->model ?: '*'])) ?: ['*'],
                'firmware' => array_values($meta['firmware'] ?? ['*']),
                'protocol' => 'rest',
                'access_methods' => [$tls ? 'https' : 'http'],
                'version' => '0.1.0',
                'description' => $meta['description'] ?? "Rascunho gerado pela sondagem #{$probe->id}. Só leitura; consultas ainda não validadas.",
            ], fn ($v) => $v !== null && $v !== ''),
            'actions' => $actions,
        ];

        return ['data' => $data, 'skipped' => $skipped];
    }

    public static function json(WebProbe $probe, ?array $pageIds = null, array $meta = []): string
    {
        return json_encode(self::build($probe, $pageIds, $meta)['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }
}
