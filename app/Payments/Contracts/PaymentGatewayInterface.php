<?php

namespace App\Payments\Contracts;

use App\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * Simulate a gateway request and return a normalized result.
     *
     * @param  bool  $simulateFailure  Force a declined result in the local simulation.
     * @return array{successful: bool, transaction_id: string, message: string}
     */
    public function charge(Payment $payment, bool $simulateFailure = false): array;
}
