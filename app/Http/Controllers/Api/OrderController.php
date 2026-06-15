<?php

namespace App\Http\Controllers\Api;

use App\Enums\Order\DeliveryMethod;
use App\Enums\Order\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
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
        $perPage = (int) $request->get('per_page', 10);
        $perPage = max(1, min($perPage, 50));

        $orders = $request->user()
            ->orders()
            ->with(['items'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($orders);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $order->load(['items.product.images', 'shop', 'events']);

        $this->orderService->enrichRemovedEvents($order);

        return response()->json($order);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delivery_method'    => ['required', Rule::enum(DeliveryMethod::class)],
            'payment_method'     => ['required', Rule::enum(PaymentMethod::class)],

            'customer_name'      => 'required|string|max:150',
            'customer_phone'     => 'required|string|max:30',
            'customer_email'     => 'nullable|email|max:150',

            // Courier
            'address_full'       => 'nullable|string|max:500',
            'address_lat'        => 'nullable|numeric',
            'address_lon'        => 'nullable|numeric',
            'city'               => 'nullable|string|max:100',
            'street'             => 'nullable|string|max:200',
            'house'              => 'nullable|string|max:20',
            'apartment'          => 'nullable|string|max:20',
            'entrance'           => 'nullable|string|max:10',
            'floor'              => 'nullable|string|max:10',
            'intercom'           => 'nullable|string|max:20',

            // Pickup
            'shop_id'            => 'nullable|exists:shops,id',
            'pickup_slot_at'     => 'nullable|date',

            // CDEK / Russian post
            'cdek_pvz_code'      => 'nullable|string|max:50',
            'russian_post_index' => 'nullable|string|max:10',

            'comment'            => 'nullable|string|max:1000',
        ]);

        $order = $this->orderService->createFromCart($request->user(), $validated);

        return response()->json($order, 201);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $order = $this->orderService->cancel($order, $request->user()->id);

        return response()->json($order);
    }
}