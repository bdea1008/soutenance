<?php

namespace App\Mail;

use App\Mail\Transport\SimulatedTransport;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Confirmation d'un changement de mot de passe.
 *
 * Ce message n'est pas une politesse : c'est la seule chose qui alerte le
 * titulaire si quelqu'un d'autre a réinitialisé son mot de passe. Il part donc
 * même quand le changement est parfaitement légitime.
 */
class PasswordChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly Carbon $changedAt;

    public function __construct(public readonly User $user)
    {
        $this->changedAt = now();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            // Adresse nommée : c'est ce qui s'affiche comme destinataire
            // dans un client de messagerie, et dans la boîte simulée.
            to: [new Address($this->user->email, $this->user->name)],
            subject: 'Votre mot de passe AndTabbax a été modifié',
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            SimulatedTransport::CONTEXT_HEADER => 'password.changed',
            SimulatedTransport::ACTION_HEADER => $this->loginUrl(),
        ]);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-changed',
            with: [
                'user' => $this->user,
                'changedAt' => $this->changedAt,
                'loginUrl' => $this->loginUrl(),
            ],
        );
    }

    public function loginUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/connexion';
    }
}
