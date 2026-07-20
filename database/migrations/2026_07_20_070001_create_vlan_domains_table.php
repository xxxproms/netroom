<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A VLAN domain groups sites that share one switched network, and with it
     * one VLAN plan. Most sites are a domain of their own; neighbouring sites
     * joined by a direct link belong to the same one.
     */
    public function up(): void
    {
        Schema::create('vlan_domains', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vlan_domains');
    }
};
