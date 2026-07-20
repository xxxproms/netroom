<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('racks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('u_height')->default(42);
            $table->string('kind')->default('rack');
            $table->unsignedInteger('sort')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('racks');
    }
};
