<?php

namespace App\Events;

use App\Models\OrderItem;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KitchenItemStatusUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public OrderItem $orderItem)
    {
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('outlet.' . $this->orderItem->order->outlet_id),
            new Channel('order.' . $this->orderItem->order_id),
        ];

        if ($this->orderItem->order && $this->orderItem->order->table_id) {
            $channels[] = new Channel('table.' . $this->orderItem->order->table_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'kitchen.item.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'item_id' => $this->orderItem->id,
            'order_id' => $this->orderItem->order_id,
            'status' => $this->orderItem->kitchen_status,
        ];
    }
}
