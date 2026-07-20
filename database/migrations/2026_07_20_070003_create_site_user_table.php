<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles decide what a user may do; this table decides where. A user with
     * `has_all_sites` skips it and sees every site.
     */
    public function up(): void
    {
        Schema::create('site_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['site_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('has_all_sites')->default(false)->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('has_all_sites');
        });

        Schema::dropIfExists('site_user');
    }
};
