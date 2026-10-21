<?php

namespace App\Payments\Gateways;

use App\Models\Payment;
use App\Payments\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Str;

class PayPalGateway implements PaymentGatewayInterface
{
    public function charge(Payment $payment, bool $simulateFailure = false): array
    {
        if ($simulateFailure) {
            return [
                'successful' => false,
                'transaction_id' => 'pp_declined_'.Str::uuid(),
                'message' => 'PayPal payment was simulated as declined.',
            ];
        }

        return [
            'successful' => true,
            'transaction_id' => 'pp_'.Str::uuid(),
            'message' => 'PayPal payment simulated successfully.',
        ];
    }
}
