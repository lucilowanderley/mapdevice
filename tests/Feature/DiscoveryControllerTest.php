<?php

namespace Tests\Feature;

use App\Discovery\DeviceProber;
use App\Discovery\DiscoveryResult;
use App\Models\Device;
use App\Models\DeviceDiscovery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FakeProber;
use Tests\TestCase;

class DiscoveryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'A', 'email' => 'a@b.c', 'password' => Hash::make('x'), 'role' => 'admin', 'active' => true]);
    }

    private function device(): Device
    {
        return Device::create([
            'name' => 'Rádio', 'module' => 'ap', 'host' => '10.0.0.9', 'access_method' => 'telnet', 'active' => true,
        ]);
    }

    private function bindProber(): void
    {
        $this->app->instance(DeviceProber::class, new FakeProber(new DiscoveryResult(
            ports: ['ssh' => false, 'routeros_api' => true],
            clues: ['snmp_sysobjectid' => '1.3.6.1.4.1.14988.1'],
            manufacturer: 'MikroTik', os: 'RouterOS', accessMethod: 'api', confidence: 90,
        )));
    }

    public function test_run_saves_a_discovery(): void
    {
        $device = $this->device();
        $this->bindProber();

        $this->actingAs($this->admin())
            ->post("/equipamentos/{$device->id}/descoberta")
            ->assertRedirect(route('discovery.show', $device));

        $this->assertDatabaseHas('device_discoveries', [
            'device_id' => $device->id, 'manufacturer' => 'MikroTik', 'confidence' => 90,
        ]);
    }

    public function test_apply_updates_the_device(): void
    {
        $device = $this->device();
        $discovery = DeviceDiscovery::create([
            'device_id' => $device->id, 'manufacturer' => 'MikroTik', 'os' => 'RouterOS',
            'access_method' => 'api', 'confidence' => 90,
        ]);

        $this->actingAs($this->admin())
            ->post("/equipamentos/{$device->id}/descoberta/{$discovery->id}/aplicar")
            ->assertRedirect(route('discovery.show', $device));

        $device->refresh();
        $this->assertSame('MikroTik', $device->manufacturer);
        $this->assertSame('RouterOS', $device->os);
        $this->assertSame('api', $device->access_method);
    }
}
