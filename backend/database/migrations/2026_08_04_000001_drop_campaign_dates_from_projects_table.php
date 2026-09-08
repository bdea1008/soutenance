<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retrait des dates de campagne d'un projet.
 *
 * Décision produit : le promoteur ne fixe pas de fenêtre de collecte, donc ni
 * clôture, ni démarrage de travaux, ni livraison prévue. Une opération est
 * ouverte au co-investissement tant qu'elle est publiée et que son objectif
 * n'est pas atteint — c'est le statut qui fait foi, pas un calendrier.
 *
 * La seule date que porte encore un projet est `published_at`, posée par le
 * serveur à la mise en ligne : elle constate un fait, elle n'engage à rien.
 *
 * L'horizon de placement reste `duration_months` : ce n'est pas une fenêtre de
 * campagne mais la durée d'immobilisation annoncée à l'investisseur, sur
 * laquelle repose le rendement promis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['funding_deadline', 'start_date', 'expected_end_date']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('funding_deadline')->nullable()->after('duration_months');
            $table->date('start_date')->nullable()->after('funding_deadline');
            $table->date('expected_end_date')->nullable()->after('start_date');
        });
    }
};
