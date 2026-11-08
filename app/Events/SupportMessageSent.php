<?php

namespace App\Events;

use App\Models\SupportMessage;
use App\Services\Chat\ChatPresenter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A Help Center message. One event instance targets one audience:
 *
 *   "user"  -> private channel support.user.{userId}  (the member's own tabs)
 *   "admin" -> private channel support.admin          (every signed-in admin)
 *
 * Use SupportMessageSent::dispatchToAll() so both audiences stay in sync.
 */
class SupportMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public const AUDIENCE_USER = 'user';
    public const AUDIENCE_ADMIN = 'admin';

    public function __construct(
        public SupportMessage $message,
        public string $audience = self::AUDIENCE_USER,
    ) {
    }

    /**
     * Broadcasts to the member and to the support team, skipping the socket
     * that sent the message (it already rendered it locally).
     */
    public static function dispatchToAll(SupportMessage $message): void
    {
        foreach ([self::AUDIENCE_USER, self::AUDIENCE_ADMIN] as $audience) {
            try {
                broadcast(new self($message, $audience))->toOthers();
            } catch (\Throwable $e) {
                // Real-time is best effort; the chat falls back to polling.
            }
        }
    }

    public function broadcastOn()
    {
        if ($this->audience === self::AUDIENCE_ADMIN) {
            return new PrivateChannel('support.admin');
        }

        return new PrivateChannel('support.user.' . $this->message->conversation->user_id);
    }

    public function broadcastAs()
    {
        return 'SupportMessageSent';
    }

    public function broadcastWith(): array
    {
        $presenter = app(ChatPresenter::class);

        $data = [
            'conversation_id' => $this->message->conversation_id,
            'payload'         => $presenter->supportMessage($this->message, $this->audience),
        ];

        // The admin inbox also needs the conversation row so a brand-new
        // thread (or a changed preview / unread count) appears without a reload.
        if ($this->audience === self::AUDIENCE_ADMIN) {
            $data['contact'] = $presenter->supportContact($this->message->conversation);
        }

        return $data;
    }
}