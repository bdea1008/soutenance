<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppression de compte par l'administration (§5) : un compte supprimé ne
 * doit jamais entraîner la suppression en cascade de ses projets, de ses
 * contributions ou de ses paiements — les clés étrangères vers `users` sont
 * en `cascadeOnDelete()`, une suppression réelle en base effacerait donc
 * l'historique financier des autres utilisateurs. La suppression douce
 * (`deleted_at`) retire le compte de l'annuaire et de la connexion sans
 * jamais exécuter ce `DELETE` en cascade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
