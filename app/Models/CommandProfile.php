<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Perfil da biblioteca de comandos: fabricante + modelos + firmware + protocolo, com as ações padronizadas. */
class CommandProfile extends Model
{
    protected $fillable = ['slug', 'name', 'module', 'device_type', 'manufacturer', 'models', 'firmware', 'protocol', 'access_methods', 'session', 'version', 'description', 'source'];

    protected $casts = ['models' => 'array', 'firmware' => 'array', 'access_methods' => 'array', 'session' => 'array'];

    public const PROTOCOLS = ['cli' => 'CLI (Telnet/SSH/Console)', 'routeros_api' => 'API RouterOS', 'snmp' => 'SNMP', 'rest' => 'HTTP / API web'];

    /** Métodos de acesso do equipamento que cada protocolo aceita. */
    public const PROTOCOL_ACCESS = [
        'cli' => ['ssh', 'telnet', 'console'],
        'routeros_api' => ['api', 'api_ssl'],
        'snmp' => ['snmp'],
        'rest' => ['http', 'https'],
    ];

    public const MODULES = Device::MODULES;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function actions(): HasMany
    {
        return $this->hasMany(CommandAction::class)->orderBy('sort')->orderBy('key');
    }

    public function action(string $key): ?CommandAction
    {
        return $this->relationLoaded('actions') ? $this->actions->firstWhere('key', $key) : $this->actions()->where('key', $key)->first();
    }

    public function protocolLabel(): string
    {
        return self::PROTOCOLS[$this->protocol] ?? $this->protocol;
    }

    /**
     * Quão bem o perfil atende ao equipamento: null = não atende; maior = mais específico.
     * Modelos e firmware são padrões com * (AN5516-*, 6.*); vazio no equipamento casa com qualquer padrão.
     */
    public function score(string $manufacturer, ?string $model, ?string $firmware, ?string $method): ?int
    {
        if (mb_strtolower($manufacturer) !== mb_strtolower((string) $this->manufacturer)) {
            return null;
        }
        if ($method && $this->access_methods && ! in_array($method, $this->access_methods, true)) {
            return null;
        }
        $score = 0;
        foreach ([[$this->models, $model], [$this->firmware, $firmware]] as [$patterns, $value]) {
            $best = self::bestMatch($patterns ?: ['*'], $value);
            if ($best === null) {
                return null;
            }
            $score += $best;
        }

        return $score;
    }

    /** Pontua o padrão mais específico que casa (mais caracteres fixos); null = nenhum casa. */
    private static function bestMatch(array $patterns, ?string $value): ?int
    {
        $best = null;
        foreach ($patterns as $pattern) {
            $fixed = strlen(str_replace('*', '', $pattern));
            if ($value === null || $value === '') {
                $hit = true;
                $fixed = 0;
            } else {
                $hit = Str::is(mb_strtolower($pattern), mb_strtolower($value));
            }
            if ($hit && ($best === null || $fixed > $best)) {
                $best = $fixed;
            }
        }

        return $best;
    }
}
