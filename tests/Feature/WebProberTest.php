<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Probe\WebProber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebProberTest extends TestCase
{
    use RefreshDatabase;

    private function device(): Device
    {
        return Device::create([
            'name' => 'ONT', 'module' => 'cpe', 'host' => '10.0.0.5', 'access_method' => 'http',
            'username' => 'admin', 'password' => 'p', 'active' => true,
        ]);
    }

    public function test_probe_fetches_pages_crawls_links_and_masks_secrets(): void
    {
        Http::fake([
            'http://10.0.0.5/' => Http::response('<html><title>Início</title><input name="wlanpassword" value="supersecret"><a href="/status.cgi">status</a></html>'),
            'http://10.0.0.5/status.cgi' => Http::response('<html><title>Status</title>ok</html>'),
        ]);

        $probe = (new WebProber)->probe($this->device(), 'nenhum', ['paths' => ['/'], 'crawl' => true]);

        $this->assertTrue($probe->ok);
        $this->assertSame(2, $probe->pages_total);

        $home = $probe->pages->firstWhere('path', '/');
        $this->assertStringContainsString('***', $home->body);
        $this->assertStringNotContainsString('supersecret', $home->body);
        $this->assertSame('Início', $home->title);

        $status = $probe->pages->firstWhere('path', '/status.cgi');
        $this->assertNotNull($status, 'o crawl deve ter seguido o link');
        $this->assertSame('link', $status->found_via);
    }

    public function test_probe_marks_failure_when_nothing_responds(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $probe = (new WebProber)->probe($this->device(), 'nenhum', ['paths' => ['/']]);

        $this->assertFalse($probe->ok);
        $this->assertNotNull($probe->message);
    }
}
