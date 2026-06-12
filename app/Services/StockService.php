<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Добавить товар на склад (приход)
     */
    public function addStock(
        int $productId,
        int $warehouseId,
        int $quantity,
        ?string $reason = null,
        ?string $comment = null,
        ?int $userId = null
    ): ProductStock {
        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $reason, $comment, $userId) {
            $stock = ProductStock::firstOrCreate(
                ['product_id' => $productId, 'warehouse_id' => $warehouseId],
                ['quantity' => 0, 'reserved_quantity' => 0]
            );

            $quantityBefore = $stock->quantity;
            $stock->quantity += $quantity;
            $stock->save();

            StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'type' => 'in',
                'quantity' => $quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stock->quantity,
                'reason' => $reason ?? 'Приход товара',
                'comment' => $comment,
                'user_id' => $userId,
            ]);

            return $stock->fresh();
        });
    }

    /**
     * Списать товар со склада (расход).
     * $force = true разрешает уйти в минус (для отгрузки заказов с over-reserve).
     */
    public function removeStock(
        int $productId,
        int $warehouseId,
        int $quantity,
        ?string $reason = null,
        ?string $comment = null,
        ?int $userId = null,
        bool $force = false
    ): ProductStock {
        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $reason, $comment, $userId, $force) {
            $stock = ProductStock::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->firstOrFail();

            if (!$force && $stock->available_quantity < $quantity) {
                throw new \Exception("Недостаточно товара на складе. Доступно: {$stock->available_quantity}, запрошено: {$quantity}");
            }

            $quantityBefore = $stock->quantity;
            $stock->quantity -= $quantity;
            $stock->save();

            StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'type' => 'out',
                'quantity' => -$quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stock->quantity,
                'reason' => $reason ?? 'Расход товара',
                'comment' => $comment,
                'user_id' => $userId,
            ]);

            return $stock->fresh();
        });
    }

    /**
     * Переместить товар между складами
     */
    public function transferStock(
        int $productId,
        int $fromWarehouseId,
        int $toWarehouseId,
        int $quantity,
        ?string $reason = null,
        ?string $comment = null,
        ?int $userId = null
    ): array {
        return DB::transaction(function () use ($productId, $fromWarehouseId, $toWarehouseId, $quantity, $reason, $comment, $userId) {
            // Списание с исходного склада
            $fromStock = ProductStock::where('product_id', $productId)
                ->where('warehouse_id', $fromWarehouseId)
                ->firstOrFail();

            if ($fromStock->available_quantity < $quantity) {
                throw new \Exception("Недостаточно товара на складе отправителе. Доступно: {$fromStock->available_quantity}");
            }

            $fromQuantityBefore = $fromStock->quantity;
            $fromStock->quantity -= $quantity;
            $fromStock->save();

            // Приход на целевой склад
            $toStock = ProductStock::firstOrCreate(
                ['product_id' => $productId, 'warehouse_id' => $toWarehouseId],
                ['quantity' => 0, 'reserved_quantity' => 0]
            );

            $toQuantityBefore = $toStock->quantity;
            $toStock->quantity += $quantity;
            $toStock->save();

            $transferReason = $reason ?? 'Перемещение между складами';

            // Движение списания
            StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $fromWarehouseId,
                'type' => 'transfer_out',
                'quantity' => -$quantity,
                'quantity_before' => $fromQuantityBefore,
                'quantity_after' => $fromStock->quantity,
                'related_warehouse_id' => $toWarehouseId,
                'reason' => $transferReason,
                'comment' => $comment,
                'user_id' => $userId,
            ]);

            // Движение прихода
            StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $toWarehouseId,
                'type' => 'transfer_in',
                'quantity' => $quantity,
                'quantity_before' => $toQuantityBefore,
                'quantity_after' => $toStock->quantity,
                'related_warehouse_id' => $fromWarehouseId,
                'reason' => $transferReason,
                'comment' => $comment,
                'user_id' => $userId,
            ]);

            return [
                'from' => $fromStock->fresh(),
                'to' => $toStock->fresh(),
            ];
        });
    }

    /**
     * Корректировка остатков (установить точное значение)
     */
    public function adjustStock(
        int $productId,
        int $warehouseId,
        int $newQuantity,
        ?string $reason = null,
        ?string $comment = null,
        ?int $userId = null
    ): ProductStock {
        return DB::transaction(function () use ($productId, $warehouseId, $newQuantity, $reason, $comment, $userId) {
            $stock = ProductStock::firstOrCreate(
                ['product_id' => $productId, 'warehouse_id' => $warehouseId],
                ['quantity' => 0, 'reserved_quantity' => 0]
            );

            $quantityBefore = $stock->quantity;
            $difference = $newQuantity - $quantityBefore;
            $stock->quantity = $newQuantity;
            $stock->save();

            StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'type' => 'adjustment',
                'quantity' => $difference,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stock->quantity,
                'reason' => $reason ?? 'Корректировка остатков',
                'comment' => $comment,
                'user_id' => $userId,
            ]);

            return $stock->fresh();
        });
    }

    /**
     * Зарезервировать товар.
     * $force = true разрешает уйти в минус по доступности (over-reserve).
     */
    public function reserveStock(
        int $productId,
        int $warehouseId,
        int $quantity,
        bool $force = false
    ): ProductStock {
        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $force) {
            $stock = ProductStock::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$force && $stock->available_quantity < $quantity) {
                throw new \Exception("Недостаточно доступного товара для резервирования. Доступно: {$stock->available_quantity}");
            }

            $stock->reserved_quantity += $quantity;
            $stock->save();

            return $stock->fresh();
        });
    }

    /**
     * Снять резерв товара
     */
    public function unreserveStock(
        int $productId,
        int $warehouseId,
        int $quantity
    ): ProductStock {
        return DB::transaction(function () use ($productId, $warehouseId, $quantity) {
            $stock = ProductStock::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($stock->reserved_quantity < $quantity) {
                throw new \Exception("Невозможно снять резерв. Зарезервировано: {$stock->reserved_quantity}, запрошено: {$quantity}");
            }

            $stock->reserved_quantity -= $quantity;
            $stock->save();

            return $stock->fresh();
        });
    }

    /**
     * Получить остатки товара на всех складах
     */
    public function getProductStocks(int $productId)
    {
        return ProductStock::where('product_id', $productId)
            ->with('warehouse')
            ->get();
    }

    /**
     * Получить историю движений товара
     */
    public function getStockMovements(int $productId, ?int $warehouseId = null)
    {
        $query = StockMovement::where('product_id', $productId)
            ->with(['warehouse', 'relatedWarehouse', 'user'])
            ->orderBy('created_at', 'desc');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->get();
    }
}