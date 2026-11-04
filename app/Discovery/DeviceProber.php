<?php

namespace App\Discovery;

use App\Execution\ExecutionException;
use App\Execution\Transports\SnmpTransport;
use App\Models\Device;
use Illuminate\Support\Facades\Http;

/**
 * Sonda de descoberta: coleta pistas só-leitura de um equipamento (portas abertas, banner do SSH/Telnet,
 * sysDescr/sysObjectID por SNMP e cabeçalho/título por HTTP) e deduz fabricante e sistema embarcado.
 * Não envia nenhum comando de configuração; nunca altera o equipamento.
 */
class DeviceProber
{
    /** Portas sondadas: rótulo => porta TCP. */
    public const PORTS = ['ssh' => 22, 'telnet' => 23, 'http' => 80, 'https' => 443, 'routeros_api' => 8728, 'routeros_api_ssl' => 8729];

    public function __construct(private readonly Fingerprints $fingerprints) {}

    public function probe(Device $device, int $timeout = 4): DiscoveryResult
    {
        $host = (string) $device->host;
        if ($host === '') {
            return new DiscoveryResult(notes: ['O equipamento não tem endereço (host) cadastrado.']);
        }

        $ports = [];
        foreach (self::PORTS as $label => $port) {
            $ports[$label] = $this->portOpen($host, $port, $timeout);
        }

        $clues = [];
        $notes = [];

        if ($ports['ssh']) {
            $clues['ssh_banner'] = $this->bannerLine($host, 22, $timeout);
        }
        if ($ports['telnet']) {
            $clues['telnet_banner'] = $this->telnetBanner($host, 23, $timeout);
        }
        foreach (['https' => 443, 'http' => 80] as $scheme => $port) {
            if ($ports[$scheme] && empty($clues['http_server']) && empty($clues['http_title'])) {
                [$server, $title] = $this->http($scheme, $host, $port, $timeout);
                if ($server !== null) {
                    $clues['http_server'] = $server;
                }
                if ($title !== null) {
                    $clues['http_title'] = $title;
                }
            }
        }
        if ($device->snmp_community) {
            [$descr, $oid, $note] = $this->snmp($device, $timeout);
            if ($descr !== null) {
                $clues['snmp_sysdescr'] = $descr;
            }
            if ($oid !== null) {
                $clues['snmp_sysobjectid'] = $oid;
            }
            if ($note) {
                $notes[] = $note;
            }
        } else {
            $notes[] = 'SNMP não sondado: o equipamento não tem community cadastrada.';
        }

        $clues = array_filter($clues, fn ($v) => $v !== null && $v !== '');
        $guess = $this->fingerprints->match($clues);

        return new DiscoveryResult(
            ports: $ports,
            clues: $clues,
            manufacturer: $guess['manufacturer'],
            os: $guess['os'],
            accessMethod: $guess['access'],
            confidence: $guess['confidence'],
            notes: $notes,
        );
    }

    private function portOpen(string $host, int $port, int $timeout): bool
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, min($timeout, 3));
        if ($socket) {
            fclose($socket);

            return true;
        }

        return false;
    }

    private function bannerLine(string $host, int $port, int $timeout): ?string
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (! $socket) {
            return null;
        }
        stream_set_timeout($socket, $timeout);
        $line = @fgets($socket, 256);
        @fclose($socket);

        return $line ? trim(preg_replace('/[^\x20-\x7e]/', '', $line)) : null;
    }

    private function telnetBanner(string $host, int $port, int $timeout): ?string
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (! $socket) {
            return null;
        }
        stream_set_timeout($socket, $timeout);
        $raw = @fread($socket, 1024) ?: '';
        @fclose($socket);
        // Remove bytes de negociação Telnet (IAC = 0xFF e os dois seguintes) e caracteres de controle.
        $clean = preg_replace('/\xff[\xfb-\xfe]./s', '', $raw);
        $clean = preg_replace('/[^\x20-\x7e\n]/', '', (string) $clean);

        return trim($clean) ?: null;
    }

    /** @return array{0: ?string, 1: ?string} [server, title] */
    private function http(string $scheme, string $host, int $port, int $timeout): array
    {
        try {
            $response = Http::withOptions(['verify' => false])->timeout($timeout)->get("{$scheme}://{$host}:{$port}/");
        } catch (\Throwable $e) {
            return [null, null];
        }
        $server = $response->header('Server') ?: null;
        $title = null;
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $response->body(), $m)) {
            $title = trim(preg_replace('/\s+/', ' ', $m[1])) ?: null;
        }

        return [$server, $title];
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} [sysDescr, sysObjectID, nota] */
    private function snmp(Device $device, int $timeout): array
    {
        $transport = new SnmpTransport;
        try {
            $transport->connect($device, null, $timeout);
            $descr = $this->snmpValue($transport->execute(['oid' => '1.3.6.1.2.1.1.1']));
            $oid = $this->snmpValue($transport->execute(['oid' => '1.3.6.1.2.1.1.2']));

            return [$descr, $oid, null];
        } catch (ExecutionException $e) {
            return [null, null, 'SNMP: '.$e->getMessage()];
        } finally {
            $transport->disconnect();
        }
    }

    /** A primeira linha de um walk vem como "oid.indice = valor"; devolve só o valor. */
    private function snmpValue(string $walk): ?string
    {
        $first = strtok($walk, "\n");
        if ($first === false) {
            return null;
        }
        $pos = strpos($first, '= ');

        return $pos !== false ? trim(substr($first, $pos + 2)) : trim($first);
    }
}
