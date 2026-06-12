<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Order\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Order::query()->with(['user', 'items']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'ilike', "%{$search}%")
                  ->orWhere('customer_phone', 'ilike', "%{$search}%")
                  ->orWhere('customer_name', 'ilike', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return response()->json(
            $query->orderByDesc('created_at')->paginate($request->get('per_page', 30))
        );
    }

    public function show(Order $order): JsonResponse
    {
        $order->load([
            'user',
            'items.product.images',
            'items.product.stocks.warehouse',
            'items.reservedWarehouse',
            'shop',
            'events',
        ]);

        $this->orderService->enrichRemovedEvents($order);

        return response()->json($order);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ]);

        $order = $this->orderService->transitionTo(
            $order,
            OrderStatus::from($validated['status']),
            $request->user()->id
        );

        return response()->json($order);
    }

    public function updateAdminComment(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'admin_comment' => 'nullable|string|max:2000',
        ]);

        $order->update($validated);

        return response()->json($order->fresh());
    }

    public function updateTrackingNumber(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'tracking_number' => 'nullable|string|max:100',
        ]);

        $order->update($validated);

        return response()->json($order->fresh());
    }

    public function updateItemWarehouse(Request $request, Order $order, OrderItem $item): JsonResponse
    {
        if ($item->order_id !== $order->id) {
            return response()->json(['message' => 'Item not in order'], 422);
        }

        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        $item = $this->orderService->changeItemWarehouse($item, $validated['warehouse_id']);

        return response()->json($item);
    }

    public function addItem(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity'   => 'required|integer|min:1',
            'price'      => 'nullable|numeric|min:0',
            'force'      => 'sometimes|boolean',
        ]);

        $item = $this->orderService->addItem(
            $order,
            $validated['product_id'],
            $validated['quantity'],
            isset($validated['price']) ? (float) $validated['price'] : null,
            (bool) ($validated['force'] ?? false)
        );

        return response()->json([
            'item'  => $item,
            'order' => $order->fresh()->load(['items.reservedWarehouse', 'items.product.images', 'items.product.stocks.warehouse', 'events']),
        ]);
    }

    public function updateItem(Request $request, Order $order, OrderItem $item): JsonResponse
    {
        if ($item->order_id !== $order->id) {
            return response()->json(['message' => 'Item not in order'], 422);
        }

        $validated = $request->validate([
            'quantity' => 'nullable|integer|min:1',
            'price'    => 'nullable|numeric|min:0',
            'force'    => 'sometimes|boolean',
        ]);

        $item = $this->orderService->updateItem(
            $item,
            $validated['quantity'] ?? null,
            isset($validated['price']) ? (float) $validated['price'] : null,
            (bool) ($validated['force'] ?? false)
        );

        return response()->json([
            'item'  => $item,
            'order' => $order->fresh()->load(['items.reservedWarehouse', 'items.product.images', 'items.product.stocks.warehouse', 'events']),
        ]);
    }

    public function removeItem(Order $order, OrderItem $item): JsonResponse
    {
        if ($item->order_id !== $order->id) {
            return response()->json(['message' => 'Item not in order'], 422);
        }

        $this->orderService->removeItem($item);

        return response()->json([
            'order' => $order->fresh()->load(['items.reservedWarehouse', 'items.product.images', 'items.product.stocks.warehouse', 'events']),
        ]);
    }

    public function restoreItem(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|exists:order_events,id',
        ]);

        $event = \App\Models\OrderEvent::findOrFail($validated['event_id']);
        $item  = $this->orderService->restoreItem($order, $event);

        return response()->json([
            'item'  => $item,
            'order' => $order->fresh()->load(['items.reservedWarehouse', 'items.product.images', 'items.product.stocks.warehouse', 'events']),
        ]);
    }

    /**
     * Быстрый поиск товаров для добавления в заказ.
     * Возвращает только листовые продукты (без детей-вариантов).
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $upper = 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower = 'абвгдеёжзийклмнопрстуфхцчшщъыьэюяabcdefghijklmnopqrstuvwxyz';
        $like  = "%{$q}%";

        $products = \App\Models\Product::query()
            ->where('is_active', true)
            ->whereDoesntHave('children')
            ->with([
                'images',
                'parent.images',
                'stocks',
                'paramValues.param',
                'paramValues.paramOption',
                'optionValues.param',
                'optionValues.paramOption',
            ])
            ->where(function ($w) use ($like, $upper, $lower) {
                $w->whereRaw("translate(title, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", [$like])
                    ->orWhereRaw("translate(article, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", [$like])
                    ->orWhereHas('barcodes', function ($b) use ($like, $upper, $lower) {
                        $b->whereRaw("translate(barcode, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", [$like]);
                    })
                    ->orWhereHas('parent', function ($p) use ($like, $upper, $lower) {
                        $p->whereRaw("translate(title, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", [$like])
                            ->orWhereRaw("translate(article, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", [$like]);
                    });
            })
            ->limit(15)
            ->get();

        return response()->json($products->map(function (\App\Models\Product $p) {
            $title = $p->parent?->title ?? $p->title;
            $image = $p->parent?->images->first()?->url ?? $p->images->first()?->url;

            $size = null;
            foreach ($p->paramValues as $pv) {
                if ($pv->param && $pv->param->is_size) {
                    $size = $pv->value_string
                        ?? $pv->value_text
                        ?? $pv->value_int
                        ?? $pv->value_float
                        ?? $pv->paramOption?->value;
                    if ($size !== null && $size !== '') break;
                    $size = null;
                }
            }
            if ($size === null) {
                foreach ($p->optionValues as $ov) {
                    if ($ov->param && $ov->param->is_size && $ov->paramOption) {
                        $size = $ov->paramOption->value;
                        if ($size !== null && $size !== '') break;
                        $size = null;
                    }
                }
            }

            $available = $p->stocks->sum(fn ($s) => max(0, $s->quantity - $s->reserved_quantity));

            return [
                'id'        => $p->id,
                'title'     => $title,
                'size'      => $size,
                'article'   => $p->article,
                'price'     => (float) $p->price,
                'image'     => $image,
                'available' => (int) $available,
            ];
        }));
    }
}