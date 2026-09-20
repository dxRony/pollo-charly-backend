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
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('cash_movement_type_id')->constrained('cash_movement_types')->restrictOnDelete();
            $table->foreignId('cash_movement_category_id')->constrained('cash_movement_categories')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('concept', 255);
            $table->text('justification')->nullable();
            $table->string('evidence_path', 255)->nullable();
            $table->date('date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
