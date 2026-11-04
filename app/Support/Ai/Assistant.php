<?php

namespace App\Support\Ai;

use App\CommandLibrary\CommandLibrary;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiProvider;
use App\Models\CommandProfile;
use App\Models\Device;
use Illuminate\Support\Str;

/** Monta o contexto do equipamento, conversa com o provedor e grava a resposta. */
class Assistant
{
    /** Atalhos exibidos na tela do assistente. 'needs' = 'device' exige um equipamento vinculado. */
    public const ACTIONS = [
        'diagnostico' => ['needs' => 'device', 'label' => 'Diagnosticar',
            'prompt' => 'Analise os dados do equipamento abaixo e diga a causa provável do problema e como confirmar. Liste o que verificar, do mais provável ao menos provável.'],
        'comandos' => ['needs' => 'device', 'label' => 'Sugerir comandos',
            'prompt' => 'Sugira os comandos de terminal adequados a este equipamento (pelo fabricante, modelo e firmware) para investigar o problema. Explique o que cada comando mostra. Não invente comandos que não existam no equipamento.'],
        'fabricante' => ['needs' => 'device', 'label' => 'Identificar fabricante/SO',
            'prompt' => 'A partir das pistas disponíveis (fabricante, modelo, firmware, banners, respostas), identifique o fabricante e o sistema operacional embarcado deste equipamento e como confirmar essa identificação.'],
        'perfil' => ['needs' => 'device', 'label' => 'Gerar perfil da Biblioteca',
            'prompt' => 'Com base no que foi coletado e testado neste equipamento, redija um rascunho do perfil da Biblioteca de comandos no formato JSON "mapdevice-commands" versão 1 (profile + actions), usando apenas comandos confirmados. Deixe entre colchetes o que faltar.'],
        'sondagem' => ['needs' => 'device', 'label' => 'Planejar Sondagem',
            'prompt' => 'Indique como sondar a interface web deste modelo (apenas leitura): o tipo de login (nenhum, HTTP Basic ou formulário — com o caminho e os campos de usuário/senha), as páginas que valem a pena baixar e os campos a procurar em cada uma. A sondagem roda na tela Sondagem e gera o rascunho do perfil da Biblioteca.'],
        'explicar' => ['needs' => null, 'label' => 'Explicar ao cliente',
            'prompt' => "Escreva uma mensagem curta e simples para o cliente explicando a situação do equipamento dele e o que será feito:\n\n(descreva a situação)"],
    ];

    public function __construct(private LlmClient $client, private ?CommandLibrary $library = null) {}

    public static function actionsFor(?AiConversation $conversation): array
    {
        return array_filter(self::ACTIONS, fn ($a) => $a['needs'] === null
            || ($a['needs'] === 'device' && $conversation?->device_id));
    }

    /** Envia o histórico ao provedor e grava a resposta (ou o erro) na conversa. */
    public function reply(AiConversation $conversation, ?AiProvider $provider = null): AiMessage
    {
        $provider ??= $conversation->provider?->active ? $conversation->provider : AiProvider::default();
        if (! $provider) {
            throw new AiException('Nenhum provedor de IA está configurado. Um administrador precisa cadastrar um em Assistente › Provedores.');
        }

        $system = $this->systemPrompt($conversation, $provider);

        try {
            $reply = $this->client->chat($provider, $system, $this->history($conversation));
        } catch (AiException $e) {
            return $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $e->getMessage(),
                'provider_name' => $provider->name,
                'model' => $provider->model,
                'is_error' => true,
            ]);
        }

        $conversation->forceFill(['ai_provider_id' => $provider->id])->touch();

        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $reply->text,
            'provider_name' => $provider->name,
            'model' => $reply->model,
            'input_tokens' => $reply->inputTokens,
            'output_tokens' => $reply->outputTokens,
        ]);
    }

    /** Histórico no formato comum: começa pelo usuário, alterna papéis e ignora respostas com erro. */
    public function history(AiConversation $conversation): array
    {
        $limit = max(2, (int) config('services.ai.history', 20));
        $rows = $conversation->messages()->where('is_error', false)->get()->slice(-$limit);

        $out = [];
        foreach ($rows as $m) {
            $role = $m->role === 'assistant' ? 'assistant' : 'user';
            if ($out === [] && $role === 'assistant') {
                continue;
            }
            $last = array_key_last($out);
            if ($last !== null && $out[$last]['role'] === $role) {
                $out[$last]['content'] .= "\n\n".$m->content;
            } else {
                $out[] = ['role' => $role, 'content' => $m->content];
            }
        }

        return $out;
    }

    public function systemPrompt(AiConversation $conversation, AiProvider $provider): string
    {
        $parts = [
            'Você é o assistente técnico de redes do sistema '.config('app.name').'. '
            .'Ajuda a equipe de suporte de um provedor de internet (ISP) a diagnosticar e resolver problemas nos equipamentos que o provedor administra: OLTs, ONUs/ONTs, PPPoE, rádios, access points, gateways, firewalls e CPEs. Responde sempre em português do Brasil.',
            'Diretrizes:'
            ."\n- Baseie-se no fabricante, modelo e firmware do equipamento. Nunca invente comandos, parâmetros ou respostas; se não tiver certeza, diga e indique como confirmar no próprio equipamento."
            ."\n- Prefira ações só-leitura para diagnosticar. Toda ação que altera a configuração (ex.: trocar SSID, reiniciar, mudar regra) deve ser destacada como ESCRITA e só executada pelo técnico, com confirmação."
            ."\n- Depois de uma alteração, indique como conferir que ela funcionou (ex.: ler o valor de volta)."
            ."\n- Use apenas os dados do equipamento fornecidos abaixo; quando faltar algo, deixe um campo entre colchetes, ex.: [IP de gerência]."
            ."\n- Formate com Markdown quando ajudar; comandos de terminal em blocos de código."
            ."\n- O trabalho é sempre revisado pelo(a) técnico(a) responsável antes de qualquer uso.",
            'Data de hoje: '.now()->translatedFormat('l, d \d\e F \d\e Y').'.',
        ];

        if ($provider->system_prompt) {
            $parts[] = "Instruções do provedor:\n".$provider->system_prompt;
        }
        if ($conversation->device) {
            $parts[] = $this->deviceContext($conversation->device);
        }

        return implode("\n\n", $parts);
    }

    public function deviceContext(Device $device): string
    {
        $lines = ['## Equipamento vinculado a esta conversa'];
        $lines[] = $this->fields([
            'Nome' => $device->name,
            'Módulo' => $device->moduleLabel(),
            'Fabricante' => $device->manufacturer,
            'Modelo' => $device->model,
            'Firmware' => $device->firmware,
            'Sistema embarcado' => $device->os,
            'Endereço (host)' => $device->host,
            'Acesso' => $device->accessLabel().($device->port ? ' (porta '.$device->port.')' : ''),
            'Usuário' => $device->username,
            'Observações' => $device->notes,
        ]);
        $discovery = $device->discoveries()->latest()->first();
        if ($discovery) {
            $lines[] = "\n### Última descoberta (sonda só-leitura)";
            if ($discovery->manufacturer) {
                $lines[] = "- Palpite: {$discovery->manufacturer} / {$discovery->os} (confiança {$discovery->confidence}%)";
            }
            $lines[] = '- Portas abertas: ' . (implode(', ', $discovery->openPorts()) ?: 'nenhuma');
            foreach ($discovery->clues ?? [] as $key => $value) {
                $lines[] = "- {$key}: {$value}";
            }
        }

        $profile = $this->library?->profileFor($device, (string) $device->module);
        if ($profile) {
            $lines[] = "\n### Perfil da Biblioteca que atende a este equipamento";
            $lines[] = "- Perfil: {$profile->name} ({$profile->protocolLabel()})";
            $lines[] = '- Ações disponíveis (execute pela tela Comandos, com confirmação do técnico para as de escrita):';
            foreach ($profile->actions as $action) {
                $kind = $action->isWrite() ? 'ESCRITA' : 'consulta';
                $lines[] = "  - `{$action->key}` — {$action->name} [{$kind}]";
            }
        }

        $lines[] = "\n> As credenciais do equipamento não são enviadas ao provedor de IA; a senha fica protegida no sistema.";
        $lines[] = '> Você não executa comandos diretamente: sugere a ação da Biblioteca; quem roda no equipamento é o técnico, pela tela Comandos, e o sistema confere o resultado (lê de volta).';

        return implode("\n", $lines);
    }

    private function fields(array $fields): string
    {
        return collect($fields)
            ->filter(fn ($v) => $v !== null && $v !== '' && $v !== '—')
            ->map(fn ($v, $k) => '- '.$k.': '.$v)
            ->implode("\n");
    }

    public static function titleFrom(string $text): string
    {
        $title = Str::limit(trim(preg_replace('/\s+/', ' ', $text)), 70);

        return $title !== '' ? $title : 'Nova conversa';
    }
}
