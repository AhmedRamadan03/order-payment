<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;

it('creates an order and calculates the total on the server', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'api')->postJson('/api/orders', ['items' => [
        ['product_name' => 'Keyboard', 'quantity' => 2, 'price' => '25.50'],
        ['product_name' => 'Mouse', 'quantity' => 1, 'price' => '10.00'],
    ], 'total_amount' => '0.01'])->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.total_amount', '61.00');
    $this->assertDatabaseCount('order_items', 2);
});

it('rejects an order total that exceeds the database decimal range', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')->postJson('/api/orders', ['items' => [
        ['product_name' => 'Bulk item', 'quantity' => 100000, 'price' => '99999999.99'],
    ]])->assertUnprocessable()->assertJsonValidationErrors(['items']);

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('order_items', 0);
});

it('rejects payment until the order is confirmed', function () {
    $user = User::factory()->create();
    $order = Order::create(['user_id' => $user->id, 'status' => OrderStatus::Pending, 'total_amount' => '20.00']);
    $this->actingAs($user, 'api')
        ->postJson("/api/orders/{$order->id}/payments", ['method' => 'credit_card'])
        ->assertUnprocessable()->assertJsonValidationErrors(['order']);
    $this->assertDatabaseCount('payments', 0);
});

it('prevents deleting an order that has any payment', function () {
    $user = User::factory()->create();
    $order = Order::create(['user_id' => $user->id, 'status' => OrderStatus::Confirmed, 'total_amount' => '20.00']);
    $order->payments()->create(['status' => PaymentStatus::Failed, 'method' => 'credit_card', 'amount' => '20.00']);
    $this->actingAs($user, 'api')->deleteJson("/api/orders/{$order->id}")
        ->assertConflict()->assertJsonPath('code', 'ORDER_HAS_PAYMENTS');
    $this->assertDatabaseHas('orders', ['id' => $order->id]);
});

it('prevents editing an order after a payment attempt exists', function () {
    $user = User::factory()->create();
    $order = Order::create(['user_id' => $user->id, 'status' => OrderStatus::Confirmed, 'total_amount' => '20.00']);
    $order->payments()->create(['status' => PaymentStatus::Failed, 'method' => 'credit_card', 'amount' => '20.00']);

    $this->actingAs($user, 'api')->putJson("/api/orders/{$order->id}", [
        'items' => [['product_name' => 'Changed item', 'quantity' => 1, 'price' => '1.00']],
    ])->assertUnprocessable()->assertJsonValidationErrors(['order']);

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'total_amount' => '20.00']);
});

it('does not expose another users order', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $order = Order::create(['user_id' => $owner->id, 'status' => OrderStatus::Pending, 'total_amount' => '10.00']);
    $this->actingAs($other, 'api')->getJson("/api/orders/{$order->id}")->assertNotFound();
});
