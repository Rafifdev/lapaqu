<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('outlet.' . $this->order->outlet_id),
            new Channel('order.' . $this->order->id),
        ];

        if ($this->order->table_id) {
            $channels[] = new Channel('table.' . $this->order->table_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'order.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'payment_status' => $this->order->payment_status,
            'table_id' => $this->order->table_id,
            'order' => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'order_type' => $this->order->order_type,
                'status' => $this->order->status,
                'payment_status' => $this->order->payment_status,
                'total_amount' => $this->order->total_amount,
                'customer_name' => $this->order->customer_name,
                'table' => $this->order->table ? ['id' => $this->order->table->id, 'table_number' => $this->order->table->table_number] : null,
                'created_at' => $this->order->created_at ? $this->order->created_at->toISOString() : now()->toISOString(),
            ],
        ];
    }
}
