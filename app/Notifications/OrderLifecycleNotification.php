<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderLifecycleNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $title,
        protected string $message,
        protected string $type = 'info',
        protected ?string $url = null,
        protected ?int $orderId = null,
        protected ?int $sellerOrderId = null,
        protected ?int $shipmentId = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'           => $this->title,
            'message'         => $this->message,
            'type'            => $this->type,
            'url'             => $this->url,
            'order_id'        => $this->orderId,
            'seller_order_id' => $this->sellerOrderId,
            'shipment_id'     => $this->shipmentId,
        ];
    }
}