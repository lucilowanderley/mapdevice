# mapdevice

Assistente de IA para **diagnóstico e gestão de equipamentos de rede** de um provedor de internet (ISP).
Sistema independente (Laravel) que ajuda a equipe de suporte a:

1. **Cadastrar os equipamentos** da rede (OLT, ONU/ONT, PPPoE, rádio, AP, gateway, firewall, CPE) com seus dados de acesso (SSH, Telnet, HTTP/HTTPS, SNMP, API/API-SSL, Console).
2. **Conversar com um assistente de IA** que lê os dados do equipamento e ajuda a diagnosticar, sugerir comandos de terminal e identificar fabricante/sistema embarcado.
3. (Próximas fases) **Executar comandos** da Biblioteca no equipamento — leitura e, com confirmação do técnico, escrita (ex.: trocar SSID) — e **conferir o resultado**; depois **redigir os arquivos** da Biblioteca de comandos e da Sondagem para o modelo.

> Ferramenta de suporte autorizada: opera sobre a frota que o provedor administra. As credenciais dos equipamentos são guardadas criptografadas e **não são enviadas ao provedor de IA**.

## Stack

- PHP 8.1+ / Laravel 10
- MySQL (ou outro banco suportado pelo Laravel)
- Camada de IA multi-provedor reaproveitada do projeto ADVOGABT (`app/Support/Ai`): Anthropic Claude (Claude 5), OpenAI, Gemini, Mistral, DeepSeek, Groq, OpenRouter, **Ollama (local, sem nuvem)** e qualquer serviço compatível com a API da OpenAI.

## Instalação

```bash
composer install
cp .env.example .env
php artisan key:generate
# configure o banco no .env e rode:
php artisan migrate --seed
php artisan serve
```

Usuário inicial criado pelo seeder: **admin@mapdevice.local** / senha **mapdevice** (troque no primeiro acesso).

## Provedores de IA

Cada instalação cadastra suas próprias conexões em **Assistente › Provedores** (somente administradores):
escolha o provedor, informe a chave de API (guardada criptografada) e o modelo, e use **Testar conexão**.
Há opções gratuitas (Ollama local) e pagas. A chave nunca é exibida depois de salva.

## Estrutura (Fase 1)

- `app/Support/Ai/` — motor de IA (Drivers, LlmClient, Assistant, AiReply, CertificateStore).
- `app/Models/` — `Device`, `AiProvider`, `AiConversation`, `AiMessage`, `User`.
- `app/Http/Controllers/` — `DeviceController`, `AssistantController`, `AiProviderController`, `DashboardController`, `Auth/LoginController`.

## Roteiro

- **Fase 1 (esta):** base do sistema, camada de IA, cadastro de equipamentos e chat do assistente.
- **Fase 2:** Biblioteca de comandos + motor de execução (SSH/Telnet/HTTP/SNMP/API) com verificação do resultado.
- **Fase 3:** descoberta de fabricante/SO e geração dos arquivos da Biblioteca e da Sondagem.
