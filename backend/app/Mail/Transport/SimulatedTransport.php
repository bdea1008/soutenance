<?php

namespace App\Mail\Transport;

use App\Models\SimulatedEmail;
use App\Models\User;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Transport « simulated » : écrit le message en base au lieu de l'expédier.
 *
 * Il s'insère tout en bas de la chaîne d'envoi Laravel — le Mailable est
 * construit, le gabarit Blade est rendu, les en-têtes sont posés, exactement
 * comme pour un envoi réel. Seule la remise à un serveur SMTP est remplacée.
 * Basculer sur un envoi réel ne demande donc que MAIL_MAILER=smtp, pas une
 * réécriture du code applicatif.
 *
 * Enregistré dans App\Providers\AppServiceProvider, déclaré dans config/mail.php.
 */
class SimulatedTransport extends AbstractTransport
{
    /** En-tête portant la nature du message (« password.reset »…). */
    public const CONTEXT_HEADER = 'X-Andtabbax-Context';

    /** En-tête portant le lien principal du message. */
    public const ACTION_HEADER = 'X-Andtabbax-Action';

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $to = $email->getTo()[0] ?? null;
        $from = $email->getFrom()[0] ?? null;

        $recipient = $to?->getAddress() ?? '(inconnu)';

        SimulatedEmail::create([
            'to_email' => $recipient,
            'to_name' => $this->nameOf($to),
            'from_email' => $from?->getAddress() ?? (string) config('mail.from.address'),
            'from_name' => $this->nameOf($from) ?? config('mail.from.name'),
            'subject' => $email->getSubject() ?? '(sans objet)',
            'body_html' => $this->bodyOf($email, 'html'),
            'body_text' => $this->bodyOf($email, 'text'),
            'context' => $this->headerValue($email, self::CONTEXT_HEADER),
            'action_url' => $this->headerValue($email, self::ACTION_HEADER),
            // Rattachement au compte quand l'adresse en désigne un. Les comptes
            // supprimés en douceur comptent : le message leur a bien été
            // adressé au moment de l'envoi.
            'user_id' => User::withTrashed()->where('email', $recipient)->value('id'),
        ]);

        SimulatedEmail::prune();
    }

    private function nameOf(?Address $address): ?string
    {
        $name = $address?->getName();

        return $name === '' ? null : $name;
    }

    /**
     * Corps du message. Symfony accepte une chaîne ou un flux : un Mailable
     * rendu depuis une vue Blade donne une chaîne, mais une pièce construite à
     * la main peut donner un flux, qu'il faut lire avant de le stocker.
     */
    private function bodyOf(Email $email, string $part): ?string
    {
        $body = $part === 'html' ? $email->getHtmlBody() : $email->getTextBody();

        if (is_resource($body)) {
            return (string) stream_get_contents($body);
        }

        return $body === null ? null : (string) $body;
    }

    private function headerValue(Email $email, string $name): ?string
    {
        $header = $email->getHeaders()->get($name);

        return $header?->getBodyAsString() ?: null;
    }

    public function __toString(): string
    {
        return 'simulated://';
    }
}
