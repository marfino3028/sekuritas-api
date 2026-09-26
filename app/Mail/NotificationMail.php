<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email notifikasi umum ke nasabah (status KYC, SID, transaksi).
 * Dikirim lewat App\Support\Notify agar kegagalan SMTP tidak menggagalkan proses.
 */
class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param string[]              $lines   paragraf isi (teks biasa)
     * @param array<string, string> $details tabel ringkasan (label => nilai)
     * @param array{label: string, url: string}|null $cta tombol aksi
     */
    public function __construct(
        public string $subjectLine,
        public string $recipientName,
        public array $lines,
        public array $details = [],
        public ?array $cta = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notification', with: [
            'title'   => $this->subjectLine,
            'name'    => $this->recipientName,
            'lines'   => $this->lines,
            'details' => $this->details,
            'cta'     => $this->cta,
            'brand'   => config('mail.from.name'),
        ]);
    }
}
