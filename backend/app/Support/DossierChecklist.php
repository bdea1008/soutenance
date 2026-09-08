<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\VerificationDocument;
use Illuminate\Support\Collection;

/**
 * Construction d'une checklist de dossier — la même mécanique sert au dossier
 * de l'opérateur (niveau 2, déposé une seule fois) et au dossier d'une
 * opération (niveau 3, refait à chaque projet). Seules les listes de pièces
 * attendues changent, elles viennent de `PromoterType`.
 *
 * Une pièce n'est acquise que si elle est **validée et non périmée** : c'est le
 * seul endroit qui en décide, pour que la checklist affichée à l'utilisateur et
 * le verrou de publication ne puissent jamais diverger.
 */
class DossierChecklist
{
    /**
     * @param  Collection<int, VerificationDocument>  $documents  pièces du périmètre concerné
     * @param  array<int, DocumentType>  $required
     * @param  array<int, DocumentType>  $optional
     * @return array<int, array<string, mixed>>
     */
    public static function build(Collection $documents, array $required, array $optional): array
    {
        $items = [];

        foreach ($required as $type) {
            $items[] = self::item($documents, $type, true);
        }

        foreach ($optional as $type) {
            // « Autre » n'est pas une ligne de checklist : c'est un fourre-tout
            // de dépôt, il n'a rien à attendre.
            if ($type === DocumentType::Other) {
                continue;
            }

            $items[] = self::item($documents, $type, false);
        }

        return $items;
    }

    /**
     * @param  Collection<int, VerificationDocument>  $documents
     * @return array<string, mixed>
     */
    private static function item(Collection $documents, DocumentType $type, bool $required): array
    {
        $ofType = $documents->where('type', $type);
        $document = self::mostRelevant($ofType);

        $status = $document?->status;
        $expired = $document !== null
            && $status === DocumentStatus::Approved
            && $document->isExpired();

        return [
            'type' => $type->value,
            'label' => $type->label(),
            'hint' => $type->hint(),
            'required' => $required,
            'status' => $expired ? 'expired' : $status?->value,
            'status_label' => $expired ? 'Expirée' : ($status?->label() ?? 'À déposer'),
            'satisfied' => $status === DocumentStatus::Approved && ! $expired,
            'expired' => $expired,
            'expires_at' => $document?->expires_at?->toDateString(),
            'validity_months' => $type->validityMonths(),
            'document_id' => $document?->id,
        ];
    }

    /**
     * Pièce représentative d'un type : la meilleure issue l'emporte, pour
     * qu'un nouveau dépôt après rejet fasse repasser la ligne « en attente ».
     * Une pièce validée mais périmée passe **derrière** un nouveau dépôt en
     * attente : le renouvellement est déjà en cours d'examen.
     *
     * @param  Collection<int, VerificationDocument>  $documents
     */
    private static function mostRelevant(Collection $documents): ?VerificationDocument
    {
        $valid = $documents->first(
            fn (VerificationDocument $d) => $d->status === DocumentStatus::Approved && ! $d->isExpired()
        );

        return $valid
            ?? $documents->firstWhere('status', DocumentStatus::Pending)
            ?? $documents->firstWhere('status', DocumentStatus::Approved) // validée mais périmée
            ?? $documents->firstWhere('status', DocumentStatus::Rejected);
    }

    /**
     * Le dossier est-il complet ? Seules les pièces obligatoires comptent.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function isComplete(array $items): bool
    {
        return self::missing($items) === [];
    }

    /**
     * Pièces obligatoires manquantes, périmées, rejetées ou encore en examen —
     * autrement dit tout ce qui empêche le dossier d'aboutir.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function missing(array $items): array
    {
        return array_values(array_filter(
            $items,
            fn (array $item) => $item['required'] && ! $item['satisfied'],
        ));
    }

    /**
     * Résumé chiffré, pour les pastilles d'avancement.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, int|bool>
     */
    public static function progress(array $items): array
    {
        $required = array_filter($items, fn (array $i) => $i['required']);
        $satisfied = array_filter($required, fn (array $i) => $i['satisfied']);

        return [
            'required' => count($required),
            'satisfied' => count($satisfied),
            'complete' => count($required) === count($satisfied),
        ];
    }
}
