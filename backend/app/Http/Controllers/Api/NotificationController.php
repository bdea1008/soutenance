<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Boîte de réception applicative de l'utilisateur (§7.7).
 * Un utilisateur ne voit jamais que ses propres notifications.
 */
class NotificationController extends Controller
{
    /** Liste paginée, les plus récentes d'abord. */
    public function index(Request $request): JsonResponse
    {
        // L'identifiant départage les notifications émises dans la même
        // seconde — sans quoi l'ordre d'affichage serait arbitraire.
        $query = $request->user()->appNotifications()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => NotificationResource::collection($notifications->items()),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
                'unread' => $this->unreadCount($request),
            ],
        ]);
    }

    /** Compteur pour la pastille de la barre de navigation. */
    public function unread(Request $request): JsonResponse
    {
        return response()->json(['unread' => $this->unreadCount($request)]);
    }

    /** Marquer une notification comme lue. */
    public function read(Request $request, AppNotification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Notification introuvable.'], 404);
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'notification' => new NotificationResource($notification->fresh()),
            'unread' => $this->unreadCount($request),
        ]);
    }

    /** Tout marquer comme lu. */
    public function readAll(Request $request): JsonResponse
    {
        $marked = $request->user()
            ->appNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => $marked > 0
                ? "{$marked} notification(s) marquée(s) comme lues."
                : 'Aucune notification non lue.',
            'unread' => 0,
        ]);
    }

    /** Retirer une notification de la boîte de réception. */
    public function destroy(Request $request, AppNotification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Notification introuvable.'], 404);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notification supprimée.',
            'unread' => $this->unreadCount($request),
        ]);
    }

    private function unreadCount(Request $request): int
    {
        return $request->user()->appNotifications()->whereNull('read_at')->count();
    }
}
