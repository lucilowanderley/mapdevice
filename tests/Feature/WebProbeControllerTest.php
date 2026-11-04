<?php

namespace Tests\Feature;

use App\Models\CommandProfile;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebProbeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'A', 'email' => 'a@b.c', 'password' => Hash::make('x'), 'role' => 'admin', 'active' => true]);
    }

    private function device(): Device
    {
        return Device::create([
            'name' => 'ONT', 'module' => 'cpe', 'manufacturer' => 'Intelbras', 'model' => '121',
            'host' => '10.0.0.5', 'access_method' => 'http', 'username' => 'admin', 'password' => 'p', 'active' => true,
        ]);
    }

    public function test_run_saves_a_probe_and_then_imports_the_draft(): void
    {
        Http::fake(['http://10.0.0.5/*' => Http::response('<html><title>Início</title>ok</html>'), 'http://10.0.0.5' => Http::response('<html><title>Início</title>ok</html>')]);
        $device = $this->device();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post("/equipamentos/{$device->id}/sondagem", ['login_mode' => 'nenhum', 'paths' => '/'])
            ->assertRedirect(route('probe.show', $device));

        $probe = $device->webProbes()->firstOrFail();
        $this->assertDatabaseHas('web_probe_pages', ['web_probe_id' => $probe->id, 'path' => '/']);

        $this->actingAs($admin)
            ->post("/equipamentos/{$device->id}/sondagem/{$probe->id}/importar")
            ->assertRedirect(route('library.index'));

        $this->assertDatabaseHas('command_profiles', ['source' => 'sondagem']);
        $this->assertGreaterThan(0, CommandProfile::where('source', 'sondagem')->first()->actions()->count());
    }
}
