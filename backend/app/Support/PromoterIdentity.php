<?php

namespace App\Support;

use App\Enums\PromoterType;
use App\Enums\UserRole;
use App\Http\Requests\Concerns\ValidatesPromoterIdentity;

/**
 * Seul endroit qui décide ce qui, du formulaire d'inscription, entre dans les
 * colonnes de structure de `users`.
 *
 * Sans lui, un compte pourrait garder une raison sociale après être passé
 * particulier, ou un investisseur porter un sous-type de promoteur : les champs
 * sont tous nullable, rien en base ne l'empêcherait.
 *
 * @see ValidatesPromoterIdentity
 */
class PromoterIdentity
{
    /** Colonnes de structure, portées uniquement par un promoteur confirmé. */
    private const COMPANY_COLUMNS = [
        'company_name',
        'legal_form',
        'registration_number',
        'tax_number',
        'signatory_role',
    ];

    /**
     * Colonnes à écrire, déduites du rôle et du sous-type retenus.
     *
     * @param  array<string, mixed>  $data  données déjà validées
     * @return array<string, mixed>
     */
    public static function columns(array $data): array
    {
        $isPromoter = ($data['role'] ?? null) === UserRole::Promoter->value;
        $type = $isPromoter ? PromoterType::tryFrom((string) ($data['promoter_type'] ?? '')) : null;

        $columns = ['promoter_type' => $type?->value];

        foreach (self::COMPANY_COLUMNS as $column) {
            // Un particulier n'a pas de structure : les champs sont remis à
            // vide plutôt que laissés tels quels.
            $columns[$column] = $type === PromoterType::Company
                ? ($data[$column] ?? null)
                : null;
        }

        return $columns;
    }
}
