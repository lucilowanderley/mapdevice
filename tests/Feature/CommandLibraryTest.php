<?php

namespace Tests\Feature;

use App\CommandLibrary\CommandLibrary;
use App\CommandLibrary\CommandLibraryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_the_default_mikrotik_profile(): void
    {
        $library = app(CommandLibrary::class);
        $profile = $library->import((string) file_get_contents(database_path('commands/mikrotik-routeros-ap.json')));

        $this->assertSame('mikrotik-routeros-ap', $profile->slug);
        $this->assertSame('routeros_api', $profile->protocol);
        $this->assertNotNull($profile->action('wifi.ssid.set'));
        $this->assertTrue($profile->action('wifi.ssid.set')->isWrite());
        $this->assertSame('wifi.ssid.get', $profile->action('wifi.ssid.set')->verify['action']);
    }

    public function test_rejects_a_file_with_the_wrong_format(): void
    {
        $this->expectException(CommandLibraryException::class);
        app(CommandLibrary::class)->import(json_encode(['format' => 'outro', 'format_version' => 1]));
    }

    public function test_rejects_a_cli_step_with_a_newline(): void
    {
        $json = json_encode([
            'format' => 'mapdevice-commands', 'format_version' => 1,
            'profile' => [
                'slug' => 'teste-cli', 'name' => 'Teste', 'module' => 'gateway', 'device_type' => 'router',
                'manufacturer' => 'Generico', 'models' => ['*'], 'firmware' => ['*'], 'protocol' => 'cli', 'access_methods' => ['ssh'],
            ],
            'actions' => [
                ['key' => 'x', 'name' => 'X', 'steps' => [['send' => "um\ndois"]]],
            ],
        ]);

        $this->expectException(CommandLibraryException::class);
        app(CommandLibrary::class)->import($json);
    }
}
