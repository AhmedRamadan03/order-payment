<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'status' => $this->status,
            'method' => $this->method,
            'amount' => $this->amount,
            'transaction_id' => $this->transaction_id,
            'order' => $this->whenLoaded('order', fn (Order $order): array => [
                'id' => $order->id,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
