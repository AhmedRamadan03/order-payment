<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    private const MAX_AMOUNT_CENTS = 999_999_999_999;

    public function create(int $userId, array $data): Order
    {
        return DB::transaction(function () use ($userId, $data): Order {
            $order = Order::create(['user_id' => $userId, 'status' => OrderStatus::Pending, 'total_amount' => 0]);
            $this->syncItems($order, $data['items']);

            return $order->load('items');
        });
    }

    public function update(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($lockedOrder->payments()->exists()) {
                throw ValidationException::withMessages([
                    'order' => ['Orders with payments cannot be modified.'],
                ]);
            }

            if (array_key_exists('items', $data)) {
                $lockedOrder->items()->delete();
                $this->syncItems($lockedOrder, $data['items']);
            }

            return $lockedOrder->refresh()->load('items');
        });
    }

    private function syncItems(Order $order, array $items): void
    {
        $normalizedItems = [];
        $totalCents = 0;

        foreach ($items as $item) {
            $priceParts = array_pad(explode('.', (string) $item['price'], 2), 2, '0');
            $unitCents = ((int) $priceParts[0] * 100) + (int) str_pad($priceParts[1], 2, '0');
            $quantity = (int) $item['quantity'];
            $lineCents = $unitCents * $quantity;

            if ($lineCents > self::MAX_AMOUNT_CENTS || $totalCents > self::MAX_AMOUNT_CENTS - $lineCents) {
                throw ValidationException::withMessages([
                    'items' => ['The combined order total must not exceed 9,999,999,999.99.'],
                ]);
            }

            $totalCents += $lineCents;
            $normalizedItems[] = [
                'product_name' => $item['product_name'],
                'quantity' => $quantity,
                'price' => $this->formatCents($unitCents),
                'line_total' => $this->formatCents($lineCents),
            ];
        }

        foreach ($normalizedItems as $item) {
            $order->items()->create($item);
        }

        $order->update(['total_amount' => $this->formatCents($totalCents)]);
    }

    private function formatCents(int $amountCents): string
    {
        return intdiv($amountCents, 100).'.'.str_pad((string) ($amountCents % 100), 2, '0', STR_PAD_LEFT);
    }
}
