<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_model_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            // A device is usually mounted in a rack, but not always: small
            // sites often have a switch sitting on a shelf.
            $table->foreignId('rack_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('position_u')->nullable();
            $table->string('face')->default('front');
            $table->string('name');
            $table->string('serial')->nullable();
            $table->string('mgmt_ip', 45)->nullable();
            $table->string('mgmt_url')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'name']);
            $table->index(['rack_id', 'position_u']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
