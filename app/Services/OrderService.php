<?php

namespace App\Services;

use App\Enums\Order\DeliveryMethod;
use App\Enums\Order\OrderEventType;
use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private StockService $stockService
    ) {}

    /**
     * Создать заказ из корзины пользователя.
     *
     * $data:
     *  - delivery_method (string)
     *  - payment_method (string)
     *  - customer_name, customer_phone, customer_email?
     *  - comment?
     *  - доставка (по способу): shop_id | address_* | cdek_pvz_code+address_full | russian_post_index+address_full
     */
    public function createFromCart(User $user, array $data): Order
    {
        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Корзина пуста']);
        }

        // Подготовка: для каждой позиции — продукт и выбор склада с достаточным резервом.
        $itemsPayload = [];
        $subtotal = 0.0;

        foreach ($cart->items as $cartItem) {
            $product = $cartItem->product;

            if (!$product) {
                throw ValidationException::withMessages([
                    'cart' => 'Один из товаров недоступен',
                ]);
            }

            if ($product->children()->exists()) {
                throw ValidationException::withMessages([
                    'cart' => "Товар «{$product->title}» содержит варианты — выберите конкретный",
                ]);
            }

            if ($product->price === null || $product->price <= 0) {
                throw ValidationException::withMessages([
                    'cart' => "У товара «{$product->title}» не указана цена",
                ]);
            }

            $warehouseId = $this->pickWarehouseForReserve($product->id, $cartItem->quantity);

            if (!$warehouseId) {
                throw ValidationException::withMessages([
                    'cart' => "Недостаточно товара «{$product->title}» для оформления заказа",
                ]);
            }

            $price = (float) $cartItem->price;
            $qty   = (int) $cartItem->quantity;
            $total = $price * $qty;
            $subtotal += $total;

            $itemsPayload[] = [
                'product'      => $product,
                'warehouse_id' => $warehouseId,
                'price'        => $price,
                'quantity'     => $qty,
                'total'        => $total,
                'size'         => $this->extractSize($product),
            ];
        }

        $deliveryMethod = DeliveryMethod::from($data['delivery_method']);
        $paymentMethod  = PaymentMethod::from($data['payment_method']);

        return DB::transaction(function () use ($user, $cart, $itemsPayload, $subtotal, $deliveryMethod, $paymentMethod, $data) {
            $order = new Order([
                'number'          => $this->generateNumber(),
                'user_id'         => $user->id,
                'status'          => OrderStatus::NEW,
                'delivery_method' => $deliveryMethod,
                'payment_method'  => $paymentMethod,
                'subtotal'        => $subtotal,
                'delivery_cost'   => 0,
                'total'           => $subtotal,
                'customer_name'   => $data['customer_name'],
                'customer_phone'  => $data['customer_phone'],
                'customer_email'  => $data['customer_email'] ?? null,
                'comment'         => $data['comment'] ?? null,
            ]);

            $this->applyDeliveryFields($order, $deliveryMethod, $data);

            $order->save();

            foreach ($itemsPayload as $payload) {
                /** @var Product $product */
                $product = $payload['product'];

                $order->items()->create([
                    'product_id'            => $product->id,
                    'product_title'         => $this->displayTitle($product),
                    'product_slug'          => $this->displaySlug($product),
                    'product_image'         => $this->displayImage($product),
                    'product_size'          => $payload['size'],
                    'price'                 => $payload['price'],
                    'quantity'              => $payload['quantity'],
                    'total'                 => $payload['total'],
                    'reserved_warehouse_id' => $payload['warehouse_id'],
                ]);

                $this->stockService->reserveStock(
                    $product->id,
                    $payload['warehouse_id'],
                    $payload['quantity']
                );
            }

            $cart->items()->delete();

            $this->logEvent($order, OrderEventType::CREATED, [
                'items_count' => count($itemsPayload),
                'total'       => $subtotal,
            ]);

            return $order->fresh(['items.product', 'shop', 'events']);
        });
    }

    /**
     * Отменить заказ — снимает резерв (или возвращает товар на склад, если уже списан).
     */
    public function cancel(Order $order, ?int $userId = null): Order
    {
        if (!$order->status->canCancel() && $order->status !== OrderStatus::ASSEMBLING && $order->status !== OrderStatus::SHIPPED) {
            throw ValidationException::withMessages([
                'status' => "Заказ в статусе «{$order->status->label()}» нельзя отменить",
            ]);
        }

        return DB::transaction(function () use ($order, $userId) {
            $order->load('items');

            $alreadyShipped = $order->status === OrderStatus::SHIPPED;
            $fromStatus = $order->status;

            foreach ($order->items as $item) {
                if (!$item->reserved_warehouse_id || !$item->product_id) {
                    continue;
                }

                if ($alreadyShipped) {
                    // Уже списали — возвращаем приходом
                    $this->stockService->addStock(
                        $item->product_id,
                        $item->reserved_warehouse_id,
                        $item->quantity,
                        'Возврат по отменённому заказу',
                        "Заказ {$order->number}",
                        $userId
                    );
                } else {
                    // Просто снимаем резерв
                    $this->stockService->unreserveStock(
                        $item->product_id,
                        $item->reserved_warehouse_id,
                        $item->quantity
                    );
                }
            }

            $order->status = OrderStatus::CANCELLED;
            $order->cancelled_at = now();
            $order->save();

            $this->logEvent($order, OrderEventType::STATUS_CHANGED, [
                'from' => $fromStatus->value,
                'to'   => OrderStatus::CANCELLED->value,
            ]);

            return $order->fresh(['items.product', 'events']);
        });
    }

    /**
     * Перевести заказ в новый статус (админ).
     */
    public function transitionTo(Order $order, OrderStatus $to, ?int $userId = null): Order
    {
        if ($to === OrderStatus::CANCELLED) {
            return $this->cancel($order, $userId);
        }

        $allowed = $this->allowedTransitions($order->status);

        if (!in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Недопустимый переход: {$order->status->label()} → {$to->label()}",
            ]);
        }

        $fromStatus = $order->status;

        return DB::transaction(function () use ($order, $to, $userId, $fromStatus) {
            // При отгрузке — списываем фактически и снимаем резерв
            if ($to === OrderStatus::SHIPPED) {
                $order->loadMissing('items');
                foreach ($order->items as $item) {
                    if (!$item->reserved_warehouse_id || !$item->product_id) {
                        throw ValidationException::withMessages([
                            'status' => "У позиции «{$item->product_title}» не назначен склад",
                        ]);
                    }

                    // Снять резерв и списать. Списание делаем с force=true,
                    // чтобы over-reserved позиции уехали в минус — админ это уже согласовал.
                    $this->stockService->unreserveStock(
                        $item->product_id,
                        $item->reserved_warehouse_id,
                        $item->quantity
                    );
                    $this->stockService->removeStock(
                        $item->product_id,
                        $item->reserved_warehouse_id,
                        $item->quantity,
                        'Отгрузка заказа',
                        "Заказ {$order->number}",
                        $userId,
                        true
                    );
                }
            }

            $order->status = $to;

            match ($to) {
                OrderStatus::CONFIRMED  => $order->confirmed_at = now(),
                OrderStatus::SHIPPED    => $order->shipped_at   = now(),
                OrderStatus::DELIVERED  => $order->delivered_at = now(),
                default                 => null,
            };

            $order->save();

            $this->logEvent($order, OrderEventType::STATUS_CHANGED, [
                'from' => $fromStatus->value,
                'to'   => $to->value,
            ]);

            return $order->fresh(['items.product', 'events']);
        });
    }

    /**
     * Переназначить склад резерва для позиции заказа.
     */
    public function changeItemWarehouse(\App\Models\OrderItem $item, int $newWarehouseId): \App\Models\OrderItem
    {
        $order = $item->order;

        if (in_array($order->status, [OrderStatus::SHIPPED, OrderStatus::DELIVERED, OrderStatus::CANCELLED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Нельзя менять склад после отгрузки',
            ]);
        }

        if (!$item->product_id) {
            throw ValidationException::withMessages([
                'product' => 'Товар недоступен',
            ]);
        }

        if ($item->reserved_warehouse_id === $newWarehouseId) {
            return $item;
        }

        return DB::transaction(function () use ($item, $newWarehouseId) {
            // Зарезервировать на новом — может выкинуть, тогда старый резерв не трогаем
            $this->stockService->reserveStock(
                $item->product_id,
                $newWarehouseId,
                $item->quantity
            );

            // Снять со старого
            if ($item->reserved_warehouse_id) {
                $this->stockService->unreserveStock(
                    $item->product_id,
                    $item->reserved_warehouse_id,
                    $item->quantity
                );
            }

            $item->reserved_warehouse_id = $newWarehouseId;
            $item->save();

            return $item->fresh(['reservedWarehouse']);
        });
    }

    /**
     * Добавить позицию в существующий заказ.
     * Цена — если не передана, берём текущую цену товара.
     */
    public function addItem(Order $order, int $productId, int $quantity, ?float $price = null, bool $force = false): \App\Models\OrderItem
    {
        $this->assertEditable($order);

        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Количество должно быть больше 0']);
        }

        $product = Product::find($productId);
        if (!$product) {
            throw ValidationException::withMessages(['product_id' => 'Товар не найден']);
        }
        if ($product->children()->exists()) {
            throw ValidationException::withMessages(['product_id' => 'Выберите конкретный вариант товара']);
        }

        $effectivePrice = $price ?? (float) $product->price;
        if ($effectivePrice <= 0) {
            throw ValidationException::withMessages(['price' => 'Укажите цену больше 0']);
        }

        $overReserved = false;
        $warehouseId  = $this->pickWarehouseForReserve($productId, $quantity);

        if (!$warehouseId) {
            if (!$force) {
                throw ValidationException::withMessages(['product_id' => "Недостаточно «{$product->title}» на складах"]);
            }
            // force: выбираем склад с максимальным запасом (или любой), пишем в минус
            $warehouseId  = $this->pickFallbackWarehouse($productId);
            $overReserved = true;

            if (!$warehouseId) {
                throw ValidationException::withMessages(['product_id' => "Для «{$product->title}» нет ни одного склада с остатками"]);
            }
        }

        return DB::transaction(function () use ($order, $product, $quantity, $effectivePrice, $warehouseId, $force, $overReserved) {
            $item = $order->items()->create([
                'product_id'            => $product->id,
                'product_title'         => $this->displayTitle($product),
                'product_slug'          => $this->displaySlug($product),
                'product_image'         => $this->displayImage($product),
                'product_size'          => $this->extractSize($product),
                'price'                 => $effectivePrice,
                'quantity'              => $quantity,
                'total'                 => $effectivePrice * $quantity,
                'reserved_warehouse_id' => $warehouseId,
            ]);

            $this->stockService->reserveStock($product->id, $warehouseId, $quantity, $force);
            $this->recalculateTotals($order);

            $this->logEvent($order, OrderEventType::ITEM_ADDED, [
                'item_id'       => $item->id,
                'title'         => $item->product_title,
                'size'          => $item->product_size,
                'quantity'      => $quantity,
                'price'         => $effectivePrice,
                'over_reserved' => $overReserved,
            ]);

            return $item->fresh(['reservedWarehouse', 'product.images']);
        });
    }

    /**
     * Обновить количество и/или цену позиции. Корректирует резерв на разницу.
     */
    public function updateItem(\App\Models\OrderItem $item, ?int $quantity = null, ?float $price = null, bool $force = false): \App\Models\OrderItem
    {
        $order = $item->order;
        $this->assertEditable($order);

        $newQuantity = $quantity ?? $item->quantity;
        $newPrice    = $price    ?? (float) $item->price;

        if ($newQuantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Количество должно быть больше 0']);
        }
        if ($newPrice <= 0) {
            throw ValidationException::withMessages(['price' => 'Цена должна быть больше 0']);
        }

        $prevQuantity = (int) $item->quantity;
        $prevPrice    = (float) $item->price;

        // Определяем, выйдет ли позиция в over-reserve после изменения.
        $overReserved = false;
        if ($item->reserved_warehouse_id && $item->product_id) {
            $stock = ProductStock::where('product_id', $item->product_id)
                ->where('warehouse_id', $item->reserved_warehouse_id)
                ->first();
            if ($stock) {
                // Сколько физически может удержать склад под эту позицию
                $effectiveAvailable = (int) $stock->quantity - ((int) $stock->reserved_quantity - $prevQuantity);
                if ($newQuantity > $effectiveAvailable) {
                    $overReserved = true;
                }
            }
        }

        return DB::transaction(function () use ($item, $order, $newQuantity, $newPrice, $prevQuantity, $prevPrice, $force, $overReserved) {
            $diff = $newQuantity - $item->quantity;

            if ($diff !== 0 && $item->reserved_warehouse_id && $item->product_id) {
                if ($diff > 0) {
                    $this->stockService->reserveStock(
                        $item->product_id,
                        $item->reserved_warehouse_id,
                        $diff,
                        $force
                    );
                } else {
                    $this->stockService->unreserveStock(
                        $item->product_id,
                        $item->reserved_warehouse_id,
                        -$diff
                    );
                }
            }

            $item->quantity = $newQuantity;
            $item->price    = $newPrice;
            $item->total    = $newQuantity * $newPrice;
            $item->save();

            $this->recalculateTotals($order);

            if ($prevQuantity !== $newQuantity || abs($prevPrice - $newPrice) > 0.0001) {
                $this->logEvent($order, OrderEventType::ITEM_UPDATED, [
                    'item_id'       => $item->id,
                    'title'         => $item->product_title,
                    'size'          => $item->product_size,
                    'quantity_from' => $prevQuantity,
                    'quantity_to'   => $newQuantity,
                    'price_from'    => $prevPrice,
                    'price_to'      => $newPrice,
                    'over_reserved' => $overReserved,
                ]);
            }

            return $item->fresh(['reservedWarehouse', 'product.images']);
        });
    }

    /**
     * Удалить позицию из заказа — снимает резерв.
     */
    public function removeItem(\App\Models\OrderItem $item): void
    {
        $order = $item->order;
        $this->assertEditable($order);

        $snapshot = [
            'item_id'    => $item->id,
            'product_id' => $item->product_id,
            'title'      => $item->product_title,
            'slug'       => $item->product_slug,
            'image'      => $item->product_image,
            'size'       => $item->product_size,
            'quantity'   => (int) $item->quantity,
            'price'      => (float) $item->price,
        ];

        DB::transaction(function () use ($item, $order, $snapshot) {
            if ($item->reserved_warehouse_id && $item->product_id && $item->quantity > 0) {
                $this->stockService->unreserveStock(
                    $item->product_id,
                    $item->reserved_warehouse_id,
                    $item->quantity
                );
            }
            $item->delete();
            $this->recalculateTotals($order);

            $this->logEvent($order, OrderEventType::ITEM_REMOVED, $snapshot);
        });
    }

    /**
     * Восстановить ранее удалённую позицию из события item_removed.
     */
    public function restoreItem(Order $order, OrderEvent $event): \App\Models\OrderItem
    {
        $this->assertEditable($order);

        if ($event->order_id !== $order->id) {
            throw ValidationException::withMessages(['event' => 'Событие не относится к заказу']);
        }
        if ($event->type !== OrderEventType::ITEM_REMOVED) {
            throw ValidationException::withMessages(['event' => 'Это событие не является удалением позиции']);
        }

        $data = $event->data ?? [];
        $productId = $data['product_id'] ?? null;
        $quantity  = (int) ($data['quantity'] ?? 0);
        $price     = (float) ($data['price'] ?? 0);

        // Fallback для старых событий, где product_id не сохранялся —
        // пытаемся найти товар по сохранённому title.
        if (!$productId && !empty($data['title'])) {
            $productId = $this->findProductIdByTitle($data['title'], $data['size'] ?? null);
        }

        if (!$productId) {
            throw ValidationException::withMessages([
                'event' => 'Не удалось определить товар для восстановления. Добавьте позицию вручную через «+ Добавить позицию».',
            ]);
        }
        if ($quantity <= 0 || $price <= 0) {
            throw ValidationException::withMessages(['event' => 'В снапшоте нет данных о цене или количестве']);
        }

        // Уже восстановлено?
        $already = $order->events()
            ->where('type', OrderEventType::ITEM_RESTORED->value)
            ->get()
            ->contains(fn ($e) => ($e->data['original_item_id'] ?? null) === ($data['item_id'] ?? null));
        if ($already) {
            throw ValidationException::withMessages(['event' => 'Позиция уже восстановлена']);
        }

        $product = Product::find($productId);
        if (!$product) {
            throw ValidationException::withMessages(['product' => 'Товар больше недоступен']);
        }
        if ($product->children()->exists()) {
            throw ValidationException::withMessages(['product' => 'Товар содержит варианты — выберите вариант вручную']);
        }

        $warehouseId = $this->pickWarehouseForReserve($productId, $quantity);
        if (!$warehouseId) {
            throw ValidationException::withMessages(['product' => "Недостаточно «{$product->title}» на складах"]);
        }

        return DB::transaction(function () use ($order, $product, $quantity, $price, $warehouseId, $data) {
            $item = $order->items()->create([
                'product_id'            => $product->id,
                'product_title'         => $data['title']  ?? $this->displayTitle($product),
                'product_slug'          => $data['slug']   ?? $this->displaySlug($product),
                'product_image'         => $data['image']  ?? $this->displayImage($product),
                'product_size'          => $data['size']   ?? $this->extractSize($product),
                'price'                 => $price,
                'quantity'              => $quantity,
                'total'                 => $price * $quantity,
                'reserved_warehouse_id' => $warehouseId,
            ]);

            $this->stockService->reserveStock($product->id, $warehouseId, $quantity);
            $this->recalculateTotals($order);

            $this->logEvent($order, OrderEventType::ITEM_RESTORED, [
                'original_item_id' => $data['item_id'] ?? null,
                'new_item_id'      => $item->id,
                'title'            => $item->product_title,
                'size'             => $item->product_size,
                'quantity'         => $quantity,
                'price'            => $price,
            ]);

            return $item->fresh(['reservedWarehouse', 'product.images']);
        });
    }

    // ───────────── private ─────────────

    private function recalculateTotals(Order $order): void
    {
        $subtotal = (float) $order->items()->sum('total');
        $order->subtotal = $subtotal;
        $order->total    = $subtotal + (float) $order->delivery_cost;
        $order->save();
    }

    private function assertEditable(Order $order): void
    {
        $editable = [OrderStatus::NEW, OrderStatus::CONFIRMED, OrderStatus::ASSEMBLING];
        if (!in_array($order->status, $editable, true)) {
            throw ValidationException::withMessages([
                'status' => "Заказ в статусе «{$order->status->label()}» нельзя редактировать",
            ]);
        }
    }

    private function logEvent(Order $order, OrderEventType $type, array $data = []): void
    {
        OrderEvent::create([
            'order_id'   => $order->id,
            'type'       => $type,
            'data'       => $data,
            'created_at' => now(),
        ]);
    }

    /**
     * Найти товар-вариант (без детей) по родительскому или собственному title.
     * Если совпадений несколько — отдаём первый активный.
     */
    private function findProductIdByTitle(string $title, ?string $size = null): ?int
    {
        $candidates = Product::query()
            ->where('is_active', true)
            ->whereDoesntHave('children')
            ->with(['parent', 'paramValues.param', 'paramValues.paramOption'])
            ->where(function ($q) use ($title) {
                $q->where('title', $title)
                    ->orWhereHas('parent', fn ($p) => $p->where('title', $title));
            })
            ->limit(50)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        // Если знаем размер — фильтруем
        if ($size) {
            $bySize = $candidates->first(function (Product $p) use ($size) {
                $extracted = $this->extractSize($p);
                return $extracted !== null && (string) $extracted === (string) $size;
            });
            if ($bySize) {
                return $bySize->id;
            }
        }

        return $candidates->first()->id;
    }

    /**
     * Обогащает старые item_removed события данными из текущих товаров,
     * если в снапшоте нет product_id / slug / image. Это позволяет фронту
     * показать фото и ссылку даже для давно удалённых позиций.
     */
    public function enrichRemovedEvents(Order $order): void
    {
        $order->loadMissing('events');

        $cache = [];

        foreach ($order->events as $event) {
            if ($event->type !== OrderEventType::ITEM_REMOVED) {
                continue;
            }

            $data = $event->data ?? [];
            $hasImage = !empty($data['image']);
            $hasSlug  = !empty($data['slug']);
            $hasPid   = !empty($data['product_id']);
            if ($hasImage && $hasSlug && $hasPid) {
                continue;
            }

            $title = $data['title'] ?? null;
            $size  = $data['size']  ?? null;
            if (!$title) {
                continue;
            }

            $key = $title . '|' . ($size ?? '');
            if (!array_key_exists($key, $cache)) {
                $cache[$key] = $this->findProductIdByTitle($title, $size);
            }
            $productId = $cache[$key];
            if (!$productId) {
                continue;
            }

            $product = Product::with(['images', 'parent.images'])->find($productId);
            if (!$product) {
                continue;
            }

            // Не пишем в БД — только обогащаем модель для текущего ответа
            $data['product_id'] = $data['product_id'] ?? $product->id;
            $data['slug']       = $data['slug']       ?? $this->displaySlug($product);
            $data['image']      = $data['image']      ?? $this->displayImage($product);
            $event->data = $data;
        }
    }

    /**
     * Найти склад, на котором available_quantity >= нужного. Первый подходящий.
     */
    private function pickWarehouseForReserve(int $productId, int $needed): ?int
    {
        $stocks = ProductStock::where('product_id', $productId)
            ->whereRaw('quantity - reserved_quantity >= ?', [$needed])
            ->orderByDesc(DB::raw('quantity - reserved_quantity'))
            ->get(['warehouse_id']);

        return $stocks->first()?->warehouse_id;
    }

    /**
     * Резервный выбор склада, когда подходящего по доступности нет.
     * Берём склад с наибольшим физическим запасом — туда и пишем over-reserve.
     */
    private function pickFallbackWarehouse(int $productId): ?int
    {
        return ProductStock::where('product_id', $productId)
            ->orderByDesc('quantity')
            ->value('warehouse_id');
    }

    private function applyDeliveryFields(Order $order, DeliveryMethod $method, array $data): void
    {
        match ($method) {
            DeliveryMethod::COURIER => $this->fillCourierFields($order, $data),
            DeliveryMethod::PICKUP  => $this->fillPickupFields($order, $data),
            DeliveryMethod::CDEK    => $this->fillCdekFields($order, $data),
            DeliveryMethod::RUSSIAN_POST => $this->fillRussianPostFields($order, $data),
        };
    }

    private function fillCourierFields(Order $order, array $data): void
    {
        if (empty($data['address_full'])) {
            throw ValidationException::withMessages([
                'address_full' => 'Укажите адрес доставки',
            ]);
        }

        $order->address_full = $data['address_full'];
        $order->address_lat  = $data['address_lat']  ?? null;
        $order->address_lon  = $data['address_lon']  ?? null;
        $order->city         = $data['city']         ?? null;
        $order->street       = $data['street']       ?? null;
        $order->house        = $data['house']        ?? null;
        $order->apartment    = $data['apartment']    ?? null;
        $order->entrance     = $data['entrance']     ?? null;
        $order->floor        = $data['floor']        ?? null;
        $order->intercom     = $data['intercom']     ?? null;
    }

    private function fillPickupFields(Order $order, array $data): void
    {
        if (empty($data['shop_id'])) {
            throw ValidationException::withMessages([
                'shop_id' => 'Выберите магазин для самовывоза',
            ]);
        }

        $shop = \App\Models\Shop::find($data['shop_id']);
        if (!$shop || !$shop->is_active) {
            throw ValidationException::withMessages([
                'shop_id' => 'Магазин недоступен',
            ]);
        }
        if (!$shop->pickup_enabled) {
            throw ValidationException::withMessages([
                'shop_id' => 'В этом магазине самовывоз отключён',
            ]);
        }

        if (empty($data['pickup_slot_at'])) {
            throw ValidationException::withMessages([
                'pickup_slot_at' => 'Выберите время самовывоза',
            ]);
        }

        $slot = \Carbon\Carbon::parse($data['pickup_slot_at']);

        $minLead = (int) ($shop->pickup_min_lead_minutes ?? 120);
        $advance = (int) ($shop->pickup_advance_days ?? 7);
        $slotMin = (int) ($shop->pickup_slot_minutes ?? 60);
        $maxPer  = (int) ($shop->pickup_max_per_slot ?? 5);

        if ($slot->lessThan(now()->addMinutes($minLead))) {
            throw ValidationException::withMessages([
                'pickup_slot_at' => "Заказ нужно забронировать минимум за {$minLead} минут до забора",
            ]);
        }
        if ($slot->greaterThan(now()->addDays($advance))) {
            throw ValidationException::withMessages([
                'pickup_slot_at' => "Самовывоз можно запланировать максимум на {$advance} дней вперёд",
            ]);
        }

        // Слот должен быть выровнен по сетке (start-of-day + N * slotMinutes)
        $startOfDay = $slot->copy()->startOfDay();
        $offsetMinutes = $slot->diffInMinutes($startOfDay);
        if ($offsetMinutes % $slotMin !== 0) {
            throw ValidationException::withMessages([
                'pickup_slot_at' => 'Выбранное время не соответствует слоту магазина',
            ]);
        }

        // Проверка занятости слота
        $booked = \App\Models\Order::query()
            ->where('shop_id', $shop->id)
            ->where('pickup_slot_at', $slot)
            ->whereNotIn('status', ['cancelled'])
            ->count();
        if ($booked >= $maxPer) {
            throw ValidationException::withMessages([
                'pickup_slot_at' => 'Этот слот уже занят, выберите другой',
            ]);
        }

        $order->shop_id        = $shop->id;
        $order->pickup_slot_at = $slot;
    }

    private function fillCdekFields(Order $order, array $data): void
    {
        if (empty($data['cdek_pvz_code'])) {
            throw ValidationException::withMessages([
                'cdek_pvz_code' => 'Укажите код пункта выдачи СДЭК',
            ]);
        }
        $order->cdek_pvz_code = $data['cdek_pvz_code'];
        $order->address_full  = $data['address_full'] ?? null;
        $order->city          = $data['city']         ?? null;
    }

    private function fillRussianPostFields(Order $order, array $data): void
    {
        if (empty($data['russian_post_index'])) {
            throw ValidationException::withMessages([
                'russian_post_index' => 'Укажите индекс отделения Почты России',
            ]);
        }
        $order->russian_post_index = $data['russian_post_index'];
        $order->address_full       = $data['address_full'] ?? null;
        $order->city               = $data['city']         ?? null;
    }

    private function generateNumber(): string
    {
        $date = now()->format('ymd');

        for ($i = 0; $i < 10; $i++) {
            $suffix = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $number = "R-{$date}-{$suffix}";

            if (!Order::where('number', $number)->exists()) {
                return $number;
            }
        }

        return 'R-' . $date . '-' . uniqid();
    }

    private function allowedTransitions(OrderStatus $from): array
    {
        return match ($from) {
            OrderStatus::NEW        => [OrderStatus::CONFIRMED, OrderStatus::CANCELLED],
            OrderStatus::CONFIRMED  => [OrderStatus::ASSEMBLING, OrderStatus::CANCELLED],
            OrderStatus::ASSEMBLING => [OrderStatus::PACKED, OrderStatus::CANCELLED],
            OrderStatus::PACKED     => [OrderStatus::SHIPPED, OrderStatus::ASSEMBLING, OrderStatus::CANCELLED],
            OrderStatus::SHIPPED    => [OrderStatus::DELIVERED, OrderStatus::CANCELLED],
            default                 => [],
        };
    }

    private function extractSize(Product $product): ?string
    {
        if (!$product->parent_id) {
            return null;
        }

        $product->loadMissing([
            'paramValues.param',
            'paramValues.paramOption',
            'optionValues.param',
            'optionValues.paramOption',
        ]);

        foreach ($product->paramValues as $pv) {
            if ($pv->param && $pv->param->is_size) {
                $val = $pv->value_string
                    ?? $pv->value_text
                    ?? $pv->value_int
                    ?? $pv->value_float
                    ?? $pv->paramOption?->value;
                if ($val !== null && $val !== '') {
                    return (string) $val;
                }
            }
        }

        foreach ($product->optionValues as $ov) {
            if ($ov->param && $ov->param->is_size && $ov->paramOption) {
                $val = $ov->paramOption->value;
                if ($val !== null && $val !== '') {
                    return (string) $val;
                }
            }
        }

        return null;
    }

    private function displayTitle(Product $product): string
    {
        if ($product->parent_id) {
            $product->loadMissing('parent');
            return $product->parent?->title ?? $product->title;
        }
        return $product->title;
    }

    private function displaySlug(Product $product): ?string
    {
        if ($product->parent_id) {
            $product->loadMissing('parent');
            return $product->parent?->slug ?? $product->slug;
        }
        return $product->slug;
    }

    private function displayImage(Product $product): ?string
    {
        $product->loadMissing(['images', 'parent.images']);

        if ($product->parent_id) {
            return $product->parent?->images->first()?->url
                ?? $product->images->first()?->url;
        }

        return $product->images->first()?->url;
    }
}