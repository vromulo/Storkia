<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function broadcastOn()
    {
        // Broadcasts to a private channel unique to the receiver
        return new PrivateChannel('chat.' . $this->message->receiver_id);
    }

    /**
     * Define explicitly the broadcast event name so Laravel Echo 
     * doesn't prefix it with 'App\Events\' on the frontend.
     */
    public function broadcastAs()
    {
        return 'MessageSent';
    }
}