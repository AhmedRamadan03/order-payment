<?php

namespace App\Payments\Gateways;

use App\Models\Payment;
use App\Payments\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Str;

class CreditCardGateway implements PaymentGatewayInterface
{
    public function charge(Payment $payment, bool $simulateFailure = false): array
    {
        if ($simulateFailure) {
            return [
                'successful' => false,
                'transaction_id' => 'cc_declined_'.Str::uuid(),
                'message' => 'Credit card payment was simulated as declined.',
            ];
        }

        return [
            'successful' => true,
            'transaction_id' => 'cc_'.Str::uuid(),
            'message' => 'Credit card payment simulated successfully.',
        ];
    }
}
