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
        Schema::table('supply_alerts', function (Blueprint $table) {
            $table->foreignId('inventory_movement_id')
                ->nullable()
                ->after('purchase_request_id')
                ->constrained('inventory_movements')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supply_alerts', function (Blueprint $table) {
            $table->dropForeign(['inventory_movement_id']);
            $table->dropColumn('inventory_movement_id');
        });
    }
};
