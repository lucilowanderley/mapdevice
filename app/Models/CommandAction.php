<?php

namespace App\Models;

use App\CommandLibrary\CommandLibraryException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ação de um perfil da biblioteca (ex.: wifi.ssid.set) com os parâmetros e a sequência de passos.
 * Passo CLI (SSH/Telnet): {"send": "set ssid={ssid} interface={iface}", "timeout": 30}
 * Passo API RouterOS: {"command": "/interface/wireless/print", "args": {"?name": "{iface}", "=.proplist": "ssid"}}
 * Passo HTTP (REST): {"method": "POST", "path": "/stakick.cgi", "form": {"staif": "ath0", "staid": "{mac}"}}
 * Passo SNMP: {"oid": "1.3.6.1.2.1.1.5"} (walk, só leitura)
 *   (chaves com "=" são atributos e com "?" são filtros, como na API do RouterOS)
 * {nome} é trocado pelo parâmetro já validado.
 *
 * Verificação (só em ações de configuração): "verify": {"action": "wifi.ssid.get", "contains": "{ssid}"}
 *   — depois da escrita, roda a ação de consulta indicada (mesmos parâmetros) e confere que a saída
 *   contém o valor esperado já renderizado. É assim que se afere que o comando funcionou.
 */
class CommandAction extends Model
{
    protected $fillable = ['key', 'name', 'kind', 'description', 'params', 'steps', 'parser', 'verify', 'validated', 'notes', 'sort'];

    protected $casts = ['params' => 'array', 'steps' => 'array', 'verify' => 'array', 'validated' => 'boolean'];

    public const KINDS = ['consulta' => 'Consulta', 'configuracao' => 'Configuração'];

    // Padrão de parâmetro quando a ação não define um: sem espaço, quebra de linha ou caracteres de controle da CLI
    public const DEFAULT_PATTERN = '^[A-Za-z0-9_.:@\/\-]{1,64}$';

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CommandProfile::class, 'command_profile_id');
    }

    public function isWrite(): bool
    {
        return $this->kind === 'configuracao';
    }

    /**
     * Passos com os parâmetros aplicados. Cada valor é validado pelo padrão do parâmetro
     * (nunca entra quebra de linha ou ";" num comando de CLI).
     *
     * @throws CommandLibraryException parâmetro obrigatório ausente ou inválido
     */
    public function render(array $values = []): array
    {
        return array_map($this->substituter($values), $this->steps ?? []);
    }

    /** Valor esperado da verificação já renderizado (ex.: o SSID novo), ou null. */
    public function expectedValue(array $values = []): ?string
    {
        $contains = $this->verify['contains'] ?? null;
        if (! is_string($contains) || $contains === '') {
            return null;
        }

        return $this->replaceWith($this->clean($values), $contains);
    }

    /** @return callable(array): array */
    private function substituter(array $values): callable
    {
        $clean = $this->clean($values);

        return function (array $step) use ($clean) {
            foreach ($step as $k => $v) {
                $step[$k] = is_array($v)
                    ? array_combine(array_map(fn ($x) => $this->replaceWith($clean, $x), array_keys($v)), array_map(fn ($x) => $this->replaceWith($clean, $x), $v))
                    : $this->replaceWith($clean, $v);
            }

            return $step;
        };
    }

    /** Valida e normaliza os parâmetros recebidos. @return array<string,string> */
    private function clean(array $values): array
    {
        $clean = [];
        foreach ($this->params ?? [] as $p) {
            $name = $p['name'];
            $value = $values[$name] ?? ($p['default'] ?? null);
            $value = is_scalar($value) ? trim((string) $value) : null;
            if ($value === null || $value === '') {
                if ($p['required'] ?? true) {
                    throw new CommandLibraryException('Informe o parâmetro "'.($p['label'] ?? $name).'".');
                }
                $value = '';
            } elseif (! preg_match('/'.str_replace('/', '\/', $p['pattern'] ?? self::DEFAULT_PATTERN).'/u', $value) || preg_match('/[\r\n\x00-\x1f]/', $value)) {
                throw new CommandLibraryException('Valor inválido para "'.($p['label'] ?? $name).'".');
            }
            $clean[$name] = $value;
        }

        return $clean;
    }

    private function replaceWith(array $clean, mixed $text): mixed
    {
        return is_string($text)
            ? preg_replace_callback('/\{([a-z_][a-z0-9_]*)\}/i', fn ($m) => array_key_exists($m[1], $clean) ? $clean[$m[1]] : $m[0], $text)
            : $text;
    }

    /** Texto dos passos para exibição (uma linha por passo). */
    public static function describeStep(array $step): string
    {
        if (isset($step['send'])) {
            return $step['send'];
        }
        if (isset($step['oid'])) {
            return 'SNMP walk '.$step['oid'];
        }
        if (isset($step['path'])) {
            $line = ($step['method'] ?? 'GET').' '.$step['path'];
            foreach ($step['form'] ?? [] as $k => $v) {
                $line .= " {$k}={$v}";
            }

            return $line;
        }
        $line = $step['command'] ?? '?';
        foreach ($step['args'] ?? [] as $k => $v) {
            $line .= " {$k}={$v}";
        }

        return $line;
    }
}
