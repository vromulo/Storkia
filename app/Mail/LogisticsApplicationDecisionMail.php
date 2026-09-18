<?php

namespace App\Mail;

use App\Models\LogisticsApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LogisticsApplicationDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public LogisticsApplication $application,
        public string $status,
        public ?string $reason = null
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->status === 'approved'
            ? 'Storkia - Your Logistics Sorting Center Has Been Approved!'
            : 'Storkia - Update on Your Logistics Application';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.seller-application-decision', // Reuses the unified decision email template
        );
    }
}