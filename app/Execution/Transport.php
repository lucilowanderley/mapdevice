<?php

namespace App\Execution;

use App\Models\Device;

/**
 * Canal com o equipamento para um protocolo (CLI por SSH/Telnet, API RouterOS, HTTP/REST, SNMP).
 * O motor abre a conexão, executa cada passo já renderizado e devolve a saída em texto, e fecha.
 */
interface Transport
{
    /**
     * Abre a conexão. $session traz os passos de abertura/encerramento e o prompt da CLI (profile.session).
     *
     * @throws ExecutionException
     */
    public function connect(Device $device, ?array $session, int $timeout): void;

    /**
     * Executa um passo já renderizado (sem {placeholders}) e devolve a saída em texto.
     *
     * @throws ExecutionException
     */
    public function execute(array $step): string;

    public function disconnect(): void;
}
