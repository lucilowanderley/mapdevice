<?php

namespace App\Discovery;

/** O que a sonda de descoberta achou num equipamento (tudo só leitura). */
class DiscoveryResult
{
    /**
     * @param  array<string, bool>  $ports  porta => aberta
     * @param  array<string, string>  $clues  pista => texto (ssh_banner, telnet_banner, http_server, http_title, snmp_sysdescr, snmp_sysobjectid)
     * @param  string|null  $manufacturer  palpite do fabricante
     * @param  string|null  $os  palpite do sistema embarcado
     * @param  string|null  $accessMethod  método de acesso sugerido (ssh, telnet, api, http...)
     * @param  int  $confidence  0 a 100
     */
    public function __construct(
        public readonly array $ports = [],
        public readonly array $clues = [],
        public readonly ?string $manufacturer = null,
        public readonly ?string $os = null,
        public readonly ?string $accessMethod = null,
        public readonly int $confidence = 0,
        public readonly array $notes = [],
    ) {}

    public function openPorts(): array
    {
        return array_keys(array_filter($this->ports));
    }

    public function hasGuess(): bool
    {
        return $this->manufacturer !== null;
    }

    public function toArray(): array
    {
        return [
            'ports' => $this->ports,
            'clues' => $this->clues,
            'manufacturer' => $this->manufacturer,
            'os' => $this->os,
            'access_method' => $this->accessMethod,
            'confidence' => $this->confidence,
            'notes' => $this->notes,
        ];
    }
}
