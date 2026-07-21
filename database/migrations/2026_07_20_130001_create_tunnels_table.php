<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How two sites reach each other across the internet: a VPN tunnel through
     * a Kerio Control, or an IPsec tunnel where a MikroTik terminates it. This
     * is the connectivity the spreadsheet never recorded.
     */
    public function up(): void
    {
        Schema::create('tunnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_a_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('site_b_id')->constrained('sites')->cascadeOnDelete();
            // Which device terminates the tunnel at either end, when it is known.
            $table->foreignId('device_a_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('device_b_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('type')->default('kerio_vpn');
            $table->string('status')->default('up');
            $table->string('label')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tunnels');
    }
};
