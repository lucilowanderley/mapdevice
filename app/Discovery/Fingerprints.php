<?php

namespace App\Discovery;

/**
 * Impressões digitais: casa as pistas coletadas (banners, sysDescr, sysObjectID, cabeçalhos HTTP)
 * com um fabricante e o sistema embarcado, e sugere o método de acesso.
 */
class Fingerprints
{
    /**
     * Cada regra: 'oid' (prefixo do sysObjectID, mais específico) e/ou 'text' (regex nas pistas de texto).
     * 'access' é o método de acesso típico para gerar comandos naquele equipamento.
     *
     * @var array<int, array{manufacturer: string, os: string, access: string, oid?: string, text?: string, weight?: int}>
     */
    public const RULES = [
        ['manufacturer' => 'MikroTik', 'os' => 'RouterOS', 'access' => 'api', 'oid' => '1.3.6.1.4.1.14988', 'text' => '/routeros|mikrotik/i', 'weight' => 90],
        ['manufacturer' => 'Ubiquiti', 'os' => 'airOS', 'access' => 'https', 'oid' => '1.3.6.1.4.1.41112', 'text' => '/airos|ubnt|ubiquiti/i', 'weight' => 85],
        ['manufacturer' => 'Huawei', 'os' => 'VRP', 'access' => 'ssh', 'oid' => '1.3.6.1.4.1.2011', 'text' => '/huawei|\bvrp\b/i', 'weight' => 80],
        ['manufacturer' => 'Fiberhome', 'os' => 'AN5516', 'access' => 'telnet', 'oid' => '1.3.6.1.4.1.5875', 'text' => '/fiberhome|an5516/i', 'weight' => 80],
        ['manufacturer' => 'Intelbras', 'os' => 'WiFiber', 'access' => 'https', 'text' => '/intelbras|wifiber/i', 'weight' => 75],
        ['manufacturer' => 'Cisco', 'os' => 'IOS', 'access' => 'ssh', 'oid' => '1.3.6.1.4.1.9', 'text' => '/cisco|ios software/i', 'weight' => 75],
        ['manufacturer' => 'Linux', 'os' => 'BusyBox/Linux', 'access' => 'ssh', 'text' => '/busybox|dropbear/i', 'weight' => 40],
    ];

    /**
     * @param  array<string, string>  $clues
     * @return array{manufacturer: ?string, os: ?string, access: ?string, confidence: int}
     */
    public function match(array $clues): array
    {
        $oid = ltrim($clues['snmp_sysobjectid'] ?? '', '.');
        $text = implode("\n", array_filter([
            $clues['ssh_banner'] ?? null,
            $clues['telnet_banner'] ?? null,
            $clues['http_server'] ?? null,
            $clues['http_title'] ?? null,
            $clues['snmp_sysdescr'] ?? null,
        ]));

        $best = ['manufacturer' => null, 'os' => null, 'access' => null, 'confidence' => 0];
        foreach (self::RULES as $rule) {
            $score = 0;
            if (! empty($rule['oid']) && $oid !== '' && str_starts_with($oid, ltrim($rule['oid'], '.'))) {
                $score = $rule['weight'] ?? 70;
            } elseif (! empty($rule['text']) && $text !== '' && preg_match($rule['text'], $text)) {
                // Uma pista de texto sozinha vale menos que o sysObjectID.
                $score = (int) (($rule['weight'] ?? 70) * 0.8);
            }
            if ($score > $best['confidence']) {
                $best = ['manufacturer' => $rule['manufacturer'], 'os' => $rule['os'], 'access' => $rule['access'], 'confidence' => $score];
            }
        }

        return $best;
    }
}
