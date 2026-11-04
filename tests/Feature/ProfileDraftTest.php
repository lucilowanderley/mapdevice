<?php

namespace Tests\Feature;

use App\CommandLibrary\CommandLibrary;
use App\Models\Device;
use App\Models\WebProbe;
use App\Probe\ProfileDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_from_probed_pages_is_a_valid_library_profile(): void
    {
        $device = Device::create([
            'name' => 'ONT', 'module' => 'cpe', 'manufacturer' => 'Intelbras', 'model' => '121 AC',
            'host' => '10.0.0.5', 'access_method' => 'https', 'active' => true,
        ]);
        $probe = WebProbe::create(['device_id' => $device->id, 'base_url' => 'https://10.0.0.5', 'login_mode' => 'nenhum', 'ok' => true, 'pages_total' => 2]);
        $probe->pages()->create(['path' => '/status.asp', 'status' => 200, 'bytes' => 100, 'title' => 'Status', 'sort' => 0]);
        $probe->pages()->create(['path' => '/pon.asp', 'status' => 200, 'bytes' => 80, 'sort' => 1]);

        $built = ProfileDraft::build($probe);

        $this->assertSame('rest', $built['data']['profile']['protocol']);
        $this->assertSame(['https'], $built['data']['profile']['access_methods']);
        $this->assertCount(2, $built['data']['actions']);
        $this->assertSame('consulta', $built['data']['actions'][0]['kind']);
        $this->assertFalse($built['data']['actions'][0]['validated']);

        // O rascunho precisa passar na validação da Biblioteca (para poder ser importado).
        $profile = app(CommandLibrary::class)->import(ProfileDraft::json($probe), 'teste');
        $this->assertSame('Intelbras', $profile->manufacturer);
        $this->assertSame(2, $profile->actions()->count());
    }

    public function test_pages_that_did_not_respond_are_excluded(): void
    {
        $device = Device::create(['name' => 'x', 'module' => 'cpe', 'host' => '1.1.1.1', 'access_method' => 'http', 'active' => true]);
        $probe = WebProbe::create(['device_id' => $device->id, 'base_url' => 'http://1.1.1.1', 'login_mode' => 'nenhum']);
        $probe->pages()->create(['path' => '/ok', 'status' => 200, 'bytes' => 10, 'sort' => 0]);
        $probe->pages()->create(['path' => '/erro', 'status' => 500, 'bytes' => 10, 'sort' => 1]);

        $built = ProfileDraft::build($probe);
        $this->assertCount(1, $built['data']['actions']);
    }
}
