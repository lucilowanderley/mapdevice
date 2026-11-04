<?php

namespace Tests\Support;

use App\Discovery\DeviceProber;
use App\Discovery\DiscoveryResult;
use App\Discovery\Fingerprints;
use App\Models\Device;

/** Sonda de teste: não toca na rede; devolve um resultado fixo. */
class FakeProber extends DeviceProber
{
    public function __construct(private readonly DiscoveryResult $result)
    {
        parent::__construct(new Fingerprints);
    }

    public function probe(Device $device, int $timeout = 4): DiscoveryResult
    {
        return $this->result;
    }
}
