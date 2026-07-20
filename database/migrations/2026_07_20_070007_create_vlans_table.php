<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vlans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vlan_domain_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('vid');
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('color', 7)->nullable();
            $table->timestamps();

            // The same VID means different things in different domains, so it
            // is only unique within one.
            $table->unique(['vlan_domain_id', 'vid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vlans');
    }
};
