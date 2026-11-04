<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_probes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('base_url');
            $table->string('login_mode', 20)->default('nenhum');
            $table->string('login_user')->nullable();
            $table->boolean('ok')->default(false);
            $table->string('message')->nullable();
            $table->unsignedInteger('pages_total')->default(0);
            $table->timestamps();
        });

        Schema::create('web_probe_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('web_probe_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('status')->default(0);
            $table->unsignedInteger('bytes')->default(0);
            $table->string('title')->nullable();
            $table->string('found_via', 20)->default('semente');
            $table->longText('body')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_probe_pages');
        Schema::dropIfExists('web_probes');
    }
};
