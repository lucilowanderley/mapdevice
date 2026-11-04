<?php

namespace App\Execution\Transports;

use App\Execution\ExecutionException;
use App\Execution\Transport;
use App\Models\Device;
use phpseclib3\Net\SSH2;

/**
 * CLI por SSH (phpseclib) ou Telnet (socket puro). Cada passo tem {"send": "comando", "timeout": 30}.
 * No SSH cada comando roda com exec(). No Telnet o login vem de session.setup e a saída é lida até o prompt
 * (session.prompt, regex; padrão termina em $ # ou >).
 */
class CliTransport implements Transport
{
    private const IAC = 255;

    private const DONT = 254;

    private const DO = 253;

    private const WONT = 252;

    private const WILL = 251;

    private const SB = 250;

    private const SE = 240;

    private ?SSH2 $ssh = null;

    /** @var resource|null */
    private $socket = null;

    private string $prompt = '/[\$#>]\s*$/';

    private int $timeout = 20;

    public function connect(Device $device, ?array $session, int $timeout): void
    {
        $this->timeout = $timeout;
        $this->prompt = $this->validPattern($session['prompt'] ?? null) ?? $this->prompt;

        if ($device->access_method === 'ssh') {
            $this->connectSsh($device, $timeout);

            return;
        }
        $this->connectTelnet($device, $timeout);
        foreach ($session['setup'] ?? [] as $step) {
            $this->execute($step);
        }
    }

    public function execute(array $step): string
    {
        $command = (string) ($step['send'] ?? '');
        $timeout = isset($step['timeout']) ? (int) $step['timeout'] : $this->timeout;

        if ($this->ssh) {
            $this->ssh->setTimeout($timeout);
            $out = $this->ssh->exec($command);
            if ($out === false) {
                throw new ExecutionException('Falha ao executar o comando por SSH.');
            }

            return $this->clean((string) $out);
        }

        $this->write($command);
        $raw = $this->readUntil($this->prompt, $timeout);
        // Remove o comando ecoado da primeira linha e a linha do prompt no fim.
        $lines = explode("\n", $raw);
        if ($lines && str_contains($lines[0], $command)) {
            array_shift($lines);
        }
        if ($lines && preg_match($this->prompt, end($lines))) {
            array_pop($lines);
        }

        return trim(implode("\n", $lines));
    }

    public function disconnect(): void
    {
        if ($this->ssh) {
            $this->ssh->disconnect();
            $this->ssh = null;
        }
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    private function connectSsh(Device $device, int $timeout): void
    {
        try {
            $ssh = new SSH2($device->host, $device->port ?: 22, $timeout);
            if (! $ssh->login((string) $device->username, (string) $device->password)) {
                throw new ExecutionException("Usuário ou senha recusados pelo SSH em {$device->host}.");
            }
            $this->ssh = $ssh;
        } catch (ExecutionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ExecutionException("Não foi possível conectar por SSH em {$device->host}: ".$e->getMessage());
        }
    }

    private function connectTelnet(Device $device, int $timeout): void
    {
        $port = $device->port ?: 23;
        $socket = @stream_socket_client("tcp://{$device->host}:{$port}", $errno, $error, $timeout);
        if (! $socket) {
            throw new ExecutionException("Não foi possível conectar por Telnet em {$device->host}:{$port} ({$error}). Confira o IP e se a porta Telnet está liberada.");
        }
        stream_set_blocking($socket, false);
        $this->socket = $socket;
    }

    private function readUntil(string $pattern, int $timeoutSeconds): string
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $buffer = '';
        $handled = 0;
        while (microtime(true) < $deadline) {
            $read = [$this->socket];
            $write = $except = null;
            if (@stream_select($read, $write, $except, 0, 200_000) === false) {
                break;
            }
            $chunk = $read ? fread($this->socket, 8192) : '';
            if ($chunk === false || ($chunk === '' && feof($this->socket))) {
                throw new ExecutionException('O equipamento encerrou a conexão Telnet.');
            }
            if ($chunk === '') {
                continue;
            }
            $buffer .= $this->negotiate($chunk);
            if (preg_match('/-+\s*more\s*-+|press any key/i', substr($buffer, $handled), $m, PREG_OFFSET_CAPTURE)) {
                $handled += $m[0][1] + strlen($m[0][0]);
                fwrite($this->socket, ' ');

                continue;
            }
            if (preg_match($pattern, $this->clean($buffer))) {
                return $this->clean($buffer);
            }
        }
        throw new ExecutionException('Tempo esgotado esperando a resposta do equipamento por Telnet. Último trecho: '.mb_substr(trim($this->clean($buffer)), -120));
    }

    private function write(string $line): void
    {
        fwrite($this->socket, $line."\r\n");
    }

    /** Responde WONT/DONT a toda negociação e devolve só os dados. */
    private function negotiate(string $data): string
    {
        $out = '';
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($data[$i]);
            if ($c !== self::IAC || $i + 1 >= $len) {
                $out .= $data[$i];

                continue;
            }
            $cmd = ord($data[++$i]);
            if (in_array($cmd, [self::DO, self::DONT, self::WILL, self::WONT], true) && $i + 1 < $len) {
                $opt = $data[++$i];
                if ($cmd === self::DO) {
                    fwrite($this->socket, chr(self::IAC).chr(self::WONT).$opt);
                } elseif ($cmd === self::WILL) {
                    fwrite($this->socket, chr(self::IAC).chr(self::DONT).$opt);
                }
            } elseif ($cmd === self::SB) {
                while ($i + 1 < $len && ! (ord($data[$i]) === self::IAC && ord($data[$i + 1]) === self::SE)) {
                    $i++;
                }
                $i++;
            } elseif ($cmd === self::IAC) {
                $out .= chr(self::IAC);
            }
        }

        return $out;
    }

    private function clean(string $text): string
    {
        $text = preg_replace('/\e\[[0-9;?]*[A-Za-z]/', '', $text); // sequências ANSI
        while (preg_match('/[^\x08]\x08/', $text)) {
            $text = preg_replace('/[^\x08]\x08/', '', $text); // backspaces da paginação
        }
        $text = preg_replace('/-+\s*more\s*-+[ \t]*|press any key[^\r\n]*/i', '', $text);

        return str_replace(["\r\n", "\r", "\0"], ["\n", '', ''], $text);
    }

    private function validPattern(?string $pattern): ?string
    {
        if (! is_string($pattern) || $pattern === '') {
            return null;
        }
        $regex = '/'.str_replace('/', '\/', $pattern).'/';

        return @preg_match($regex, '') === false ? null : $regex;
    }
}
