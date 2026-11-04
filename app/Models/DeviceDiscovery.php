<?php

namespace App\Models;

use App\Discovery\DiscoveryResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Resultado de uma sonda de descoberta de um equipamento (histórico, só leitura). */
class DeviceDiscovery extends Model
{
    protected $fillable = ['device_id', 'user_id', 'ports', 'clues', 'manufacturer', 'os', 'access_method', 'confidence', 'notes'];

    protected $casts = [
        'ports' => 'array',
        'clues' => 'array',
        'notes' => 'array',
        'confidence' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function fromResult(Device $device, DiscoveryResult $result, ?int $userId): self
    {
        return self::create([
            'device_id' => $device->id,
            'user_id' => $userId,
            'ports' => $result->ports,
            'clues' => $result->clues,
            'manufacturer' => $result->manufacturer,
            'os' => $result->os,
            'access_method' => $result->accessMethod,
            'confidence' => $result->confidence,
            'notes' => $result->notes,
        ]);
    }

    public function openPorts(): array
    {
        return array_keys(array_filter($this->ports ?? []));
    }
}
