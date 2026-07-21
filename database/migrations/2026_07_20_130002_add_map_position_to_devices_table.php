<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a device sits on its site's topology map once someone arranges it.
     * Empty until then; the map lays untouched devices out on a grid.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->integer('map_x')->nullable()->after('color');
            $table->integer('map_y')->nullable()->after('map_x');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['map_x', 'map_y']);
        });
    }
};
