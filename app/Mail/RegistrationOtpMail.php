<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $expiresInMinutes = 5,
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = (string) (config('mail.from.address') ?: 'team@storkia.shop');
        $fromName = (string) (config('mail.from.name') ?: config('app.name', 'Storkia'));

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: [new Address($fromAddress, $fromName)],
            subject: "Your Storkia Verification Code: {$this->code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.registration-otp',
            with: [
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }
}