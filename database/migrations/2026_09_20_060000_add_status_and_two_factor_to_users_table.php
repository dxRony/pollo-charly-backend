<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
            $table->boolean('two_factor_enabled')->default(false)->after('is_active');
            $table->string('two_factor_code', 10)->nullable()->after('two_factor_enabled');
            $table->timestamp('two_factor_expires_at')->nullable()->after('two_factor_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'two_factor_enabled', 'two_factor_code', 'two_factor_expires_at']);
        });
    }
};