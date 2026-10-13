<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartQuantityUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $cart_id;
    public $user_id;
    public $quantity;

    public function __construct($cart_id, $user_id, $quantity)
    {
        $this->cart_id = $cart_id;
        $this->user_id = $user_id;
        $this->quantity = $quantity;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('cart.' . $this->user_id);
    }

    public function broadcastAs()
    {
        return 'CartQuantityUpdated';
    }
}