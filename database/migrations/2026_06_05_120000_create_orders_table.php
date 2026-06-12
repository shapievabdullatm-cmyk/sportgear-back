<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique()->comment('Видимый клиенту номер заказа');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('status', [
                'new',
                'confirmed',
                'assembling',
                'shipped',
                'delivered',
                'cancelled',
            ])->default('new')->index();

            $table->enum('delivery_method', [
                'courier',
                'pickup',
                'cdek',
                'russian_post',
            ]);

            $table->enum('payment_method', [
                'cash',
                'card_on_delivery',
            ]);

            $table->enum('payment_status', ['pending', 'paid'])->default('pending');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('delivery_cost', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // Контактные данные snapshot
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();

            // Доставка snapshot — плоско, без таблиц-наследников
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete()
                ->comment('Магазин для самовывоза');

            $table->string('address_full', 500)->nullable();
            $table->float('address_lat')->nullable();
            $table->float('address_lon')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('street', 200)->nullable();
            $table->string('house', 20)->nullable();
            $table->string('apartment', 20)->nullable();
            $table->string('entrance', 10)->nullable();
            $table->string('floor', 10)->nullable();
            $table->string('intercom', 20)->nullable();

            $table->string('cdek_pvz_code', 50)->nullable()->comment('Код ПВЗ СДЭК');
            $table->string('russian_post_index', 10)->nullable()->comment('Индекс отделения Почты России');

            $table->text('comment')->nullable()->comment('Комментарий клиента');
            $table->text('admin_comment')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};