<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An IP subnet, tied to a VLAN plan the way the rest of the network is: two
     * neighbouring complexes on one plan share one 10.40.0.0/24, so a subnet
     * belongs to a VLAN domain rather than a single site.
     */
    public function up(): void
    {
        Schema::create('subnets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vlan_domain_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vlan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cidr', 43);
            // Stored alongside the text so containment queries are plain integers.
            $table->unsignedBigInteger('network');
            $table->unsignedBigInteger('broadcast');
            $table->string('name')->nullable();
            $table->string('gateway', 45)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['vlan_domain_id', 'cidr']);
            $table->index(['network', 'broadcast']);
        });

        Schema::create('ip_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subnet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('address');
            $table->string('address_text', 45);
            // A documented address often belongs to a device the panel knows.
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hostname')->nullable();
            $table->string('status')->default('reserved');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['subnet_id', 'address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_addresses');
        Schema::dropIfExists('subnets');
    }
};
