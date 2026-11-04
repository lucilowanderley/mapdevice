<?php

namespace App\Execution\Transports;

use App\Execution\ExecutionException;
use App\Execution\Transport;
use App\Models\Device;
use Symfony\Component\Process\Process;

/**
 * SNMP v2c (somente leitura). Passo: {"oid": "1.3.6.1.2.1.1.5"} — faz um walk e devolve índice => valor.
 * Usa a extensão php-snmp quando instalada; senão, o snmpbulkwalk do Net-SNMP.
 */
class SnmpTransport implements Transport
{
    private string $host = '';

    private int $port = 161;

    private string $community = 'public';

    private int $timeout = 5;

    public function connect(Device $device, ?array $session, int $timeout): void
    {
        $this->host = (string) $device->host;
        $this->port = $device->port ?: 161;
        $this->community = (string) ($device->snmp_community ?: 'public');
        $this->timeout = $timeout;
    }

    public function execute(array $step): string
    {
        $oid = ltrim((string) ($step['oid'] ?? ''), '.');
        if ($oid === '') {
            throw new ExecutionException('Passo SNMP sem OID.');
        }
        $rows = $this->walk($oid);
        if ($rows === []) {
            return '(sem resposta)';
        }

        return implode("\n", array_map(fn ($k, $v) => "{$oid}.{$k} = {$v}", array_keys($rows), $rows));
    }

    public function disconnect(): void {}

    /** @return array<string, string> */
    private function walk(string $oid): array
    {
        $raw = function_exists('snmp2_real_walk')
            ? $this->walkExtension($oid)
            : $this->walkCli($oid);

        $out = [];
        foreach ($raw as $fullOid => $value) {
            $fullOid = ltrim((string) $fullOid, '.');
            if (! str_starts_with($fullOid, $oid.'.')) {
                continue;
            }
            $out[substr($fullOid, strlen($oid) + 1)] = $this->clean((string) $value);
        }

        return $out;
    }

    private function walkExtension(string $oid): array
    {
        snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
        snmp_set_quick_print(true);
        $result = @snmp2_real_walk("{$this->host}:{$this->port}", $this->community, $oid, $this->timeout * 1_000_000, 1);
        if ($result === false) {
            throw new ExecutionException("O equipamento {$this->host} não respondeu ao SNMP (OID {$oid}). Confira IP, porta, community e a ACL de SNMP.");
        }

        return $result;
    }

    private function walkCli(string $oid): array
    {
        $process = new Process(['snmpbulkwalk', '-v2c', '-c', $this->community, '-On', '-Oq', '-t', (string) $this->timeout, '-r', '1', "{$this->host}:{$this->port}", $oid]);
        $process->setTimeout(($this->timeout * 2) + 120);
        try {
            $process->run();
        } catch (\Throwable $e) {
            throw new ExecutionException('Não foi possível executar o snmpbulkwalk. Instale o Net-SNMP (snmp) ou a extensão php-snmp no servidor.');
        }
        if (! $process->isSuccessful()) {
            $err = trim($process->getErrorOutput()) ?: 'sem resposta';
            if (str_contains($err, 'not found') || $process->getExitCode() === 127) {
                throw new ExecutionException('snmpbulkwalk não encontrado. Instale o Net-SNMP (snmp) ou a extensão php-snmp no servidor.');
            }
            throw new ExecutionException("O equipamento {$this->host} não respondeu ao SNMP (OID {$oid}): ".mb_substr($err, 0, 200));
        }

        $out = [];
        foreach (preg_split('/\r?\n/', $process->getOutput()) as $line) {
            if (preg_match('/^(\.?[\d.]+)\s+(.*)$/', trim($line), $m)) {
                $out[$m[1]] = $m[2];
            }
        }

        return $out;
    }

    private function clean(string $value): string
    {
        $value = preg_replace('/^[A-Za-z0-9\-]+:\s/', '', trim($value));

        return trim($value, "\" \t");
    }
}
