<?php

namespace Tests\Feature;

use App\Discovery\Fingerprints;
use Tests\TestCase;

class FingerprintsTest extends TestCase
{
    public function test_sysobjectid_identifies_the_manufacturer(): void
    {
        $guess = (new Fingerprints)->match(['snmp_sysobjectid' => '1.3.6.1.4.1.14988.1']);

        $this->assertSame('MikroTik', $guess['manufacturer']);
        $this->assertSame('RouterOS', $guess['os']);
        $this->assertSame('api', $guess['access']);
        $this->assertSame(90, $guess['confidence']);
    }

    public function test_text_clue_identifies_with_lower_confidence(): void
    {
        $guess = (new Fingerprints)->match(['http_title' => 'RouterOS router configuration page']);

        $this->assertSame('MikroTik', $guess['manufacturer']);
        $this->assertLessThan(90, $guess['confidence']);
        $this->assertGreaterThan(0, $guess['confidence']);
    }

    public function test_airos_server_header_identifies_ubiquiti(): void
    {
        $guess = (new Fingerprints)->match(['http_server' => 'Server: airOS']);
        $this->assertSame('Ubiquiti', $guess['manufacturer']);
    }

    public function test_no_clues_yields_no_guess(): void
    {
        $guess = (new Fingerprints)->match([]);
        $this->assertNull($guess['manufacturer']);
        $this->assertSame(0, $guess['confidence']);
    }
}
