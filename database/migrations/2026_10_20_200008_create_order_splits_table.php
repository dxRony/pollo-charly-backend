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
        Schema::create('order_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->unsignedSmallInteger('split_number');
            $table->string('split_type'); // 'equitativo', 'por_items'
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('status')->default('pendiente'); // 'pendiente', 'pagado', 'cancelado'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_splits');
    }
};
