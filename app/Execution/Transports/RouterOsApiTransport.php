<?php

namespace App\Execution\Transports;

use App\Execution\ExecutionException;
use App\Execution\Transport;
use App\Models\Device;

/**
 * Protocolo da API do MikroTik RouterOS em PHP puro (sem dependências): cada sentença é uma lista de palavras com o
 * tamanho codificado em 1 a 5 bytes e termina numa palavra vazia. Respostas: !re (linha), !done (fim), !trap (erro do
 * comando), !fatal (conexão encerrada). Funciona nas versões 6.x e 7.x. Porta 8728 (api) ou 8729 com TLS (api-ssl).
 *
 * Passo: {"command": "/interface/wireless/set", "args": {"?name": "wlan1", "=ssid": "NovoSSID"}}
 */
class RouterOsApiTransport implements Transport
{
    /** @var resource|null */
    private $socket = null;

    private int $timeout = 10;

    public function connect(Device $device, ?array $session, int $timeout): void
    {
        $this->timeout = $timeout;
        $tls = $device->access_method === 'api_ssl';
        $port = $device->port ?: ($tls ? 8729 : 8728);
        $context = stream_context_create(['ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            // API-SSL sem certificado no RouterOS usa Diffie-Hellman anônimo (ADH)
            'ciphers' => 'DEFAULT:ADH:@SECLEVEL=0',
        ]]);
        $connectTimeout = min($timeout, 5);
        $socket = @stream_socket_client(($tls ? 'tls' : 'tcp')."://{$device->host}:{$port}", $errno, $error, $connectTimeout, STREAM_CLIENT_CONNECT, $context);
        if (! $socket) {
            throw new ExecutionException("Não foi possível conectar na API do RouterOS em {$device->host}:{$port} ({$error}). Confira o IP, se o serviço "
                .($tls ? 'api-ssl' : 'api').' está ativo em /ip service e se este servidor está em "available from".');
        }
        stream_set_timeout($socket, $timeout);
        $this->socket = $socket;
        $this->login((string) $device->username, (string) $device->password);
    }

    public function execute(array $step): string
    {
        $command = (string) ($step['command'] ?? '');
        $words = [$command];
        foreach ($step['args'] ?? [] as $key => $value) {
            $words[] = "{$key}={$value}";
        }
        $rows = $this->talk($words)['rows'];
        if ($rows === []) {
            return '(ok)';
        }

        return implode("\n", array_map(
            fn (array $row) => implode('  ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($row), $row)),
            $rows
        ));
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            @fclose($this->socket);
        }
        $this->socket = null;
    }

    private function login(string $username, string $password): void
    {
        try {
            $reply = $this->talk(['/login', "=name={$username}", "=password={$password}"]);
        } catch (ExecutionException $e) {
            throw new ExecutionException('Usuário ou senha da API recusados pelo RouterOS. '.$e->getMessage());
        }
        // Antes da 6.43: o servidor devolve um desafio (=ret=) e espera o hash MD5
        $challenge = $reply['done']['ret'] ?? null;
        if ($challenge) {
            $response = '00'.md5(chr(0).$password.pack('H*', $challenge));
            try {
                $this->talk(['/login', "=name={$username}", "=response={$response}"]);
            } catch (ExecutionException $e) {
                throw new ExecutionException('Usuário ou senha da API recusados pelo RouterOS. '.$e->getMessage());
            }
        }
    }

    /** @return array{rows: array<int, array<string, string>>, done: array<string, string>} */
    private function talk(array $words): array
    {
        $this->writeSentence($words);
        $rows = [];
        while (true) {
            $sentence = $this->readSentence();
            $type = array_shift($sentence);
            $attrs = self::attributes($sentence);
            switch ($type) {
                case '!re':
                    $rows[] = $attrs;
                    break;
                case '!done':
                    return ['rows' => $rows, 'done' => $attrs];
                case '!trap':
                    $message = $attrs['message'] ?? 'erro sem mensagem';
                    do {
                        $next = $this->readSentence();
                    } while (($next[0] ?? null) !== '!done');
                    throw new ExecutionException("RouterOS recusou {$words[0]}: {$message}");
                case '!fatal':
                    throw new ExecutionException('RouterOS encerrou a conexão: '.($sentence[0] ?? ''));
                default:
                    throw new ExecutionException("Resposta inesperada da API do RouterOS ({$type}).");
            }
        }
    }

    public static function attributes(array $words): array
    {
        $attrs = [];
        foreach ($words as $word) {
            if (str_starts_with($word, '=')) {
                $pos = strpos($word, '=', 1);
                if ($pos !== false) {
                    $value = substr($word, $pos + 1);
                    $attrs[substr($word, 1, $pos - 1)] = mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
                }
            }
        }

        return $attrs;
    }

    public static function encodeLength(int $len): string
    {
        return match (true) {
            $len < 0x80 => chr($len),
            $len < 0x4000 => pack('n', $len | 0x8000),
            $len < 0x200000 => substr(pack('N', $len | 0xC00000), 1),
            $len < 0x10000000 => pack('N', $len | 0xE0000000),
            default => chr(0xF0).pack('N', $len),
        };
    }

    public static function encodeSentence(array $words): string
    {
        $out = '';
        foreach ($words as $word) {
            $out .= self::encodeLength(strlen($word)).$word;
        }

        return $out."\0";
    }

    private function writeSentence(array $words): void
    {
        $data = self::encodeSentence($words);
        while ($data !== '') {
            $written = @fwrite($this->socket, $data);
            if ($written === false || $written === 0) {
                throw new ExecutionException('Falha ao enviar o comando para a API do RouterOS.');
            }
            $data = substr($data, $written);
        }
    }

    private function readSentence(): array
    {
        $words = [];
        while (($len = $this->readLength()) > 0) {
            $words[] = $this->readBytes($len);
        }

        return $words;
    }

    private function readLength(): int
    {
        $b = ord($this->readBytes(1));

        return match (true) {
            ($b & 0x80) === 0 => $b,
            ($b & 0xC0) === 0x80 => (($b & 0x3F) << 8) | ord($this->readBytes(1)),
            ($b & 0xE0) === 0xC0 => (($b & 0x1F) << 16) | unpack('n', $this->readBytes(2))[1],
            ($b & 0xF0) === 0xE0 => (($b & 0x0F) << 24) | (unpack('n', $this->readBytes(2))[1] << 8) | ord($this->readBytes(1)),
            default => unpack('N', $this->readBytes(4))[1],
        };
    }

    private function readBytes(int $len): string
    {
        $data = '';
        while (strlen($data) < $len) {
            $chunk = $this->socket ? @fread($this->socket, $len - strlen($data)) : false;
            if ($chunk === false || $chunk === '') {
                $meta = $this->socket ? stream_get_meta_data($this->socket) : ['timed_out' => false];
                throw new ExecutionException($meta['timed_out'] ? "Tempo esgotado ({$this->timeout}s) esperando resposta da API do RouterOS." : 'A API do RouterOS encerrou a conexão.');
            }
            $data .= $chunk;
        }

        return $data;
    }
}
