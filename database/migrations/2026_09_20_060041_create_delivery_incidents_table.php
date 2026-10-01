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
        Schema::create('delivery_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('receiving_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('delivery_incident_type_id')->constrained('delivery_incident_types')->restrictOnDelete();
            $table->foreignId('delivery_incident_status_id')->constrained('delivery_incident_statuses')->restrictOnDelete();
            $table->text('description');
            $table->string('evidence_path', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_incidents');
    }
};
