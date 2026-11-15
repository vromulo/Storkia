<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StorkiaRealtimeUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<string>  $channelNames
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $channelNames,
        public string $scope,
        public string $action,
        public array $payload = []
    ) {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        foreach (array_unique(array_filter($this->channelNames)) as $rawName) {
            $clean = trim((string) $rawName);
            if ($clean === '') {
                continue;
            }

            if (str_starts_with($clean, 'public:')) {
                $channels[] = new Channel(substr($clean, 7));
            } elseif (str_starts_with($clean, 'private-')) {
                $channels[] = new PrivateChannel(substr($clean, 8));
            } else {
                $channels[] = new PrivateChannel($clean);
            }
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'storkia.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_merge($this->payload, [
            'scope' => $this->scope,
            'action' => $this->action,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}