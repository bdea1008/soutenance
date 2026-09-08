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

/**
 * Lien de réinitialisation du mot de passe (§10).
 *
 * Le lien pointe sur l'interface React, pas sur l'API : c'est elle qui porte
 * le formulaire. L'API ne reçoit le jeton qu'à la validation du nouveau mot
 * de passe.
 */
class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            // Adresse nommée : c'est ce qui s'affiche comme destinataire
            // dans un client de messagerie, et dans la boîte simulée.
            to: [new Address($this->user->email, $this->user->name)],
            subject: 'Réinitialisation de votre mot de passe AndTabbax',
        );
    }

    /**
     * En-têtes lus par le transport simulé pour classer le message dans la
     * boîte d'envoi et en extraire le lien d'action. Un serveur SMTP réel les
     * transporte sans les interpréter : rien à retirer si l'envoi devient réel.
     */
    public function headers(): Headers
    {
        return new Headers(text: [
            SimulatedTransport::CONTEXT_HEADER => 'password.reset',
            SimulatedTransport::ACTION_HEADER => $this->resetUrl(),
        ]);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            with: [
                'user' => $this->user,
                'resetUrl' => $this->resetUrl(),
                'expiresInMinutes' => (int) config('auth.passwords.users.expire', 60),
            ],
        );
    }

    /** Lien vers le formulaire React, jeton et adresse en paramètres. */
    public function resetUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/')
            .'/reinitialiser-mot-de-passe?'
            .http_build_query([
                'token' => $this->token,
                'email' => $this->user->email,
            ]);
    }
}
