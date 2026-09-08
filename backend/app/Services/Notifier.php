<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Point d'entrée unique des notifications applicatives (§7.7).
 *
 * Seul le canal « application » est réellement délivré en MVP. SMS, email et
 * WhatsApp sont enregistrés et tracés mais pas encore émis : comme pour les
 * paiements, la mécanique est en place et l'intégration opérateur viendra
 * ensuite. Un canal non délivré ne doit jamais faire échouer l'action métier
 * qui l'a déclenché.
 */
class Notifier
{
    /**
     * Notifie un utilisateur.
     *
     * @param  array<string, mixed>  $data  contexte du message (titres, montants, url…)
     */
    public function notify(
        User $user,
        NotificationType $type,
        array $data = [],
        NotificationChannel $channel = NotificationChannel::InApp,
    ): AppNotification {
        $message = $type->compose($data);

        $notification = AppNotification::create([
            'user_id' => $user->id,
            'channel' => $channel->value,
            'type' => $type->value,
            'title' => $message['title'],
            'body' => $message['body'],
            'data' => $data,
            // Le canal « application » est délivré dès l'écriture en base :
            // il n'y a rien à envoyer, l'utilisateur le lit quand il revient.
            'sent_at' => $channel === NotificationChannel::InApp ? now() : null,
        ]);

        if ($channel !== NotificationChannel::InApp) {
            Log::info('Notification hors application non émise (MVP)', [
                'channel' => $channel->value,
                'type' => $type->value,
                'user_id' => $user->id,
            ]);
        }

        return $notification;
    }

    /**
     * Notifie plusieurs utilisateurs du même événement, sans doublon.
     *
     * @param  Collection<int, User>|array<int, User>  $users
     * @param  array<string, mixed>  $data
     * @return int  nombre de notifications créées
     */
    public function notifyMany(
        Collection|array $users,
        NotificationType $type,
        array $data = [],
        ?int $exceptUserId = null,
    ): int {
        $recipients = collect($users)
            ->filter()
            ->unique('id')
            ->reject(fn (User $user) => $user->id === $exceptUserId);

        foreach ($recipients as $user) {
            $this->notify($user, $type, $data);
        }

        return $recipients->count();
    }
}
