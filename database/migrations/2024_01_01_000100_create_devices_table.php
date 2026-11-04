<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Equipamentos da rede gerenciados pelo provedor.
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('module', 20)->nullable(); // ftth, pppoe, cpe, ap, gateway, firewall
            $table->string('manufacturer', 60)->nullable();
            $table->string('model', 80)->nullable();
            $table->string('firmware', 60)->nullable();
            $table->string('os', 80)->nullable(); // sistema embarcado detectado
            $table->string('host')->nullable(); // IP ou hostname de gerência
            $table->string('access_method', 20)->default('ssh'); // ssh, telnet, http, https, snmp, api, api_ssl, console
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('username', 120)->nullable();
            $table->text('password')->nullable();        // criptografada (APP_KEY)
            $table->text('snmp_community')->nullable();   // criptografada (APP_KEY)
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['manufacturer', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
