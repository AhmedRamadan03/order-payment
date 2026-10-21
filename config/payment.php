<?php

use App\Payments\Gateways\CreditCardGateway;
use App\Payments\Gateways\PayPalGateway;

return [
    /*
     * Register gateways here. Secrets are read from environment variables and
     * never returned by the API.
     */
    'gateways' => [
        'credit_card' => [
            'driver' => CreditCardGateway::class,
            'api_key' => env('CREDIT_CARD_API_KEY'),
            'secret' => env('CREDIT_CARD_SECRET'),
        ],
        'paypal' => [
            'driver' => PayPalGateway::class,
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'secret' => env('PAYPAL_SECRET'),
        ],
    ],
];
