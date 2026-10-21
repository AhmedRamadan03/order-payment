<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ModifyOrderRequest;
use App\Http\Requests\Api\OrderIndexRequest;
use App\Http\Requests\Api\StoreOrderRequest;
use App\Http\Requests\Api\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function index(OrderIndexRequest $request): JsonResponse
    {
        $data = $request->validated();
        $orders = $request->user()->orders()->with('items')
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', OrderStatus::from($status)->value))
            ->latest()->paginate($data['per_page'] ?? 15);

        return ApiResponse::success(OrderResource::collection($orders), 'Orders retrieved successfully.');
    }

    public function store(StoreOrderRequest $request, OrderService $service): JsonResponse
    {
        return ApiResponse::success(
            OrderResource::make($service->create((int) $request->user()->id, $request->validated())),
            'Order created successfully.',
            201,
        );
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        return ApiResponse::success(
            OrderResource::make($this->ownedOrder($request, $order)->load(['items', 'payments'])),
            'Order retrieved successfully.',
        );
    }

    public function update(ModifyOrderRequest $request, Order $order, OrderService $service): JsonResponse
    {
        $owned = $this->ownedOrder($request, $order);

        return ApiResponse::success(
            OrderResource::make($service->update($owned, $request->validated())),
            'Order updated successfully.',
        );
    }

    public function destroy(Request $request, Order $order): JsonResponse
    {
        return DB::transaction(function () use ($request, $order): JsonResponse {
            $owned = $request->user()->orders()->lockForUpdate()->findOrFail($order->getKey());

            if ($owned->payments()->exists()) {
                return ApiResponse::error(
                    'Orders with associated payments cannot be deleted.',
                    'ORDER_HAS_PAYMENTS',
                    Response::HTTP_CONFLICT,
                );
            }

            $owned->delete();

            return ApiResponse::success(null, 'Order deleted successfully.');
        });
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $targetStatus = OrderStatus::from($request->validated('status'));

        return DB::transaction(function () use ($request, $order, $targetStatus): JsonResponse {
            $owned = $request->user()->orders()->lockForUpdate()->findOrFail($order->getKey());

            if ($owned->payments()->whereIn('status', [
                PaymentStatus::Pending->value,
                PaymentStatus::Successful->value,
            ])->exists() && $targetStatus !== OrderStatus::Confirmed) {
                return ApiResponse::error(
                    'An order with a pending or successful payment must remain confirmed.',
                    'PAID_ORDER_STATUS_LOCKED',
                    Response::HTTP_CONFLICT,
                );
            }

            $owned->update(['status' => $targetStatus]);

            return ApiResponse::success(
                OrderResource::make($owned->refresh()->load('items')),
                'Order status updated successfully.',
            );
        });
    }

    private function ownedOrder(Request $request, Order $order): Order
    {
        return $request->user()->orders()->findOrFail($order->getKey());
    }
}
