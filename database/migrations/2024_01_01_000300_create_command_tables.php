<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('module', 20)->index();
            $table->string('device_type', 30);
            $table->string('manufacturer', 40)->index();
            $table->json('models');
            $table->json('firmware');
            $table->string('protocol', 20);
            $table->json('access_methods');
            $table->json('session')->nullable();
            $table->string('version', 20)->default('1.0.0');
            $table->text('description')->nullable();
            $table->string('source', 40)->default('importado');
            $table->timestamps();
        });

        Schema::create('command_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('command_profile_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('name');
            $table->string('kind', 20)->default('consulta');
            $table->text('description')->nullable();
            $table->json('params')->nullable();
            $table->json('steps');
            $table->string('parser', 60)->nullable();
            $table->json('verify')->nullable();
            $table->boolean('validated')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['command_profile_id', 'key']);
        });

        // Trilha de auditoria de toda execução no equipamento (consulta e, principalmente, escrita).
        Schema::create('command_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('profile_slug')->nullable();
            $table->string('action_key', 60)->nullable();
            $table->string('kind', 20)->default('consulta');
            $table->json('params')->nullable();
            $table->boolean('success')->default(false);
            $table->boolean('verified')->nullable();
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_executions');
        Schema::dropIfExists('command_actions');
        Schema::dropIfExists('command_profiles');
    }
};
