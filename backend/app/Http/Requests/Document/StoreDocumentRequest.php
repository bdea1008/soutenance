<?php

namespace App\Http\Requests\Document;

use App\Enums\DocumentType;
use App\Models\Project;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Dépôt d'une pièce de vérification. Le contexte est déduit du rôle de
 * l'utilisateur, sauf pour une pièce rattachée à un projet.
 *
 * Deux dossiers coexistent et n'attendent pas les mêmes pièces : celui de
 * l'opérateur (niveau 2, une seule fois) et celui d'une opération (niveau 3,
 * à chaque projet). Le rattachement à un projet suffit à les distinguer.
 */
class StoreDocumentRequest extends FormRequest
{
    /** Taille maximale acceptée, en kilo-octets. */
    public const MAX_KB = 5120; // 5 Mo

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.self::MAX_KB],
            'type' => ['required', $this->typeRule()],
            // Rattachement facultatif : dossier d'une opération précise.
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            // Date d'émission, exigée pour les pièces qui périment (RCCM de
            // moins de 3 mois, bulletins de salaire, trésorerie…). Sans elle,
            // impossible de savoir si la pièce est encore recevable.
            'issued_at' => [
                Rule::requiredIf(fn () => $this->documentType()?->requiresIssueDate() === true),
                'nullable',
                'date',
                'before_or_equal:today',
            ],
        ];
    }

    /**
     * Refuse une pièce déjà hors délai au moment du dépôt : la faire examiner
     * puis valider par un administrateur pour qu'elle soit périmée dans la
     * foulée ferait perdre du temps à tout le monde.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = $this->documentType();
            $months = $type?->validityMonths();

            if ($months === null || ! $this->filled('issued_at')) {
                return;
            }

            $expiry = Carbon::parse($this->input('issued_at'))->addMonths($months);

            if ($expiry->isPast()) {
                $validator->errors()->add('issued_at', sprintf(
                    'Cette pièce est valable %d mois : celle-ci a expiré le %s. Déposez-en une plus récente.',
                    $months,
                    $expiry->format('d/m/Y'),
                ));
            }
        });
    }

    /** Type demandé, `null` si absent ou inconnu (la règle `type` s'en charge). */
    private function documentType(): ?DocumentType
    {
        $value = $this->input('type');

        return is_string($value) ? DocumentType::tryFrom($value) : null;
    }

    /**
     * Seules les pièces effectivement attendues sont recevables. La liste
     * dépend du dossier visé **et** du sous-type de promoteur : un particulier
     * ne dépose pas de statuts de SCI, une société pas de bulletins de salaire.
     * Sans cette borne, un dépôt hors sujet encombrerait la file de modération
     * sans jamais faire avancer la vérification.
     */
    private function typeRule(): mixed
    {
        $user = $this->user();

        if ($this->filled('project_id')) {
            // Le sous-type est celui du **porteur du projet**, qui n'est pas
            // toujours l'auteur du dépôt (un administrateur peut déposer pour
            // lui). Projet inconnu : la règle `exists` tranchera.
            $project = Project::find($this->integer('project_id'));
            $allowed = $project?->promoterProfile()->acceptedProjectDocuments() ?? [];
        } else {
            $allowed = $user->acceptedKycDocuments();
        }

        // Rôle non soumis au dossier (administrateur) : on laisse passer la
        // validation pour que le contrôleur renvoie un motif compréhensible.
        if ($allowed === []) {
            return Rule::enum(DocumentType::class);
        }

        return Rule::in(array_map(fn (DocumentType $type) => $type->value, $allowed));
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Veuillez sélectionner un fichier.',
            'file.mimes' => 'Formats acceptés : JPG, PNG ou PDF.',
            'file.max' => 'Le fichier ne doit pas dépasser 5 Mo.',
            'type.required' => 'Veuillez indiquer la nature de la pièce.',
            'type.enum' => 'Nature de pièce inconnue.',
            'type.in' => "Cette nature de pièce n'est pas attendue pour ce dépôt.",
            'project_id.exists' => 'Le projet indiqué est introuvable.',
            'issued_at.required' => "Indiquez la date d'émission : cette pièce a une durée de validité.",
            'issued_at.before_or_equal' => "La date d'émission ne peut pas être dans le futur.",
        ];
    }
}
