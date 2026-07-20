<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vlan_domain_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 12)->unique();
            $table->string('kind')->default('complex');
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('color', 7)->nullable();
            // Where the site sits on the global map once someone drags it.
            $table->integer('map_x')->nullable();
            $table->integer('map_y')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
