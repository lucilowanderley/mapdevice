<?php

namespace Tests\Feature;

use App\CommandLibrary\CommandLibrary;
use App\Execution\CommandRunner;
use App\Execution\ExecutionException;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeTransport;
use Tests\Support\FakeTransportFactory;
use Tests\TestCase;

class CommandRunnerTest extends TestCase
{
    use RefreshDatabase;

    private function profile(): \App\Models\CommandProfile
    {
        return app(CommandLibrary::class)->import((string) file_get_contents(
            database_path('commands/mikrotik-routeros-ap.json')
        ), 'teste');
    }

    private function device(): Device
    {
        return Device::create([
            'name' => 'AP Torre', 'module' => 'ap', 'manufacturer' => 'MikroTik',
            'model' => 'hAP ac2', 'host' => '10.0.0.2', 'access_method' => 'api',
            'username' => 'admin', 'password' => 'secret', 'active' => true,
        ]);
    }

    private function runner(FakeTransport $transport): CommandRunner
    {
        return new CommandRunner(new FakeTransportFactory($transport));
    }

    public function test_consulta_runs_and_is_audited(): void
    {
        $profile = $this->profile();
        $device = $this->device();
        $transport = new FakeTransport(fn (array $s) => 'name=wlan1 ssid=CasaDoLucilo');

        $result = $this->runner($transport)->run($device, $profile, $profile->action('wifi.ssid.get'), ['iface' => 'wlan1']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('CasaDoLucilo', $result->output());
        $this->assertTrue($transport->connected === false, 'deve desconectar ao final');
        $this->assertDatabaseHas('command_executions', [
            'device_id' => $device->id, 'action_key' => 'wifi.ssid.get', 'kind' => 'consulta', 'success' => true,
        ]);
    }

    public function test_write_requires_confirmation(): void
    {
        $profile = $this->profile();
        $device = $this->device();
        $transport = new FakeTransport(fn (array $s) => '(ok)');

        $this->expectException(ExecutionException::class);
        $this->runner($transport)->run($device, $profile, $profile->action('wifi.ssid.set'), ['iface' => 'wlan1', 'ssid' => 'NovoNome']);
    }

    public function test_write_with_confirmation_verifies_by_reading_back(): void
    {
        $profile = $this->profile();
        $device = $this->device();
        // A escrita devolve (ok); a leitura de verificação devolve o SSID novo.
        $transport = new FakeTransport(function (array $step) {
            return isset($step['args']['=ssid']) ? '(ok)' : 'name=wlan1 ssid=NovoNome';
        });

        $result = $this->runner($transport)->run(
            $device, $profile, $profile->action('wifi.ssid.set'),
            ['iface' => 'wlan1', 'ssid' => 'NovoNome'], confirmWrite: true,
        );

        $this->assertTrue($result->success);
        $this->assertTrue($result->verified, 'a leitura de volta deve confirmar o SSID novo');
        $this->assertSame('NovoNome', $result->expected);
        $this->assertDatabaseHas('command_executions', [
            'action_key' => 'wifi.ssid.set', 'kind' => 'configuracao', 'success' => true, 'verified' => true,
        ]);
    }

    public function test_write_verification_fails_when_value_absent(): void
    {
        $profile = $this->profile();
        $device = $this->device();
        // A leitura de verificação devolve um SSID diferente: a verificação não confere.
        $transport = new FakeTransport(function (array $step) {
            return isset($step['args']['=ssid']) ? '(ok)' : 'name=wlan1 ssid=OutroNome';
        });

        $result = $this->runner($transport)->run(
            $device, $profile, $profile->action('wifi.ssid.set'),
            ['iface' => 'wlan1', 'ssid' => 'NovoNome'], confirmWrite: true,
        );

        $this->assertTrue($result->success);
        $this->assertFalse($result->verified);
    }
}
