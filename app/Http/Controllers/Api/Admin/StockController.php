<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductStock;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    public function __construct(
        private StockService $stockService
    ) {}

    /**
     * Получить остатки товара на всех складах
     */
    public function index(Request $request)
    {
        $query = ProductStock::with(['product', 'warehouse']);

        // Поиск по товару (название, артикул, ID, штрихкод)
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('product', function($q) use ($search) {
                // Используем translate для преобразования кириллицы и латиницы в нижний регистр
                $upper = 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $lower = 'абвгдеёжзийклмнопрстуфхцчшщъыьэюяabcdefghijklmnopqrstuvwxyz';

                $q->where(function($sq) use ($search, $upper, $lower) {
                    // Поиск по самому товару
                    $sq->whereRaw("translate(title, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"])
                       ->orWhereRaw("translate(external_title, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"])
                       ->orWhereRaw("translate(article, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"]);

                    if (is_numeric($search)) {
                        $sq->orWhere('id', $search);
                    }

                    // Поиск по штрихкодам товара
                    $sq->orWhereHas('barcodes', function($bq) use ($search, $upper, $lower) {
                        $bq->whereRaw("translate(barcode, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"]);
                    });

                    // Поиск по родителю (если это дочерний товар)
                    $sq->orWhereHas('parent', function($pq) use ($search, $upper, $lower) {
                        $pq->whereRaw("translate(title, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"])
                           ->orWhereRaw("translate(external_title, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"])
                           ->orWhereRaw("translate(article, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"]);
                    });
                });
            });
        }

        // Фильтр по товару
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Фильтр по складу
        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Фильтр по наличию товара
        if ($request->has('in_stock') && $request->in_stock) {
            $query->where('quantity', '>', 0);
        }

        return $query->paginate($request->per_page ?? 50);
    }

    /**
     * Получить остатки конкретного товара
     */
    public function show(int $productId)
    {
        $stocks = $this->stockService->getProductStocks($productId);

        return response()->json([
            'stocks' => $stocks,
            'total_quantity' => $stocks->sum('quantity'),
            'total_reserved' => $stocks->sum('reserved_quantity'),
            'total_available' => $stocks->sum(fn($s) => $s->available_quantity),
        ]);
    }

    /**
     * Добавить товар на склад (приход)
     */
    public function addStock(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:1000',
        ]);

        try {
            $stock = $this->stockService->addStock(
                $validated['product_id'],
                $validated['warehouse_id'],
                $validated['quantity'],
                $validated['reason'] ?? null,
                $validated['comment'] ?? null,
                $request->user()->id
            );

            return response()->json([
                'message' => 'Товар успешно добавлен на склад',
                'stock' => $stock->load(['product', 'warehouse']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Списать товар со склада (расход)
     */
    public function removeStock(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:1000',
        ]);

        try {
            $stock = $this->stockService->removeStock(
                $validated['product_id'],
                $validated['warehouse_id'],
                $validated['quantity'],
                $validated['reason'] ?? null,
                $validated['comment'] ?? null,
                $request->user()->id
            );

            return response()->json([
                'message' => 'Товар успешно списан со склада',
                'stock' => $stock->load(['product', 'warehouse']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Переместить товар между складами
     */
    public function transferStock(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => [
                'required',
                'exists:warehouses,id',
                'different:from_warehouse_id'
            ],
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:1000',
        ]);

        try {
            $result = $this->stockService->transferStock(
                $validated['product_id'],
                $validated['from_warehouse_id'],
                $validated['to_warehouse_id'],
                $validated['quantity'],
                $validated['reason'] ?? null,
                $validated['comment'] ?? null,
                $request->user()->id
            );

            return response()->json([
                'message' => 'Товар успешно перемещен между складами',
                'from_stock' => $result['from']->load(['product', 'warehouse']),
                'to_stock' => $result['to']->load(['product', 'warehouse']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Корректировка остатков
     */
    public function adjustStock(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:1000',
        ]);

        try {
            $stock = $this->stockService->adjustStock(
                $validated['product_id'],
                $validated['warehouse_id'],
                $validated['quantity'],
                $validated['reason'] ?? null,
                $validated['comment'] ?? null,
                $request->user()->id
            );

            return response()->json([
                'message' => 'Остатки успешно скорректированы',
                'stock' => $stock->load(['product', 'warehouse']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Получить историю движений товара
     */
    public function movements(Request $request, int $productId)
    {
        $validated = $request->validate([
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $movements = $this->stockService->getStockMovements(
            $productId,
            $validated['warehouse_id'] ?? null
        );

        return response()->json($movements);
    }

    /**
     * Зарезервировать товар
     */
    public function reserve(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            $stock = $this->stockService->reserveStock(
                $validated['product_id'],
                $validated['warehouse_id'],
                $validated['quantity']
            );

            return response()->json([
                'message' => 'Товар успешно зарезервирован',
                'stock' => $stock->load(['product', 'warehouse']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Снять резерв товара
     */
    public function unreserve(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            $stock = $this->stockService->unreserveStock(
                $validated['product_id'],
                $validated['warehouse_id'],
                $validated['quantity']
            );

            return response()->json([
                'message' => 'Резерв успешно снят',
                'stock' => $stock->load(['product', 'warehouse']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
