<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Database notification sent to a Buyer / Seller / Logistics account when the
 * Storkia support team answers their Help Center conversation while they were
 * not looking at it.
 *
 * The payload keys (title, message, type, url) are the same ones the existing
 * notification dropdowns of the buyer, seller and logistics layouts render.
 */
class SupportReplyNotification extends Notification
{
    use Queueable;

    public function __construct(protected string $url)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'   => 'Storkia Support replied',
            'message' => 'You have a new reply in your Help Center conversation.',
            'type'    => 'info',
            'url'     => $this->url,
        ];
    }
}