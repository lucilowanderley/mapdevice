<?php

namespace App\Execution;

use App\Models\CommandAction;
use App\Models\CommandExecution;
use App\Models\CommandProfile;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Executa uma ação da biblioteca no equipamento.
 *
 * Regras de segurança:
 *  - Uma ação de configuração (escrita) só roda com confirmação explícita do técnico ($confirmWrite = true).
 *  - Depois de uma escrita com "verify", roda a ação de consulta indicada e confere que o valor novo está lá
 *    (é assim que se afere que o comando funcionou — ex.: trocar o SSID e ler de volta).
 *  - Toda execução fica registrada em command_executions (auditoria).
 */
class CommandRunner
{
    public function __construct(private readonly TransportFactory $factory) {}

    /**
     * @throws \App\CommandLibrary\CommandLibraryException parâmetros inválidos
     * @throws ExecutionException confirmação de escrita ausente
     */
    public function run(Device $device, CommandProfile $profile, CommandAction $action, array $params = [], bool $confirmWrite = false, ?User $user = null): ExecutionResult
    {
        if ($action->isWrite() && ! $confirmWrite) {
            throw new ExecutionException('Esta é uma ação de configuração (escrita). Confirme para executar no equipamento.');
        }

        // Renderiza antes de conectar: valida os parâmetros (pode lançar CommandLibraryException).
        $steps = $action->render($params);

        try {
            $result = $this->execute($device, $profile, $steps);
        } catch (ExecutionException $e) {
            $this->audit($device, $profile, $action, $params, false, null, null, $e->getMessage(), $user);

            return ExecutionResult::failed($e->getMessage());
        }

        $verified = null;
        $verifyOutput = null;
        $expected = null;

        if ($action->isWrite() && ! empty($action->verify['action'])) {
            [$verified, $verifyOutput, $expected] = $this->verify($device, $profile, $action, $params);
        }

        $this->audit($device, $profile, $action, $params, true, $verified, $result->output(), null, $user);

        return new ExecutionResult(true, $result->steps, null, $verified, $verifyOutput, $expected);
    }

    /** Conecta, roda a sessão de abertura, os passos e a sessão de encerramento, e devolve as saídas. */
    private function execute(Device $device, CommandProfile $profile, array $steps): ExecutionResult
    {
        $transport = $this->factory->for($profile, $device);
        $timeout = (int) config('mapdevice.execution.timeout', 20);
        $outcomes = [];
        try {
            $transport->connect($device, $profile->session, $timeout);
            foreach ($steps as $step) {
                $outcomes[] = ['command' => CommandAction::describeStep($step), 'output' => $transport->execute($step)];
            }
            foreach ($profile->session['logout'] ?? [] as $step) {
                $transport->execute($step);
            }
        } finally {
            $transport->disconnect();
        }

        return new ExecutionResult(true, $outcomes);
    }

    /** Roda a ação de consulta de verificação e confere que a saída contém o valor esperado. */
    private function verify(Device $device, CommandProfile $profile, CommandAction $action, array $params): array
    {
        $verifyAction = $profile->action($action->verify['action']);
        if (! $verifyAction || $verifyAction->isWrite()) {
            return [null, null, null];
        }
        $expected = $action->expectedValue($params);
        try {
            $result = $this->execute($device, $profile, $verifyAction->render($params));
        } catch (\Throwable $e) {
            Log::warning('Verificação de leitura falhou: '.$e->getMessage());

            return [false, null, $expected];
        }
        $output = $result->output();
        $verified = $expected === null ? null : str_contains($output, $expected);

        return [$verified, $output, $expected];
    }

    private function audit(Device $device, CommandProfile $profile, CommandAction $action, array $params, bool $success, ?bool $verified, ?string $output, ?string $error, ?User $user): void
    {
        CommandExecution::create([
            'user_id' => $user?->id,
            'device_id' => $device->id,
            'profile_slug' => $profile->slug,
            'action_key' => $action->key,
            'kind' => $action->kind,
            'params' => $this->maskParams($action, $params),
            'success' => $success,
            'verified' => $verified,
            'output' => $output ? mb_substr($output, 0, 20000) : null,
            'error' => $error,
        ]);
    }

    /** Não grava na auditoria o valor de parâmetros sensíveis (senha, chave). */
    private function maskParams(CommandAction $action, array $params): array
    {
        $sensitive = [];
        foreach ($action->params ?? [] as $p) {
            if (! empty($p['secret'])) {
                $sensitive[] = $p['name'];
            }
        }

        return collect($params)->map(fn ($v, $k) => in_array($k, $sensitive, true) ? '••••' : $v)->all();
    }
}
