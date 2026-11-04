<?php

namespace Tests\Feature;

use App\CommandLibrary\CommandLibrary;
use App\Execution\TransportFactory;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FakeTransport;
use Tests\Support\FakeTransportFactory;
use Tests\TestCase;

class CommandControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'a@b.c', 'password' => Hash::make('x'), 'role' => 'admin', 'active' => true,
        ]);
    }

    private function mikrotik(): Device
    {
        app(CommandLibrary::class)->import((string) file_get_contents(database_path('commands/mikrotik-routeros-ap.json')));

        return Device::create([
            'name' => 'AP', 'module' => 'ap', 'manufacturer' => 'MikroTik', 'model' => 'hAP',
            'host' => '10.0.0.2', 'access_method' => 'api', 'username' => 'admin', 'password' => 'p', 'active' => true,
        ]);
    }

    public function test_command_screens_render(): void
    {
        $device = $this->mikrotik();
        $admin = $this->admin();

        $this->actingAs($admin)->get('/comandos')->assertOk()->assertSee('AP');
        $this->actingAs($admin)->get("/comandos/{$device->id}")
            ->assertOk()
            ->assertSee('Trocar o SSID de uma interface')
            ->assertSee('escrita');
    }

    public function test_run_consulta_through_the_controller(): void
    {
        $device = $this->mikrotik();
        $this->app->instance(TransportFactory::class, new FakeTransportFactory(
            new FakeTransport(fn ($s) => 'name=wlan1 ssid=CasaDoLucilo')
        ));

        $this->actingAs($this->admin())
            ->post("/comandos/{$device->id}/executar", ['action' => 'wifi.ssid.get', 'params' => ['iface' => 'wlan1']])
            ->assertOk()
            ->assertSee('CasaDoLucilo');
    }

    public function test_write_without_confirmation_is_blocked(): void
    {
        $device = $this->mikrotik();
        $this->app->instance(TransportFactory::class, new FakeTransportFactory(new FakeTransport(fn ($s) => '(ok)')));

        $this->actingAs($this->admin())
            ->post("/comandos/{$device->id}/executar", ['action' => 'wifi.ssid.set', 'params' => ['iface' => 'wlan1', 'ssid' => 'Novo']])
            ->assertRedirect();

        $this->assertDatabaseCount('command_executions', 0);
    }
}
