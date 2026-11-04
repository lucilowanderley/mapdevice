<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Uma sondagem da interface web de um equipamento (mapeamento de modelo, só leitura). */
class WebProbe extends Model
{
    public const LOGIN_MODES = [
        'nenhum' => 'Sem login (só páginas públicas)',
        'basic' => 'HTTP Basic (usuário/senha do equipamento)',
        'formulario' => 'Formulário de login (POST com usuário/senha)',
    ];

    protected $fillable = ['device_id', 'user_id', 'base_url', 'login_mode', 'login_user', 'ok', 'message', 'pages_total'];

    protected $casts = ['ok' => 'boolean'];

    public function pages(): HasMany
    {
        return $this->hasMany(WebProbePage::class)->orderBy('sort');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loginLabel(): string
    {
        return self::LOGIN_MODES[$this->login_mode] ?? $this->login_mode;
    }
}
