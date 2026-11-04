<?php

namespace Tests\Feature;

use App\CommandLibrary\CommandLibrary;
use App\Models\CommandProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultProfilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_default_profiles_import_and_validate(): void
    {
        $library = app(CommandLibrary::class);
        $imported = $library->importDirectory($library->defaultsDirectory(), 'padrao');

        $this->assertNotEmpty($imported);
        $this->assertSame(count(glob(database_path('commands/*.json'))), CommandProfile::count());
    }
}
