<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailChangeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $token,
        public string $newEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Подтверждение нового email — Bismar');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.email-change');
    }
}
