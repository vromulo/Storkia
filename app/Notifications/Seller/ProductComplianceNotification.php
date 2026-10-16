<?php

namespace App\Notifications\Seller;

use App\Models\ProductApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductComplianceNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ProductApproval $approval,
        public string $action, // 'approved' or 'disapproved'
        public ?string $disapprovalType = null,
        public ?string $remarks = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $product = $this->approval->product;
        $productName = $product ? $product->name : 'Product';

        if ($this->action === 'approved') {
            return [
                'title'       => 'Product Approved',
                'message'     => "Your product \"{$productName}\" has been approved and is now active in your catalog.",
                'status'      => 'Approved',
                'type'        => 'success',
                'product_id'  => $this->approval->product_id,
                'approval_id' => $this->approval->id,
            ];
        }

        $typeLabel = $this->disapprovalType === 'Violation' ? 'Violation' : 'Warning';
        return [
            'title'            => "Product Disapproved ({$typeLabel})",
            'message'          => "Your product \"{$productName}\" was disapproved. Reason: {$this->remarks}",
            'status'           => 'Disapproved',
            'disapproval_type' => $this->disapprovalType,
            'remarks'          => $this->remarks,
            'type'             => $this->disapprovalType === 'Violation' ? 'danger' : 'warning',
            'product_id'       => $this->approval->product_id,
            'approval_id'      => $this->approval->id,
        ];
    }
}