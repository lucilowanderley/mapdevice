<?php

namespace Tests\Support;

use App\Execution\Transport;
use App\Models\Device;

/** Transporte de teste: não abre conexão; devolve a saída decidida por um resolvedor. */
class FakeTransport implements Transport
{
    /** @var array<int, array> passos executados, em ordem */
    public array $executed = [];

    public bool $connected = false;

    /** @param callable(array): string $resolver */
    public function __construct(private $resolver) {}

    public function connect(Device $device, ?array $session, int $timeout): void
    {
        $this->connected = true;
    }

    public function execute(array $step): string
    {
        $this->executed[] = $step;

        return ($this->resolver)($step);
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }
}
