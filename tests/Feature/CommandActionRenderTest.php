<?php

namespace Tests\Feature;

use App\CommandLibrary\CommandLibraryException;
use App\Models\CommandAction;
use App\Models\CommandProfile;
use Tests\TestCase;

class CommandActionRenderTest extends TestCase
{
    public function test_render_substitutes_validated_params(): void
    {
        $action = new CommandAction([
            'key' => 'wifi.ssid.set', 'name' => 'SSID', 'kind' => 'configuracao',
            'params' => [['name' => 'ssid', 'pattern' => '^[A-Za-z0-9 ._-]{1,32}$']],
            'steps' => [['command' => '/interface/wireless/set', 'args' => ['=ssid' => '{ssid}']]],
            'verify' => ['action' => 'wifi.ssid.get', 'contains' => '{ssid}'],
        ]);

        $steps = $action->render(['ssid' => 'Casa 2']);

        $this->assertSame('Casa 2', $steps[0]['args']['=ssid']);
        $this->assertSame('Casa 2', $action->expectedValue(['ssid' => 'Casa 2']));
    }

    public function test_render_rejects_a_value_that_breaks_the_pattern(): void
    {
        $action = new CommandAction([
            'key' => 'x', 'name' => 'X',
            'params' => [['name' => 'iface', 'pattern' => '^[A-Za-z0-9_-]{1,16}$']],
            'steps' => [['send' => 'print {iface}']],
        ]);

        $this->expectException(CommandLibraryException::class);
        $action->render(['iface' => 'wlan1; reboot']);
    }

    public function test_render_requires_mandatory_params(): void
    {
        $action = new CommandAction([
            'key' => 'x', 'name' => 'X',
            'params' => [['name' => 'ssid', 'required' => true]],
            'steps' => [['send' => 'set {ssid}']],
        ]);

        $this->expectException(CommandLibraryException::class);
        $action->render([]);
    }

    public function test_profile_score_matches_manufacturer_and_access(): void
    {
        $profile = new CommandProfile([
            'manufacturer' => 'MikroTik', 'models' => ['*'], 'firmware' => ['*'], 'access_methods' => ['api', 'api_ssl'],
        ]);

        $this->assertNotNull($profile->score('mikrotik', 'hAP ac2', '7.15', 'api'));
        $this->assertNull($profile->score('Ubiquiti', 'NanoStation', null, 'api'), 'fabricante diferente não casa');
        $this->assertNull($profile->score('MikroTik', null, null, 'ssh'), 'método de acesso fora da lista não casa');
    }
}
