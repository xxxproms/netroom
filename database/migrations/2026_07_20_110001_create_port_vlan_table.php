<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VLAN membership, the way a switch sees it: a port carries a VLAN either
     * tagged (trunk) or untagged (access). The untagged one is the port's PVID,
     * so a port has at most one of those.
     */
    public function up(): void
    {
        Schema::create('port_vlan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('port_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vlan_id')->constrained()->cascadeOnDelete();
            $table->string('mode')->default('tagged');
            $table->timestamps();

            $table->unique(['port_id', 'vlan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('port_vlan');
    }
};
