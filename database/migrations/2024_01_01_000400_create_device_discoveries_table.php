<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_discoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('ports')->nullable();
            $table->json('clues')->nullable();
            $table->string('manufacturer', 40)->nullable();
            $table->string('os', 60)->nullable();
            $table->string('access_method', 20)->nullable();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->json('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_discoveries');
    }
};
