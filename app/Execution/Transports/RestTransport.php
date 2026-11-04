<?php

namespace App\Execution\Transports;

use App\Execution\ExecutionException;
use App\Execution\Transport;
use App\Models\Device;
use Illuminate\Support\Facades\Http;

/**
 * HTTP / API web (ex.: airOS, WiFiber). Passo: {"method": "POST", "path": "/stakick.cgi", "form": {"staid": "{mac}"}}.
 * A sessão de login (profile.session.setup) é uma sequência de passos HTTP; os cookies são mantidos entre os passos.
 */
class RestTransport implements Transport
{
    private string $base = '';

    private array $cookies = [];

    private int $timeout = 20;

    public function connect(Device $device, ?array $session, int $timeout): void
    {
        $this->timeout = $timeout;
        $scheme = $device->access_method === 'http' ? 'http' : 'https';
        $port = $device->port ? ':'.$device->port : '';
        $this->base = "{$scheme}://{$device->host}{$port}";
        $this->credentials = ['username' => (string) $device->username, 'password' => (string) $device->password];
        foreach ($session['setup'] ?? [] as $step) {
            $this->execute($this->withCredentials($step));
        }
    }

    public function execute(array $step): string
    {
        $method = strtoupper($step['method'] ?? 'GET');
        $path = (string) ($step['path'] ?? '/');
        $form = $step['form'] ?? [];

        $request = Http::withOptions(['verify' => false])
            ->timeout($this->timeout)
            ->withHeaders($this->cookies ? ['Cookie' => $this->cookieHeader()] : []);

        try {
            $response = $method === 'POST'
                ? $request->asForm()->post($this->base.$path, $form)
                : $request->get($this->base.$path, $form);
        } catch (\Throwable $e) {
            throw new ExecutionException("Falha na requisição HTTP para {$path}: ".$e->getMessage());
        }

        foreach ($response->cookies()?->toArray() ?? [] as $cookie) {
            $this->cookies[$cookie['Name']] = $cookie['Value'];
        }
        if ($response->failed()) {
            throw new ExecutionException("O equipamento respondeu HTTP {$response->status()} em {$path}.");
        }

        return trim($response->body());
    }

    public function disconnect(): void
    {
        $this->cookies = [];
    }

    private array $credentials = [];

    /** Troca {username}/{password} nos passos de login pelas credenciais do equipamento. */
    private function withCredentials(array $step): array
    {
        $map = ['{username}' => $this->credentials['username'] ?? '', '{password}' => $this->credentials['password'] ?? ''];
        foreach ($step['form'] ?? [] as $k => $v) {
            $step['form'][$k] = strtr((string) $v, $map);
        }

        return $step;
    }

    private function cookieHeader(): string
    {
        return implode('; ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($this->cookies), $this->cookies));
    }
}
