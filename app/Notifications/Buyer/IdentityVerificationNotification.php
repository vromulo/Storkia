<?php

namespace App\Notifications\Buyer;

use App\Models\IdentityVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IdentityVerificationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public IdentityVerification $verification,
        public string $status, // 'verified' or 'failed'
        public ?string $remarks = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $idName = match ($this->verification->id_type) {
            'drivers_license' => "Driver's License",
            'passport'        => "Passport",
            default           => "Identity Card",
        };

        if ($this->status === 'verified') {
            return [
                'title'           => 'Identity Verified',
                'message'         => "Your {$idName} verification has been approved. Your account is now verified.",
                'status'          => 'verified',
                'type'            => 'success',
                'verification_id' => $this->verification->id,
            ];
        }

        return [
            'title'           => 'Identity Verification Failed',
            'message'         => "Your {$idName} verification was unsuccessful. Reason: " . ($this->remarks ?? 'Please submit clear, valid documents.'),
            'status'          => 'failed',
            'remarks'         => $this->remarks,
            'type'            => 'danger',
            'verification_id' => $this->verification->id,
        ];
    }
}