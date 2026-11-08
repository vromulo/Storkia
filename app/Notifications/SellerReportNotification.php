<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Database notification used for the "Report Seller" feature.
 *
 * Sent to the buyer who filed the report (status changes) and to the reported seller
 * (only when an admin takes action against them). The payload keys (title, message, type, url)
 * are the same ones the existing buyer and seller notification dropdowns already render.
 */
class SellerReportNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $title,
        protected string $message,
        protected string $type = 'info',
        protected ?string $url = null,
        protected ?int $reportId = null,
        protected ?string $reportNumber = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'         => $this->title,
            'message'       => $this->message,
            'type'          => $this->type,
            'url'           => $this->url,
            'report_id'     => $this->reportId,
            'report_number' => $this->reportNumber,
        ];
    }
}