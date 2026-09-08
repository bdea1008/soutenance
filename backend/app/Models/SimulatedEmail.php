<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Un message déposé dans la boîte d'envoi simulée.
 *
 * Ce n'est pas une entité métier du §12 : c'est l'équivalent, pour l'email, de
 * ce que `Payment::$status = simulated` est pour le paiement — la trace d'une
 * intégration externe qui n'est pas encore branchée.
 */
class SimulatedEmail extends Model
{
    protected $fillable = [
        'uuid',
        'to_email',
        'to_name',
        'from_email',
        'from_name',
        'subject',
        'body_html',
        'body_text',
        'context',
        'action_url',
        'user_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $email): void {
            $email->uuid ??= (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        // `withTrashed` : un message reste consultable même si le compte
        // destinataire a été supprimé depuis (suppression douce, §5).
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** Messages adressés à cette boîte, du plus récent au plus ancien. */
    public function scopeAddressedTo(Builder $query, string $email): Builder
    {
        return $query->where('to_email', $email);
    }

    /**
     * Libellés des natures de message.
     *
     * Volontairement une table ici plutôt qu'une énumération : la boîte
     * simulée n'a pas à connaître à l'avance tous les messages que
     * l'application enverra un jour, et un contexte inconnu doit s'afficher
     * tel quel plutôt que faire échouer la lecture.
     */
    public const CONTEXTS = [
        'password.reset' => 'Réinitialisation du mot de passe',
        'password.changed' => 'Confirmation de changement de mot de passe',
    ];

    public static function contextLabel(?string $context): string
    {
        return self::CONTEXTS[$context] ?? ($context ?? 'Message');
    }

    /** URL publique de prévisualisation — voir routes/web.php. */
    public function previewUrl(): string
    {
        return config('mail_simulation.preview_base').'/mail-simule/'.$this->uuid;
    }

    /**
     * Borne la taille de la boîte simulée : c'est un outil de démonstration,
     * il n'a pas vocation à accumuler indéfiniment.
     */
    public static function prune(): void
    {
        $keep = max(1, (int) config('mail_simulation.keep', 200));

        $cutoff = static::query()
            ->orderByDesc('id')
            ->skip($keep)
            ->take(1)
            ->value('id');

        if ($cutoff !== null) {
            static::query()->where('id', '<=', $cutoff)->delete();
        }
    }
}
