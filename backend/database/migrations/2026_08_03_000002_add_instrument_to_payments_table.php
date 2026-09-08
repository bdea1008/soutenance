<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coordonnées du moyen de paiement employé (§7.4).
 *
 * Ce qui est conservé est délibérément minimal — juste de quoi qu'un
 * utilisateur reconnaisse son propre paiement dans son historique :
 *
 * - Mobile Money : le numéro débité. Il peut différer de celui du compte,
 *   l'utilisateur ayant le droit de payer depuis un autre téléphone.
 * - Carte : le réseau (Visa, Mastercard…) et les quatre derniers chiffres.
 *
 * Ce qui n'est PAS conservé, et ne doit jamais l'être : le numéro de carte
 * complet, le cryptogramme (CVC) et la date d'expiration. Ils transitent pour
 * la seule durée de la requête, personne n'a le droit de les stocker sans une
 * certification PCI-DSS — et la simulation n'est pas une raison de prendre
 * l'habitude inverse. Quand un vrai prestataire sera branché, il renverra un
 * jeton, qui viendra se ranger à côté de ces colonnes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Mobile Money : numéro effectivement débité, au format E.164.
            $table->string('payer_phone', 20)->nullable()->after('provider');

            // Carte : réseau et 4 derniers chiffres. Rien d'autre.
            $table->string('card_brand', 20)->nullable()->after('payer_phone');
            $table->char('card_last4', 4)->nullable()->after('card_brand');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payer_phone', 'card_brand', 'card_last4']);
        });
    }
};
