<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache un paiement à la contribution qu'il règle (§2, cas d'utilisation
 * « Investir dans un projet » inclut « Effectuer un paiement »).
 *
 * `project_id` existait déjà sur `payments` mais restait inutilisé pour ce
 * motif — la contribution elle-même portait le montant sans paiement associé.
 * `contribution_id` est le lien précis (une contribution donnée), `project_id`
 * reste utile pour retrouver tous les paiements d'un projet sans détour par
 * les contributions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('contribution_id')->nullable()->after('subscription_id')
                ->constrained('contributions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contribution_id');
        });
    }
};
