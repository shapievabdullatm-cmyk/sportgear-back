<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->boolean('pickup_enabled')->default(true)
                ->comment('Доступен ли самовывоз в этом магазине');
            $table->unsignedSmallInteger('pickup_min_lead_minutes')->default(120)
                ->comment('Минимум минут от текущего момента до забора');
            $table->unsignedSmallInteger('pickup_slot_minutes')->default(60)
                ->comment('Длительность слота в минутах (15, 30, 60, 120…)');
            $table->unsignedSmallInteger('pickup_max_per_slot')->default(5)
                ->comment('Максимум заказов на один слот');
            $table->unsignedSmallInteger('pickup_advance_days')->default(7)
                ->comment('На сколько дней вперёд можно бронировать');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_enabled',
                'pickup_min_lead_minutes',
                'pickup_slot_minutes',
                'pickup_max_per_slot',
                'pickup_advance_days',
            ]);
        });
    }
};