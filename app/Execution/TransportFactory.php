<?php

namespace App\Execution;

use App\Execution\Transports\CliTransport;
use App\Execution\Transports\RestTransport;
use App\Execution\Transports\RouterOsApiTransport;
use App\Execution\Transports\SnmpTransport;
use App\Models\CommandProfile;
use App\Models\Device;

/** Escolhe o canal certo para o protocolo do perfil e o método de acesso do equipamento. */
class TransportFactory
{
    public function for(CommandProfile $profile, Device $device): Transport
    {
        $allowed = CommandProfile::PROTOCOL_ACCESS[$profile->protocol] ?? [];
        if ($device->access_method && $allowed && ! in_array($device->access_method, $allowed, true)) {
            throw new ExecutionException(
                "O equipamento está configurado para {$device->accessLabel()}, mas o perfil \"{$profile->name}\" usa {$profile->protocolLabel()}."
            );
        }

        return match ($profile->protocol) {
            'routeros_api' => new RouterOsApiTransport,
            'rest' => new RestTransport,
            'snmp' => new SnmpTransport,
            'cli' => new CliTransport,
            default => throw new ExecutionException("Protocolo não suportado: {$profile->protocol}."),
        };
    }
}
