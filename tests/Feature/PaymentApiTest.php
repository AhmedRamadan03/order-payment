<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Contracts\PaymentGatewayInterface;
use App\Payments\PaymentGatewayManager;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('processes a confirmed order through the selected gateway strategy', function () {
    $user = User::factory()->create();
    $order = Order::create(['user_id' => $user->id, 'status' => OrderStatus::Confirmed, 'total_amount' => '75.00']);
    $payment = app(PaymentService::class)->process($order, 'credit_card');
    expect($payment->status)->toBe(PaymentStatus::Successful)
        ->and($payment->method)->toBe('credit_card')
        ->and((string) $payment->amount)->toBe('75.00')
        ->and($payment->transaction_id)->not->toBeEmpty();

});

it('records a failed gateway response as a failed payment', function () {
    $user = User::factory()->create();
    $order = Order::create(['user_id' => $user->id, 'status' => OrderStatus::Confirmed, 'total_amount' => '75.00']);
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('charge')->once()->andReturn([
        'successful' => false,
        'transaction_id' => 'mock_failed_1',
        'message' => 'Declined',
    ]);
    $manager = Mockery::mock(PaymentGatewayManager::class);
    $manager->shouldReceive('availableMethods')->andReturn(['credit_card']);
    $manager->shouldReceive('gateway')->with('credit_card')->andReturn($gateway);

    $payment = (new PaymentService($manager))->process($order, 'credit_card');
    expect($payment->status)->toBe(PaymentStatus::Failed);

});

it('rejects payment methods that are not configured', function () {
    $user = User::factory()->create();
    $order = Order::create(['user_id' => $user->id, 'status' => OrderStatus::Confirmed, 'total_amount' => '75.00']);
    expect(fn () => app(PaymentService::class)->process($order, 'wire_transfer'))
        ->toThrow(ValidationException::class);
});

it('returns a validation response for an unsupported payment method', function () {
    $user = User::factory()->create();
    $order = Order::create(['user_id' => $user->id, 'status' => OrderStatus::Confirmed, 'total_amount' => '75.00']);

    $this->actingAs($user, 'api')
        ->postJson("/api/orders/{$order->id}/payments", ['method' => 'wire_transfer'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['method']);

    $this->assertDatabaseCount('payments', 0);
});

it('does not expose another users payment', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $order = Order::create(['user_id' => $owner->id, 'status' => OrderStatus::Confirmed, 'total_amount' => '20.00']);
    $payment = $order->payments()->create([
        'status' => PaymentStatus::Successful,
        'method' => 'credit_card',
        'amount' => '20.00',
        'transaction_id' => 'cc_owner_only',
    ]);

    $this->actingAs($other, 'api')
        ->getJson("/api/payments/{$payment->id}")
        ->assertNotFound();

    $this->getJson('/api/payments')
        ->assertOk()
        ->assertJsonPath('data.total', 0);
});

it('prevents a successfully paid order from being cancelled', function () {
    $user = User::factory()->create();
    $order = Order::create(['user_id' => $user->id, 'status' => OrderStatus::Confirmed, 'total_amount' => '20.00']);
    $order->payments()->create([
        'status' => PaymentStatus::Successful,
        'method' => 'credit_card',
        'amount' => '20.00',
        'transaction_id' => 'cc_paid_order',
    ]);

    $this->actingAs($user, 'api')
        ->patchJson("/api/orders/{$order->id}/status", ['status' => OrderStatus::Cancelled->value])
        ->assertConflict()
        ->assertJsonPath('code', 'PAID_ORDER_STATUS_LOCKED');

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Confirmed->value]);
});

it('prevents a second payment after a successful payment', function () {
    $user = User::factory()->create();
    $order = Order::create(['user_id' => $user->id, 'status' => OrderStatus::Confirmed, 'total_amount' => '75.00']);
    $service = app(PaymentService::class);

    $firstPayment = $service->process($order, 'credit_card');

    expect($firstPayment->status)->toBe(PaymentStatus::Successful)
        ->and(fn () => $service->process($order, 'paypal'))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('payments', 1);
});
it('keeps a payment pending if the gateway throws so it can be reconciled safely', function () {
    $user = User::factory()->create();
    $order = Order::create(['user_id' => $user->id, 'status' => OrderStatus::Confirmed, 'total_amount' => '75.00']);
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('charge')->once()->andReturnUsing(function (Payment $payment): array {
        expect(DB::transactionLevel())->toBe(0);
        throw new RuntimeException('Provider outcome is unknown.');
    });
    $manager = Mockery::mock(PaymentGatewayManager::class);
    $manager->shouldReceive('availableMethods')->andReturn(['credit_card']);
    $manager->shouldReceive('gateway')->with('credit_card')->andReturn($gateway);

    expect(fn () => (new PaymentService($manager))->process($order, 'credit_card'))
        ->toThrow(RuntimeException::class);

    $this->assertDatabaseHas('payments', [
        'order_id' => $order->id,
        'status' => PaymentStatus::Pending->value,
        'method' => 'credit_card',
    ]);
});

it('prevents a pending payment from being cancelled before reconciliation', function () {
    $user = User::factory()->create();
    $order = Order::create(['user_id' => $user->id, 'status' => OrderStatus::Confirmed, 'total_amount' => '20.00']);
    $order->payments()->create([
        'status' => PaymentStatus::Pending,
        'method' => 'credit_card',
        'amount' => '20.00',
    ]);

    $this->actingAs($user, 'api')
        ->patchJson("/api/orders/{$order->id}/status", ['status' => OrderStatus::Cancelled->value])
        ->assertConflict()
        ->assertJsonPath('code', 'PAID_ORDER_STATUS_LOCKED');

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Confirmed->value]);
});
