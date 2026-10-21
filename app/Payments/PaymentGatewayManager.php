<?php

namespace App\Payments;

use App\Payments\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public function gateway(string $method): PaymentGatewayInterface
    {
        $configuration = config("payment.gateways.{$method}");

        if (! is_array($configuration) || empty($configuration['driver'])) {
            throw new InvalidArgumentException('Unsupported payment method.');
        }

        $driver = app($configuration['driver']);

        if (! $driver instanceof PaymentGatewayInterface) {
            throw new InvalidArgumentException('Configured gateway must implement PaymentGatewayInterface.');
        }

        return $driver;
    }

    public function availableMethods(): array
    {
        return array_keys(config('payment.gateways', []));
    }
}
