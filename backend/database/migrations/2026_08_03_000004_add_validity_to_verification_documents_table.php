<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durée de vie d'une pièce. Un RCCM « de moins de 3 mois », des bulletins de
 * salaire ou une situation de trésorerie ne sont pas des pièces déposées une
 * fois pour toutes : elles vieillissent. `expires_at` est calculé au dépôt à
 * partir de la date d'émission déclarée et de `DocumentType::validityMonths()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_documents', function (Blueprint $table) {
            $table->date('issued_at')->nullable()->after('original_name');
            // Indexé : la file de modération et les checklists filtrent dessus.
            $table->date('expires_at')->nullable()->after('issued_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('verification_documents', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['issued_at', 'expires_at']);
        });
    }
};
