<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sous-type de promoteur (particulier / promoteur confirmé) et identité de la
 * structure porteuse. Tout est `nullable` : ces colonnes ne concernent que le
 * rôle promoteur, un investisseur les laisse vides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // individual | company — null pour les rôles non promoteurs.
            $table->string('promoter_type')->nullable()->after('role')->index();

            // Identité de la structure, demandée dès l'inscription au promoteur
            // confirmé (niveau 1). Le dossier de pièces vient plus tard.
            $table->string('company_name')->nullable();
            $table->string('legal_form', 60)->nullable();       // SA, SARL, SUARL, SCI…
            $table->string('registration_number', 60)->nullable(); // RCCM
            $table->string('tax_number', 60)->nullable();       // NINEA
            $table->string('signatory_role', 100)->nullable();  // Fonction du signataire
        });

        // Les promoteurs déjà inscrits sont des structures : c'est le seul
        // profil que l'application acceptait jusqu'ici.
        DB::table('users')
            ->where('role', 'promoter')
            ->whereNull('promoter_type')
            ->update(['promoter_type' => 'company']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['promoter_type']);
            $table->dropColumn([
                'promoter_type',
                'company_name',
                'legal_form',
                'registration_number',
                'tax_number',
                'signatory_role',
            ]);
        });
    }
};
