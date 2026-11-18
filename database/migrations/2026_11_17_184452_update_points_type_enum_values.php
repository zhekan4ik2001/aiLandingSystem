<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Для MySQL изменение enum
        DB::statement("ALTER TABLE points MODIFY COLUMN type ENUM('input', 'intermediate', 'final', 'runway', 'output') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Возвращаем обратно
        DB::statement("ALTER TABLE points MODIFY COLUMN type ENUM('input', 'intermediate', 'final') NOT NULL");
    }
};
