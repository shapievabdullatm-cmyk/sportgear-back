<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;

class ShopController extends Controller
{
    public function index(): JsonResponse
    {
        $shops = Shop::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json($shops);
    }

    /**
     * Занятость слотов самовывоза в магазине: сколько заказов уже на каждый слот.
     * Параметры: ?days=N (по умолчанию = advance_days магазина).
     */
    public function pickupSlotBookings(Shop $shop, \Illuminate\Http\Request $request): JsonResponse
    {
        if (!$shop->is_active || !$shop->pickup_enabled) {
            return response()->json([]);
        }

        $days = (int) $request->query('days', $shop->pickup_advance_days ?? 7);
        $days = max(1, min($days, 30));

        $from = now()->startOfHour();
        $to   = now()->addDays($days)->endOfDay();

        $rows = \App\Models\Order::query()
            ->where('shop_id', $shop->id)
            ->whereNotNull('pickup_slot_at')
            ->whereBetween('pickup_slot_at', [$from, $to])
            ->whereNotIn('status', ['cancelled'])
            ->selectRaw('pickup_slot_at, count(*) as cnt')
            ->groupBy('pickup_slot_at')
            ->get();

        // Возвращаем плоский map { ISO: count }
        $map = [];
        foreach ($rows as $r) {
            $map[\Carbon\Carbon::parse($r->pickup_slot_at)->toIso8601String()] = (int) $r->cnt;
        }

        return response()->json([
            'max_per_slot' => (int) ($shop->pickup_max_per_slot ?? 5),
            'bookings'     => $map,
        ]);
    }
}