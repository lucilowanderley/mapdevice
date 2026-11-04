<?php

namespace Tests\Support;

use App\Execution\Transport;
use App\Execution\TransportFactory;
use App\Models\CommandProfile;
use App\Models\Device;

/** Fábrica de teste: devolve sempre o mesmo FakeTransport, ignorando o protocolo. */
class FakeTransportFactory extends TransportFactory
{
    public function __construct(private readonly FakeTransport $transport) {}

    public function for(CommandProfile $profile, Device $device): Transport
    {
        return $this->transport;
    }
}
