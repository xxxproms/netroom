<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drawings that sit on a map without documenting a real thing: a zone that
     * frames the kit of one server room, floor or VLAN domain, and a note
     * pinned beside it. A null site means the drawing belongs to the global
     * map; otherwise it belongs to that site's own map.
     */
    public function up(): void
    {
        Schema::create('map_annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained('sites')->cascadeOnDelete();
            $table->string('type')->default('zone');
            $table->string('text')->nullable();
            $table->integer('map_x')->default(0);
            $table->integer('map_y')->default(0);
            $table->integer('width')->default(320);
            $table->integer('height')->default(220);
            $table->string('color', 7)->nullable();
            // Stacking order among annotations: notes float above zones.
            $table->integer('z')->default(0);
            $table->timestamps();

            $table->index(['site_id', 'z']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_annotations');
    }
};
