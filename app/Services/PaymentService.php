<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    public function process(Order $order, string $method, bool $simulateFailure = false): Payment
    {
        if (! in_array($method, $this->gateways->availableMethods(), true)) {
            throw ValidationException::withMessages([
                'method' => ['The selected payment method is not configured.'],
            ]);
        }

        $gateway = $this->gateways->gateway($method);

        // Reserve a single in-flight payment while holding a lock on the order.
        $payment = DB::transaction(function () use ($order, $method): Payment {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($lockedOrder->status !== OrderStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'order' => ['Payments can only be processed for confirmed orders.'],
                ]);
            }

            if ($lockedOrder->payments()->whereIn('status', [
                PaymentStatus::Pending->value,
                PaymentStatus::Successful->value,
            ])->exists()) {
                throw ValidationException::withMessages([
                    'order' => ['A payment is already pending or has been completed for this order.'],
                ]);
            }

            return $lockedOrder->payments()->create([
                'status' => PaymentStatus::Pending,
                'method' => $method,
                'amount' => $lockedOrder->total_amount,
            ]);
        });

        // Never keep a database transaction open while a gateway is processing.
        // If the provider outcome is unknown, the pending record blocks duplicate charges
        // until the payment can be reconciled.
        $result = $gateway->charge($payment, $simulateFailure);

        return DB::transaction(function () use ($payment, $result): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($lockedPayment->status !== PaymentStatus::Pending) {
                return $lockedPayment;
            }

            $lockedPayment->update([
                'status' => $result['successful'] ? PaymentStatus::Successful : PaymentStatus::Failed,
                'transaction_id' => $result['transaction_id'] ?? null,
                'gateway_response' => ['message' => $result['message'] ?? 'Payment processed.'],
            ]);

            return $lockedPayment->refresh();
        });
    }
}
