<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Расширяем CHECK-ограничение enum статусов: добавляем 'packed'.
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check");
        DB::statement("
            ALTER TABLE orders ADD CONSTRAINT orders_status_check
            CHECK (status IN ('new','confirmed','assembling','packed','shipped','delivered','cancelled'))
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check");
        DB::statement("
            ALTER TABLE orders ADD CONSTRAINT orders_status_check
            CHECK (status IN ('new','confirmed','assembling','shipped','delivered','cancelled'))
        ");
    }
};