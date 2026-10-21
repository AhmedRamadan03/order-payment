<?php

use App\Models\Payment;
use App\Payments\Gateways\CreditCardGateway;
use App\Payments\Gateways\PayPalGateway;

it('simulates a successful credit card charge with a unique transaction reference', function () {
    $result = (new CreditCardGateway)->charge(new Payment);

    expect($result['successful'])->toBeTrue()
        ->and(str_starts_with($result['transaction_id'], 'cc_'))->toBeTrue()
        ->and($result['message'])->not->toBeEmpty();
});

it('simulates a successful PayPal charge with a unique transaction reference', function () {
    $result = (new PayPalGateway)->charge(new Payment);

    expect($result['successful'])->toBeTrue()
        ->and(str_starts_with($result['transaction_id'], 'pp_'))->toBeTrue()
        ->and($result['message'])->not->toBeEmpty();
});
