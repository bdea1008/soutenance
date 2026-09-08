<?php

namespace App\Support;

use App\Enums\PaymentProvider;
use App\Models\User;

/**
 * Coordonnées du moyen de paiement, telles qu'elles seront conservées.
 *
 * Ce petit objet existe pour une raison précise : c'est le seul endroit qui
 * décide **ce qui sort de la requête et entre en base**. Les contrôleurs ne
 * touchent jamais aux champs bruts, ils reçoivent déjà le tri fait.
 *
 * Ce qui est retenu :
 * - Mobile Money : le numéro débité (celui du compte par défaut).
 * - Carte : le réseau et les quatre derniers chiffres.
 *
 * Ce qui est écarté ici même, et ne va donc nulle part : le numéro de carte
 * complet, le cryptogramme, la date d'expiration. Aucune certification
 * PCI-DSS n'autorise à les conserver, et la simulation n'est pas une raison
 * d'en prendre l'habitude. Quand un vrai prestataire de paiement sera branché,
 * il rendra un jeton, qui viendra simplement s'ajouter ici.
 */
final class PaymentInstrument
{
    private function __construct(
        public readonly PaymentProvider $provider,
        public readonly ?string $phone,
        public readonly ?string $cardBrand,
        public readonly ?string $cardLast4,
    ) {}

    /**
     * Construit l'instrument à partir des données validées.
     *
     * @param  array<string, mixed>  $data  sortie de `FormRequest::validated()`
     */
    public static function fromValidated(array $data, User $payer): self
    {
        $provider = PaymentProvider::from($data['provider']);

        if ($provider->isCard()) {
            $digits = preg_replace('/\D/', '', (string) ($data['card_number'] ?? ''));

            return new self(
                provider: $provider,
                // Aucun numéro de téléphone n'est rattaché à un paiement par
                // carte : le champ existe dans la requête, il n'a pas à être
                // recopié pour autant.
                phone: null,
                cardBrand: self::brandOf($digits),
                cardLast4: $digits === '' ? null : substr($digits, -4),
            );
        }

        return new self(
            provider: $provider,
            // À défaut de numéro fourni, c'est celui du compte qui est débité :
            // le paiement ne doit jamais rester sans payeur identifié.
            phone: $data['phone'] ?? $payer->phone,
            cardBrand: null,
            cardLast4: null,
        );
    }

    /**
     * Colonnes à écrire sur le paiement.
     *
     * @return array<string, mixed>
     */
    public function columns(): array
    {
        return [
            'provider' => $this->provider->value,
            'payer_phone' => $this->phone,
            'card_brand' => $this->cardBrand,
            'card_last4' => $this->cardLast4,
        ];
    }

    // La mise en forme lisible (« Visa •••• 4242 ») vit sur le modèle
    // (`Payment::instrumentLabel()`) et pas ici : elle sert surtout à relire
    // un paiement déjà enregistré, et un libellé n'a qu'une seule définition.

    /**
     * Réseau déduit des premiers chiffres (norme ISO/IEC 7812).
     *
     * Volontairement limité aux réseaux qu'on rencontre au Sénégal ; un
     * numéro non reconnu ne bloque rien — il est simplement enregistré sans
     * marque, plutôt que rangé de force sous une étiquette fausse.
     */
    public static function brandOf(string $digits): ?string
    {
        return match (true) {
            $digits === '' => null,
            (bool) preg_match('/^4/', $digits) => 'Visa',
            // Mastercard : 51-55 et la plage 2221-2720 ouverte en 2017.
            (bool) preg_match('/^(5[1-5]|2(2[2-9]|[3-6]\d|7[01])\d)/', $digits) => 'Mastercard',
            (bool) preg_match('/^3[47]/', $digits) => 'American Express',
            (bool) preg_match('/^(6011|65|64[4-9])/', $digits) => 'Discover',
            (bool) preg_match('/^(50|6)/', $digits) => 'Maestro',
            default => null,
        };
    }
}
