<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Equipamento da rede gerenciado pelo provedor (OLT, ONU/ONT, PPPoE, rádio, AP, gateway, firewall, CPE...). */
class Device extends Model
{
    public const MODULES = [
        'ftth' => 'FTTH', 'pppoe' => 'PPPoE', 'cpe' => 'CPE (cliente)',
        'ap' => 'Access Point', 'gateway' => 'Gateway', 'firewall' => 'Firewall',
    ];

    public const ACCESS_METHODS = [
        'ssh' => 'SSH', 'telnet' => 'Telnet', 'http' => 'HTTP', 'https' => 'HTTPS',
        'snmp' => 'SNMP', 'api' => 'API', 'api_ssl' => 'API-SSL', 'console' => 'Console',
    ];

    protected $fillable = [
        'name', 'module', 'manufacturer', 'model', 'firmware', 'os',
        'host', 'access_method', 'port', 'username', 'password', 'snmp_community', 'notes', 'active',
    ];

    protected $hidden = ['password', 'snmp_community'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'snmp_community' => 'encrypted',
            'active' => 'boolean',
            'port' => 'integer',
        ];
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    public function moduleLabel(): string
    {
        return self::MODULES[$this->module] ?? ($this->module ?: '—');
    }

    public function accessLabel(): string
    {
        return self::ACCESS_METHODS[$this->access_method] ?? ($this->access_method ?: '—');
    }

    /** Senha mascarada para exibição (a senha real nunca é mostrada nem enviada ao LLM). */
    public function getMaskedPasswordAttribute(): string
    {
        return $this->password ? str_repeat('•', 8) : '—';
    }
}
