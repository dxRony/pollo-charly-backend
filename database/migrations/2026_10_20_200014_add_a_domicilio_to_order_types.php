<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        DB::table('order_types')->updateOrInsert(
            ['name' => 'a_domicilio'],
            ['created_at' => $now, 'updated_at' => $now]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('order_types')->where('name', 'a_domicilio')->delete();
    }
};
