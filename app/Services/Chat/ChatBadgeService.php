<?php

namespace App\Services\Chat;

use App\Models\Admin;
use App\Models\Message;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;

/**
 * Unread counters shown next to the chat links (storefront navbar and the
 * seller, logistics and admin sidebars).
 *
 * Every counter is a single indexed COUNT, and the result is memoized for the
 * rest of the request so a layout that renders several badges does not query
 * twice. A missing table (migrations not run yet) or any query failure yields
 * zeros: a badge must never break the page it decorates.
 *
 * Shape of every result:
 *
 *   messages  unread direct messages (Buyer <-> Seller, Seller <-> Logistics)
 *   support   Buyer / Seller / Logistics: unread replies from the support team
 *             Admin: conversations that hold a member message nobody read yet
 *   total     messages + support
 */
class ChatBadgeService
{
    /** @var array<string, array{messages:int, support:int, total:int}> */
    private array $memo = [];

    /**
     * @return array{messages:int, support:int, total:int}
     */
    public function forUser(?User $user): array
    {
        if (! $user) {
            return $this->build(0, 0);
        }

        return $this->memo['u:' . $user->id] ??= $this->compute(function () use ($user) {
            $messages = Message::unreadFor((int) $user->id)->count();

            $support = SupportMessage::unreadByUser()
                ->whereHas('conversation', fn ($q) => $q->where('user_id', $user->id))
                ->count();

            return [$messages, $support];
        });
    }

    /**
     * @return array{messages:int, support:int, total:int}
     */
    public function forAdmin(?Admin $admin): array
    {
        if (! $admin) {
            return $this->build(0, 0);
        }

        return $this->memo['a:' . $admin->id] ??= $this->compute(function () {
            // Shared inbox: every admin sees the same number. Count conversations,
            // not messages, so one chatty member does not inflate the badge.
            $support = SupportMessage::unreadByAdmin()
                ->whereHas('conversation')
                ->distinct()
                ->count('conversation_id');

            return [0, $support];
        });
    }

    /**
     * Open conversations waiting for an answer (admin dashboards may show it).
     */
    public function openConversations(): int
    {
        try {
            return SupportConversation::open()->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @param  callable():array{0:int,1:int}  $query
     * @return array{messages:int, support:int, total:int}
     */
    private function compute(callable $query): array
    {
        try {
            [$messages, $support] = $query();

            return $this->build((int) $messages, (int) $support);
        } catch (\Throwable $e) {
            return $this->build(0, 0);
        }
    }

    /**
     * @return array{messages:int, support:int, total:int}
     */
    private function build(int $messages, int $support): array
    {
        return [
            'messages' => $messages,
            'support'  => $support,
            'total'    => $messages + $support,
        ];
    }
}