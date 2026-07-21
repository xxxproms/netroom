<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a cable ends up in the real world: a desk, a camera mount, a till.
     * Its outlets are the sockets on the wall, which is what a cable plugs in
     * to — a workplace usually has more than one.
     */
    public function up(): void
    {
        Schema::create('workplaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('person')->nullable();
            $table->string('floor')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'name']);
        });

        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workplace_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('media')->default('rj45');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['workplace_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlets');
        Schema::dropIfExists('workplaces');
    }
};
