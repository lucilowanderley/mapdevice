<?php

namespace Database\Seeders;

use App\CommandLibrary\CommandLibrary;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Usuário administrador inicial. Troque a senha no primeiro acesso.
        User::firstOrCreate(
            ['email' => 'admin@mapdevice.local'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('mapdevice'),
                'role' => 'admin',
                'active' => true,
            ],
        );

        // Perfis padrão da Biblioteca de comandos.
        $library = app(CommandLibrary::class);
        if (is_dir($library->defaultsDirectory())) {
            $library->importDirectory($library->defaultsDirectory(), 'padrao');
        }
    }
}
