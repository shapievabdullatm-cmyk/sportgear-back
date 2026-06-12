<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Order\OrderEventType;
use App\Enums\Order\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'stats'         => $this->buildStats(),
            'recent_orders' => $this->buildRecentOrders(),
            'top_products'  => $this->buildTopProducts(),
            'activity'      => $this->buildActivity(),
        ]);
    }

    private function buildStats(): array
    {
        $now              = Carbon::now();
        $monthStart       = $now->copy()->startOfMonth();
        $prevMonthStart   = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $prevMonthEnd     = $now->copy()->subMonthNoOverflow()->endOfMonth();

        $ordersCurrent = Order::where('created_at', '>=', $monthStart)->count();
        $ordersPrev    = Order::whereBetween('created_at', [$prevMonthStart, $prevMonthEnd])->count();

        $revenueCurrent = (float) Order::where('created_at', '>=', $monthStart)
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->sum('total');
        $revenuePrev = (float) Order::whereBetween('created_at', [$prevMonthStart, $prevMonthEnd])
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->sum('total');

        $customersCurrent = User::where('created_at', '>=', $monthStart)->count();
        $customersPrev    = User::whereBetween('created_at', [$prevMonthStart, $prevMonthEnd])->count();

        $avgCurrent = $ordersCurrent > 0 ? $revenueCurrent / $ordersCurrent : 0;
        $avgPrev    = $ordersPrev    > 0 ? $revenuePrev    / $ordersPrev    : 0;

        return [
            [
                'key'      => 'orders',
                'label'    => 'Всего заказов',
                'value'    => $ordersCurrent,
                'format'   => 'int',
                'change'   => $this->changePercent($ordersCurrent, $ordersPrev),
                'icon'     => 'heroicons:shopping-cart',
                'color'    => 'blue',
            ],
            [
                'key'      => 'revenue',
                'label'    => 'Выручка',
                'value'    => $revenueCurrent,
                'format'   => 'currency',
                'change'   => $this->changePercent($revenueCurrent, $revenuePrev),
                'icon'     => 'heroicons:currency-dollar',
                'color'    => 'green',
            ],
            [
                'key'      => 'customers',
                'label'    => 'Новые клиенты',
                'value'    => $customersCurrent,
                'format'   => 'int',
                'change'   => $this->changePercent($customersCurrent, $customersPrev),
                'icon'     => 'heroicons:user-group',
                'color'    => 'purple',
            ],
            [
                'key'      => 'aov',
                'label'    => 'Средний чек',
                'value'    => $avgCurrent,
                'format'   => 'currency',
                'change'   => $this->changePercent($avgCurrent, $avgPrev),
                'icon'     => 'heroicons:chart-bar',
                'color'    => 'orange',
            ],
        ];
    }

    private function buildRecentOrders(): array
    {
        return Order::query()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'number', 'customer_name', 'total', 'status', 'created_at'])
            ->map(fn (Order $o) => [
                'id'         => $o->id,
                'number'     => $o->number,
                'customer'   => $o->customer_name,
                'total'      => (float) $o->total,
                'status'     => $o->status->value,
                'created_at' => $o->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function buildTopProducts(): array
    {
        $now            = Carbon::now();
        $monthStart     = $now->copy()->startOfMonth();
        $prevMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $prevMonthEnd   = $now->copy()->subMonthNoOverflow()->endOfMonth();

        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', $monthStart)
            ->where('orders.status', '!=', OrderStatus::CANCELLED->value)
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->orderByDesc(DB::raw('SUM(order_items.quantity)'))
            ->limit(5)
            ->get([
                'order_items.product_id',
                DB::raw('SUM(order_items.quantity) as sales'),
                DB::raw('SUM(order_items.total) as revenue'),
            ]);

        if ($rows->isEmpty()) {
            return [];
        }

        $productIds = $rows->pluck('product_id')->all();

        $products = Product::with(['images' => fn ($q) => $q->orderBy('sort_order')->limit(1)])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $prevSales = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$prevMonthStart, $prevMonthEnd])
            ->where('orders.status', '!=', OrderStatus::CANCELLED->value)
            ->whereIn('order_items.product_id', $productIds)
            ->groupBy('order_items.product_id')
            ->select('order_items.product_id', DB::raw('SUM(order_items.quantity) as sales'))
            ->pluck('sales', 'product_id');

        return $rows->map(function ($row) use ($products, $prevSales) {
            $product = $products->get($row->product_id);
            $prev    = (int) ($prevSales[$row->product_id] ?? 0);
            $current = (int) $row->sales;

            return [
                'id'        => $row->product_id,
                'title'     => $product?->title ?? '—',
                'slug'      => $product?->slug,
                'image_url' => $product?->images->first()?->url,
                'sales'     => $current,
                'revenue'   => (float) $row->revenue,
                'trend'     => $current >= $prev ? 'up' : 'down',
            ];
        })->values()->all();
    }

    private function buildActivity(): array
    {
        $events = OrderEvent::query()
            ->with('order:id,number,customer_name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $items = $events->map(function (OrderEvent $e) {
            return [
                'type'        => 'order_event',
                'event'       => $e->type->value,
                'action'      => $this->orderEventLabel($e->type),
                'description' => $this->orderEventDescription($e),
                'icon'        => $this->orderEventIcon($e->type),
                'color'       => $this->orderEventColor($e->type),
                'time'        => $e->created_at?->toIso8601String(),
            ];
        });

        $newUsers = User::query()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'phone', 'email', 'first_name', 'last_name', 'created_at'])
            ->map(function (User $u) {
                $name = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
                $who  = $name !== '' ? $name : ($u->email ?? $u->phone ?? 'клиент');

                return [
                    'type'        => 'user_registered',
                    'event'       => 'user_registered',
                    'action'      => 'Новый пользователь',
                    'description' => "Регистрация: {$who}",
                    'icon'        => 'heroicons:user-plus',
                    'color'       => 'purple',
                    'time'        => $u->created_at?->toIso8601String(),
                ];
            });

        return $items->concat($newUsers)
            ->sortByDesc('time')
            ->take(10)
            ->values()
            ->all();
    }

    private function orderEventLabel(OrderEventType $type): string
    {
        return match ($type) {
            OrderEventType::CREATED        => 'Новый заказ',
            OrderEventType::STATUS_CHANGED => 'Изменён статус',
            OrderEventType::ITEM_ADDED     => 'Товар добавлен',
            OrderEventType::ITEM_UPDATED   => 'Товар обновлён',
            OrderEventType::ITEM_REMOVED   => 'Товар удалён',
            OrderEventType::ITEM_RESTORED  => 'Товар восстановлен',
        };
    }

    private function orderEventIcon(OrderEventType $type): string
    {
        return match ($type) {
            OrderEventType::CREATED        => 'heroicons:shopping-bag',
            OrderEventType::STATUS_CHANGED => 'heroicons:arrow-path',
            OrderEventType::ITEM_ADDED     => 'heroicons:plus-circle',
            OrderEventType::ITEM_UPDATED   => 'heroicons:pencil-square',
            OrderEventType::ITEM_REMOVED   => 'heroicons:x-circle',
            OrderEventType::ITEM_RESTORED  => 'heroicons:arrow-uturn-left',
        };
    }

    private function orderEventColor(OrderEventType $type): string
    {
        return match ($type) {
            OrderEventType::CREATED        => 'blue',
            OrderEventType::STATUS_CHANGED => 'green',
            OrderEventType::ITEM_ADDED     => 'blue',
            OrderEventType::ITEM_UPDATED   => 'green',
            OrderEventType::ITEM_REMOVED   => 'red',
            OrderEventType::ITEM_RESTORED  => 'green',
        };
    }

    private function orderEventDescription(OrderEvent $event): string
    {
        $orderNumber = $event->order?->number ?? '—';
        $customer    = $event->order?->customer_name ?? '';
        $data        = $event->data ?? [];

        return match ($event->type) {
            OrderEventType::CREATED        => "Заказ #{$orderNumber}" . ($customer ? " от {$customer}" : ''),
            OrderEventType::STATUS_CHANGED => "Заказ #{$orderNumber}: " . $this->statusLabel($data['from'] ?? null) . ' → ' . $this->statusLabel($data['to'] ?? null),
            OrderEventType::ITEM_ADDED     => "В заказ #{$orderNumber} добавлен товар",
            OrderEventType::ITEM_UPDATED   => "В заказе #{$orderNumber} обновлён товар",
            OrderEventType::ITEM_REMOVED   => "Из заказа #{$orderNumber} удалён товар",
            OrderEventType::ITEM_RESTORED  => "В заказ #{$orderNumber} восстановлен товар",
        };
    }

    private function statusLabel(?string $value): string
    {
        if ($value === null) {
            return '—';
        }
        $status = OrderStatus::tryFrom($value);
        return $status?->label() ?? $value;
    }

    private function changePercent(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }
}