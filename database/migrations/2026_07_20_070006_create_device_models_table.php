<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_models', function (Blueprint $table) {
            $table->id();
            $table->string('vendor');
            $table->string('model');
            $table->string('kind')->default('switch');
            $table->unsignedSmallInteger('u_height')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['vendor', 'model']);
        });

        Schema::create('port_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_model_id')->constrained()->cascadeOnDelete();
            // A model describes its ports as ranges; creating a device expands
            // them into real rows, the way NetBox lays out device types.
            $table->string('name_prefix')->default('');
            $table->unsignedSmallInteger('start_number')->default(1);
            $table->unsignedSmallInteger('count');
            $table->string('media')->default('rj45');
            $table->unsignedInteger('speed_mbps')->nullable();
            $table->string('role')->default('network');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('port_templates');
        Schema::dropIfExists('device_models');
    }
};
