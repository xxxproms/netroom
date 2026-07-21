<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A cable joins two ends, and either end is a device port or a wall outlet.
     * Nothing else is stored about the route: a trace is walked at read time by
     * hopping through the front/rear pairs of the panels along the way.
     */
    public function up(): void
    {
        Schema::create('cables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->morphs('a');
            $table->morphs('b');
            $table->string('media')->default('utp');
            // Fibre is pulled as one strand or two; copper leaves this empty.
            $table->unsignedTinyInteger('strands')->nullable();
            $table->string('label')->nullable();
            $table->unsignedInteger('length_cm')->nullable();
            $table->string('color', 7)->nullable();
            $table->string('status')->default('connected');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cables');
    }
};
