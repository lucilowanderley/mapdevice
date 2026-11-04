<?php

namespace Tests\Feature;

use App\Models\CommandProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CommandProfileImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'A', 'email' => 'a@b.c', 'password' => Hash::make('x'), 'role' => 'admin', 'active' => true]);
    }

    public function test_admin_imports_a_profile_json(): void
    {
        $json = (string) file_get_contents(database_path('commands/mikrotik-routeros-ap.json'));

        $this->actingAs($this->admin())
            ->post('/biblioteca/importar', ['json' => $json])
            ->assertRedirect(route('library.index'));

        $this->assertDatabaseHas('command_profiles', ['slug' => 'mikrotik-routeros-ap', 'source' => 'importado']);
        $this->assertSame(4, CommandProfile::first()->actions()->count());
    }

    public function test_invalid_json_is_rejected_with_an_error(): void
    {
        $this->actingAs($this->admin())
            ->post('/biblioteca/importar', ['json' => '{"format":"errado"}'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('command_profiles', 0);
    }

    public function test_tecnico_cannot_import(): void
    {
        $tecnico = User::create(['name' => 'T', 'email' => 't@b.c', 'password' => Hash::make('x'), 'role' => 'tecnico', 'active' => true]);

        $this->actingAs($tecnico)
            ->post('/biblioteca/importar', ['json' => '{}'])
            ->assertForbidden();
    }
}
