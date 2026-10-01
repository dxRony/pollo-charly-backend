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
        Schema::create('supplies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measurement_unit_id')->constrained('measurement_units')->restrictOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->decimal('current_stock', 12, 2)->default(0.00);
            $table->decimal('minimum_stock', 12, 2)->default(0.00);
            $table->decimal('unit_cost', 10, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplies');
    }
};
