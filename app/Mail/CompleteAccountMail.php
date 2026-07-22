<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email "Lengkapi Akun" — dokumen pembukaan rekening untuk ditandatangani.
 *
 * Dikirim setelah nasabah menyelesaikan pengisian data (submit KYC/eKYC).
 * Contoh:
 *   Mail::to($user->email)->send(new CompleteAccountMail($linkEfek, $linkRdn, $linkTax));
 */
class CompleteAccountMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ?string $linkEfek = null,
        public ?string $linkRdn = null,
        public ?string $linkTax = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Lengkapi Akun — Dokumen Pembukaan Rekening Victoria Sekuritas');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.complete-account', with: [
            'linkEfek' => $this->linkEfek,
            'linkRdn'  => $this->linkRdn,
            'linkTax'  => $this->linkTax,
        ]);
    }
}
