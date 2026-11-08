<?php

namespace App\Events;

use App\Models\Message;
use App\Services\Chat\ChatPresenter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new Buyer <-> Seller or Seller <-> Logistics message.
 * Delivered to the receiver's private channel (see routes/channels.php).
 */
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

    /**
     * Same JSON the chat endpoints return, already formatted for the
     * receiver (product / shipment cards, absolute photo URL, ISO date).
     */
    public function broadcastWith(): array
    {
        $receiverRole = $this->message->receiver?->role ?? 'Buyer';

        return [
            'payload' => app(ChatPresenter::class)->message($this->message, $receiverRole),
        ];
    }
}