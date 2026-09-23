<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email konfirmasi registrasi + tautan aktivasi akun (LiF Manajemen Investasi).
 *
 * Contoh pemakaian (mis. di AuthController@register):
 *   Mail::to($user->email)->send(new RegistrationMail(
 *       $user->email,
 *       url("/aktivasi?token={$token}"),
 *       $user->user_id ?? null,
 *   ));
 */
class RegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $activationUrl,
        public ?string $userId = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Registrasi LiF Manajemen Investasi — Aktivasi Akun');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.registration', with: [
            'email'         => $this->email,
            'activationUrl' => $this->activationUrl,
            'userId'        => $this->userId,
        ]);
    }
}
