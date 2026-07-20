<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('number');
            $table->string('media')->default('rj45');
            $table->unsignedInteger('speed_mbps')->nullable();
            $table->string('role')->default('network');
            // A patch panel's front port is wired to one rear port; a cable
            // trace passes through the pair rather than stopping at it.
            $table->foreignId('rear_port_id')->nullable()->constrained('ports')->nullOnDelete();
            $table->boolean('is_uplink')->default(false);
            $table->boolean('enabled')->default(true);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'role', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ports');
    }
};
