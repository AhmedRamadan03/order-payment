<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaymentIndexRequest;
use App\Http\Requests\Api\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(PaymentIndexRequest $request): JsonResponse
    {
        $data = $request->validated();
        $payments = Payment::query()
            ->whereHas('order', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('order:id,user_id,status,total_amount')
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', PaymentStatus::from($status)->value))
            ->latest()->paginate($data['per_page'] ?? 15);

        return ApiResponse::success(PaymentResource::collection($payments), 'Payments retrieved successfully.');
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        $record = Payment::query()
            ->whereHas('order', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('order:id,user_id,status,total_amount')->findOrFail($payment->getKey());

        return ApiResponse::success(PaymentResource::make($record), 'Payment retrieved successfully.');
    }

    public function forOrder(PaymentIndexRequest $request, Order $order): JsonResponse
    {
        $ownedOrder = $request->user()->orders()->findOrFail($order->getKey());
        $data = $request->validated();
        $payments = $ownedOrder->payments()
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', PaymentStatus::from($status)->value))
            ->latest()
            ->paginate($data['per_page'] ?? 15);

        return ApiResponse::success(
            PaymentResource::collection($payments),
            'Order payments retrieved successfully.',
        );
    }

    public function store(StorePaymentRequest $request, Order $order, PaymentService $service): JsonResponse
    {
        $ownedOrder = $request->user()->orders()->findOrFail($order->getKey());
        $payment = $service->process(
            $ownedOrder,
            $request->validated('method'),
            $request->boolean('simulate_failure'),
        )->load('order');

        if ($payment->status !== PaymentStatus::Successful) {
            return ApiResponse::warning(PaymentResource::make($payment), 'Payment was processed but not successful.', 201, 'PAYMENT_FAILED');
        }

        return ApiResponse::success(PaymentResource::make($payment), 'Payment completed successfully.', 201);
    }
}
