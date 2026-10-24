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
        Schema::create('driver_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_driver_id')->constrained('delivery_drivers')->cascadeOnDelete();
            $table->foreignId('cashier_user_id')->constrained('users');
            $table->date('settlement_date');
            $table->unsignedInteger('total_orders_delivered')->default(0);
            $table->decimal('total_cash_expected', 10, 2)->default(0);
            $table->decimal('total_cash_received', 10, 2)->default(0);
            $table->decimal('difference', 10, 2)->default(0);
            $table->string('status')->default('cuadrado'); // 'cuadrado', 'faltante', 'sobrante'
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_settlements');
    }
};
