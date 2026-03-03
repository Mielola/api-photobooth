<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;

    /**
     * Terima OTP dari controller
     */
    public function __construct(string $otp)
    {
        $this->otp = $otp;
    }

    /**
     * Judul Email
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kode OTP Anda',
        );
    }

    /**
     * View email + data OTP
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'otp' => $this->otp
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
