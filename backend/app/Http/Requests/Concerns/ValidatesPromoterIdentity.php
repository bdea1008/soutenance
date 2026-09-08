<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PromoterType;
use App\Enums\UserRole;
use Illuminate\Validation\Rule;

/**
 * Règles du niveau 1 d'un compte promoteur : son sous-type, et — pour un
 * promoteur confirmé — l'identité de la structure porteuse.
 *
 * C'est volontairement peu : quatre champs, aucun fichier. Le reste du dossier
 * est demandé plus tard, quand le promoteur veut réellement mettre un projet en
 * financement. Partagé entre l'inscription publique et la création de compte
 * par l'administration, pour que les deux portes posent les mêmes exigences.
 */
trait ValidatesPromoterIdentity
{
    /** Le compte en cours de création est-il un compte promoteur ? */
    protected function isPromoterRole(): bool
    {
        return $this->input('role') === UserRole::Promoter->value;
    }

    /** S'agit-il d'un promoteur confirmé (structure) plutôt que d'un particulier ? */
    protected function registersCompany(): bool
    {
        return $this->isPromoterRole()
            && $this->input('promoter_type') === PromoterType::Company->value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function promoterIdentityRules(): array
    {
        $ifCompany = Rule::requiredIf(fn () => $this->registersCompany());

        return [
            'promoter_type' => [
                Rule::requiredIf(fn () => $this->isPromoterRole()),
                'nullable',
                Rule::enum(PromoterType::class),
            ],
            'company_name' => [$ifCompany, 'nullable', 'string', 'max:180'],
            'legal_form' => [$ifCompany, 'nullable', 'string', 'max:60'],
            // RCCM et NINEA sont saisis dès l'inscription, la pièce
            // justificative n'est demandée qu'au dossier (niveau 2).
            'registration_number' => [$ifCompany, 'nullable', 'string', 'max:60'],
            'tax_number' => [$ifCompany, 'nullable', 'string', 'max:60'],
            'signatory_role' => [$ifCompany, 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function promoterIdentityMessages(): array
    {
        return [
            'promoter_type.required' => 'Indiquez si vous êtes un particulier ou un promoteur immobilier.',
            'promoter_type.enum' => 'Type de promoteur inconnu.',
            'company_name.required' => 'La raison sociale est obligatoire pour un promoteur immobilier.',
            'legal_form.required' => 'La forme juridique est obligatoire (SA, SARL, SUARL, SCI…).',
            'registration_number.required' => 'Le numéro RCCM est obligatoire.',
            'tax_number.required' => 'Le NINEA est obligatoire.',
            'signatory_role.required' => 'Indiquez votre fonction dans la société.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function promoterIdentityAttributes(): array
    {
        return [
            'promoter_type' => 'type de promoteur',
            'company_name' => 'raison sociale',
            'legal_form' => 'forme juridique',
            'registration_number' => 'numéro RCCM',
            'tax_number' => 'NINEA',
            'signatory_role' => 'fonction',
        ];
    }
}
