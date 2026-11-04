<?php

namespace App\Probe;

use App\Models\Device;
use App\Models\WebProbe;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;

/**
 * Sonda da interface web de um equipamento: faz login (opcional), baixa as páginas escolhidas e, se pedido, segue
 * os links da mesma origem até um limite. É só leitura — além do POST de login, só faz GET. Nunca altera nada.
 * Senhas, chaves Wi-Fi e segredos são mascarados (***) antes de guardar.
 */
class WebProber
{
    private const MAX_PAGES = 15;

    private const MAX_BODY = 20000;

    public function probe(Device $device, string $loginMode = 'nenhum', array $options = [], ?int $userId = null): WebProbe
    {
        $base = $this->baseUrl($device);
        $jar = new CookieJar;
        $timeout = (int) ($options['timeout'] ?? 8);

        $probe = WebProbe::create([
            'device_id' => $device->id,
            'user_id' => $userId,
            'base_url' => $base,
            'login_mode' => $loginMode,
            'login_user' => $device->username,
        ]);

        $auth = null;
        $message = null;
        if ($loginMode === 'basic') {
            $auth = [(string) $device->username, (string) $device->password];
        } elseif ($loginMode === 'formulario') {
            $message = $this->formLogin($base, $device, $options, $jar, $timeout);
        }

        $seed = $this->normalizePaths($options['paths'] ?? ['/']);
        $crawl = (bool) ($options['crawl'] ?? false);

        $queue = array_map(fn ($p) => [$p, 'semente'], $seed);
        $seen = [];
        $sort = 0;
        $okCount = 0;
        while ($queue && count($seen) < self::MAX_PAGES) {
            [$path, $via] = array_shift($queue);
            if (isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;

            [$status, $body, $title] = $this->get($base, $path, $auth, $jar, $timeout);
            if ($status === 0) {
                continue;
            }
            $okCount += ($status >= 200 && $status < 400) ? 1 : 0;
            $masked = $this->mask($body);
            $probe->pages()->create([
                'path' => $path,
                'status' => $status,
                'bytes' => strlen($body),
                'title' => $title,
                'found_via' => $via,
                'body' => mb_substr($masked, 0, self::MAX_BODY),
                'sort' => $sort++,
            ]);

            if ($crawl && $status >= 200 && $status < 400) {
                foreach ($this->links($body) as $link) {
                    if (! isset($seen[$link]) && count($seen) + count($queue) < self::MAX_PAGES) {
                        $queue[] = [$link, 'link'];
                    }
                }
            }
        }

        $probe->update([
            'ok' => $okCount > 0,
            'message' => $message ?? ($okCount > 0 ? null : 'Nenhuma página respondeu. Confira o endereço, a porta e o login.'),
            'pages_total' => $sort,
        ]);

        return $probe->load('pages');
    }

    private function baseUrl(Device $device): string
    {
        $scheme = $device->access_method === 'http' ? 'http' : 'https';
        $port = $device->port ? ':'.$device->port : '';

        return "{$scheme}://{$device->host}{$port}";
    }

    private function formLogin(string $base, Device $device, array $options, CookieJar $jar, int $timeout): ?string
    {
        $path = $options['login_path'] ?? '/login.cgi';
        $userField = $options['user_field'] ?? 'username';
        $passField = $options['pass_field'] ?? 'password';
        try {
            $response = Http::withOptions(['verify' => false, 'cookies' => $jar])
                ->timeout($timeout)
                ->asForm()
                ->post($base.$path, [$userField => (string) $device->username, $passField => (string) $device->password]);

            return $response->failed() ? "Login por formulário respondeu HTTP {$response->status()}." : null;
        } catch (\Throwable $e) {
            return 'Falha no login por formulário: '.$e->getMessage();
        }
    }

    /** @return array{0:int,1:string,2:?string} [status, body, title] */
    private function get(string $base, string $path, ?array $auth, CookieJar $jar, int $timeout): array
    {
        try {
            $request = Http::withOptions(['verify' => false, 'cookies' => $jar])->timeout($timeout);
            if ($auth) {
                $request = $request->withBasicAuth($auth[0], $auth[1]);
            }
            $response = $request->get($base.$path);
        } catch (\Throwable $e) {
            return [0, '', null];
        }
        $body = $response->body();
        $title = null;
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m)) {
            $title = trim(preg_replace('/\s+/', ' ', $m[1])) ?: null;
        }

        return [$response->status(), $body, $title];
    }

    /** Links da mesma origem (caminhos relativos), para o crawl raso. @return string[] */
    private function links(string $html): array
    {
        preg_match_all('/<a\b[^>]*href=["\']([^"\'#?]+)["\']/i', $html, $m);
        $out = [];
        foreach ($m[1] as $href) {
            if (preg_match('#^(https?:)?//#i', $href) || str_starts_with($href, 'mailto:') || str_starts_with($href, 'javascript:')) {
                continue;
            }
            $path = '/'.ltrim($href, '/');
            if (preg_match('#^/[A-Za-z0-9_.\-/]*$#', $path)) {
                $out[$path] = true;
            }
        }

        return array_keys($out);
    }

    /** @param string[] $paths */
    private function normalizePaths(array $paths): array
    {
        $out = [];
        foreach ($paths as $p) {
            $p = '/'.ltrim(trim((string) $p), '/');
            if (preg_match('#^/[A-Za-z0-9_.\-/]*$#', $p)) {
                $out[$p] = true;
            }
        }

        return array_keys($out) ?: ['/'];
    }

    /** Mascara valores que parecem senha, chave Wi-Fi ou segredo. */
    public function mask(string $html): string
    {
        // Atributo value de inputs cujo nome/id sugere segredo.
        $html = preg_replace(
            '/(<input\b[^>]*\b(?:name|id)\s*=\s*["\']?[^"\'>]*(?:pass|senha|psk|key|wpa|secret)[^"\'>]*["\']?[^>]*\bvalue\s*=\s*["\'])([^"\']*)(["\'])/i',
            '$1***$3',
            $html
        );
        // Pares "campo": "valor" em JSON/JS.
        $html = preg_replace(
            '/((?:password|senha|passwd|pwd|psk|wpakey|wlanpassword|preshared[a-z]*|wpapsk|key)["\']?\s*[:=]\s*["\'])([^"\']+)(["\'])/i',
            '$1***$3',
            (string) $html
        );

        return (string) $html;
    }
}
