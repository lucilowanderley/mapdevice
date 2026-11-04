<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de auditoria de uma execução no equipamento (consulta ou configuração). */
class CommandExecution extends Model
{
    protected $fillable = [
        'user_id', 'device_id', 'profile_slug', 'action_key', 'kind', 'params', 'success', 'verified', 'output', 'error',
    ];

    protected $casts = [
        'params' => 'array',
        'success' => 'boolean',
        'verified' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function isWrite(): bool
    {
        return $this->kind === 'configuracao';
    }
}
